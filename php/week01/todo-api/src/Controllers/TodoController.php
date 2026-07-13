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
}