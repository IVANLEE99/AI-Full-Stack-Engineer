<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository
{
    public function findNameById(int $id): string
    {
        return "User#" . $id;
    }
}