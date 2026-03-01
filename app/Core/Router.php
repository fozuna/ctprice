<?php

namespace App\Core;

class Router
{
    protected $routes = [];

    public function get($uri, $controller)
    {
        $this->routes['GET'][$uri] = $controller;
    }

    public function post($uri, $controller)
    {
        $this->routes['POST'][$uri] = $controller;
    }

    public function dispatch($uri, $method)
    {
        // Remove query string
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Remove trailing slash if not root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }

        // Handle base path if running in subdirectory
        // This is important for XAMPP htdocs/ctprice
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = str_replace('/public_html/index.php', '', $scriptName);
        
        // If URI starts with base path, strip it
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // If empty, it's root
        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        if (array_key_exists($uri, $this->routes[$method])) {
            $controllerAction = $this->routes[$method][$uri];
            $controller = new $controllerAction[0]();
            $action = $controllerAction[1];
            return $controller->$action();
        }

        // 404 Not Found
        http_response_code(404);
        // Simple 404 view or message
        if (class_exists('App\Controllers\ErrorController')) {
            $controller = new \App\Controllers\ErrorController();
            return $controller->notFound();
        } else {
            echo "404 Not Found";
        }
    }
}
