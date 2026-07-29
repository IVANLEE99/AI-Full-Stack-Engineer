#!/usr/bin/env php
<?php

require __DIR__ . '/NavicatPassword.php';

use FatSmallTools\NavicatPassword;

$encrypted = $argv[1] ?? '';
$version = (int)($argv[2] ?? 12);

if ($encrypted === '') {
    fwrite(STDERR, "用法: php navicat-decrypt.php <密文> [版本:11|12]\n");
    fwrite(STDERR, "示例: php navicat-decrypt.php 4F3A2B1C9D8E7F60514253647586970 12\n");
    exit(1);
}

$encrypted = strtoupper(preg_replace('/\s+/', '', $encrypted));

foreach ([$version, $version === 12 ? 11 : 12] as $tryVersion) {
    $tool = new NavicatPassword($tryVersion);
    $plain = $tool->decrypt($encrypted);

    if ($plain !== false && $plain !== '') {
        echo "版本: {$tryVersion}\n";
        echo "密码: {$plain}\n";
        exit(0);
    }
}

fwrite(STDERR, "解密失败，请检查密文或 Navicat 版本（仅支持 11/12 算法）。\n");
exit(1);
