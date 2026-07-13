<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\TodoController;
use App\Services\TodoService;
use App\Support\Response;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $path === '/todos') {
    $controller = new TodoController(new TodoService());
    $controller->index();
    return;
}

Response::error(404, 'Not Found');