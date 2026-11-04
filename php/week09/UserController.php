<?php


namespace AppUserApi\controllers;

use App\Utils\BaseFunction;
use App\Utils\EnterpriseWechatMessage;
use App\Utils\MyFunction;
use common\enums\LoginChannel;
use common\repositorys\user\UserAuthsRepository;
use common\repositorys\user\UserRepository;
use common\services\login\UserService;
use common\services\member\UserProfileService;
use common\services\sensorsdata\SensorsDataBaseEvent;
use common\services\sensorsdata\SensorsDataService;
use common\services\SubscribeService;
use common\services\user\EmailService;
use common\services\user\GuestService;
use common\services\user\RegisterService;
use Yii;
use common\AuthApiController;
use common\services\user\LoginService;

class UserController extends AuthApiController
{
    public function actionSend()
    {
        try {
            $params = $this->requestParams;
            $result = EmailService::instance()->sendEmail($params);
            if (1 == $result['code']) {
                $this->endSuccess($result);
            } else {
                $this->endFail($result['code'], $result['info']);
            }
            $this->endSuccess();
        } catch (\Exception $e) {
            MyFunction::instance()->mLog("errorsql.log", "", $e->getMessage());
            $msg = [
                "#### 邮件发送出现错误",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'new_user');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }

    }

    public function actionSubscribe()
    {
        try {
            // 获取参数
            $params = $this->requestParams;
            // 新的订阅方法，只订阅，没有注册
            $result = SubscribeService::instance()->subscribe($params);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'SubscribeException');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }

    public function actionQuickLogin()
    {
        try {
            // 获取参数
            $params = $this->requestParams;

            $result = LoginService::instance()->quickLogin($params);

            $this->endSuccess($result['data'], $result['info']);

        } catch (\Exception $e) {

            $msg = [
                "#### 快捷登录报错",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'fire_base_login');
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionQuickLogin');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }

    /**
     * 登录后设密（不要求旧密码）。
     *
     * @return void
     */
    public function actionSetPassword()
    {
        try {
            $params = $this->requestParams;
            $result = \common\services\login\SocialLinkService::instance()->setPassword($params);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            $this->endFail(20000, 'Operation failed, please try again');
        }
    }

    public function actionResetPassword()
    {
        try {
            // 获取参数
            $params = $this->requestParams;

            $result = RegisterService::instance()->resetPassword($params);

            if (1 == $result['code']) {

                $this->endSuccess($result['data'], $result['info']);

            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }

        } catch (\Exception $e) {

            $msg = [
                "#### 修改用户密码出现错误",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'fire_base_login');
            $this->endFail(20000, 'Operation failed, please try again');

        }
    }

    public function actionBindEmail()
    {
        try {
            // 获取参数
            $params = $this->requestParams;

            $result = RegisterService::instance()->bindEmail($params);

            $this->endSuccess($result['data'], $result['info']);

        } catch (\Exception $e) {

            $msg = [
                "#### 获取用户信息出现异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'fire_base_login');
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionBindEmail');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }

    public function actionCheckEmail()
    {
        try {
            // 获取参数
            $params = $this->requestParams;
            $result = RegisterService::instance()->checkEmail($params);

            $this->endSuccess($result['data'], $result['info']);

        } catch (\Exception $e) {

            $msg = [
                "#### 获取用户信息出现异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'fire_base_login');
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionCheckEmail');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }

    public function actionEmailCheckPassword()
    {
        try {
            // 获取参数
            $params = $this->requestParams;
            $result = RegisterService::instance()->emailCheckPassword($params);

            $this->endSuccess($result['data'], $result['info']);

        } catch (\Exception $e) {

            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionCheckEmail');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }

    public function actionUserInfo()
    {
        try {
            // 获取参数
            $params = $this->requestParams;

            $result = LoginService::instance()->getUserInfo($params, $this->request->getUserAgent());

            if (1 == $result['code']) {

                $this->endSuccess($result['data'], $result['info']);

            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }

        } catch (\Exception $e) {

            $msg = [
                "#### 获取用户信息出现异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'fire_base_login');
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionUserInfo');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }

    /**
     * 用户完善资料接口。
     *
     * 聚合本地 user 表资料 + 用增会员详情(profile_complete) + 用增权益包(BF_PROFILE_200 grant_point)，
     * 返回选项(含多语言)与用户已选值，供 account_setting 页面渲染。
     *
     * @return void
     */
    public function actionUserProfile()
    {
        try {
            $params = $this->requestParams;
            $result = UserProfileService::instance()->getProfile($params);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionUserProfile');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 根据用户ID+邮箱以及地址获取登录用户ID--如果是登录用户ID，则无需查找
     * http://newuser.internal.bm.com/user/get-login-user-id-by-user-info
     */
    public function actionGetLoginUserIdByUserInfo()
    {
        try {
            // 获取参数
            $params      = $this->requestParams;
            $userId      = (int)($params['user_id'] ?? 0);
            $email       = $params['email'] ?? '';
            $addressInfo = $params['address_info'] ?? [];
            $realUserId  = \common\services\user\UserService::instance()->getLoginUserIdByUserInfo($userId, $email, $addressInfo);

            return $this->endSuccess([
                'user_id' => $realUserId
            ]);

        } catch (\Exception $e) {

            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionGetLoginUserIdByUserInfo');
            return $this->endFail(20000, '检测异常', '', '', $e->getMessage() . $e->getTraceAsString());

        }
    }


    /**
     * 创建商城游客账号，成功仅返回 user_id。
     *
     * @return void
     */
    public function actionAddGuest()
    {
        $params = $this->requestParams;
        $email  = $params['email'] ?? '';

        try {
            // 与代客下单 CreateTouristByValetOrderNode 同一套建游客逻辑（GuestService 内对齐）
            $result = GuestService::instance()->createKolGuest($params);
            if (1 != ($result['code'] ?? 0)) {
                $this->endFail($result['code'], $result['info']);
            }

            g_log_info('kol_guest.log', 'KOL 游客创建成功', [
                'email'   => $email,
                'user_id' => $result['data']['user_id'] ?? 0,
            ]);
            $this->endSuccess($result['data'], $result['info']);
        } catch (\Throwable $e) {
            g_log_error('kol_guest.log', 'KOL 游客创建接口异常', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->endFail(20000, 'Operation failed, please try again');
        }
    }

    /**
     * 咨询开始通知：加锁 30 秒后入专用 MQ，消费时拉增长并推送快牛。
     * 后续其他页面场景可按同样模式扩展，不复用本动作名。
     *
     * @return mixed
     */
    public function actionStartConsultationNotify()
    {
        try {
            $params = $this->requestParams;
            $token  = $params['token'] ?? '';
            $userId = (int)($params['user_id'] ?? 0);
            if ($userId <= 0 && $token !== '') {
                $userId = (int)BaseFunction::instance()->getUserId($token);
            }
            $result            = (new \common\services\member\CustomerServiceSyncService())->enqueue(
                $userId,
                $params['site'] ?? 'us',
                $params['language'] ?? 'en',
                $params['country_code'] ?? '',
                $params['pf'] ?? 'pc',
                $params['request_id'] ?? ''
            );
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            }
            return $this->endFail($result['code'], $result['info']);
        } catch (\Exception $e) {
            g_log_error('startConsultationNotify.log', '咨询通知入口异常', [
                'error' => $e->getMessage(), 'params' => $params
            ]);
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage());
        }
    }

}
