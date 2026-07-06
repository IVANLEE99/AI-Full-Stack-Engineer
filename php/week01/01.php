<?php
declare(strict_types=1);

echo "Hello PHP\n";

$name = "Tom";
$age = 18;

echo $name;
echo "\n";
echo $age;
echo "\n";
echo "Hello PHP\n";

echo $name . " " . $age;
echo "\n";

echo "=== 变量类型演示 ===\n";

$name = "Alice";      // string 字符串
$age = 20;            // int 整数
$price = 19.99;       // float 浮点数
$isVip = true;        // bool 布尔值
$items = [1, 2, 3];   // array 数组
$user = null;         // null

var_dump($name);
echo "\n";
var_dump($age);
echo "\n";
var_dump($isVip);
echo "\n";
var_dump($items);
echo "\n";
var_dump($user);
echo "\n";

// 1.5 理解 PHP 数组
$names = ["Tom", "Jerry", "Alice"];

echo $names[0]; // Tom
echo "\n";

$user = [
    "name" => "Tom",
    "age" => 18,
    "is_vip" => true,
];

echo $user["name"];

echo "\n";

// 1.6 理解 PHP 函数和类型声明
function add($a, $b)
{
    return $a + $b;
}

echo add(1, 2);
echo "\n";

// 类型声明
function addTyped(int $a, int $b): int
{
    return $a + $b;
}

echo addTyped(1, 2);
echo "\n";

// 1.7 理解 strict_types=1
// declare(strict_types=1);

function addStrict(int $a, int $b): int
{
    return $a + $b;
}

echo addStrict(1, 2);
echo "\n";

// 1.8 至少掌握 3 个 PHP 8 特性
// 特性 1：match 类似 JS 的 switch，但更像表达式：
$status = 1;

$text = match ($status) {
    0 => "待支付",
    1 => "已支付",
    2 => "已取消",
    default => "未知状态",
};

echo $text;
echo "\n";

// 特性 2：nullsafe operator：?->

$user = null;
$name = $user?->profile?->name;
echo $name;
echo "\n";

// 特性 3：named arguments
function createUser(string $name, int $age, bool $isVip): array
{
    return [
        "name" => $name,
        "age" => $age,
        "is_vip" => $isVip,
    ];
}

$user = createUser(
    name: "Tom",
    age: 18,
    isVip: true,
);
echo json_encode($user);
echo "\n";

// 特性 4：enum
enum OrderStatus: int
{
    case Pending = 0;
    case Paid = 1;
    case Cancelled = 2;
}

echo OrderStatus::Pending->value;
echo "\n";
echo OrderStatus::Paid->value;
echo "\n";
echo OrderStatus::Cancelled->value;
echo "\n";

// 特性 5：readonly
class User
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}

$user = new User("Tom", 18);
echo $user->name;
echo "\n";
echo $user->age;
echo "\n";
// 修改会报错
// $user->name = "Jerry";

// 1.9 理解 Composer 是什么
// Composer 是 PHP 的依赖管理工具，类似于 Node.js 的 npm 或 Python 的 pip。
// 它允许你声明项目所依赖的外部库，并自动管理它们的安装和更新。
// 通过 Composer，你可以轻松地引入和使用第三方库，而无需手动下载和配置。


