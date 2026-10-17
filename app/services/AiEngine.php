<?php
// FILE: /app/services/AiEngine.php

/**
 * AI Engine (mock implementation)
 *
 * - answer():  FAQ-style answers from the knowledge base, with a free-form
 *              fallback that lists the topics the bot knows about.
 * - rewrite(): tidy up / improve an agent's draft reply.
 *
 * Swap the body of answer() for a real LLM call (OpenAI, Anthropic, ...)
 * later; the rest of the platform only depends on this interface.
 */
class AiEngine {

    const TONES = ['friendly', 'formal', 'concise', 'professional'];

    private $tenantId;
    private $botId;
    private $tone;
    private $maxLength;

    public function __construct($tenantId, $botId = null, $tone = 'friendly', $maxLength = 500) {
        $this->tenantId = (int) $tenantId;
        $this->botId = $botId !== null ? (int) $botId : null;
        $this->tone = in_array($tone, self::TONES, true) ? $tone : 'friendly';
        $this->maxLength = max(50, (int) $maxLength);
    }

    /**
     * @return array ['text' => string, 'source' => 'knowledge_base'|'fallback', 'kb_id' => int|null]
     */
    public function answer($question) {
        $kb = new KnowledgeBase();
        $results = $kb->search($question, $this->tenantId, $this->botId, 3);

        if (!empty($results)) {
            $best = $results[0];
            return [
                'text' => $this->finish($this->applyTone($best['answer'])),
                'source' => 'knowledge_base',
                'kb_id' => (int) $best['id']
            ];
        }

        return ['text' => $this->finish($this->freeForm($question)), 'source' => 'fallback', 'kb_id' => null];
    }

    /**
     * Mock "free-form" answer: acknowledge and suggest known topics.
     */
    private function freeForm($question) {
        $kb = new KnowledgeBase();
        $topics = array_slice(array_unique(array_column(
            $kb->findAll(['status' => 'active'], $this->tenantId, 5, 0),
            'topic'
        )), 0, 4);

        $text = "I don't have a confident answer to that yet, so I've flagged it for our team.";
        if (!empty($topics)) {
            $text .= ' Meanwhile, I can help with: ' . implode(', ', $topics) . '.';
        }
        return $this->applyTone($text);
    }

    /**
     * Improve a draft reply: normalise whitespace, capitalise, punctuate,
     * then apply the tone.
     */
    public function rewrite($text) {
        $text = trim(preg_replace('/[ \t]+/', ' ', (string) $text));
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/\bi\b/', 'I', $text);
        $text = preg_replace_callback('/(^|[.!?]\s+)([a-z])/', function ($m) {
            return $m[1] . strtoupper($m[2]);
        }, $text);
        if (!preg_match('/[.!?)]$/u', $text)) {
            $text .= '.';
        }
        return $this->finish($this->applyTone($text));
    }

    private function applyTone($text) {
        switch ($this->tone) {
            case 'friendly':
                return $text . ' 😊';
            case 'formal':
                return 'Dear customer, ' . self::lowerFirst($text) . ' Kind regards.';
            case 'concise':
                return preg_match('/^(.+?[.!?])(\s|$)/u', $text, $m) ? $m[1] : $text;
            default:
                return $text;
        }
    }

    /** Lower-case the first letter unless the text starts with "I " or an acronym. */
    private static function lowerFirst($text) {
        if (preg_match('/^(I\s|[A-Z]{2,})/', $text)) {
            return $text;
        }
        return mb_strtolower(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($text, 1, null, 'UTF-8');
    }

    /** Enforce the bot's max answer length on a word boundary. */
    private function finish($text) {
        if (mb_strlen($text, 'UTF-8') <= $this->maxLength) {
            return $text;
        }
        $cut = mb_substr($text, 0, $this->maxLength - 3, 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($space !== false && $space > $this->maxLength / 2) {
            $cut = mb_substr($cut, 0, $space, 'UTF-8');
        }
        return rtrim($cut, " ,;:") . '...';
    }
}

