<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Services\UserService;

$userService = new UserService();

echo $userService->getName() . PHP_EOL;