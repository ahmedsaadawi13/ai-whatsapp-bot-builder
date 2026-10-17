<?php
// FILE: /app/controllers/ApiController.php

/**
 * Public API (JSON).
 *
 *   POST /api/v1/send-message        X-API-KEY  trigger a bot conversation / send a message
 *   GET  /api/v1/bots                X-API-KEY  list bots
 *   GET  /api/v1/conversations/{id}  X-API-KEY  conversation + messages
 *   GET  /api/v1/webhook             provider verification (hub.challenge)
 *   POST /api/v1/webhook             X-Webhook-Token  simulated incoming WhatsApp message
 *
 * Errors: {"error": "message", "details": {...}} with a matching HTTP status.
 */
class ApiController extends Controller {

    const MAX_MESSAGE_LENGTH = 4096;

    /**
     * POST /api/v1/send-message
     *
     * Body: {"bot_id": 1, "phone_number": "+15551234567", "message": "Hi",
     *        "contact_name": "Jane", "metadata": {...}, "direction": "inbound"|"outbound"}
     *
     * direction "inbound" (default): the message is treated as written by the
     * contact and the bot replies. "outbound": the business sends the message
     * to the contact (no bot processing).
     */
    public function sendMessage() {
        $key = $this->authenticateKey();
        $tenantId = (int) $key['tenant_id'];
        $data = $this->jsonBody();

        $errors = [];
        $botId = isset($data['bot_id']) ? $data['bot_id'] : null;
        $phone = isset($data['phone_number']) && is_string($data['phone_number']) ? Validator::normalizePhone($data['phone_number']) : '';
        $message = isset($data['message']) && is_string($data['message']) ? trim($data['message']) : '';
        $direction = isset($data['direction']) ? $data['direction'] : 'inbound';
        $contactName = isset($data['contact_name']) && is_string($data['contact_name']) ? mb_substr(trim($data['contact_name']), 0, 255, 'UTF-8') : null;
        $metadata = isset($data['metadata']) ? $data['metadata'] : null;

        if (filter_var($botId, FILTER_VALIDATE_INT) === false) {
            $errors['bot_id'] = 'bot_id is required and must be an integer';
        }
        if (!Validator::isValidPhone($phone)) {
            $errors['phone_number'] = 'phone_number is required in E.164 format, e.g. +15551234567';
        }
        if ($message === '' || mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            $errors['message'] = 'message is required (1-' . self::MAX_MESSAGE_LENGTH . ' characters)';
        }
        if (!in_array($direction, ['inbound', 'outbound'], true)) {
            $errors['direction'] = 'direction must be "inbound" or "outbound"';
        }
        if ($metadata !== null && !is_array($metadata)) {
            $errors['metadata'] = 'metadata must be a JSON object';
        }
        if (!empty($errors)) {
            $this->json(['error' => 'Validation failed', 'details' => $errors], 422);
        }

        $bot = (new Bot())->find((int) $botId, $tenantId);
        if (!$bot) {
            $this->json(['error' => 'Bot not found'], 404);
        }
        $channel = (new Channel())->find($bot['channel_id'], $tenantId);
        if (!$channel || $channel['status'] !== 'active') {
            $this->json(['error' => 'The bot\'s WhatsApp channel is inactive'], 409);
        }

        if ($direction === 'outbound') {
            $this->sendOutbound($tenantId, $bot, $phone, $message, $contactName, $metadata);
        }

        $result = (new MessageProcessor($tenantId))->handleInbound($channel, $bot, $phone, $message, [
            'contact_name' => $contactName,
            'metadata' => $metadata,
            'source' => 'api'
        ]);
        if (!$result['ok']) {
            $this->json(['error' => $result['error']], $result['status']);
        }

        $this->json([
            'success' => true,
            'conversation_id' => (int) $result['conversation']['id'],
            'conversation_created' => $result['conversation_created'],
            'contact_id' => (int) $result['contact']['id'],
            'inbound_message_id' => (int) $result['inbound_message_id'],
            'bot_status' => $bot['status'],
            'handed_off_to_agent' => $result['handed_off'],
            'messages' => $result['replies']
        ], 200);
    }

    private function sendOutbound($tenantId, $bot, $phone, $message, $contactName, $metadata) {
        $contactModel = new Contact();
        if (!$contactModel->findByPhoneNumber($phone, $tenantId)) {
            $quota = QuotaService::check($tenantId, 'contacts');
            if (!$quota['allowed']) {
                $this->json(['error' => $quota['message']], 429);
            }
        }
        list($contact) = $contactModel->findOrCreate($phone, $tenantId, ['name' => $contactName]);
        if ($contact['status'] !== 'active') {
            $this->json(['error' => 'Contact is ' . $contact['status'] . ' and cannot receive messages'], 409);
        }
        list($conversation) = (new Conversation())->findOrCreate($contact['id'], $bot['channel_id'], $tenantId, $bot['id']);

        $sent = (new MessageProcessor($tenantId))->sendOutbound($conversation, [
            'type' => 'text',
            'content' => $message,
            'triggered_by' => 'api',
            'metadata' => $metadata ? ['external' => $metadata] : null
        ]);
        if ($sent === false) {
            $this->json(['error' => QuotaService::check($tenantId, 'messages')['message']], 429);
        }

        $this->json([
            'success' => true,
            'conversation_id' => (int) $conversation['id'],
            'contact_id' => (int) $contact['id'],
            'messages' => [$sent]
        ], 201);
    }

    /** GET /api/v1/bots */
    public function listBots() {
        $key = $this->authenticateKey();
        $bots = (new Bot())->listWithChannel((int) $key['tenant_id'], [], null, 100, 0);
        $this->json(['data' => array_map(function ($b) {
            return [
                'id' => (int) $b['id'],
                'name' => $b['name'],
                'status' => $b['status'],
                'channel' => ['id' => (int) $b['channel_id'], 'name' => $b['channel_name'], 'phone_number' => $b['phone_number']],
                'ai_enabled' => (bool) $b['ai_enabled']
            ];
        }, $bots)]);
    }

    /** GET /api/v1/conversations/{id} */
    public function getConversation($id) {
        $key = $this->authenticateKey();
        $tenantId = (int) $key['tenant_id'];
        $conversation = (new Conversation())->getWithDetails((int) $id, $tenantId);
        if (!$conversation) {
            $this->json(['error' => 'Conversation not found'], 404);
        }
        $messages = (new Message())->getByConversation($conversation['id'], $tenantId, 200);
        $this->json([
            'id' => (int) $conversation['id'],
            'status' => $conversation['status'],
            'bot_id' => $conversation['bot_id'] !== null ? (int) $conversation['bot_id'] : null,
            'contact' => ['phone_number' => $conversation['phone_number'], 'name' => $conversation['contact_name']],
            'variables' => (new ConversationVariable())->getAllVariables($conversation['id'], false),
            'messages' => array_map(function ($m) {
                return [
                    'id' => (int) $m['id'],
                    'direction' => $m['direction'],
                    'type' => $m['type'],
                    'content' => $m['content'],
                    'media_url' => $m['media_url'],
                    'triggered_by' => $m['triggered_by'],
                    'created_at' => $m['created_at'] . 'Z'
                ];
            }, $messages)
        ]);
    }

    /**
     * GET /api/v1/webhook?hub_mode=subscribe&hub_verify_token=TOKEN&hub_challenge=123
     * (PHP converts "hub.mode" to "hub_mode".) Echoes the challenge when the token is known.
     */
    public function verifyWebhook() {
        $mode = $this->request->get('hub_mode');
        $token = (string) $this->request->get('hub_verify_token', '');
        $challenge = (string) $this->request->get('hub_challenge', '');

        if ($mode === 'subscribe' && $token !== '' && (new Channel())->findByVerifyToken($token)) {
            header('Content-Type: text/plain');
            echo preg_replace('/[^A-Za-z0-9_\-]/', '', $challenge);
            exit;
        }
        $this->json(['error' => 'Verification failed'], 403);
    }

    /**
     * POST /api/v1/webhook  (simulated WhatsApp provider callback)
     *
     * Header: X-Webhook-Token: <channel verify token>
     * Body:   {"to_phone": "+15551234567", "from_phone": "+15557001001",
     *          "message": "Hello", "contact_name": "Alice", "message_id": "wamid.123"}
     */
    public function webhook() {
        $data = $this->jsonBody();
        $token = isset($_SERVER['HTTP_X_WEBHOOK_TOKEN']) ? (string) $_SERVER['HTTP_X_WEBHOOK_TOKEN'] : '';
        if ($token === '') {
            $this->json(['error' => 'Missing X-Webhook-Token header'], 401);
        }

        $to = isset($data['to_phone']) && is_string($data['to_phone']) ? Validator::normalizePhone($data['to_phone']) : '';
        $from = isset($data['from_phone']) && is_string($data['from_phone']) ? Validator::normalizePhone($data['from_phone']) : '';
        $message = isset($data['message']) && is_string($data['message']) ? trim($data['message']) : '';

        $errors = [];
        if (!Validator::isValidPhone($to)) {
            $errors['to_phone'] = 'to_phone must be your channel number in E.164 format';
        }
        if (!Validator::isValidPhone($from)) {
            $errors['from_phone'] = 'from_phone must be in E.164 format';
        }
        if ($message === '' || mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            $errors['message'] = 'message is required (1-' . self::MAX_MESSAGE_LENGTH . ' characters)';
        }
        if (!empty($errors)) {
            $this->json(['error' => 'Validation failed', 'details' => $errors], 422);
        }

        $channel = (new Channel())->findActiveByPhone($to);
        if (!$channel || !hash_equals((string) $channel['webhook_verify_token'], $token)) {
            // Same response for unknown channel and wrong token (no enumeration).
            $this->json(['error' => 'Invalid webhook token or channel'], 401);
        }

        $tenantId = (int) $channel['tenant_id'];
        $tenant = (new Tenant())->find($tenantId);
        if (!$tenant || $tenant['status'] !== 'active') {
            $this->json(['error' => 'Account suspended'], 403);
        }

        $bot = (new Bot())->findActiveForChannel($channel['id']);
        $result = (new MessageProcessor($tenantId))->handleInbound($channel, $bot, $from, $message, [
            'contact_name' => isset($data['contact_name']) && is_string($data['contact_name']) ? mb_substr($data['contact_name'], 0, 255, 'UTF-8') : null,
            'metadata' => isset($data['message_id']) && is_scalar($data['message_id']) ? ['provider_message_id' => (string) $data['message_id']] : null,
            'source' => 'webhook'
        ]);
        if (!$result['ok']) {
            $this->json(['error' => $result['error']], $result['status']);
        }

        $this->json([
            'success' => true,
            'conversation_id' => (int) $result['conversation']['id'],
            'bot_id' => $bot ? (int) $bot['id'] : null,
            'messages' => $result['replies']
        ], 200);
    }

    /** Require a valid X-API-KEY (or "Authorization: Bearer <key>"). */
    private function authenticateKey() {
        $raw = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;
        if (!$raw && isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(\S+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $raw = $m[1];
        }
        if (!$raw) {
            $this->json(['error' => 'Missing API key. Send it in the X-API-KEY header.'], 401);
        }
        $key = (new ApiKey())->validateKey($raw);
        if (!$key) {
            $this->json(['error' => 'Invalid or revoked API key'], 401);
        }
        return $key;
    }

    /** Parse a JSON object body (POST + application/json only). */
    private function jsonBody() {
        if (!$this->request->isPost()) {
            $this->json(['error' => 'Method not allowed'], 405);
        }
        $type = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
        if (strpos($type, 'application/json') !== 0) {
            $this->json(['error' => 'Content-Type must be application/json'], 415);
        }
        $raw = file_get_contents('php://input', false, null, 0, 1048576);
        $data = json_decode((string) $raw, true);
        if (!is_array($data) || (!empty($data) && array_values($data) === $data)) {
            $this->json(['error' => 'Request body must be a JSON object'], 400);
        }
        return $data;
    }
}
