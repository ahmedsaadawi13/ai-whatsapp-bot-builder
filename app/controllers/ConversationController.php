<?php
// FILE: /app/controllers/ConversationController.php

/**
 * Conversation Controller
 *
 * Handles individual conversation details and messaging.
 */
class ConversationController extends Controller {

    /**
     * Show conversation
     */
    public function show($id) {
        $this->requireAuth();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Conversation.php';
        require_once __DIR__ . '/../models/Message.php';

        $conversationModel = new Conversation();
        $messageModel = new Message();

        $conversation = $conversationModel->getWithDetails($id, $tenantId);

        if (!$conversation) {
            $this->session->flash('error', 'Conversation not found');
            $this->redirect('/inbox');
        }

        $messages = $messageModel->getByConversation($id, $tenantId);

        $this->view('conversations/show', [
            'title' => 'Conversation',
            'conversation' => $conversation,
            'messages' => $messages
        ]);
    }

    /**
     * Send message in conversation
     */
    public function sendMessage($id) {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();
        $userId = $this->auth->id();

        $content = $this->request->post('message');

        if (empty($content)) {
            $this->session->flash('error', 'Message cannot be empty');
            $this->redirect('/conversations/' . $id);
        }

        require_once __DIR__ . '/../models/Message.php';
        $messageModel = new Message();

        $messageModel->createMessage([
            'tenant_id' => $tenantId,
            'conversation_id' => $id,
            'direction' => 'outbound',
            'type' => 'text',
            'content' => $content,
            'triggered_by' => 'agent',
            'sent_by_user_id' => $userId,
            'status' => 'sent'
        ]);

        $this->session->flash('success', 'Message sent');
        $this->redirect('/conversations/' . $id);
    }

    /**
     * Close conversation
     */
    public function close($id) {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Conversation.php';
        $conversationModel = new Conversation();

        if ($conversationModel->update($id, ['status' => 'closed'], $tenantId)) {
            $this->session->flash('success', 'Conversation closed');
        } else {
            $this->session->flash('error', 'Failed to close conversation');
        }

        $this->redirect('/conversations/' . $id);
    }

    /**
     * Reopen conversation
     */
    public function reopen($id) {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Conversation.php';
        $conversationModel = new Conversation();

        if ($conversationModel->update($id, ['status' => 'open'], $tenantId)) {
            $this->session->flash('success', 'Conversation reopened');
        } else {
            $this->session->flash('error', 'Failed to reopen conversation');
        }

        $this->redirect('/conversations/' . $id);
    }
}
