<?php

declare(strict_types=1);

class User
{
    public string $name = "Tom";

    public function sayHello(): string
    {
        return "Hello, " . $this->name;
    }
}

$user = new User();

echo $user->sayHello();

echo "\n";

class Counter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}

$counter = new Counter();
$counter->increment();
$counter->increment();

echo $counter->count; // 2

echo "\n";

class UserConstructor
{
    public string $name;
    public int $age;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;
    }

    public function profile(): string
    {
        return $this->name . " is " . $this->age . " years old";
    }
}

$user = new UserConstructor("Tom", 18);

echo $user->profile();

echo "\n";

// 1.6 PHP 8 构造函数属性提升
// PHP 8 引入了构造函数属性提升（Constructor Property Promotion），允许在构造函数的参数中直接声明类属性，从而简化代码。

class UserConstructorPropertyPromotion
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}

    public function profile(): string
    {
        return $this->name . " is " . $this->age . " years old";
    }
}

$user = new UserConstructorPropertyPromotion("Tom", 18);

echo $user->profile();

echo "\n";


// 1.7 访问控制：public / protected / private

class UserAccessControl
{
    public string $name = "Tom";
    protected string $role = "member";
    private string $password = "secret";

    public function getPasswordMask(): string
    {
        return "******";
    }
}

$user = new UserAccessControl();

echo $user->name; // 可以
// echo $user->role; // 不可以：protected
// echo $user->password; // 不可以：private

echo "\n";

// 1.9 继承：extends

class Animal
{
    public function eat(): string
    {
        return "eating";
    }
}

class Dog extends Animal
{
    public function bark(): string
    {
        return "wang wang";
    }
}

$dog = new Dog();

echo $dog->eat();  // 从 Animal 继承
echo "\n";
echo $dog->bark(); // Dog 自己的方法
echo "\n";


// 1.10 方法重写 Override

class AnimalOverride
{
    public function speak(): string
    {
        return "some sound";
    }
}

class DogOverride extends AnimalOverride
{
    public function speak(): string
    {
        return "wang wang";
    }
}

class CatOverride extends AnimalOverride
{
    public function speak(): string
    {
        return "miao miao";
    }
}

echo (new DogOverride())->speak(); // wang wang
echo "\n";
echo (new CatOverride())->speak(); // miao miao
echo "\n";

// 1.11 多态是什么？
// 多态是指同一个接口可以有多种不同的实现方式。在 PHP 中，多态通常通过继承和方法重写来实现。
// 例如，Animal 类可以有多种不同的实现，如 Dog、Cat 等，它们都可以实现 speak() 方法，但具体实现不同。

class AnimalPoly
{
    public function speak(): string
    {
        return "some sound";
    }
}

class DogPoly extends AnimalPoly
{
    public function speak(): string
    {
        return "wang wang";
    }
}

class CatPoly extends AnimalPoly
{
    public function speak(): string
    {
        return "miao miao";
    }
}

function makeAnimalSpeak(AnimalPoly $animal): void
{
    echo $animal->speak() . PHP_EOL;
    // PHP_EOL 是 PHP 内置常量，表示当前操作系统的换行符。
}

makeAnimalSpeak(new DogPoly());
makeAnimalSpeak(new CatPoly());