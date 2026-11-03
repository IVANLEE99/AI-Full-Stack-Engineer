<?php
/**
 * day01 概念的动手验证：Producer → Exchange → Queue → Consumer
 * 用法：docker compose exec php php /app/docker/demo_mq.php
 *
 * 注意：这里用的 AMQPStreamConnection 就是 RabbitMq.php 里的同一个类，
 *      只是把 host 换成了 compose 服务名 rabbitmq。
 */

require '/app/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'payment.events';   // 交换机：决定怎么路由
$queue    = 'order_paid_queue'; // 队列：存消息
$routeKey = 'payment.succeeded'; // 路由键：消息类型

echo "=== 1. 建立连接（用服务名 rabbitmq，不是 127.0.0.1）===\n";
$conn = new AMQPStreamConnection('rabbitmq', 5672, 'admin', 'admin123');
$ch   = $conn->channel();
echo "连接成功\n\n";

echo "=== 2. 声明 exchange / queue 并绑定 ===\n";
$ch->exchange_declare($exchange, 'direct', false, true, false);
[$queueName] = $ch->queue_declare($queue, false, true, false, false);
$ch->queue_bind($queueName, $exchange, $routeKey);
echo "exchange={$exchange} type=direct\n";
echo "queue={$queueName}\n";
echo "binding: {$exchange} --[{$routeKey}]--> {$queueName}\n\n";

echo "=== 3. 发送消息（persistent，对应 RabbitMq.php 的 delivery_mode）===\n";
$payload = json_encode([
    'event_id'   => 'evt_' . time(),
    'event_type' => 'payment.succeeded',
    'order_id'   => 1001,
    'amount'     => 1999,
], JSON_UNESCAPED_UNICODE);

$msg = new AMQPMessage($payload, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
$ch->basic_publish($msg, $exchange, $routeKey);
echo "已发送: {$payload}\n\n";

echo "=== 4. 消费（拉一条，手动 ack）===\n";
$msg = $ch->basic_get($queueName, false);
if ($msg) {
    echo "收到: {$msg->body}\n";
    $data = json_decode($msg->body, true);
    echo "解析 event_id = {$data['event_id']}\n";
    echo "解析 order_id = {$data['order_id']}\n";

    // 幂等在这里做：真实项目应用 event_id 查库/缓存判断是否处理过
    echo "\n>>> 消费者幂等提示：用 event_id 判断是否已处理，避免重复发权益\n";

    $msg->ack();
    echo "已 ack\n";
} else {
    echo "队列为空\n";
}

echo "\n=== 5. 队列当前状态 ===\n";
[$q, $messageCount, $consumerCount] = $ch->queue_declare($queueName, true);
echo "queue={$q} 剩余消息={$messageCount} 消费者数={$consumerCount}\n";

$ch->close();
$conn->close();
echo "\n完成\n";