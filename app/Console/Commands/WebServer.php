<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WebServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webserver {cmd}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'web server handle';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $process = new \Swoole\Process(function (\Swoole\Process $childProcess) {
            $childProcess->exec('/usr/local/openresty/nginx/sbin/nginx', ['-c', '/data/wwwroot/bevan.top/storage/app/server/nginx/nginx.conf','-s','reload']);
        });

        $pid = $process->start();

        \swoole_process::signal(SIGCHLD, function($sig)  {
            //必须为false，非阻塞模式
            while($ret =  \swoole_process::wait(false)) {
//                echo "PID={$ret['pid']}\n";
                $this->info('is restart ok ！');
            }
        });
//        echo "from exec: ". $process->read(). "\n";
    }
}
