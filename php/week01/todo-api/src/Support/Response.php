<?php

declare(strict_types=1);

namespace App\Support;

class Response
{
    public static function json(int $code, string $message, mixed $data = null): void
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            "code" => $code,
            "message" => $message,
            "data" => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function success(mixed $data = null): void
    {
        self::json(0, "success", $data);
    }

    public static function error(int $code, string $message): void
    {
        self::json($code, $message, null);
    }
}