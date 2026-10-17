<?php
// FILE: /app/models/Template.php

/**
 * Template Model - reusable message templates with {{placeholders}}.
 */
class Template extends Model {

    const CATEGORIES = ['notification', 'support', 'marketing', 'other'];

    protected $table = 'templates';
    protected $searchable = ['name', 'body'];

    /**
     * Replace {{key}} placeholders. Unknown placeholders become empty strings.
     */
    public static function render($text, $variables) {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($variables) {
            return isset($variables[$m[1]]) ? (string) $variables[$m[1]] : '';
        }, (string) $text);
    }

    /** List placeholder names used in a text, e.g. ["name", "order_id"]. */
    public static function extractVariables($text) {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', (string) $text, $m);
        return array_values(array_unique($m[1]));
    }

    /** Full message (header + body + footer) rendered for one contact. */
    public static function renderFull($template, $variables) {
        $parts = [];
        foreach (['header', 'body', 'footer'] as $part) {
            if (!empty($template[$part])) {
                $parts[] = self::render($template[$part], $variables);
            }
        }
        return implode("\n\n", $parts);
    }

    public function getActiveForSelect($tenantId) {
        return $this->query(
            "SELECT id, name, body, variables FROM {$this->table} WHERE tenant_id = ? AND status = 'active' ORDER BY name",
            [$tenantId]
        )->fetchAll();
    }
}
