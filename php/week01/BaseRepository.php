<?php

namespace common;

use Yii;
use yii\db\Connection;

/**
 * Class BaseRepository
 * 持久化基类，单例调用
 * @package common
 */
abstract class BaseRepository
{
    // 删除标志 0正常 1删除
    const DEL_FLAG_0 = 0;
    const DEL_FLAG_1 = 1;

    private static $useSlave = null;

    /**
     * 从容器中获取单实例
     *
     * @param bool $useSlave 是否使用从库
     *
     * @return static
     */
    public static function instance($useSlave = false)
    {
        self::$useSlave = $useSlave;
        $container      = Yii::$container;
        if (!$container->hasSingleton(static::class)) {
            $container->setSingleton(static::class);
        }
        return $container->get(static::class);
    }

    /**
     * 获取库连接
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public function getDb()
    {
        return self::$useSlave ? Yii::$app->dbFecshopSlave : null;
    }

    /**
     * 获取从库连接
    */
    protected function getSlaveDb()
    {
        return Yii::$app->dbFecshopSlave;
    }

    /**
     * 获取数据库连接
     *
     * @return Connection
     */
    abstract public function getConnection();
}
