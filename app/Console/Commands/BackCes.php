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

class BackCes extends Command
{

    //进程数
    public $course = 0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Back:Ces
        {--orgdb= : 原始数据库信息, eg host1,dbname1|dbname2,user,psd;host2,dbname1|dbname2,user,psd}
        {--trgdb= : 目标数据库信息, eg host1,dbname1|dbname2,user,psd;host2,dbname1|dbname2,user,psd}
        {--shard= : 分片, eg 1或-1}
        {--gid= : 需要跑的集合 eg 以模糊匹配方式获取}
        {--notgid= : 不需要跑的集合 eg 以模糊匹配方式获取}
        {--reran= : 重跑 eg -1或1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ces';

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

        //var_dump($this->orgdb);die;
        //原始服务器
        iniConfig($this->orgdb[0], 'mongodb');
        //var_dump(config('database.connections'),$this->orgdb[0],$this->orgdb[0]['host'] . $this->orgdb[0]['database']);die;
        $orgbas = DB::connection($this->orgdb[0]['host'] . $this->orgdb[0]['database']);

        var_dump($orgbas->table('g106_data_charge')->count(),$orgbas->collection('g106_data_charge')->count());
        die;


    }


    /**
     * 获取进程文件个数
     * @param $url
     * @return int
     */
    private function getCourselogsize($url)
    {
        $nub = file_exists($url) ? count(scandir($url)) - 2 : 0;
        //var_dump($url,$nub);
        return $nub;
    }

    /**
     * 获取进程数
     * @return mixed
     */
    private function getDirsize($tp = "/data/*")
    {

        $nub = count(glob($tp));
        //var_dump($tp, $nub, date("Y-m-d H:i:s", time()));
        return $nub;
    }

    /**
     * 获取进程数
     * @return mixed
     */
    private function getProsize($tp = "Back:")
    {

        $cmd = "sudo ps aux |grep '{$tp}'|wc -l";
        $nub = trim(shell_exec($cmd));
        /*$cmds = "sudo ps aux |grep '{$tp}'";
        $nubs = shell_exec($cmds);
        var_dump($cmd, $nub, $cmds, $nubs, date("Y-m-d H:i:s", time()));*/
        return $nub;
        /*exec($cmd, $nub);
        var_dump($cmd,$nub);
        return $nub[0];*/
    }

    /**
     * 获取进程id
     * @return mixed
     */
    private function getProid($tp = "Back:")
    {

        $cmd = "ps aux |grep {$tp}|awk '{print $2}'";
        exec($cmd, $nub);
        //var_dump($cmd, $nub);
        return $nub;
    }

    /**
     * 获取进程
     * @return mixed
     */
    private function getProcesses($tp = "Back:")
    {

        $cmd = "ps aux |grep {$tp}";
        exec($cmd, $nub);
        //var_dump($cmd, $nub);
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
