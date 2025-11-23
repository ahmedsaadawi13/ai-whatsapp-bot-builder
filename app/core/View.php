<?php
// FILE: /app/core/View.php

/**
 * View Renderer
 *
 * Handles rendering of view templates with layout support.
 */
class View {

    /**
     * Render a view with optional layout
     *
     * @param string $view View file path (relative to app/views)
     * @param array $data Data to extract into view scope
     * @param string|null $layout Layout file or null for no layout
     */
    public function render($view, $data = [], $layout = 'layout') {
        extract($data);

        // Start output buffering
        ob_start();

        // Load the view file
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new Exception("View not found: {$view}");
        }

        require $viewFile;

        // Get view content
        $content = ob_get_clean();

        // If layout is specified, wrap content in layout
        if ($layout) {
            $layoutFile = __DIR__ . '/../views/layouts/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new Exception("Layout not found: {$layout}");
            }

            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Escape HTML output
     *
     * @param string $value
     * @return string
     */
    public static function e($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Include a partial view
     *
     * @param string $partial
     * @param array $data
     */
    public static function partial($partial, $data = []) {
        extract($data);
        $partialFile = __DIR__ . '/../views/partials/' . $partial . '.php';
        if (file_exists($partialFile)) {
            require $partialFile;
        }
    }
}
