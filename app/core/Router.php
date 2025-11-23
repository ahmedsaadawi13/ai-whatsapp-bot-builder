<?php
// FILE: /app/core/Router.php

/**
 * Router Class
 *
 * Handles URL routing and dispatches requests to appropriate controllers.
 */
class Router {

    private $routes = [];
    private $currentRoute = null;

    /**
     * Add a GET route
     *
     * @param string $path
     * @param string $handler Controller@method format
     * @param array $middleware
     */
    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add a POST route
     *
     * @param string $path
     * @param string $handler Controller@method format
     * @param array $middleware
     */
    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add a route
     *
     * @param string $method HTTP method
     * @param string $path URL path
     * @param string $handler Controller@method
     * @param array $middleware
     */
    private function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    /**
     * Dispatch current request
     */
    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove trailing slash except for root
        if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
            $requestUri = rtrim($requestUri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchRoute($route['path'], $requestUri, $params)) {
                $this->currentRoute = $route;
                $this->handleRoute($route, $params);
                return;
            }
        }

        // No route matched - 404
        $this->handleNotFound();
    }

    /**
     * Match route pattern against URI
     *
     * @param string $pattern
     * @param string $uri
     * @param array $params Output parameters
     * @return bool
     */
    private function matchRoute($pattern, $uri, &$params) {
        $params = [];

        // Convert pattern to regex
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Extract parameter names
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $pattern, $paramNames);

            // Map parameter values to names
            for ($i = 0; $i < count($paramNames[1]); $i++) {
                $params[$paramNames[1][$i]] = $matches[$i + 1];
            }

            return true;
        }

        return false;
    }

    /**
     * Handle matched route
     *
     * @param array $route
     * @param array $params
     */
    private function handleRoute($route, $params) {
        // Run middleware
        foreach ($route['middleware'] as $middleware) {
            $middlewareClass = new $middleware();
            $middlewareClass->handle();
        }

        // Parse handler
        list($controllerName, $method) = explode('@', $route['handler']);

        // Load controller
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
        if (!file_exists($controllerFile)) {
            throw new Exception("Controller not found: {$controllerName}");
        }

        require_once $controllerFile;

        // Instantiate and call method
        $controller = new $controllerName();

        if (!method_exists($controller, $method)) {
            throw new Exception("Method not found: {$controllerName}@{$method}");
        }

        // Call controller method with params
        call_user_func_array([$controller, $method], $params);
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound() {
        http_response_code(404);
        echo '<h1>404 - Page Not Found</h1>';
        exit;
    }
}
