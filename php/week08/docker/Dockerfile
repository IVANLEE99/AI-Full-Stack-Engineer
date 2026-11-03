# 学习用 PHP 环境：与 day04 六条标准一一对应
# 基础镜像固定版本 —— 这就是「容器 PHP ≠ 本机 PHP」的证据
FROM php:8.2-cli

# pdo_mysql 是 PHP 自带的，直接编译；sockets 是 php-amqplib 3.x 的硬依赖
RUN docker-php-ext-install pdo_mysql sockets

# redis / amqp 需要 pecl；amqp 依赖 librabbitmq-dev
RUN apt-get update \
    && apt-get install -y --no-install-recommends librabbitmq-dev libssl-dev unzip \
    && pecl install redis amqp \
    && docker-php-ext-enable redis amqp \
    && rm -rf /var/lib/apt/lists/*

# composer：从官方安装脚本装，避免多拉一个镜像
RUN curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
