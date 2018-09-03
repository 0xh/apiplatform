<?php

//use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
//$dispatcher = app('Dingo\Api\Dispatcher');

app('api.exception')->register(function (Exception $exception) {

//    dd(get_class($exception));
//    if(config('app.debug')){
//        $request=Request::capture();
//        //交给laravelz自带的错误异常接管类处理
//        return app('App\Exceptions\Handler')->render($request,$exception);
//    }else{
//
//        if( get_class($exception)=='Illuminate\Validation\ValidationException'){
//            return Response()->json(['status_code'=>422,'message'=>$exception->validator->errors(),'data'=>''],422);
//        }
//
//    }
//    return Response()->json(['status_code'=>$exception->getStatusCode(),'message'=>$exception->validator->errors(),'data'=>''],$exception->getStatusCode());
//    var_dump(get_class($exception));
    if ($exception instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException) {
        return Response()->json(['status_code' => $exception->getStatusCode(), 'message' => $exception->getMessage(), 'respData' => ''], $exception->getStatusCode());
    } else if (get_class($exception) == 'Illuminate\Validation\ValidationException') {
        return Response()->json(['status_code' => 422, 'message' => $exception->validator->errors(), 'respData' => ''], 422);
    } else {
        $status_code = $exception->getCode() == 0 ? 400 : $exception->getCode();
        $err_message = $exception->getMessage() == '' ? '路由不存在' : $exception->getMessage();
        return Response()->json(['status_code' => $status_code, 'message' => $err_message, 'respData' => ''], $status_code);
    }
});

$api = app('Dingo\Api\Routing\Router');

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});
//
// 项目 应用1
$api->version('v1', [], function ($api) {
    $api->get('showtest', \Show\Api\V1\IndexController::class . '@index');
});
//....


$api->version('v1', ['middleware' => 'api.throttle', 'namespace' => '\App\Http\Api\V1'], function ($api) {
    $api->group(['prefix' => 'cli'], function ($api) {
        // 前台無需授权api
        $api->group([], function ($api) {

            $api->group(['limit' => 300, 'expires' => 5], function ($api) {
                $api->post('login', AuthController::class . '@login');

                $api->post('register', AuthController::class . '@register');
            });

            $api->group(['limit' => 200, 'expires' => 10], function ($api) {
                // 生成 access_token
                $api->get('token', ApiAuthController::class . '@getAccessToken')->middleware('checkAppKeySecret');
            });

            $api->get('test', ShowController::class . '@index');

            $api->get('testEvent', ShowController::class . '@testEvent');

            // 产品列表
            $api->get('productList/{type?}', PlatformProductController::class . '@index');
//            $api->group(['namespace'=>''], function($api){
//
//            })

            $api->get('categoriesList', PlatformProductController::class . '@allList');
        });

        // 前台需授权的 api
        $api->group(['middleware' => ['self.jwt.auth']], function ($api) {
            $api->group(['limit' => 10, 'expires' => 1], function ($api) {
                $api->get('showauth', ApiAuthController::class . '@test');
                $api->get('getUserInfo', ApiAuthController::class . '@uInfo');
            });

            // 开通 产品 服务 (单个)
            $api->get('openServiceSelf/{product_id}', ProductServiceController::class . '@addService');
            // 编辑 产品 服务 (多开通， 多 关闭)
            $api->post('editServiceSelf', ProductServiceController::class . '@editService');
            // 关闭 产品 服务 (单个)
            $api->get('delService/{product_id}', ProductServiceController::class . '@delService');

            $api->group(['limit' => 300, 'expires' => 5], function ($api) {
                // 刷新token
                $api->get('refreshToken', ApiAuthController::class . '@refreshAccessToken');
            });
        });

        // 后台的api （都需登录）
        $api->group(['middleware' => ['admin.jwt.changeAuth', 'self.jwt.refresh:admin', 'admin.jwt.auth'], 'namespace' => 'Admin', 'prefix' => 'admin'], function ($api) {
            $api->group(['middleware' => ['admin.jwt.permission:admins|opeartor']], function ($api) {
                $api->get('index', PlatformProductController::class . '@index');
                $api->get('test', PlatformProductController::class . '@test');

                $api->group(['middleware' => ['admin.jwt.permission:admins|opeartor']], function ($api) {
                    // 删除某个 产品服务
                    $api->delete('platformProduct/{product_id}', PlatformProductController::class . '@delete')->where(['product_id' => '[0-9]+']);
                    // 更新某个 产品服务
                    $api->put('platformProduct/{product_id}', PlatformProductController::class . '@edit')->where(['product_id' => '[0-9]+']);

                    // 添加 产品服务
                    $api->post('platformProduct', PlatformProductController::class . '@add');
                });
            });

            // 内部的 应用 可以调用的 api
            $api->group(['middleware' => ['admin.jwt.permission:admins|internal']], function ($api) {
                $api->post('openUserService/{user_id}', PlatformProductController::class . '@openService')->where(['user_id' => '[0-9]+']);
                $api->post('disableUserService/{user_id}', PlatformProductController::class . '@disableUserService')->where(['user_id' => '[0-9]+']);
            });

            $api->post('login', LoginController::class . '@login');
        });
    });
});
