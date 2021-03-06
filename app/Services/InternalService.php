<?php
/**
 * Created by Bevan.
 * User: Bevan@zhoubinwei@aliyun.com
 * Date: 2018/8/31
 * Time: 10:38
 */

namespace App\Services;


use App\Events\AsyncLogEvent;
use App\Exceptions\PlatformProductException;
use App\Models\Admin;
use App\Models\AppUser;
use App\Models\UuidUser;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tymon\JWTAuth\Facades\JWTAuth;

class InternalService extends BaseLoginService
{
    /**
     * @var 校验异常模型对象
     */
    protected $errorModel;

    /**
     *  admin创建内部应用
     */
    public function createAdminUser($data)
    {
        // check unique
//        $this->checkUnique($data, '\App\Models\Admin');
        $password = md5($this->customCreateUUID());
        $uuid = $this->factoryAppAdminUUID($this->randomStr());

        DB::beginTransaction();
        try {
            $admin = Admin::create([
                'name' => md5($this->customCreateUUID()),
                'email' => $data['email'] ?? null,
                'password' => bcrypt($password),
                'uuid' => $uuid,
                'telephone' => $data['telephone'] ?? null,
            ]);

            // app_user
            $app_key = $this->factoryUserAppkey();
            $app_secret = $this->factoryUserAppkey();
            AppUser::updateOrCreate([
                'user_id' => $admin->id,
                'model' => get_class($admin),
            ], [
                'app_key' => $app_key,
                'app_secret' => $app_secret,
                'model' => get_class($admin),
                'user_id' => $admin->id
            ]);

            // 分配角色
            $admin->assignRole('internal');

            DB::commit();

            return ['res' => true, 'data' => ['app_key' => $app_key, 'app_secret' => $app_secret, 'uuid' => $uuid]];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new PlatformProductException(5020, '创建失败' . $e->getMessage());
        }
//        return ['res' => false, 'data' => []];
    }

    /**
     * 用户 模型 唯一校验
     * @param $data
     * @param string $model
     */
    protected function checkUnique($data, $model = '\App\User')
    {
        if (!$this->checkIsPhone($data['telephone'])) throw new PlatformProductException(4023, '手机号码不正确');

        if ($user = $model::where('name', $data['name'])->first(['id', 'name'])) {
//            return Response()->json(['status_code' => 200,'msg'=>'用户名已存在', 'data'=> ]);
            $this->errorModel = $user;
//            $uuidUser = $user->getOpenid($user->id, $this->user->id, $this->user->uuid, get_class($this->user));
//            if ($uuidUser) return $this->responseClient(203, '用户名已经存在', ['openid' => $uuidUser->openid]);
            throw new PlatformProductException(4023, '用户名已存在');
        }

        if ($user = $model::where('telephone', $data['telephone'])->first(['id', 'telephone'])) {
            $this->errorModel = $user;
            throw new PlatformProductException(4023, '该号码已注册');
        }

        if (isset($data['email']) && $data['email']) {
            if ($user = $model::where('email', $data['email'])->first(['id', 'email'])) {
                $this->errorModel = $user;
                throw new PlatformProductException(4023, '该邮箱已注册');
            }
        }
    }

    /**
     * 开通用户
     * @param $data
     */
    public function openUser($data)
    {
        try {
            $this->checkUnique($data, '\App\User');
        } catch (PlatformProductException $e) {
            if ($e->getStatusCode() == 4023) {
                $uuidUser = $this->errorModel->getOpenid($this->errorModel->id, $this->user->id, $this->user->uuid, get_class($this->user));
                if ($uuidUser) $openid = $uuidUser->openid;
                else $openid = $this->createNewAppUserOpenid();
                return ['res' => false, 'errormsg' => $e->getMessage(), 'data' => ['openid' => $openid]];
            } else {
                throw $e;
            }
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => ($data['email'] ?? null),
                'password' => bcrypt($data['password']),
                'telephone' => $data['telephone'],
                'type' => (int)$data['type'] ?? 0
            ]);

            // app_user
            $app_key = md5($this->customCreateUUID());
            $app_secret = md5($this->randomStr(11));
            AppUser::updateOrCreate([
                'user_id' => $user->id,
                'model' => get_class($user),
            ], [
                'app_key' => $app_key,
                'app_secret' => $app_secret,
                'model' => get_class($user),
                'user_id' => $user->id
            ]);

            $creater = JWTAuth::parseToken()->user();

            // 生成 用户 对应的 uuid
            $creater_uuid = $creater->uuid;
            $uuid = $this->customCreateUUID();
            $openid = $creater_uuid . $uuid;
            // 生成 对应 应用的openid
            UuidUser::updateOrCreate([
                'user_id' => $user->id,
                'model_id' => $creater->id,
                'model_uuid' => $creater_uuid,
                'model' => get_class($creater)
            ], [
                'user_id' => $user->id,
                'model_id' => $creater->id,
                'model_uuid' => $creater_uuid,
                'openid' => $openid,
                'model' => get_class($creater)
            ]);
            // 记录 哪个 应用 请求 创建的
            Event::fire(new AsyncLogEvent("\r\n\r\n" . $creater->name . '(model_id:' . $creater->id . ')创建了用户: ' . $user->name . '(user_id:' . $user->id . ')', 'info'));
            DB::commit();
            return ['res' => true, 'data' => ['app_key' => $app_key, 'app_secret' => $app_secret, 'openid' => $openid]];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new PlatformProductException(5020, '创建失败' . $e->getMessage());
        }
    }

    /**
     * 授权 生成 token
     * @param $app_key
     * @param $app_secret
     * @return mixed
     */
    public function factoryAccessToken($app_key, $app_secret)
    {
        if (!$app_key || !$app_secret) throw new PlatformProductException(4025, 'appkey或appsecret未获取');

        $admin = AppUser::where([
            'app_key' => $app_key,
            'app_secret' => $app_secret,
            'model' => 'App\Models\Admin'
        ])->first()->admin;

        $token = JWTAuth::claims(['model' => 'admin'])->fromUser($admin);
        // 获取过期时间
//        $express_in = config('jwt.ttl') * 60; // second

        if (!$token) throw new PlatformProductException(5020, '令牌生成失败');

        return $token;
    }

    /**
     * 检验类 异常 用户处理 生成openid
     * @return string
     * @throws \Exception
     */
    protected function createNewAppUserOpenid()
    {
        // 生成openid
        $uuid = $this->customCreateUUID();
        $openid = $this->user->uuid . $uuid;
        try {
            UuidUser::updateOrCreate([
                'user_id' => $this->errorModel->id,
                'model_id' => $this->user->id,
                'model_uuid' => $this->user->uuid,
                'model' => get_class($this->user)
            ], [
                'user_id' => $this->errorModel->id,
                'model_id' => $this->user->id,
                'model_uuid' => $this->user->uuid,
                'openid' => $openid,
                'model' => get_class($this->user)
            ]);

        } catch (\Exception $e) {
            Event::fire(new AsyncLogEvent("\r\n\r\n" . $this->user->name . '(model_id:' . $this->user->id . ',model: ' . get_class($this->user) . ')创建用户失败: ' . $this->errorModel->name . '(user_id:' . $this->errorModel->id . ')', 'error'));
            throw $e;
        }
        return $openid;
    }
}