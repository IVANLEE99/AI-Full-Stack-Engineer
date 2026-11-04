<?php

namespace common\redis\user;

use common\repositorys\user\UserRepository;

class UserDetailRedis extends \common\BaseRedis
{

    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        try {
            $connect = \Yii::$app->redisUserDetail;
            $connect->select(7);
            return $connect;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getUserDetailKey($userId)
    {
        return self::$key['user']['detail'] . $userId;
    }

    /**
     * 缓存用户信息
     */
    public function setUserDetail($userId, $userDetail)
    {
        $key = $this->getUserDetailKey($userId);

        $redis = $this->getConnection();

        return $redis->setex($key, 86400 * 3, @json_encode($userDetail, 320));
    }

    /**
     * 删除用户缓存
     *
     * @param $userId
     *
     * @return mixed
     */
    public function delUserDetail($userId)
    {
        $key = $this->getUserDetailKey($userId);

        $redis = $this->getConnection();

        return $redis->del($key);
    }

    /**
     * 用户信息 带被动缓存的
     */
    public function getUserDetail($userId)
    {
        $key        = $this->getUserDetailKey($userId);
        $redis      = $this->getConnection();
        $userDetail = $redis->get($key);

        //被动缓存
        if (empty($userDetail)) {
            $userDetail = UserRepository::instance()->getUserById($userId);
            if (empty($userDetail)) {
                return [];
            }
            $userDetail['user_id'] = $userDetail['id'];
            $this->setUserDetail($userId, $userDetail);
            return $userDetail;
        }
        return @json_decode($userDetail, true);
    }


}