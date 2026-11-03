<?php
/**
 *
 * @author  白杨
 * @Date    2021/1/14 下午8:49
 * @package App\Utils
 */

namespace App\Utils;

use common\redis\common\MqRedis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMq
{
    /**
     * @return AMQPStreamConnection
     */
    public static function connectionInit()
    {
        //创建一个 RabbitMQ 的连接
        $connection = new AMQPStreamConnection(
            \Yii::$app->params["rabbitmq"]["host"],
            \Yii::$app->params["rabbitmq"]["port"],
            \Yii::$app->params["rabbitmq"]["user"],
            \Yii::$app->params["rabbitmq"]["pwd"],
            '/'
        );
        return $connection;
    }

    public static function send($exchangeName, $routeKey, $msg, $delaySeconds = 0, $isFormatMsg = true)
    {
        $envPre = getenv('YII_ENV_PRE');
        try {
            //创建一个 RabbitMQ 的连接
            $connection = new AMQPStreamConnection(
                \Yii::$app->params["rabbitmq"]["host"],
                \Yii::$app->params["rabbitmq"]["port"],
                \Yii::$app->params["rabbitmq"]["user"],
                \Yii::$app->params["rabbitmq"]["pwd"],
                '/'
            );
            //新建一个 channel
            $channel = $connection->channel();

            $sendResult = false;
            //推送成功
            $channel->set_ack_handler(
                function (AMQPMessage $message) use (&$sendResult) {
                    $sendResult = true;
                }
            );
            //推送失败
            $channel->set_nack_handler(
                function (AMQPMessage $message) use (&$sendResult) {
                    \Yii::info(sprintf('发送失败：,exchangeName:%s,routeKey:%s,msg:%s', $message->getExchange(), $message->getRoutingKey(), $message->body));
                    $sendResult = false;
                }
            );
            //进入发布确认模式。如果在将通道引入此模式之前或之后调用$ch->tx_select(),下一个调用$ch->wait()将导致发布确认模式和事务异常是互斥的.
            // 发布确认模式
            $channel->confirm_select();

            if (is_array($msg)) {
                $msg = json_encode($msg, 320);
            }

            if ($isFormatMsg) {
                //body处理
                $msg = self::formatMsg($msg);
            }

            $sendmsg = new AMQPMessage($msg, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            if (!empty($delaySeconds)) {
                $sendmsg->set('application_headers', new AMQPTable([
                    'x-delay' => $delaySeconds * 1000 // 延迟时间，单位毫秒
                ]));
            }

            $channel->basic_publish($sendmsg, $exchangeName, $routeKey);

            // 监听成功或失败返回结束 成功/失败 => set_ack_handler/set_nack_handler
            $channel->wait_for_pending_acks();

            //关闭渠道
            $channel->close();
            //关闭连接
            $connection->close();

            $sendStatus = 0;
            if ($sendResult) {
                $sendStatus = 1;
            }
            // 写入mq日志redis（成功/失败都会记，供落表 mq_insert_log）
            self::pushMqInsertLog($exchangeName, $routeKey, $msg, $delaySeconds, $sendStatus);

            //返回结果
            if ($sendResult) {
                return ['code' => 1, 'info' => 'success'];
            } else {
                $errorMsg= [
                    '发送rabbitMq 失败--' . ($envPre == 1 ? '预览' : ''),
                    json_encode([$exchangeName, $routeKey, $msg, $delaySeconds], 320),
                ];
                g_log_error('rabbitmq-error', 'mq_send_error', $errorMsg);
                return ['code' => 1002, 'info' => 'error'];
            }
        } catch (\Exception $e) {
            g_log_error('rabbitmq-error', $e);
            // 异常路径原先只打文件日志，这里补记到 mq_insert_log（send_status=0）
            $logMsg = is_array($msg) ? json_encode($msg, 320) : (string)$msg;
            $logMsg .= "\n" . json_encode(['exception' => $e->getMessage()], 320);
            self::pushMqInsertLog($exchangeName, $routeKey, $logMsg, $delaySeconds, 0);

            return ['code' => 1003, 'info' => $e->getMessage() . $e->getTraceAsString()];
        }
    }

    /**
     * 写入 MQ 发送日志队列，由 console 消费落库 mq_insert_log
     * send_status：1-成功，0-失败/异常
     *
     * @param string     $exchangeName
     * @param string     $routeKey
     * @param string|array $msg
     * @param int        $delaySeconds
     * @param int        $sendStatus
     */
    private static function pushMqInsertLog($exchangeName, $routeKey, $msg, $delaySeconds, $sendStatus)
    {
        try {
            //配置无需记录日志的exchange
            $noNeedLogExchanges = [\Yii::$app->params['event_track']['exchange_name'] ?? ''];
            if (in_array($exchangeName, $noNeedLogExchanges)) {
                return;
            }
            if (is_array($msg)) {
                $msg = json_encode($msg, 320);
            }
            MqRedis::instance()->lPushMqLogData([
                'msg'           => (string)$msg,
                'exchange_name' => $exchangeName,
                'route_key'     => $routeKey,
                'delay_seconds' => $delaySeconds,
                'time'          => time(),
                'send_status'   => $sendStatus,
            ]);
        } catch (\Exception $e) {
            // 写日志失败不影响主流程
            g_log_error('rabbitmq-error', 'pushMqInsertLogError', [
                'exchange' => $exchangeName,
                'route_key'=> $routeKey,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param $msg
     *
     * @return string
     */
    private static function formatMsg($msg)
    {
        //header信息
        $header = ["_empty" => true];
        MyFunction::instance()->mergeHmHeader($header, 'internal.bm.com');
        unset($header['_empty']);
        if (!YII_DEBUG) {
            $header['user-agent'] = 'Go-http-client/1.1';
        }
        $hmHeaderData = json_encode($header);

        //ip
        $realIp = MyFunction::instance()->JPGetRealIp();
        $serverIp = getenv('SERVER_IP');
        $realIp = $serverIp != '' ? $serverIp : $realIp;
        //数据处理
        $msg = $msg . "\n" . $hmHeaderData . "\n" . $realIp;
        return $msg;
    }

    /**
     * @deprecated
     * @param        $queue
     * @param        $callBackFunction
     * @param string $consumerType
     *
     * @throws \Exception
     */
    public static function consume($queue, $callBackFunction, $consumerType = 'order')
    {
        try {
            //创建一个 RabbitMQ 的连接
            $connection = new AMQPStreamConnection(
                \Yii::$app->params["rabbitmq"]["host"],
                \Yii::$app->params["rabbitmq"]["port"],
                \Yii::$app->params["rabbitmq"]["user"],
                \Yii::$app->params["rabbitmq"]["pwd"],
                '/'
            );
            //新建一个 channel
            $channel = $connection->channel();
            //公平调度 在确认前一个消息之前，不要向消费者发送新的消息。
            $channel->basic_qos(null, 1, null);


            //queue 队列名
            //consumer_tag 消费者标签，用来区分多个消费者
            //no_local 设置为true，表示 不能将同一个Conenction中生产者发送的消息传递给这个Connection中 的消费者
            //no_ack 收到消息后,是否不需要回复确认即被认为被消费
            //exclusive 排他消费者,即这个队列只能由一个消费者消费.适用于任务不允许进行并发处理的情况下.比如系统对接
            //nowait 不返回执行结果,但是如果排他开启的话,则必须需要等待结果的,如果两个一起开就会报错
            //callback 回调函数
            $channel->basic_consume($queue, $consumerType, false, false, false, false, $callBackFunction);

            while (count($channel->callbacks)) {
                $channel->wait();
            }
            //关闭渠道
            $channel->close();
            //关闭连接
            $connection->close();
        } catch (\Exception $e) {
            //todo 报警
            throw new \Exception('rabbitMq exception：' . $e->getMessage(), 1001);
        }

    }

    /**
     * 获取队列信息
     * @param $queueName
     *
     * @return array|null
     */
    public static function getQueueData($queueName)
    {
        $conn = self::connectionInit();
        $channel = $conn->channel();

        try {
            $data = $channel->queue_declare($queueName,true);
            return [
                'code' => 1,
                'data' => $data,
                'info' => ''
            ];
        } catch (\Exception $e) {
            return [
                'code' => 1001,
                'data' => [],
                'info' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取队列消息总数
     * @param $queueName
     *
     * @return int
     */
    public static function getQueueMessageCount($queueName){
        $result = self::getQueueData($queueName);
        return $result['data'][1] ?? 0;
    }

    /**
     * 创建交换机，对联，并执行绑定
     *
     * @param string $exchange
     * @param string $type
     * @param string $queue
     * @param string $routingKey
     * @param bool  $passive
     * @param bool  $durable
     * @param bool  $autoDelete
     * @param bool  $internal
     * @param bool  $nowait
     * @param array $arguments
     * @param null  $ticket
     * @return bool
     */
    public static function createExchange($exchange,
                                          $type,
                                          $queue,
                                          $routingKey,
                                          $passive = false,
                                          $durable = false,
                                          $autoDelete = true,
                                          $internal = false,
                                          $nowait = false,
                                          $arguments = [],
                                          $ticket = null)
    {
        $conn = self::connectionInit();
        $channel = $conn->channel();
        $channel->exchange_declare(
            $exchange,
            $type,
            $passive,
            $durable,
            $autoDelete,
            $internal,
            $nowait,
            $arguments,
            $ticket);

        //队列参数--如需自定义，则需要修改代码
        $queueArguments = new AMQPTable(['x-queue-type' => 'classic']);
        $channel->queue_declare($queue, $passive, $durable, false, $autoDelete, $nowait, $queueArguments);
        //队列与exchange绑定
        $result = $channel->queue_bind($queue, $exchange, $routingKey);
        return $result;
    }


    /**
     * 公用mq
     * @param string $notifyUrl 接收数据的地址
     * @param array $sendData 发送的数据
     * @param int $delaySeconds 延迟时间，当$delaySeconds > 0，则调用的是延迟交换机
     *
     * @return array
     */
    public static function sendCommonMq($notifyUrl, $sendData, $delaySeconds = 0)
    {
        $exchange = \Yii::$app->params['public_direct_mq']['exchange_name'];
        $routeKey = \Yii::$app->params['public_direct_mq']['route_key'];
        if ($delaySeconds > 0) {
            $exchange = \Yii::$app->params['public_delay_mq']['exchange_name'];
            $routeKey = \Yii::$app->params['public_delay_mq']['route_key'];
        }
        $data = [
            'notify_url' => $notifyUrl,
            'send_data' => $sendData
        ];
        return self::send($exchange, $routeKey, @json_encode($data, 320), $delaySeconds);
    }
}
