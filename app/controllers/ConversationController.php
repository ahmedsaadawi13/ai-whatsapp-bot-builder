<?php
// FILE: /app/controllers/ConversationController.php

/**
 * Conversation Controller - full history, agent replies, assignment, status.
 */
class ConversationController extends Controller {

    public function show($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $conversation = (new Conversation())->getWithDetails((int) $id, $tenantId);
        if (!$conversation) {
            $this->abort(404, 'Conversation not found');
        }

        $messageModel = new Message();
        $messageModel->markConversationRead($conversation['id'], $tenantId);

        $this->view('conversations/show', [
            'title' => 'Conversation with ' . ($conversation['contact_name'] ?: $conversation['phone_number']),
            'conversation' => $conversation,
            'messages' => $messageModel->getByConversation($conversation['id'], $tenantId),
            'variables' => (new ConversationVariable())->getAllVariables($conversation['id'], false),
            'agents' => (new User())->getAssignable($tenantId),
            'templates' => (new Template())->getActiveForSelect($tenantId)
        ]);
    }

    /**
     * Agent reply (free text or a template). Assigns the conversation to the
     * agent if nobody owns it yet, which silences the bot (human takeover).
     */
    public function sendMessage($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->requireCsrf();
        $conversationModel = new Conversation();
        $conversation = $this->findOwned($conversationModel, $id, $tenantId);

        $content = trim((string) $this->request->post('message', ''));
        $templateId = (int) $this->request->post('template_id');
        $type = 'text';

        if ($templateId > 0) {
            $template = (new Template())->find($templateId, $tenantId);
            if (!$template) {
                $this->failValidation(['template_id' => ['Choose one of your templates']]);
            }
            $contact = (new Contact())->find($conversation['contact_id'], $tenantId);
            $variables = BroadcastRunner::variablesFor($contact, (new ConversationVariable())->getAllVariables($conversation['id'], false));
            $content = Template::renderFull($template, $variables);
            $type = 'template';
        }

        if ($content === '' || mb_strlen($content, 'UTF-8') > 4096) {
            $this->failValidation(['message' => ['Message must be between 1 and 4096 characters']]);
        }
        if ($conversation['status'] === 'closed') {
            $conversationModel->setStatus($conversation['id'], $tenantId, 'open');
        }

        $sent = (new MessageProcessor($tenantId))->sendOutbound($conversation, [
            'type' => $type,
            'content' => $content,
            'triggered_by' => 'agent',
            'sent_by_user_id' => $this->auth->id()
        ]);
        if ($sent === false) {
            $this->session->flash('error', QuotaService::check($tenantId, 'messages')['message']);
            $this->redirect('/conversations/' . $conversation['id']);
        }

        if (empty($conversation['assigned_to'])) {
            $conversationModel->update($conversation['id'], ['assigned_to' => $this->auth->id()], $tenantId);
        }

        $this->redirect('/conversations/' . $conversation['id'] . '#composer');
    }

    /** AJAX: improve a draft reply with the AI engine. */
    public function rewrite($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->requireCsrf();
        $conversation = $this->findOwned(new Conversation(), $id, $tenantId);
        $bot = $conversation['bot_id'] ? (new Bot())->find($conversation['bot_id'], $tenantId) : null;
        $ai = new AiEngine($tenantId, $bot ? $bot['id'] : null, $bot ? $bot['ai_tone'] : 'professional', 4000);
        $this->json(['text' => $ai->rewrite((string) $this->request->post('message', ''))]);
    }

    public function assign($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->requireCsrf();
        $model = new Conversation();
        $conversation = $this->findOwned($model, $id, $tenantId);

        $userId = (int) $this->request->post('assigned_to');
        if ($userId > 0) {
            $agent = (new User())->find($userId, $tenantId);
            if (!$agent || $agent['status'] !== 'active') {
                $this->failValidation(['assigned_to' => ['Choose an active team member']]);
            }
        }
        $model->update($conversation['id'], ['assigned_to' => $userId > 0 ? $userId : null], $tenantId);
        $this->session->flash('success', $userId > 0 ? 'Conversation assigned (the bot will stay silent)' : 'Conversation returned to the bot');
        $this->redirect('/conversations/' . $conversation['id']);
    }

    public function setStatus($id) {
        $this->changeStatus($id, $this->request->post('status'));
    }

    public function close($id) {
        $this->changeStatus($id, 'closed');
    }

    public function reopen($id) {
        $this->changeStatus($id, 'open');
    }

    private function changeStatus($id, $status) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->requireCsrf();
        if (!in_array($status, Conversation::STATUSES, true)) {
            $this->failValidation(['status' => ['Invalid status']]);
        }
        $model = new Conversation();
        $conversation = $this->findOwned($model, $id, $tenantId);
        $model->setStatus($conversation['id'], $tenantId, $status);

        if ($status === 'closed' && $conversation['status'] !== 'closed') {
            WebhookDispatcher::dispatch($tenantId, 'conversation.closed', [
                'conversation_id' => (int) $conversation['id'],
                'closed_by_user_id' => $this->auth->id()
            ], $conversation['bot_id']);
        }
        $this->session->flash('success', 'Conversation marked as ' . $status);
        $this->redirect('/conversations/' . $conversation['id']);
    }
}
