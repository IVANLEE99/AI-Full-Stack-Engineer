<?php

declare(strict_types=1);

trait LogTrait
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}

trait TimeTrait
{
    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

class UserService
{
    use LogTrait;
    use TimeTrait;

    public function createUser(string $name): void
    {
        $this->log("Create user: " . $name . " at " . $this->now());
    }
}

$service = new UserService();
$service->createUser("Tom");


trait A
{
    public function hello(): string
    {
        return "A";
    }
}

trait B
{
    public function hello(): string
    {
        return "B";
    }
}


class Demo
{
    use A, B {
        A::hello insteadof B;
        B::hello as helloFromB;
    }
}

$demo = new Demo();
echo $demo->hello() . PHP_EOL;
echo $demo->helloFromB() . PHP_EOL;