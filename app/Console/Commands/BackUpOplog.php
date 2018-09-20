<?php
/**
 * mongo数据oplog备份
 * 例子：
 * php /data/www/php-script/artisan Back:UpOplog --orgdb='{"database":"bas","host":"127.0.0.1","port":"27017","username":"root","password":"root","dbs":["bas"]}' --trgdb='{"database":"bas","host":"10.20.20.61","port":"27017","username":"admin","password":"admin","dbs":["bas"]}'
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class BackUpOplog extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Back:UpOplog
        {--orgdb= : 原始数据库信息, eg {"database":"bas","host":"127.0.0.1","port":"27017","username":"root","password":"root","dbs":["bas"]}
        {--trgdb= : 目标数据库信息, eg {"database":"bas","host":"10.20.20.61","port":"27017","username":"admin","password":"admin","dbs":["bas"]}}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '跑mongo的oplog';

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

        //获取输入参数
        self::getInput();
        $courselog = "{$this->mongobak}courselog/{$this->orgdb['host']}{$this->orgdb['port']}{$this->orgdb['database']}.log";

        if (!file_exists($courselog)) {
            toLog(time(), "{$this->orgdb['host']}{$this->orgdb['port']}{$this->orgdb['database']}.log", "{$this->mongobak}courselog", 0);
            //var_dump($this->input);die;
            echo "oplog任务开始时间：" . date("Y-m-d H:i:s", time()) . PHP_EOL;
            self::proOplog($this->orgdb, $this->trgdb);
            echo "oplog任务结束时间：" . date("Y-m-d H:i:s", time()) . PHP_EOL;

            shell_exec("rm -rf {$courselog}");
        }
        die;
    }

    /**
     * 跑oplog任务
     */
    private function proOplog($orghost, $trghost)
    {

        $tms = trim(file_get_contents($this->mongobak . "oplogtime/oplogst{$orghost['host']}{$orghost['port']}.log"));
        toLog(time(),"oplogst{$orghost['host']}{$orghost['port']}.log","{$this->mongobak}oplogtime",0);
        $tms = empty($tms) ? time() : $tms;
        $whe = '{"op" : {$in:["i","u"]},"ts":{$gte:Timestamp(' . $tms . ', 1)},"ns":/'.$orghost['gid'].'/}';
        //导出oplog
        $expcmd = "{$this->sudo}{$this->mongodir}mongodump -h {$orghost['host']} --port {$orghost['port']} -u {$orghost['username']} -p '{$orghost['password']}' --authenticationDatabase admin -d local -c oplog.rs -q '{$whe}' -o {$this->mongobak}oplog{$orghost['host']}{$orghost['port']}";
        exec($expcmd, $res);

        //导入oplog
        $impcmd = "{$this->sudo}{$this->mongodir}mongorestore -h {$trghost['host']} --port {$trghost['port']} -u {$trghost['username']} -p '{$trghost['password']}' --authenticationDatabase admin -d local -c oplog.rs {$this->mongobak}oplog{$orghost['host']}{$orghost['port']}/local/oplog.rs.bson";
        exec($impcmd, $res);

    }

    /**
     *  获取输入参数
     * @return array
     */
    private function getInput()
    {

        //获取原始数据库信息
        if ($this->option('orgdb')) {

            $this->orgdb = json_decode($this->option('orgdb'), true);
        }

        //获取原始数据库信息
        if ($this->option('trgdb')) {

            $this->trgdb = json_decode($this->option('trgdb'), true);
        }

        $this->input = ['orgdb' => $this->orgdb, 'trgdb' => $this->trgdb];
        return $this->input;
    }


}
