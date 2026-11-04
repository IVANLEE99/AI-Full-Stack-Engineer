<?php

namespace common\services\user;

use App\Email\Email;
use App\Utils\BaseFunction;
use App\Utils\MyFunction;
use App\Utils\NodeExecutionEngine;
use common\BaseService;
use common\repositorys\user\UserAuthsRepository;
use common\repositorys\user\UserRepository;
use common\services\login\LoginAccountHelper;
use common\services\user\contexts\RegisterContext;
use common\services\user\contexts\ResetPasswordContext;
use common\services\user\contexts\SubscribeContext;
use common\services\user\nodes\common\SubscribeRabbitMQNode;
use common\services\user\nodes\common\UserSendRabbitMQNode;
use common\services\user\nodes\email\EmailSendNode;
use common\services\user\nodes\ResetPasswordCheckNode;
use common\services\user\nodes\ResetPasswordNode;
use common\services\user\nodes\UserAddAddressEmailNode;
use common\services\user\nodes\UserEmailRegisterNode;
use common\services\user\nodes\UserLoginNode;
use common\services\user\nodes\UserLoginReturnUserDetailNode;
use common\services\user\nodes\UserSubscribeCheckNode;
use common\services\user\nodes\UserSubscribeNode;
use common\services\user\nodes\UserWebCheckNode;

class RegisterService extends BaseService
{
    public function thirdSubscribe($context, $params)
    {
        $params['email']          = $context->email;
        $params['token']          = $context->response['token'] ?? '';
        $params['module_from']    = 'Register';
        $params['from_register']  = 1;
        $this->emailSubscribe($params);
    }

    /**
     * 邮箱订阅
     */
    public function emailSubscribe($params)
    {
        try {

            // 初始化上下文
            $context = new SubscribeContext();
            BaseService::instance()->contextInit($context, $params);
            $context->email       = $params['email'] ?? '';
            $context->emailCode[] = Email::BUSINESS_CODE_SUBSCRIBE_SUCCESS;

            $userId = BaseFunction::instance()->getUserId($params['token'] ?? '');

            $context->userId = $userId;

            // 组织业务节点
            $nodeChain                             = [
                // 不管任何场景，都要跟更新这个节点
                UserAddAddressEmailNode::instance(),
                // 检查订阅
                UserSubscribeCheckNode::instance(),
                // 订阅邮箱
                UserSubscribeNode::instance(),
                // 组织数据
                EmailSendNode::instance(),
                // 发送MQ信息
                SubscribeRabbitMQNode::instance(),
            ];
            $result                                = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            $context->response['subscribe_status'] = $context->subscribeStatus;
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $this->returnError($result['code'], $result['info']);
            }

        } catch (\Exception $e) {
            return $this->returnFormat($e->getCode(), null, $e->getTraceAsString());
        }
    }

    /**
     * 邮箱绑定
     *
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function bindEmail($params)
    {
        $email  = $params['email'] ?? '';
        $token  = $params['token'] ?? '';
        $userId = BaseFunction::instance()->getUserId($token);
        if (empty($email) || empty($userId)) {
            // 邮箱为空
            return $this->returnError(10001, 'Email Empty');
        }

        // 邮箱已绑定
        $bool = UserRepository::instance()->uniqueEmail($email);
        if ($bool === true) {
            return $this->returnError(10002, 'Email is Register');
        }

        // 解析的用户ID错误
        $userInfo = UserRepository::instance()->getUserById($userId);
        if (empty($userInfo) || $userInfo['type'] == 3) {
            return $this->returnError(10002, 'User Error');
        }

        // 已存在邮箱
        if (!empty($userInfo['email'])) {
            return $this->returnError(10002, 'have email');
        }

        // 绑定邮箱
        $data = [
            'email'      => $email,
            'updated_at' => time(),
        ];
        UserRepository::instance()->updateUserById($userId, $data);
        UserAuthsRepository::instance()->insert([
            'user_id'       => $userId,
            'identity_type' => 1,
            'identifier'    => $email,
            'del_flag'      => 0,
            'created_at'    => time(),
            'updated_at'    => time(),
        ]);

        UserService::instance()->setUserDetailRedis($userId);

        // 成功绑定
        return $this->returnSuccess();

    }

    /**
     * 邮箱分流检查：是否已注册、是否有密码、是否已验邮、是否仅第三方。
     *
     * @param array $params 请求参数。email string 必填
     *
     * @return array 标准响应，data 含 is_register / has_password / exists / email_verified / social_only / register_type / social_providers
     */
    public function checkEmail($params)
    {
        $email      = $params['email'] ?? '';
        $user       = UserRepository::instance()->getUserByEmail($email);
        $isRegister = false;
        if (in_array($user['type'] ?? 0, [1, 2])) {
            $isRegister = true;
        }
        $isAutoRegister = 0;
        if (!empty($user['password'])) {
            $isAutoRegister = 1;
        }
        $auths         = !empty($user['id'])
            ? UserAuthsRepository::instance()->getListByConditions([], [['=', 'user_id', $user['id']]])
            : [];
        $socialOnly = $isRegister && empty($user['password']);
        $flags      = LoginAccountHelper::flagsFromUser($user);
        return $this->returnSuccess([
            'is_register'      => $isRegister,
            'has_password'     => $isAutoRegister,
            'exists'           => !empty($user),
            'email_verified'   => $flags['email_verified'],
            'social_only'      => $socialOnly,
            'register_type'    => LoginAccountHelper::registerType($user, $auths),
            'social_providers' => LoginAccountHelper::socialProvidersFromAuths($auths),
        ]);
    }

    public function emailCheckPassword($params)
    {
        $emailList = $params['email_list'] ?? [];
        // 加个限制，超过20个不能检查
        if (empty($emailList) || count($emailList) > 20) {
            return $this->returnSuccess();
        }
        $returnData = [];
        foreach ($emailList as $email) {
            $user               = UserRepository::instance()->getUserByEmail($email);
            $returnData[$email] = empty($user) || empty($user['password']) ? 0 : 1;
        }
        return $this->returnSuccess($returnData);
    }

    public function resetPassword($params)
    {
        try {
            // 初始化上下文
            $context = new ResetPasswordContext();
            BaseService::instance()->contextInit($context, $params);
            $context->password    = $params['password'] ?? '';
            $context->type        = $params['type'] ?? 1;
            $context->resetToken  = $params['reset_token'] ?? 1;
            $context->code        = $params['code'] ?? 1;
            $context->email       = $params['email'] ?? '';
            $context->emailCode[] = Email::BUSINESS_CODE_FORGET_PASSWORD_SUCCESS;

            // 组织业务节点
            $nodeChain = [
                // 前置验证
                ResetPasswordCheckNode::instance(),
                // 重置密码
                ResetPasswordNode::instance(),
                // 邮件数据
                EmailSendNode::instance(),
                // 组织返回的数据
                UserLoginReturnUserDetailNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $this->returnError($result['code'], $result['info']);
            }

        } catch (\Exception $e) {
            return $this->returnFormat($e->getCode(), null, $e->getTraceAsString());
        }

    }

}
