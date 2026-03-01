<?php

namespace App\Core;

class View
{
    public static function render($view, $data = [], $layout = 'layouts.main')
    {
        extract($data);
        
        // Capture view content
        ob_start();
        $viewFile = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            throw new \Exception("View {$view} not found at {$viewFile}");
        }
        $content = ob_get_clean();
        
        // Render layout with content
        if ($layout) {
            $layoutFile = __DIR__ . '/../Views/' . str_replace('.', '/', $layout) . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
            } else {
                throw new \Exception("Layout {$layout} not found at {$layoutFile}");
            }
        } else {
            echo $content;
        }
    }
}
