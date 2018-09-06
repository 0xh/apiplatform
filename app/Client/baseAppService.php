<?php
/**
 * Created by Bevan.
 * User: Bevan@zhoubinwei@aliyun.com
 * Date: 2018/9/6
 * Time: 10:39
 */

namespace App\Client;


use App\Services\BaseService;

class baseAppService extends BaseService
{
    public $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new httpClient();
    }
}