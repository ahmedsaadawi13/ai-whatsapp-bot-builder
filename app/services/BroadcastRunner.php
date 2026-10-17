<?php
// FILE: /app/services/BroadcastRunner.php

/**
 * Broadcast Runner - sends a campaign to its contact segment.
 *
 * Per-contact template variables: broadcasts.template_variables maps each
 * template placeholder to a static value or a contact field reference:
 *   {"name": "{{name}}", "month": "October", "industry": "{{custom.industry}}"}
 * Placeholders without a mapping default to the contact field of the same
 * name ({{name}}, {{first_name}}, {{phone}}, {{email}} or a custom field).
 *
 * "Sending" is simulated: an outbound message is stored in the contact's
 * conversation on the broadcast's channel.
 */
class BroadcastRunner {

    const BATCH_SIZE = 500;

    /**
     * Run every scheduled broadcast whose time has come (cron entry point).
     *
     * @return int Number of broadcasts processed
     */
    public static function runDue() {
        $count = 0;
        foreach ((new Broadcast())->getDue() as $broadcast) {
            if (self::run($broadcast['id'], $broadcast['tenant_id'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @return array|false Final stats, or false if the broadcast could not be claimed
     */
    public static function run($broadcastId, $tenantId) {
        $broadcastModel = new Broadcast();
        if (!$broadcastModel->claim($broadcastId, $tenantId)) {
            return false; // already running/completed, or not this tenant's
        }
        $broadcast = $broadcastModel->find($broadcastId, $tenantId);

        $template = $broadcast['template_id'] ? (new Template())->find($broadcast['template_id'], $tenantId) : null;
        $mapping = json_array($broadcast['template_variables']);
        $filter = json_array($broadcast['target_filter']);

        $contactModel = new Contact();
        $recipients = $contactModel->getSegment($tenantId, $filter);
        $recipientModel = new BroadcastMessage();
        $recipientModel->queue($broadcastId, array_column($recipients, 'id'));

        $contactsById = [];
        foreach ($recipients as $contact) {
            $contactsById[(int) $contact['id']] = $contact;
        }

        $processor = new MessageProcessor($tenantId);
        $conversationModel = new Conversation();
        $stopReason = null;
        @set_time_limit(0);

        try {
            // Work through the queue in batches until nothing is pending.
            while ($batch = $recipientModel->getPending($broadcastId, self::BATCH_SIZE)) {
                foreach ($batch as $row) {
                    self::sendOne($row, $broadcast, $template, $mapping, $contactsById, $recipientModel, $processor, $conversationModel, $tenantId, $stopReason);
                }
                $broadcastModel->heartbeat($broadcastId);
            }
        } finally {
            // Always finalise, even if a send threw, so the campaign never stays "running".
            $stats = $broadcastModel->getWithStats($broadcastId, $tenantId)['stats'];
            $broadcastModel->update($broadcastId, [
                'status' => $stats['pending'] > 0 ? 'running' : 'completed',
                'completed_at' => $stats['pending'] > 0 ? null : date('Y-m-d H:i:s'),
                'total_recipients' => $stats['total'],
                'sent_count' => $stats['sent'],
                'failed_count' => $stats['failed']
            ], $tenantId);
        }

        return $stats;
    }

    /**
     * Send to one queued recipient; always marks the row sent or failed.
     */
    private static function sendOne($row, $broadcast, $template, $mapping, $contactsById, $recipientModel, $processor, $conversationModel, $tenantId, &$stopReason) {
        $contact = isset($contactsById[(int) $row['contact_id']]) ? $contactsById[(int) $row['contact_id']] : null;
        if (!$contact) {
            $recipientModel->markAsFailed($row['id'], 'Contact no longer eligible (unsubscribed or deleted)');
            return;
        }
        if ($stopReason === null && !QuotaService::check($tenantId, 'messages', 1)['allowed']) {
            $stopReason = 'Monthly message limit reached';
        }
        if ($stopReason !== null) {
            $recipientModel->markAsFailed($row['id'], $stopReason);
            return;
        }

        $variables = self::variablesFor($contact, $mapping);
        $content = $template
            ? Template::renderFull($template, $variables)
            : Template::render((string) $broadcast['message_content'], $variables);
        if (trim($content) === '') {
            $recipientModel->markAsFailed($row['id'], 'Empty message');
            return;
        }

        try {
            list($conversation) = $conversationModel->findOrCreate($contact['id'], $broadcast['channel_id'], $tenantId, $broadcast['bot_id']);
            $sent = $processor->sendOutbound($conversation, [
                'type' => $template ? 'template' : 'text',
                'content' => $content,
                'triggered_by' => 'broadcast',
                'metadata' => ['broadcast_id' => (int) $broadcast['id'], 'template_id' => $template ? (int) $template['id'] : null]
            ], false);
            $recipientModel->markAsSent($row['id'], $sent['id']);
        } catch (Exception $e) {
            error_log('Broadcast send error: ' . $e->getMessage());
            $recipientModel->markAsFailed($row['id'], 'Send error');
        }
    }

    /** Variables for one contact after applying the broadcast's mapping. */
    public static function variablesFor($contact, $mapping) {
        $name = (string) $contact['name'];
        $base = [
            'name' => $name !== '' ? $name : 'there',
            'first_name' => $name !== '' ? strtok($name, ' ') : 'there',
            'phone' => $contact['phone_number'],
            'email' => (string) $contact['email']
        ];
        foreach (json_array($contact['custom_fields']) as $key => $value) {
            if (is_scalar($value)) {
                $base['custom.' . $key] = (string) $value;
                if (!isset($base[$key])) {
                    $base[$key] = (string) $value;
                }
            }
        }

        $variables = $base;
        foreach ($mapping as $key => $value) {
            if (is_scalar($value)) {
                $variables[$key] = Template::render((string) $value, $base);
            }
        }
        return $variables;
    }
}
