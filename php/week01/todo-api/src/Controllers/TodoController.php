<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TodoService;
use App\Support\Response;

class TodoController
{
    public function __construct(
        private TodoService $todoService,
    ) {}

    public function index(): void
    {
        Response::success($this->todoService->list());
    }
    public function show(int $id): void
    {
        $todo = $this->todoService->find($id);
        if ($todo === null) {
            Response::error(404, "Todo not found");
            return;
        }
        Response::success($todo);
    }
    public function store(array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            Response::error(400, "Title is required");
            return;
        }
        $todo = $this->todoService->create($title);
        Response::success($todo);
    }
    
    public function update(int $id, array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));
        $done = $payload['done'] ?? false;
        if ($title === '') {
            Response::error(400, "Title is required");
            return;
        }
        $todo = $this->todoService->update($id, $title, $done);
        if ($todo === null) {
            Response::error(404, "Todo not found");
            return;
        }
        Response::success($todo);
    }
    
    public function destroy(int $id): void
    {
        $todo = $this->todoService->find($id);
        if ($todo === null) {
            Response::error(404, "Todo not found");
            return;
        }
        $this->todoService->delete($id);
        Response::success(null);
    }
}
