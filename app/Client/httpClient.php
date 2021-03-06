<?php
/**
 * Created by Bevan.
 * User: Bevan@zhoubinwei@aliyun.com
 * Date: 2018/9/6
 * Time: 10:33
 */

namespace App\Client;


class httpClient
{
    public $request;

    public function __construct($config = [])
    {
        // 获取配置的驱动
        $driver = isset($config['driver']) ? $this->mapDriver($config['driver']) : config('app.http_driver');
        unset($config['driver']);
        $this->request = new $driver($config);
    }

    /**
     *  兼容 方法， 不建议使用
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        return $this->request->$name($arguments);
    }

    protected function mapDriver($d)
    {
        return [
            'curl' => 'App\Client\Driver\CurlHttp',
            'guzzle' => 'App\Client\Driver\BeVanGuzzleHttp'
        ][$d];
    }
}