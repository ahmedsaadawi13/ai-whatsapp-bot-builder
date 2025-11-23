<?php
// FILE: /app/core/BotEngine.php

/**
 * Bot Engine
 *
 * Processes incoming messages through bot flows and AI.
 */
class BotEngine {

    private $tenantId;
    private $botId;
    private $bot;

    public function __construct($tenantId, $botId) {
        $this->tenantId = $tenantId;
        $this->botId = $botId;

        // Load bot
        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();
        $this->bot = $botModel->find($botId, $tenantId);
    }

    /**
     * Process incoming message
     *
     * @param string $message
     * @param int $conversationId
     * @return array Reply messages
     */
    public function processMessage($message, $conversationId) {
        $replies = [];

        // Try to match flow
        require_once __DIR__ . '/../models/Flow.php';
        $flowModel = new Flow();
        $matchedFlow = $flowModel->findMatchingFlow($this->botId, $message, $this->tenantId);

        if ($matchedFlow) {
            // Execute flow
            $replies = $this->executeFlow($matchedFlow, $conversationId);
        } elseif ($this->bot && $this->bot['ai_enabled']) {
            // Fall back to AI
            $aiReply = $this->generateAIResponse($message, $conversationId);
            if ($aiReply) {
                $replies[] = [
                    'type' => 'text',
                    'content' => $aiReply,
                    'triggered_by' => 'ai'
                ];
            }
        }

        // If no flow matched and no AI, send default message
        if (empty($replies)) {
            $replies[] = [
                'type' => 'text',
                'content' => 'Thank you for your message. A team member will respond shortly.',
                'triggered_by' => 'flow'
            ];
        }

        return $replies;
    }

    /**
     * Execute flow steps
     *
     * @param array $flow
     * @param int $conversationId
     * @return array Reply messages
     */
    private function executeFlow($flow, $conversationId) {
        $replies = [];

        require_once __DIR__ . '/../models/FlowStep.php';
        $stepModel = new FlowStep();
        $steps = $stepModel->getByFlow($flow['id'], $this->tenantId);

        foreach ($steps as $step) {
            $config = json_decode($step['action_config'], true);

            switch ($step['action_type']) {
                case 'send_text':
                    $replies[] = [
                        'type' => 'text',
                        'content' => $config['message'],
                        'triggered_by' => 'flow',
                        'flow_id' => $flow['id']
                    ];
                    break;

                case 'send_media':
                    $replies[] = [
                        'type' => 'media',
                        'content' => $config['caption'] ?? '',
                        'media_url' => $config['media_url'] ?? '',
                        'triggered_by' => 'flow',
                        'flow_id' => $flow['id']
                    ];
                    break;

                case 'ask_question':
                    $replies[] = [
                        'type' => 'text',
                        'content' => $config['question'],
                        'triggered_by' => 'flow',
                        'flow_id' => $flow['id']
                    ];
                    // Save variable key for next message
                    if (isset($config['variable'])) {
                        require_once __DIR__ . '/../models/ConversationVariable.php';
                        $varModel = new ConversationVariable();
                        $varModel->setVariable($conversationId, '_awaiting_input', $config['variable']);
                    }
                    break;

                case 'call_ai':
                    $aiPrompt = $config['prompt'] ?? '';
                    $aiReply = $this->generateAIResponse($aiPrompt, $conversationId);
                    if ($aiReply) {
                        $replies[] = [
                            'type' => 'text',
                            'content' => $aiReply,
                            'triggered_by' => 'ai',
                            'flow_id' => $flow['id']
                        ];
                    }
                    break;

                case 'set_variable':
                    if (isset($config['key']) && isset($config['value'])) {
                        require_once __DIR__ . '/../models/ConversationVariable.php';
                        $varModel = new ConversationVariable();
                        $varModel->setVariable($conversationId, $config['key'], $config['value']);
                    }
                    break;
            }
        }

        return $replies;
    }

    /**
     * Generate AI response (mock implementation)
     *
     * @param string $query
     * @param int $conversationId
     * @return string|null
     */
    private function generateAIResponse($query, $conversationId) {
        // Search knowledge base first
        require_once __DIR__ . '/../models/KnowledgeBase.php';
        $kbModel = new KnowledgeBase();
        $results = $kbModel->search($query, $this->tenantId, $this->botId, 1);

        if (!empty($results)) {
            $answer = $results[0]['answer'];

            // Apply AI tone formatting
            if ($this->bot) {
                $answer = $this->applyTone($answer, $this->bot['ai_tone']);
            }

            return $answer;
        }

        // Fallback AI response (mock)
        return "I understand you're asking about: " . $query . ". Let me connect you with a team member who can help.";
    }

    /**
     * Apply tone to response
     *
     * @param string $text
     * @param string $tone
     * @return string
     */
    private function applyTone($text, $tone) {
        // Simple tone application (in real implementation, this would use LLM)
        switch ($tone) {
            case 'friendly':
                return $text . " 😊";
            case 'formal':
                return "Dear customer, " . $text;
            case 'concise':
                return substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '');
            default:
                return $text;
        }
    }
}
