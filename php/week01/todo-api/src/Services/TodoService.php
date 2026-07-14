<?php

declare(strict_types=1);

namespace App\Services;

class TodoService
{
    private array $todos = [
        1 => ["id" => 1, "title" => "Learn PHP", "done" => false],
        2 => ["id" => 2, "title" => "Learn Composer", "done" => false],
    ];
    
    public function list(): array
    {
        return array_values($this->todos);
    }
    
    public function find(int $id): ?array
    {
        return $this->todos[$id] ?? null;
    }
    
    public function create(string $title): array
    {
        $id = max(array_keys($this->todos)) + 1;
        $this->todos[$id] = ["id" => $id, "title" => $title, "done" => false];
        return $this->todos[$id];
    }
    
    public function update(int $id, string $title, bool $done): ?array
    {
        if (!isset($this->todos[$id])) {
            return null;
        }
        $this->todos[$id]["title"] = $title;
        $this->todos[$id]["done"] = $done;
        return $this->todos[$id];
    }
    
    public function delete(int $id): bool
    {
        if (!isset($this->todos[$id])) {
            return false;
        }
        unset($this->todos[$id]);
        return true;
    }
}
