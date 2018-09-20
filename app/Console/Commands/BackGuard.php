<?php
/**
 * mongo数据备份
 * 可以多服务器多库
 * orgdb和trgdb是原始库和目标库的关系，需要一一对应（，分割参数 ；分割服务器 |分割库名）
 * ip，端口，库名（|分割多库），用户名，密码；ip2，端口，库名（|分割多库），用户名，密码
 *
 * 例子：
 * php artisan Back:UpData --orgdb="host1,port,dbname1|dbname2,user,psd;host2,port,dbname1|dbname2,user,psd" --trgdb="host1,port,dbname1|dbname2,user,psd;host2,port,dbname1|dbname2,user,psd" --shard='-1' --reran=1
 * php artisan Back:UpData --orgdb='127.0.0.1,27017,bas,root,root' --trgdb='10.20.20.66,27017,bas,admin,root' --shard='-1' --reran=1
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class BackGuard extends Command
{

    //进程数
    public $course = 0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Back:Guard
    {--GuardName= : 进程名称, eg Back:UpData}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '守护进程';

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

        if (self::getProsize("notgid=log_") < 3) {

            //$cmd = 'nohup /usr/local/php/bin/php /data/www/php-script/artisan Back:UpData --orgdb=\'127.0.0.1,10010,bas,root,3i$LxH8%Ec\' --trgdb=\'10.20.20.66,27017,bas,admin,tyiSq6-ml\' --gids=\'_data_\' > /data/mongobak/logs/sync.log 2>&1 &';
            $cmd = 'nohup /usr/local/php/bin/php /data/www/php-script/artisan Back:UpData --orgdb=\'127.0.0.1,10010,bas,root,3i$LxH8%Ec\' --trgdb=\'10.20.20.66,27017,bas,admin,tyiSq6-ml\' --notgid=\'log_\' > /data/mongobak/logs/sync.log 2>&1 &';

            shell_exec($cmd);
        }
        if (self::getProsize("gids=log_") < 3) {

            //$cmd = 'nohup /usr/local/php/bin/php /data/www/php-script/artisan Back:UpData --orgdb=\'127.0.0.1,10010,bas,root,3i$LxH8%Ec\' --trgdb=\'10.20.20.66,27017,bas,admin,tyiSq6-ml\' --gids=\'_data_\' > /data/mongobak/logs/sync.log 2>&1 &';
            $cmd = 'nohup /usr/local/php/bin/php /data/www/php-script/artisan Back:UpData --orgdb=\'127.0.0.1,10010,bas,root,3i$LxH8%Ec\' --trgdb=\'10.20.20.66,27017,bas,admin,tyiSq6-ml\' --gids=\'log_\' > /data/mongobak/logs/sync_log.log 2>&1 &';

            shell_exec($cmd);
        }

    }

    /**
     * 获取进程文件个数
     * @param $url
     * @return int
     */
    private function getCourselogsize($url)
    {
        $nub = file_exists($url) ? count(scandir($url)) - 2 : 0;
        return $nub;
    }

    /**
     * 获取进程数
     * @return mixed
     */
    private function getDirsize($tp = "/data/*")
    {

        $nub = count(glob($tp));
        return $nub;
    }

    /**
     * 获取进程数
     * @return mixed
     */
    private function getProsize($tp = "Back:")
    {

        $cmd = "{$this->sudo} ps aux |grep '{$tp}'|wc -l";
        $nub = trim(shell_exec($cmd));
        var_dump($cmd, $nub);
        return $nub;
    }

    /**
     * 获取进程id
     * @return mixed
     */
    private function getProid($tp = "Back:")
    {

        $cmd = "ps aux |grep {$tp}|awk '{print $2}'";
        exec($cmd, $nub);
        return $nub;
    }

    /**
     * 获取进程
     * @return mixed
     */
    private function getProcesses($tp = "Back:")
    {

        $cmd = "sudo ps aux |grep {$tp}";
        var_dump($cmd);
        exec($cmd, $nub);
        return $nub;
    }

    /**
     *  获取输入参数
     * @return array
     */
    private function getInput()
    {

        //获取原始数据库信息
        $defdb = $this->orgdb;
        if ($this->option('orgdb')) {

            $this->orgdb = [];
            self::getDbinfo($this->orgdb, $this->option('orgdb'), $defdb);
        }

        //获取原始数据库信息
        $defdb = $this->trgdb;
        if ($this->option('trgdb')) {

            $this->trgdb = [];
            self::getDbinfo($this->trgdb, $this->option('trgdb'), $defdb);
        }

        $shard = 1;
        if ($this->option('shard')) {
            $shard = $this->option('shard');
        }

        $notgid = 0;
        if ($this->option('notgid')) {
            $notgid = $this->option('notgid');
        }

        $gid = 0;
        if ($this->option('gid')) {
            $gid = $this->option('gid');
        }

        $reran = 0;
        if ($this->option('reran')) {
            $reran = $this->option('reran');
        }

        $this->input = ['orgdb' => $this->orgdb, 'trgdb' => $this->trgdb, 'shard' => $shard, 'notgid' => $notgid, 'gid' => $gid, 'reran' => $reran];
        return $this->input;
    }

    /**
     * 获取db信息
     * @param $db
     * @param $dbinfo
     * @return array
     */
    public function getDbinfo(&$db, $dbinfo, $def)
    {
        $data = explode(';', $dbinfo);
        if (count($data)) {

            foreach ($data as $k => $v) {

                $v = explode(',', $v);
                if (count($v)) {

                    $dbname = explode('|', $v[2]);

                    $db[] = [
                        'database' => $dbname[0],
                        'host' => $v[0],
                        'port' => $v[1],
                        'username' => $v[3],
                        'password' => $v[4],
                        'dbs' => $dbname,
                    ];
                }
            }
        }
        $db = count($db) ? $db : $def;
        return $db;
    }


}
