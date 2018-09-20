<?php
/**
 * mongo数据库表备份
 * 例子：
 * php /data/www/php-script/artisan Back:UpDb --orgdb='{"database":"bas","host":"127.0.0.1","port":"27017","username":"root","password":"root","dbs":["bas"]}' --trgdb='{"database":"bas","host":"10.20.20.61","port":"27017","username":"admin","password":"admin","dbs":["bas"]}'
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class BackExImLog extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Back:ExImLog
        {--orgdb= : 原始数据库信息, eg {"database":"bas","host":"127.0.0.1","port":"27017","username":"root","password":"root","dbs":["bas"]}
        {--trgdb= : 目标数据库信息, eg {"database":"bas","host":"10.20.20.61","port":"27017","username":"admin","password":"admin","dbs":["bas"]}}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ExIm备份log表';

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
        $table = isset($this->orgdb['tables']) ? $this->orgdb['tables'] : '';
        $courselog = "{$this->mongobak}courselog/{$this->orgdb['host']}{$this->orgdb['port']}{$this->orgdb['database']}{$table}.log";

        if (!file_exists($courselog)) {
            toLog(time(), "{$this->orgdb['host']}{$this->orgdb['port']}{$this->orgdb['database']}{$table}.log", "{$this->mongobak}courselog", 0);
            //var_dump($this->input);sleep(10);die;
            echo "库表备份任务开始时间：" . date("Y-m-d H:i:s", time()) . PHP_EOL;
            //执行数据备份
            self::proData($this->orgdb, $this->trgdb);
            echo "库表备份任务结束时间：" . date("Y-m-d H:i:s", time()) . PHP_EOL;

            shell_exec("rm -rf {$courselog}");
        }
        die;
    }

    /**
     * 处理数据
     */
    private function proData($orghost, $trghost)
    {

        if (isset($orghost['tables'])) {

            $table = "-c {$orghost['tables']}";
            $tablebson = "/{$orghost['tables']}.bson";

            if($orghost['tables'] == "g108_log_data_error"){
                $orghost_count = 3088895447;
                $trghost_count = 0;
            }else{
                iniConfig($orghost, 'mongodb');
                $orgbas = DB::connection($orghost['host'] . $orghost['database']);
                $orghost_count = $orgbas->collection($orghost['tables'])->count();

                iniConfig($trghost, 'mongodb');
                $orgbas = DB::connection($trghost['host'] . $trghost['database']);
                $trghost_count = $orgbas->collection($orghost['tables'])->count();
            }

            /*var_dump($orghost_count, $trghost_count);
            die;*/

            $exppid = "grep 'mongoexport -h {$orghost['host']} --port {$orghost['port']}'|grep '{$orghost['tables']}'";
            $imppid = "grep 'mongoimport -h {$trghost['host']} --port {$trghost['port']}'|grep '{$orghost['tables']}'";

            if (self::getProsize($exppid) > 1 || self::getProsize($imppid) > 1) {
                return "别急，前面的还没跑完！";
            }

            $pcnt = $orghost_count - $trghost_count;

            if ($pcnt > 0) {

                if ($pcnt > $this->size) {

                    for (; $trghost_count < $orghost_count;) {

                        if (($trghost_count + $this->size) > $orghost_count) {
                            $cnt = $orghost_count - $trghost_count;
                            $whe = " --limit {$cnt} --skip {$trghost_count}";
                        } else {
                            $whe = " --limit {$this->size} --skip {$trghost_count}";
                        }

                        $expcmd = "{$this->sudo}{$this->mongodir}mongoexport -h {$orghost['host']} --port {$orghost['port']} -u {$orghost['username']} -p '{$orghost['password']}' --authenticationDatabase admin -d {$orghost['database']} {$table} {$whe} -o {$this->mongobak}exim/{$orghost['database']}{$tablebson}";
                        $impcmd = "{$this->sudo}{$this->mongodir}mongoimport -h {$trghost['host']} --port {$trghost['port']} -u {$trghost['username']} -p '{$trghost['password']}' --authenticationDatabase admin -d {$trghost['database']} {$table} {$this->mongobak}exim/{$orghost['database']}{$tablebson}";

                        /*var_dump($trghost_count , $orghost_count,$this->size);
                        echo $expcmd.PHP_EOL;die;*/
                        exec($expcmd, $res);

                        //echo $impcmd.PHP_EOL;die;
                        exec($impcmd, $res);

                        $url = "{$this->mongobak}{$orghost['database']}{$tablebson}";
                        //echo $url;
                        if (file_exists($url) && stripos($url, '.bson')) {
                            $rmcmd = "{$this->sudo}rm -rf {$url}";
                            shell_exec($rmcmd);
                        }

                        $trghost_count += $this->size;
                    }
                } else {

                    $cnt = $orghost_count - $trghost_count;
                    $whe = " --limit {$cnt} --skip {$trghost_count}";

                    $expcmd = "{$this->sudo}{$this->mongodir}mongoexport -h {$orghost['host']} --port {$orghost['port']} -u {$orghost['username']} -p '{$orghost['password']}' --authenticationDatabase admin -d {$orghost['database']} {$table} {$whe} -o {$this->mongobak}exim/{$orghost['database']}{$tablebson}";
                    $impcmd = "{$this->sudo}{$this->mongodir}mongoimport -h {$trghost['host']} --port {$trghost['port']} -u {$trghost['username']} -p '{$trghost['password']}' --authenticationDatabase admin -d {$trghost['database']} {$table} {$this->mongobak}exim/{$orghost['database']}{$tablebson}";

                    //echo $expcmd.PHP_EOL;die;
                    exec($expcmd, $res);

                    //echo $impcmd.PHP_EOL;die;
                    exec($impcmd, $res);

                    $url = "{$this->mongobak}{$orghost['database']}{$tablebson}";
                    //echo $url;
                    if (file_exists($url) && stripos($url, '.bson')) {
                        $rmcmd = "{$this->sudo}rm -rf {$url}";
                        shell_exec($rmcmd);
                    }
                }
            }

        }

        return true;
    }

    /**
     * 获取进程数
     * @return mixed
     */
    private function getProsize($tp = "mongoexport")
    {

        $cmd = "{$this->sudo} ps aux |{$tp}|wc -l";
        $nub = trim(shell_exec($cmd));
        //var_dump($cmd, $nub, date("Y-m-d H:i:s", time()));
        return $nub;
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
