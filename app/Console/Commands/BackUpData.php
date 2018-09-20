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

class BackUpData extends Command
{

    //进程数
    public $course = 0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Back:UpData
        {--orgdb= : 原始数据库信息, eg host1,dbname1|dbname2,user,psd;host2,dbname1|dbname2,user,psd}
        {--trgdb= : 目标数据库信息, eg host1,dbname1|dbname2,user,psd;host2,dbname1|dbname2,user,psd}
        {--shard= : 分片, eg 1或-1}
        {--gids= : 需要跑的集合 eg 以模糊匹配方式获取}
        {--notgid= : 不需要跑的集合 eg 以模糊匹配方式获取}
        {--isoplog= : 只跑oplog eg -1或1}
        {--reran= : 重跑 eg -1或1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'mongo数据备份';

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

        //var_dump($this->input,$this->orgdb,$this->trgdb);die;

        echo "全备任务开始时间：" . date("Y-m-d H:i:s", time()) . PHP_EOL;
        //执行处理
        self::proData($this->orgdb, $this->trgdb);
        echo "全备任务任务结束时间：" . date("Y-m-d H:i:s", time()) . PHP_EOL;

    }

    /**
     * 处理数据
     */
    private function proData($orghost, $trghost)
    {

        $dirs = dirname(dirname(dirname(__DIR__)));
        $step = $this->step;
        $checktable = [];
        while (true) {

            if (self::getDirsize("{$this->mongobak}courselog/*") > $this->number) {

                $step = 3;
            } else {

                $step = $this->step;
                foreach ($orghost as $k => $v) {

                    if (isset($v['dbs']) && count($v['dbs'])) {

                        if ($this->input['isoplog'] < 1) {

                            //原始服务器
                            iniConfig($v, 'mongodb');
                            $orgbas = DB::connection($v['host'] . $v['database']);

                            //目标服务器
                            iniConfig($trghost[$k], 'mongodb');
                            $trgbas = DB::connection($trghost[$k]['host'] . $trghost[$k]['database']);

                            foreach ($v['dbs'] as $dbk => $dbname) {

                                if ($dbk == 0) {
                                    toLog(time(), "oplogst{$v['host']}{$v['port']}.log", "{$this->mongobak}oplogtime", 0);
                                }

                                //获取集合
                                $orgbasTablist = mongoTablist($orgbas, [$dbname]);
                                $trgbasTablist = mongoTablist($trgbas, [$trghost[$k]['dbs'][$dbk]]);
                                $diffTables = array_diff($orgbasTablist, $trgbasTablist);
                                self::proGame($diffTables);
                                self::proGame($orgbasTablist);

                                //var_dump(count($diffTables),count($orgbasTablist), $dbname, $trghost[$k]['dbs'][$dbk]);die;

                                if ($this->input['gids'] == 'log_') {

                                    foreach ($orgbasTablist as $table) {

                                        if (!file_exists("{$this->mongobak}courselog/{$v['host']}{$v['port']}{$v['database']}{$table}.log") && self::getCourselogsize("{$this->mongobak}courselog") < $this->number) {

                                            $checktable[] = $table;
                                            //调用updb
                                            $dbhost = $v;
                                            $dbhost['tables'] = $table;
                                            $dborghost = json_encode($dbhost);
                                            $dbtrghost = json_encode($trghost[$k]);
                                            self::shell_mongo("Back:ExImLog", $dirs, $dborghost, $dbtrghost, "Back:ExImLog.{$table}.log");
                                        }
                                        //sleep(1);
                                    }

                                } else {

                                    if (count($diffTables)) {

                                        if ($this->input['shard'] > 0) {
                                            foreach ($diffTables as $table) {

                                                if (!file_exists("{$this->mongobak}courselog/{$v['host']}{$v['port']}{$v['database']}{$table}.log") && self::getCourselogsize("{$this->mongobak}courselog") < $this->number) {

                                                    toLog(time(), "{$table}{$v['host']}{$v['port']}.log", "{$this->mongobak}updbtime", 0);
                                                    //调用updb
                                                    $dbhost = $v;
                                                    $dbhost['tables'] = $table;
                                                    $dborghost = json_encode($dbhost);
                                                    $dbtrghost = json_encode($trghost[$k]);
                                                    self::shell_mongo("Back:UpDb", $dirs, $dborghost, $dbtrghost, "Back:UpDb.{$table}.log");
                                                }
                                                //sleep(1);
                                            }
                                        } else {

                                            if (!file_exists("{$this->mongobak}courselog/{$v['host']}{$v['port']}{$dbname}.log") && self::getCourselogsize("{$this->mongobak}courselog") < $this->number) {

                                                //调用updb
                                                $v['database'] = $dbname;
                                                $trghost[$k]['database'] = $trghost[$k]['dbs'][$dbk];
                                                $dborghost = json_encode($v);
                                                $dbtrghost = json_encode($trghost[$k]);
                                                self::shell_mongo("Back:UpDb", $dirs, $dborghost, $dbtrghost, "Back:UpDb.{$dbname}.log");
                                            }

                                        }

                                    } else {

                                        $orgbaslist = array_diff($orgbasTablist, $checktable);
                                        if (!count($orgbaslist)) {
                                            $orgbaslist = $orgbasTablist;
                                            $checktable = [];
                                        }

                                        foreach ($orgbaslist as $table) {

                                            if (!file_exists("{$this->mongobak}courselog/{$v['host']}{$v['port']}{$v['database']}{$table}.log") && self::getCourselogsize("{$this->mongobak}courselog") < $this->number) {

                                                $checktable[] = $table;
                                                //调用updb
                                                $dbhost = $v;
                                                $dbhost['tables'] = $table;
                                                $dborghost = json_encode($dbhost);
                                                $dbtrghost = json_encode($trghost[$k]);
                                                self::shell_mongo("Back:ExIm", $dirs, $dborghost, $dbtrghost, "Back:ExIm.{$table}.log");
                                            }
                                            //sleep(1);
                                        }

                                    }
                                }

                                //sleep(1);
                            }
                        }

                        /*if (($this->input['isoplog'] > 0 && self::getDirsize("{$this->mongobak}courselog/{$v['host']}{$v['port']}*") < $this->number)) {

                            //调用oplog
                            $v['gids'] = $this->input['gids'];
                            $oporghost = json_encode($v);
                            $optrghost = json_encode($trghost[$k]);
                            self::shell_mongo("Back:UpOplog",$dirs,$oporghost,$optrghost,"Back:UpOplog{$v['host']}{$v['port']}.log");
                        }*/

                        unset($orgbas);
                        unset($trgbas);
                    } else {

                        echo $v['host'] . " 无数据库！";
                    }

                }
            }

            sleep($step);
        }

    }

    /**
     * 跑mongo脚本
     * @param $dirs
     * @param $oporghost
     * @param $optrghost
     * @param $logname
     */
    private function shell_mongo($type, $dirs, $oporghost, $optrghost, $logname)
    {

        //调用oplog
        $oplogcmd = "nohup {$this->phpdir}php {$dirs}/artisan {$type} --orgdb='{$oporghost}' --trgdb='{$optrghost}' > {$this->mongobak}logs/{$logname} 2>&1 &";
        //echo $oplogcmd;die;
        shell_exec($oplogcmd);

    }

    /**
     * 处理游戏
     * @param $gid
     * @param $tab
     * @return array
     */
    private function proGame(&$tab)
    {

        $gid = $this->input['gids'];
        $notgid = $this->input['notgid'];
        if (!empty($gid)) {
            $data = [];
            if (is_array($tab) && count($tab)) {

                foreach ($tab as $value) {

                    if (stripos($value, $gid)) {
                        $data[] = $value;
                    }
                }
            }
            $tab = $data;
        }

        if (!empty($notgid)) {
            $data = [];
            if (is_array($tab) && count($tab)) {

                foreach ($tab as $value) {

                    if (stripos($value, $notgid) === false) {
                        $data[] = $value;
                    }
                }
            }
            $tab = $data;
        }

        return $tab;
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

        $cmd = "ps aux |grep '{$tp}'|wc -l";
        $nub = trim(shell_exec($cmd));
        //var_dump($cmd, $nub, date("Y-m-d H:i:s", time()));
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
        if ($this->option('gids')) {
            $gid = $this->option('gids');
        }

        $reran = 0;
        if ($this->option('reran')) {
            $reran = $this->option('reran');
        }

        $isoplog = 0;
        if ($this->option('isoplog')) {
            $isoplog = $this->option('isoplog');
        }

        $this->input = ['orgdb' => $this->orgdb, 'trgdb' => $this->trgdb, 'shard' => $shard, 'notgid' => $notgid, 'gids' => $gid, 'reran' => $reran, 'isoplog' => $isoplog];
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
