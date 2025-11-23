<?php
// FILE: /app/models/Template.php

/**
 * Template Model
 *
 * Handles message template data.
 */
class Template extends Model {

    protected $table = 'templates';

    /**
     * Parse template with variables
     *
     * @param string $template
     * @param array $variables
     * @return string
     */
    public function parseTemplate($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /**
     * Get template with parsed content
     *
     * @param int $id
     * @param int $tenantId
     * @param array $variables
     * @return array|null
     */
    public function getWithParsedContent($id, $tenantId, $variables = []) {
        $template = $this->find($id, $tenantId);

        if (!$template) {
            return null;
        }

        $template['parsed_body'] = $this->parseTemplate($template['body'], $variables);

        if ($template['header']) {
            $template['parsed_header'] = $this->parseTemplate($template['header'], $variables);
        }

        if ($template['footer']) {
            $template['parsed_footer'] = $this->parseTemplate($template['footer'], $variables);
        }

        return $template;
    }
}
