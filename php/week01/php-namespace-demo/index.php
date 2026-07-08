<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Repositories\UserRepository;
use App\Services\UserService;

$service = new UserService();
$repository = new UserRepository();

echo $service->getName() . PHP_EOL;
echo $repository->findNameById(1) . PHP_EOL;