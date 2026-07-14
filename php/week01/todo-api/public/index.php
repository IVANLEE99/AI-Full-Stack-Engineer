<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\TodoController;
use App\Services\TodoService;
use App\Support\Response;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$payload = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$controller = new TodoController(new TodoService());

if ($method === 'GET' && $path === '/todos') {
    $controller->index();
    return;
}

if ($method === 'POST' && $path === '/todos') {
    $controller->store($payload);
    return;
}

if(preg_match('/^\/todos\/(\d+)$/', $path, $matches)) {
    $id = (int) $matches[1];
    if ($method === 'GET') {
        $controller->show($id);
        return;
    }
    if ($method === 'PUT') {
        $controller->update($id, $payload);
        return;
    }
    if ($method === 'DELETE') {
        $controller->destroy($id);
        return;
    }
    Response::error(405, 'Method Not Allowed');
    return;
}

Response::error(404, 'Not Found');