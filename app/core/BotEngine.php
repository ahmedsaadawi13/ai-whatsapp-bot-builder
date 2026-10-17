<?php
// FILE: /app/core/BotEngine.php

/**
 * Bot Engine - turns one incoming message into bot replies.
 *
 * Order of evaluation:
 *  1. If a flow is waiting for an answer (ask_question with a variable),
 *     store the answer and resume that flow - unless the message clearly
 *     starts another flow (e.g. "menu").
 *  2. Otherwise run the best matching flow (keyword / contains / menu / button / default).
 *  3. Otherwise answer with AI (knowledge base) when the bot allows AI fallback.
 *  4. Otherwise send the bot's fallback message.
 *
 * Every reply is an array:
 *   ['type' => text|media|interactive, 'content' => string, 'media_url' => ?string,
 *    'quick_replies' => ?array, 'triggered_by' => flow|ai, 'flow_id' => ?int, 'metadata' => array]
 *
 * Message text may contain {{variables}}: any conversation variable plus
 * contact_name, contact_phone, contact_email and custom.<field>.
 */
class BotEngine {

    const MAX_FLOW_JUMPS = 3;
    const INTERNAL_KEYS = ['_awaiting_input', '_resume_flow', '_resume_step'];

    private $tenantId;
    private $bot;
    private $conversationId = 0;
    private $contact = [];
    private $lastMessage = '';
    private $variables;
    private $flowModel;
    private $stepModel;

    /**
     * @param int       $tenantId
     * @param array|int $bot Bot row or bot ID (must belong to the tenant)
     */
    public function __construct($tenantId, $bot) {
        $this->tenantId = (int) $tenantId;
        $this->bot = is_array($bot) ? $bot : (new Bot())->find((int) $bot, $this->tenantId);
        if (!$this->bot || (int) $this->bot['tenant_id'] !== $this->tenantId) {
            throw new InvalidArgumentException('Bot not found for this tenant');
        }
        $this->variables = new ConversationVariable();
        $this->flowModel = new Flow();
        $this->stepModel = new FlowStep();
    }

    /**
     * @param string $message        Incoming text
     * @param int    $conversationId Conversation (already verified for this tenant)
     * @param array  $contact        Contact row (for {{contact_name}} etc.)
     * @return array List of replies
     */
    public function processMessage($message, $conversationId, $contact = []) {
        $this->conversationId = (int) $conversationId;
        $this->contact = is_array($contact) ? $contact : [];
        $this->lastMessage = (string) $message;

        $matched = $this->flowModel->findMatchingFlow($this->bot['id'], $message, $this->tenantId);
        $awaiting = $this->variables->getVariable($this->conversationId, '_awaiting_input');

        // 1. Resume a paused flow with the user's answer.
        if ($awaiting !== null) {
            $resumeFlowId = (int) $this->variables->getVariable($this->conversationId, '_resume_flow');
            $resumeStepId = (int) $this->variables->getVariable($this->conversationId, '_resume_step');
            $this->variables->deleteVariables($this->conversationId, self::INTERNAL_KEYS);

            $startsOtherFlow = $matched && $matched['trigger_type'] !== 'default' && (int) $matched['id'] !== $resumeFlowId;
            if (!$startsOtherFlow) {
                $this->variables->setVariable($this->conversationId, $awaiting, trim($message));
                $flow = $this->flowModel->find($resumeFlowId, $this->tenantId);
                if ($flow && $flow['status'] === 'active' && (int) $flow['bot_id'] === (int) $this->bot['id']) {
                    $replies = $this->executeFlow($flow, $resumeStepId, 0);
                    // A flow that just finished silently still deserves an acknowledgement.
                    return !empty($replies) ? $replies : [$this->textReply('Thanks, got it!', 'flow', $flow['id'])];
                }
            }
        }

        // 2. Matching flow.
        if ($matched) {
            $replies = $this->executeFlow($matched, 0, 0);
            if (!empty($replies)) {
                return $replies;
            }
        }

        // 3. AI fallback.
        if (!empty($this->bot['ai_enabled'])) {
            return [$this->aiReply($message, null)];
        }

        // 4. Static fallback.
        $fallback = !empty($this->bot['fallback_message'])
            ? $this->bot['fallback_message']
            : 'Thank you for your message. A team member will respond shortly.';
        return [$this->textReply($this->render($fallback), 'flow', null)];
    }

    /**
     * Run a flow's steps in (step_order, id) order. With $afterStepId > 0 the
     * flow resumes after that step (the question that was just answered).
     * Stops at an ask_question (state saved) or when jumping to another flow.
     */
    private function executeFlow($flow, $afterStepId, $depth) {
        $replies = [];
        $steps = $this->stepModel->getByFlow($flow['id'], $this->tenantId);

        if ($afterStepId > 0) {
            $position = array_search($afterStepId, array_map('intval', array_column($steps, 'id')), true);
            // If the paused step was deleted meanwhile, there is nothing left to resume.
            $steps = $position === false ? [] : array_slice($steps, $position + 1);
        }

        foreach ($steps as $step) {

            $config = json_decode((string) $step['action_config'], true);
            if (!is_array($config)) {
                error_log("BotEngine: invalid JSON config in flow step {$step['id']}");
                continue;
            }

            switch ($step['action_type']) {
                case 'send_text':
                    if (!empty($config['message'])) {
                        $replies[] = $this->textReply($this->render($config['message']), 'flow', $flow['id']);
                    }
                    break;

                case 'send_media':
                    if (!empty($config['media_url'])) {
                        $replies[] = [
                            'type' => 'media',
                            'content' => $this->render(isset($config['caption']) ? $config['caption'] : ''),
                            'media_url' => $config['media_url'],
                            'quick_replies' => null,
                            'triggered_by' => 'flow',
                            'flow_id' => (int) $flow['id'],
                            'metadata' => ['step_id' => (int) $step['id']]
                        ];
                    }
                    break;

                case 'ask_question':
                    if (empty($config['question'])) {
                        break;
                    }
                    $options = isset($config['options']) && is_array($config['options']) ? array_values($config['options']) : [];
                    $reply = $this->textReply($this->render($config['question']), 'flow', $flow['id']);
                    if (!empty($options)) {
                        $reply['type'] = 'interactive';
                        $reply['quick_replies'] = $options;
                        $reply['metadata']['quick_replies'] = $options;
                    }
                    $replies[] = $reply;

                    if (!empty($config['variable'])) {
                        $this->variables->setVariable($this->conversationId, '_awaiting_input', $config['variable']);
                        $this->variables->setVariable($this->conversationId, '_resume_flow', $flow['id']);
                        $this->variables->setVariable($this->conversationId, '_resume_step', $step['id']);
                    }
                    return $replies; // wait for the user's answer

                case 'call_ai':
                    $prompt = !empty($config['prompt']) ? $this->render($config['prompt']) : $this->lastMessage;
                    $replies[] = $this->aiReply($prompt, $flow['id']);
                    break;

                case 'call_webhook':
                    $reply = $this->callWebhook($config, $flow['id']);
                    if ($reply) {
                        $replies[] = $reply;
                    }
                    break;

                case 'set_variable':
                    if (!empty($config['key'])) {
                        $value = isset($config['value']) ? $this->render($config['value']) : '';
                        $this->variables->setVariable($this->conversationId, $config['key'], $value);
                    }
                    break;

                case 'branch_condition':
                    $passed = $this->evaluateCondition($config);
                    $message = $passed ? 'then_message' : 'else_message';
                    $jump = $passed ? 'then_flow_id' : 'else_flow_id';

                    if (!empty($config[$message])) {
                        $replies[] = $this->textReply($this->render($config[$message]), 'flow', $flow['id']);
                    }
                    if (!empty($config[$jump]) && $depth < self::MAX_FLOW_JUMPS) {
                        $target = $this->flowModel->find((int) $config[$jump], $this->tenantId);
                        if ($target && (int) $target['bot_id'] === (int) $this->bot['id'] && (int) $target['id'] !== (int) $flow['id']) {
                            return array_merge($replies, $this->executeFlow($target, 0, $depth + 1));
                        }
                    }
                    break;
            }
        }

        return $replies;
    }

    /**
     * Compare a conversation variable with a value.
     * Operators: == != > >= < <= contains exists
     */
    private function evaluateCondition($config) {
        $name = isset($config['variable']) ? $config['variable'] : '';
        $actual = $this->context()[$name] ?? null;
        $expected = isset($config['value']) ? $this->render($config['value']) : '';
        $operator = isset($config['operator']) ? $config['operator'] : '==';

        if ($operator === 'exists') {
            return $actual !== null && $actual !== '';
        }
        if ($actual === null) {
            return false;
        }

        $actualNum = self::toNumber($actual);
        $expectedNum = self::toNumber($expected);
        $numeric = $actualNum !== null && $expectedNum !== null;

        switch ($operator) {
            case '>':  return $numeric && $actualNum > $expectedNum;
            case '>=': return $numeric && $actualNum >= $expectedNum;
            case '<':  return $numeric && $actualNum < $expectedNum;
            case '<=': return $numeric && $actualNum <= $expectedNum;
            case '!=': return Flow::normalize($actual) !== Flow::normalize($expected);
            case 'contains': return strpos(Flow::normalize($actual), Flow::normalize($expected)) !== false;
            default:   return Flow::normalize($actual) === Flow::normalize($expected);
        }
    }

    /** "1,500 USD" -> 1500.0; non-numeric -> null */
    private static function toNumber($value) {
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * POST conversation context to an external URL. A JSON response may contain
     * {"reply": "text"} and/or {"variables": {"key": "value"}}.
     */
    private function callWebhook($config, $flowId) {
        if (empty($config['url'])) {
            return null;
        }
        $body = json_encode([
            'event' => 'flow.webhook',
            'bot_id' => (int) $this->bot['id'],
            'conversation_id' => $this->conversationId,
            'message' => $this->lastMessage,
            'contact' => [
                'phone_number' => isset($this->contact['phone_number']) ? $this->contact['phone_number'] : null,
                'name' => isset($this->contact['name']) ? $this->contact['name'] : null
            ],
            'variables' => $this->variables->getAllVariables($this->conversationId, false)
        ]);

        $result = WebhookDispatcher::post($config['url'], $body, isset($config['secret']) ? $config['secret'] : null);
        $data = json_decode((string) $result['body'], true);

        if ($result['ok'] && is_array($data)) {
            if (isset($data['variables']) && is_array($data['variables'])) {
                foreach ($data['variables'] as $key => $value) {
                    if (is_scalar($value)) {
                        $this->variables->setVariable($this->conversationId, $key, (string) $value);
                    }
                }
            }
            if (!empty($data['reply']) && is_string($data['reply'])) {
                return $this->textReply($this->render($data['reply']), 'flow', $flowId);
            }
            return null;
        }

        if (!empty($config['error_message'])) {
            return $this->textReply($this->render($config['error_message']), 'flow', $flowId);
        }
        return null;
    }

    private function aiReply($question, $flowId) {
        $ai = new AiEngine($this->tenantId, $this->bot['id'], $this->bot['ai_tone'], $this->bot['ai_max_length']);
        $answer = $ai->answer($question);
        $reply = $this->textReply($answer['text'], 'ai', $flowId);
        $reply['metadata'] = ['ai_source' => $answer['source'], 'kb_id' => $answer['kb_id']];
        return $reply;
    }

    private function textReply($text, $triggeredBy, $flowId) {
        return [
            'type' => 'text',
            'content' => $text,
            'media_url' => null,
            'quick_replies' => null,
            'triggered_by' => $triggeredBy,
            'flow_id' => $flowId !== null ? (int) $flowId : null,
            'metadata' => []
        ];
    }

    /** Variables available to {{placeholders}}. */
    private function context() {
        $context = $this->variables->getAllVariables($this->conversationId, false);
        $context['contact_name'] = !empty($this->contact['name']) ? $this->contact['name'] : 'there';
        $context['contact_phone'] = isset($this->contact['phone_number']) ? $this->contact['phone_number'] : '';
        $context['contact_email'] = isset($this->contact['email']) ? $this->contact['email'] : '';
        foreach (json_array(isset($this->contact['custom_fields']) ? $this->contact['custom_fields'] : null) as $key => $value) {
            if (is_scalar($value)) {
                $context['custom.' . $key] = $value;
            }
        }
        return $context;
    }

    private function render($text) {
        return Template::render($text, $this->context());
    }
}
