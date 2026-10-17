<?php
// FILE: /app/services/MessageProcessor.php

/**
 * Message Processor - the single pipeline for every message.
 *
 * Inbound (contact -> business), used by the public API and webhook receiver:
 *   quota check -> contact -> conversation -> store inbound -> bot engine
 *   -> store replies -> outgoing webhooks.
 *
 * Outbound (business -> contact), used by agents, the API and broadcasts.
 *
 * Human takeover: once a conversation is assigned to a user, the bot stops
 * replying in it; agents answer from the inbox.
 *
 * Simulation mode (bot simulator): uses a dedicated test contact that is
 * stored as "unsubscribed" (never in broadcasts), skips quotas and outgoing
 * webhooks, and is excluded from quota counting.
 */
class MessageProcessor {

    const SIMULATOR_PHONE = '+19990000001';

    private $tenantId;

    /** True while handling a simulator message (no webhooks). */
    private $silent = false;

    public function __construct($tenantId) {
        $this->tenantId = (int) $tenantId;
    }

    /**
     * Handle a message sent by a contact.
     *
     * @param array      $channel  Channel row (belongs to this tenant)
     * @param array|null $bot      Bot answering the channel, or null (message stored, no auto-reply)
     * @param string $phone    Contact phone (E.164)
     * @param string $text     Message text
     * @param array  $options  contact_name, metadata, source ('api'|'webhook'|'simulator')
     * @return array ['ok' => bool, 'error' => ?string, 'status' => int, 'conversation' => array,
     *                'contact' => array, 'replies' => array, 'bot_replied' => bool]
     */
    public function handleInbound($channel, $bot, $phone, $text, $options = []) {
        $simulation = !empty($options['simulation']);
        if ($simulation) {
            $phone = self::SIMULATOR_PHONE;
        }
        $this->silent = $simulation;

        // Inbound + (at least) one reply must fit in the monthly quota.
        $quota = $simulation ? ['allowed' => true] : QuotaService::check($this->tenantId, 'messages', 2);
        if (!$quota['allowed']) {
            return $this->fail($quota['message'], 429);
        }

        $contactModel = new Contact();
        if (!$simulation && !$contactModel->findByPhoneNumber($phone, $this->tenantId)) {
            $contactQuota = QuotaService::check($this->tenantId, 'contacts', 1);
            if (!$contactQuota['allowed']) {
                return $this->fail($contactQuota['message'], 429);
            }
        }

        list($contact, $contactCreated) = $contactModel->findOrCreate($phone, $this->tenantId, [
            'name' => $simulation ? 'Bot Simulator' : (isset($options['contact_name']) ? $options['contact_name'] : null),
            'status' => $simulation ? 'unsubscribed' : null
        ]);
        if ($contact['status'] === 'blocked') {
            return $this->fail('This contact is blocked', 403);
        }

        list($conversation, $conversationCreated) = (new Conversation())->findOrCreate(
            $contact['id'], $channel['id'], $this->tenantId, $bot ? $bot['id'] : null
        );

        $messageModel = new Message();
        $inboundId = $messageModel->createMessage([
            'tenant_id' => $this->tenantId,
            'conversation_id' => $conversation['id'],
            'direction' => 'inbound',
            'type' => 'text',
            'content' => $text,
            'metadata' => [
                'source' => isset($options['source']) ? $options['source'] : 'api',
                'external' => isset($options['metadata']) && is_array($options['metadata']) ? $options['metadata'] : null
            ],
            'triggered_by' => (isset($options['source']) && $options['source'] === 'api') ? 'api' : null,
            'status' => 'delivered'
        ]);

        $botId = $bot ? $bot['id'] : null;

        if ($contactCreated && !$simulation) {
            WebhookDispatcher::dispatch($this->tenantId, 'contact.created', ['contact' => self::publicContact($contact)], $botId);
        }
        if ($conversationCreated && !$simulation) {
            WebhookDispatcher::dispatch($this->tenantId, 'conversation.opened', [
                'conversation_id' => (int) $conversation['id'], 'contact' => self::publicContact($contact)
            ], $botId);
        }
        if (!$simulation) {
            WebhookDispatcher::dispatch($this->tenantId, 'message.received', [
                'conversation_id' => (int) $conversation['id'], 'message_id' => $inboundId,
                'from' => $contact['phone_number'], 'content' => $text
            ], $botId);
        }

        $replies = [];
        $botReplied = false;
        $handedOff = !empty($conversation['assigned_to']);

        if ($bot && !$handedOff && ($bot['status'] === 'active' || $simulation)) {
            $engine = new BotEngine($this->tenantId, $bot);
            foreach ($engine->processMessage($text, $conversation['id'], $contact) as $i => $reply) {
                // The first reply was reserved above; every further reply needs quota too.
                if ($i > 0 && !$simulation && !QuotaService::check($this->tenantId, 'messages', 1)['allowed']) {
                    break;
                }
                $replies[] = $this->storeOutbound($conversation, $contact, $reply, $bot['id']);
            }
            $botReplied = !empty($replies);
        }

        return [
            'ok' => true,
            'error' => null,
            'status' => 200,
            'conversation' => $conversation,
            'conversation_created' => $conversationCreated,
            'contact' => $contact,
            'contact_created' => $contactCreated,
            'inbound_message_id' => $inboundId,
            'replies' => $replies,
            'bot_replied' => $botReplied,
            'handed_off' => $handedOff
        ];
    }

    /**
     * Send a message from the business to a conversation (agent, API, broadcast).
     *
     * @param array $conversation Conversation row (already verified for this tenant)
     * @param array $reply        ['type','content','media_url','triggered_by','flow_id','metadata','sent_by_user_id']
     * @return array|false Stored reply (with id) or false when over quota
     */
    public function sendOutbound($conversation, $reply, $checkQuota = true) {
        if ($checkQuota) {
            $quota = QuotaService::check($this->tenantId, 'messages', 1);
            if (!$quota['allowed']) {
                return false;
            }
        }
        $contact = (new Contact())->find($conversation['contact_id'], $this->tenantId);
        return $this->storeOutbound($conversation, $contact, $reply, $conversation['bot_id']);
    }

    private function storeOutbound($conversation, $contact, $reply, $botId) {
        $metadata = isset($reply['metadata']) && is_array($reply['metadata']) ? $reply['metadata'] : [];
        if (!empty($reply['quick_replies'])) {
            $metadata['quick_replies'] = $reply['quick_replies'];
        }

        $id = (new Message())->createMessage([
            'tenant_id' => $this->tenantId,
            'conversation_id' => $conversation['id'],
            'direction' => 'outbound',
            'type' => isset($reply['type']) ? $reply['type'] : 'text',
            'content' => isset($reply['content']) ? $reply['content'] : '',
            'media_url' => isset($reply['media_url']) ? $reply['media_url'] : null,
            'metadata' => empty($metadata) ? null : $metadata,
            'triggered_by' => isset($reply['triggered_by']) ? $reply['triggered_by'] : 'api',
            'flow_id' => isset($reply['flow_id']) ? $reply['flow_id'] : null,
            'sent_by_user_id' => isset($reply['sent_by_user_id']) ? $reply['sent_by_user_id'] : null,
            'status' => 'sent'
        ]);

        if (!$this->silent) WebhookDispatcher::dispatch($this->tenantId, 'message.sent', [
            'conversation_id' => (int) $conversation['id'],
            'message_id' => $id,
            'to' => $contact ? $contact['phone_number'] : null,
            'type' => isset($reply['type']) ? $reply['type'] : 'text',
            'content' => isset($reply['content']) ? $reply['content'] : '',
            'triggered_by' => isset($reply['triggered_by']) ? $reply['triggered_by'] : 'api'
        ], $botId);

        return [
            'id' => $id,
            'type' => isset($reply['type']) ? $reply['type'] : 'text',
            'content' => isset($reply['content']) ? $reply['content'] : '',
            'media_url' => isset($reply['media_url']) ? $reply['media_url'] : null,
            'quick_replies' => isset($reply['quick_replies']) ? $reply['quick_replies'] : null,
            'triggered_by' => isset($reply['triggered_by']) ? $reply['triggered_by'] : 'api'
        ];
    }

    public static function publicContact($contact) {
        return [
            'id' => (int) $contact['id'],
            'phone_number' => $contact['phone_number'],
            'name' => $contact['name'],
            'email' => $contact['email']
        ];
    }

    private function fail($message, $status) {
        return ['ok' => false, 'error' => $message, 'status' => $status, 'replies' => []];
    }
}
