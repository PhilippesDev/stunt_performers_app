<?php
/**
 * Router Class - Lightweight Front Controller Routing for Cascade E-Commerce
 */

class Router {
    private static array $routes = [];
    private static ?string $basePath = null;

    /**
     * Get or calculate the application base path
     */
    public static function getBasePath(): string {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = dirname($scriptName);
        
        // Normalize directory separators
        $dir = str_replace('\\', '/', $dir);

        // Remove trailing '/public' if present in base directory path
        if (substr($dir, -7) === '/public') {
            $dir = substr($dir, 0, -7);
        }

        self::$basePath = rtrim($dir, '/');
        return self::$basePath;
    }

    /**
     * Add a route to the routing table
     */
    public static function add(string $method, string $path, string $target, ?string $name = null): void {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');
        
        self::$routes[] = [
            'method' => $method,
            'path'   => $path,
            'target' => $target,
            'name'   => $name
        ];
    }

    /**
     * Register GET route
     */
    public static function get(string $path, string $target, ?string $name = null): void {
        self::add('GET', $path, $target, $name);
    }

    /**
     * Register POST route
     */
    public static function post(string $path, string $target, ?string $name = null): void {
        self::add('POST', $path, $target, $name);
    }

    /**
     * Register route for any HTTP method
     */
    public static function any(string $path, string $target, ?string $name = null): void {
        self::add('ANY', $path, $target, $name);
    }

    /**
     * Generate absolute URL for a path or route name
     */
    public static function url(string $path = '', array $params = []): string {
        // If $path matches a route name, resolve its path
        foreach (self::$routes as $route) {
            if ($route['name'] && $route['name'] === $path) {
                $path = $route['path'];
                break;
            }
        }

        $path = '/' . ltrim($path, '/');

        // Replace route parameters e.g. {id}
        foreach ($params as $key => $val) {
            if (strpos($path, '{' . $key . '}') !== false) {
                $path = str_replace('{' . $key . '}', urlencode((string)$val), $path);
                unset($params[$key]);
            }
        }

        // Add remaining params as query parameters
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }

        return self::getBasePath() . $path;
    }

    /**
     * Dispatch incoming HTTP request to target route
     */
    public static function dispatch(): void {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Extract path component from URI
        $parsedUrl = parse_url($rawUri, PHP_URL_PATH);
        $uri = urldecode($parsedUrl ?? '/');

        // Strip base path
        $base = self::getBasePath();
        if ($base && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }

        // Normalize URI
        $uri = '/' . trim($uri, '/');
        if ($uri === '/public' || $uri === '/public/') {
            $uri = '/';
        } elseif (strpos($uri, '/public/') === 0) {
            $uri = '/' . trim(substr($uri, 8), '/');
        }

        // Check legacy .php extensions in request and clean them
        if (substr($uri, -4) === '.php') {
            $cleanPath = substr($uri, 0, -4);
            if ($cleanPath === '/index') {
                $cleanPath = '/';
            }
            $uri = $cleanPath;
        }

        // Find matching route
        foreach (self::$routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $requestMethod) {
                continue;
            }

            // Convert route pattern {param} to regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                // Populate $_GET with matched parameters
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $_GET[$key] = $value;
                    }
                }

                $target = $route['target'];
                $targetFile = __DIR__ . '/../public/' . ltrim($target, '/');

                if (file_exists($targetFile)) {
                    require $targetFile;
                    exit();
                } else {
                    http_response_code(500);
                    echo "Route target file not found: " . htmlspecialchars($target);
                    exit();
                }
            }
        }

        // If no route matched, send 404
        http_response_code(404);
        if (file_exists(__DIR__ . '/../public/404.php')) {
            require __DIR__ . '/../public/404.php';
        } else {
            echo "<!DOCTYPE html><html lang='fr'><head><title>404 Non trouvé</title><style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head><body><h1>404 - Page Non Trouvée</h1><p>La page demandée n'existe pas.</p><a href='" . htmlspecialchars(self::url('/')) . "'>Retour à l'accueil</a></body></html>";
        }
        exit();
    }
}

/**
 * Global Helper Functions
 */

if (!function_exists('url')) {
    function url(string $path = '', array $params = []): string {
        return Router::url($path, $params);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string {
        return Router::url($name, $params);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $statusCode = 302): void {
        header('Location: ' . url($path), true, $statusCode);
        exit();
    }
}
