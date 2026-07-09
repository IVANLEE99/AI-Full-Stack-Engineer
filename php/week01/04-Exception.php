<?php
    declare(strict_types=1);
function divide(int $a, int $b): float
{
    if ($b === 0) {
        throw new Exception("Division by zero");
    }
    return $a / $b;
}

try {
    echo divide(10, 2);
    echo "\n";
    echo divide(10, 0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage()   . "\n";
    echo "错误消息：" . $e->getMessage() . PHP_EOL;
    echo "错误文件：" . $e->getFile() . PHP_EOL;
    echo "错误行号：" . $e->getLine() . PHP_EOL;
    echo "错误代码：" . $e->getCode() . PHP_EOL;
    echo "错误调用链：" . $e->getTraceAsString() . PHP_EOL;
    echo "错误调用链数组：" . print_r($e->getTrace(), true) . PHP_EOL;
}


try {
    echo "开始执行" . PHP_EOL;
    throw new Exception("出错了");
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . PHP_EOL;
} finally {
    echo "这里一定会执行" . PHP_EOL;
}


function errorResponse(int $code, string $message): array
{
    return [
        "code" => $code,
        "message" => $message,
        "data" => null,
    ];
}

try {
    throw new Exception("参数错误");
} catch (Exception $e) {
    print_r(errorResponse(400, $e->getMessage()));
}


class UserRepository
{
    public function findById(int $id): array
    {
        return [
            "id" => $id,
            "name" => "Tom",
        ];
    }
}

class UserService
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function getProfile(int $id): array
    {
        $user = $this->repository->findById($id);

        return [
            "id" => $user["id"],
            "display_name" => $user["name"],
        ];
    }
}

$service = new UserService(new UserRepository());

print_r($service->getProfile(1));