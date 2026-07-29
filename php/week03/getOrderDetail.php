<?php
 declare(strict_types=1);
 function getOrderDetail(int $id): array
 {
    $key = 'order:detail:' . $id;
    $cache = $redis->get($key);
    if ($cache !== false) {
        return json_decode($cache, true);
    }
    $order = $orderRepository->getOrderById($id);
    $redis->set($key, json_encode($order));
    $redis->expire($key, 60 * 60 * 24);
    return $order;
 }