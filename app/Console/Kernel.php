<?php

namespace App\Console;

use App\Console\Commands\AdvData;
use App\Console\Commands\AdvDataSum;
use App\Console\Commands\AdvEmailDispense;
use App\Console\Commands\AdvRemained;
use App\Console\Commands\AdvRetainResult;
use App\Console\Commands\BackCes;
use App\Console\Commands\BackExIm;
use App\Console\Commands\BackExImLog;
use App\Console\Commands\BackGuard;
use App\Console\Commands\BackUpData;
use App\Console\Commands\BackUpDb;
use App\Console\Commands\BackUpOplog;
use App\Console\Commands\GamePay;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [

        /***
         * af广告任务开始
         */
        //备份数据
        BackUpData::class,
        //跑库表
        BackUpDb::class,
        //跑oplog
        BackUpOplog::class,
        //ExIm导出导入
        BackExIm::class,
        //检查BackUpData进程是否存在，crontab每分钟检查一次
        BackGuard::class,
        //测试
        BackCes::class,
        //跑日志表
        BackExImLog::class,
        /***
         * af广告任务结束
         */




        
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }
}
