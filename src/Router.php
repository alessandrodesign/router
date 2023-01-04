<?php

namespace NORTEdev\Router;

use Exception;

class Router
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';
    const DEFAULT_LANG = 'pt_BR';
    protected array $routes = [
        self::METHOD_GET => [],
        self::METHOD_POST => [],
        self::METHOD_PUT => [],
        self::METHOD_PATCH => [],
        self::METHOD_DELETE => []
    ];
    protected array $namedRoutes = [];
    protected array $middleware = [];
    protected array $namespaces = [];
    protected ?string $urlNase = null;
    protected string $lang = self::DEFAULT_LANG;
    protected array $supportedLang = [];

    public function __construct(?string $urlNase = null)
    {
        $this->urlNase = $urlNase;
        $pathLangs = dirname(__DIR__) . DIRECTORY_SEPARATOR . "lang";
        if (is_dir($pathLangs)) {
            $langs = [];
            foreach (new \DirectoryIterator($pathLangs) as $file) {
                if ($file->isDot() || $file->isDir() || $file->getExtension() === "mo") {
                    continue;
                }

                $langs[] = $file->getBasename("." . $file->getExtension());
            }
            $this->setSupportedLang($langs);
            $this->loadLang($pathLangs);
        }
    }

    public function add(string $method, string $path, $callback, $name = null): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$path] = $callback;
        if ($name) {
            $this->namedRoutes[$name] = $path;
        }
    }

    public function get(string $path, $callback, $name = null): Router
    {
        $this->add(self::METHOD_GET, $path, $callback, $name);
        return $this;
    }

    public function post(string $path, $callback, $name = null): Router
    {
        $this->add(self::METHOD_POST, $path, $callback, $name);
        return $this;
    }

    public function put(string $path, $callback, $name = null): Router
    {
        $this->add(self::METHOD_PUT, $path, $callback, $name);
        return $this;
    }

    public function patch(string $path, $callback, $name = null): Router
    {
        $this->add(self::METHOD_PATCH, $path, $callback, $name);
        return $this;
    }

    public function delete(string $path, $callback, $name = null): Router
    {
        $this->add(self::METHOD_DELETE, $path, $callback, $name);
        return $this;
    }

    public function group(string $prefix, $callback, $middleware = null): void
    {
        // create a new router instance
        $group = new self();
        // call the callback with the new router instance
        call_user_func($callback, $group);
        // check if a middleware was provided
        if ($middleware !== null) {
            // add the middleware to the new router instance
            $group->addMiddleware($middleware);
        }
        // loop through the routes of the new router instance
        foreach ($group->getRoutes() as $method => $routes) {
            // loop through the routes of the current method
            foreach ($routes as $route => $callback) {
                // add the prefix to the route path
                $this->routes[$method][$prefix . $route] = $callback;
            }
        }
        // loop through the named routes of the new router instance
        foreach ($group->getNamedRoutes() as $name => $route) {
            // add the prefix to the route path
            $this->namedRoutes[$name] = $prefix . $route;
        }
        // add the middleware of the new router instance to the current router
        $this->middleware = array_merge($this->middleware, $group->getMiddleware());
        // add the namespaces of the new router instance to the current router
        $this->namespaces = array_merge($this->namespaces, $group->getNamespaces());
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $callback) {
                $matches = [];
                if (preg_match_all('#:([\w]+)#', $route, $param_keys)) {
                    // grab array with matches
                    $param_keys = $param_keys[1];
                    // prepare pattern
                    $pattern = "#^" . preg_replace('#:[\w]+#', '([\w]+)', $route) . "$#";
                    if (preg_match($pattern, $path, $matches)) {
                        // remove the first match
                        array_shift($matches);
                        // loop through matches and map parameter names to values
                        $params = array_combine($param_keys, $matches);
                        // call the middleware
                        $this->callMiddleware($route, $method);
                        // call the callback with the parameters and namespaces
                        $this->callController($callback, $params, $this->namespaces);
                        return;
                    }
                }
            }
        }
        // handle not found case
        header("HTTP/1.0 404 Not Found");
        echo gettext("404 Not Found");
        exit;
    }

    /**
     * @throws \Exception
     */
    public function redirect(string $routeName, array $params = []): void
    {
        if (!isset($this->namedRoutes[$routeName])) {
            throw new Exception(sprintf(gettext("Route %s not found"), $routeName));
        }
        $route = $this->namedRoutes[$routeName];
        // replace route parameters with values
        foreach ($params as $key => $value) {
            $route = str_replace(":" . $key, $value, $route);
        }
        header("Location: " . $route, true, 302);
        exit;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    public function addMiddleware($middleware): Router
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function addNamespace(string $namespace): Router
    {
        $this->namespaces[] = $namespace;
        return $this;
    }

    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * @throws \Exception
     */
    public function url(string $name, array $params = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception(gettext('Route not found'));
        }
        $path = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $path = str_replace(":$key", $value, $path);
        }
        return $path;
    }

    protected function callMiddleware(string $route, string $method): void
    {
        foreach ($this->middleware as $middleware) {
            if (is_callable($middleware)) {
                call_user_func_array($middleware, [$route, $method]);
            }
        }
    }

    protected function callController($callback, array $params, array $namespaces): void
    {
        // check if the callback is a controller and method string
        if (is_string($callback) && strpos($callback, '@') !== false) {
            // split the controller and method
            list($class, $method) = explode('@', $callback);
            // check if there are namespaces to be prepended
            if (!empty($namespaces)) {
                // loop through the namespaces in reverse order
                for ($i = count($namespaces) - 1; $i >= 0; $i--) {
                    // prepend the namespace to the class name
                    $class = $namespaces[$i] . '\\' . $class;
                }
            }
            // create a new instance of the controller class
            $controller = new $class();
            // call the method on the controller instance with the parameters
            call_user_func_array([$controller, $method], $params);
        } else {
            // call the callback with the parameters
            call_user_func_array($callback, $params);
        }
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @param string $lang
     */
    public function setLang(string $lang): void
    {
        $this->lang = $this->getSupportedLang($lang) ? $lang : self::DEFAULT_LANG;
    }

    protected function getSupportedLang(string $lang): bool
    {
        return in_array($lang, $this->supportedLang);
    }

    protected function setSupportedLang(array $langs): void
    {
        $this->supportedLang = array_merge($this->supportedLang, $langs);
    }

    public function listSupportedLang(): array
    {
        return $this->supportedLang;
    }

    private function loadLang(string $pathLangs)
    {
        putenv("LANG={$this->getLang()}");
        setlocale(LC_ALL, $this->getLang());
        bindtextdomain('messages', $pathLangs);
        textdomain('messages');
    }
}

