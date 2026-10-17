<?php
// FILE: /app/controllers/ApiController.php

/**
 * API Controller
 *
 * Handles public API endpoints for sending messages and receiving webhooks.
 */
class ApiController extends Controller {

    /**
     * Send message via API
     *
     * POST /api/v1/send-message
     */
    public function sendMessage() {
        // Get API key from header
        $apiKey = $this->getApiKeyFromHeader();

        if (!$apiKey) {
            $this->json(['error' => 'Missing API key'], 401);
        }

        // Validate API key
        require_once __DIR__ . '/../models/ApiKey.php';
        $apiKeyModel = new ApiKey();
        $keyData = $apiKeyModel->validateKey($apiKey);

        if (!$keyData) {
            $this->json(['error' => 'Invalid API key'], 401);
        }

        $tenantId = $keyData['tenant_id'];

        // Get request data
        $data = $this->request->json();

        if (!$data) {
            $this->json(['error' => 'Invalid JSON payload'], 400);
        }

        // Validate required fields
        if (empty($data['bot_id']) || empty($data['phone_number']) || empty($data['message'])) {
            $this->json(['error' => 'Missing required fields: bot_id, phone_number, message'], 400);
        }

        // Verify bot belongs to tenant
        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();
        $bot = $botModel->find($data['bot_id'], $tenantId);

        if (!$bot) {
            $this->json(['error' => 'Bot not found'], 404);
        }

        // Find or create contact
        require_once __DIR__ . '/../models/Contact.php';
        $contactModel = new Contact();
        $contact = $contactModel->findOrCreate($data['phone_number'], $tenantId, [
            'name' => isset($data['contact_name']) ? $data['contact_name'] : null
        ]);

        // Find or create conversation
        require_once __DIR__ . '/../models/Conversation.php';
        $conversationModel = new Conversation();
        $conversation = $conversationModel->findOrCreate(
            $contact['id'],
            $bot['channel_id'],
            $tenantId,
            $bot['id']
        );

        // Create inbound message
        require_once __DIR__ . '/../models/Message.php';
        $messageModel = new Message();
        $messageModel->createMessage([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation['id'],
            'direction' => 'inbound',
            'type' => 'text',
            'content' => $data['message'],
            'triggered_by' => 'api',
            'status' => 'delivered'
        ]);

        // Process through bot engine
        require_once __DIR__ . '/../core/BotEngine.php';
        $botEngine = new BotEngine($tenantId, $bot['id']);
        $replies = $botEngine->processMessage($data['message'], $conversation['id']);

        // Create outbound messages
        $responseMessages = [];
        foreach ($replies as $reply) {
            $messageId = $messageModel->createMessage([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation['id'],
                'direction' => 'outbound',
                'type' => $reply['type'],
                'content' => $reply['content'],
                'media_url' => isset($reply['media_url']) ? $reply['media_url'] : null,
                'triggered_by' => $reply['triggered_by'],
                'flow_id' => isset($reply['flow_id']) ? $reply['flow_id'] : null,
                'status' => 'sent'
            ]);

            $responseMessages[] = [
                'id' => $messageId,
                'type' => $reply['type'],
                'content' => $reply['content']
            ];
        }

        // Return response
        $this->json([
            'success' => true,
            'conversation_id' => $conversation['id'],
            'contact_id' => $contact['id'],
            'messages' => $responseMessages
        ], 200);
    }

    /**
     * Receive webhook (simulate WhatsApp incoming messages)
     *
     * POST /api/v1/webhook
     */
    public function webhook() {
        // Get request data
        $data = $this->request->json();

        if (!$data) {
            $this->json(['error' => 'Invalid JSON payload'], 400);
        }

        // Require webhook token for authentication
        $token = isset($_SERVER['HTTP_X_WEBHOOK_TOKEN']) ? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] : null;

        if (!$token) {
            $this->json(['error' => 'Missing webhook token'], 401);
        }

        // Find channel by phone number and verify token
        if (empty($data['to_phone']) || empty($data['from_phone']) || empty($data['message'])) {
            $this->json(['error' => 'Missing required fields'], 400);
        }

        require_once __DIR__ . '/../models/Channel.php';
        $channelModel = new Channel();

        // Find channel by phone number and verify token
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT c.*, b.id as bot_id, b.tenant_id
                FROM channels c
                LEFT JOIN bots b ON c.id = b.channel_id AND b.status = 'active'
                WHERE c.phone_number = ? AND c.webhook_verify_token = ? AND c.status = 'active'
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$data['to_phone'], $token]);
        $channel = $stmt->fetch();

        if (!$channel || !$channel['bot_id']) {
            $this->json(['error' => 'Invalid webhook token or channel not found'], 401);
        }

        $tenantId = $channel['tenant_id'];
        $botId = $channel['bot_id'];

        // Process same as send-message API
        require_once __DIR__ . '/../models/Contact.php';
        require_once __DIR__ . '/../models/Conversation.php';
        require_once __DIR__ . '/../models/Message.php';

        $contactModel = new Contact();
        $conversationModel = new Conversation();
        $messageModel = new Message();

        $contact = $contactModel->findOrCreate($data['from_phone'], $tenantId);
        $conversation = $conversationModel->findOrCreate($contact['id'], $channel['id'], $tenantId, $botId);

        $messageModel->createMessage([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation['id'],
            'direction' => 'inbound',
            'type' => 'text',
            'content' => $data['message'],
            'status' => 'delivered'
        ]);

        // Process through bot engine
        require_once __DIR__ . '/../core/BotEngine.php';
        $botEngine = new BotEngine($tenantId, $botId);
        $replies = $botEngine->processMessage($data['message'], $conversation['id']);

        // Create outbound messages
        foreach ($replies as $reply) {
            $messageModel->createMessage([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation['id'],
                'direction' => 'outbound',
                'type' => $reply['type'],
                'content' => $reply['content'],
                'triggered_by' => $reply['triggered_by'],
                'flow_id' => isset($reply['flow_id']) ? $reply['flow_id'] : null,
                'status' => 'sent'
            ]);
        }

        $this->json(['success' => true], 200);
    }

    /**
     * Get API key from header
     *
     * @return string|null
     */
    private function getApiKeyFromHeader() {
        $headers = getallheaders();

        if (isset($headers['X-API-Key'])) {
            return $headers['X-API-Key'];
        }

        if (isset($headers['X-Api-Key'])) {
            return $headers['X-Api-Key'];
        }

        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        return null;
    }
}
