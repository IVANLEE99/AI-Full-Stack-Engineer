<?php

declare(strict_types=1);

namespace App\Services;

class TodoService
{
    public function list(): array
    {
        return [
            ["id" => 1, "title" => "Learn PHP", "done" => false],
            ["id" => 2, "title" => "Learn Composer", "done" => false],
        ];
    }
}