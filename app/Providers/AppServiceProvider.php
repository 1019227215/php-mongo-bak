<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        app('queue')->before(function (JobProcessing $event) {
            //记录日志
            app('db')->collection('log_job')->where('job_id',$event->job->getJobId())->update([
                'queue' => $event->job->getQueue(),
                'status' => 'begin',
                'tsb' => intval(time()),
                'body' => $event->job->getRawBody()
            ]);
        });

        app('queue')->after(function (JobProcessed $event) {
            //记录日志
            app('db')->collection('log_job')->where('job_id',$event->job->getJobId())->update([
                'status' => 'success',
                'tse' => intval(time())
            ]);
        });

        app('queue')->failing(function (JobFailed $event) {
            //记录日志
            app('db')->collection('log_job')->where('job_id',$event->job->getJobId())->update([
                'status' => 'failed',
                'error' => $event->exception->getMessage(),
                'tse' => intval(time())
            ]);

            // 发送通知邮件
            // ...
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
