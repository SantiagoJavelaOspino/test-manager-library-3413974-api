<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Router;
use App\Infrastructure\Composition\Container;

$container = Container::instance();
$router = new Router($container);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->handle($method, $uri);
