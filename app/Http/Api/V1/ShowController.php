<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/8/27
 * Time: 14:16
 */

namespace App\Http\Api\V1;


use Dingo\Api\Routing\Helpers;

class ShowController
{
    use Helpers;

    /**
     *  测试
     */
    public function index(){
//        echo 'ok api';
        $appkey = urlencode('439d8c975f26e5005dcdbf41b0d84161');
        $appsecret = urlencode('08aee6276db142f4b8ac98fb8ee0ed1b');
//        $appsecret = urlencode('');

        // curl
        $url = 'http://laravelapi.local/api/token?app_key='.$appkey . '&$app_secret=' . $appsecret;

//        $ch = curl_init();
//        curl_setopt($ch,CURLOPT_URL,$url);
//        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
//        curl_setopt($ch,CURLOPT_HEADER,0);
//        $output = curl_exec($ch);
//
//        curl_close($ch);

//        if($output == false){
//            return Response()->json(['status_code'=>500,'msg'=>'请求失败']);
//        }else{
//            var_dump($output);die;
//            return Response()->json(['status_code'=>200,'msg'=>'success','data'=>['access_token'=>'']]);
//        }

//        $dispatcher = app('Dingo\Api\Dispatcher');
//
//        $dispatcher->get();
        $res = $this->api->get('token?app_key=' . $appkey . '&app_secret=' . $appsecret);

        var_dump($res);die;
    }
}