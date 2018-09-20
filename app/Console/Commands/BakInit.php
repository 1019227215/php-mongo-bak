<?php
/**
 * Created by PhpStorm.
 * User: zhangyu
 * Date: 2018-06-29
 * Time: 18:13
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BakInit extends Command
{

    //数据库配置
    public $dbhost = [];
    //输入参数
    public $input = "";
    //mongodump文件存放地址
    private $mongobak = "/data/mongobak/";
    //mongo安装的绝对路径
    private $mongodir = "sudo /data/mongo/bin/";
    //原始数据服务器信息
    private $orgdb = [[
        'database' => 'bas',
        'host' => '10.20.20.61',
        'port' => '27017',
        'username' => 'admin',
        'password' => 'admin',
    ]];
    //目标服务器信息
    private $trgdb = [[
        'database' => 'bas',
        'host' => '10.20.20.66',
        'port' => '27017',
        'username' => 'admin',
        'password' => 'tyiSq6-ml',
    ]];

    public function __construct()
    {
        setMemory();
        setDistrict();
        $this->dbhost = [
            'database' => $_ENV['DB_DATABASE'],
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
        ];
    }

    /**
     * 合并初始化字段、类型、长度
     * @param $m
     * @param $k
     */
    public function initMysqlfields(&$m, $k)
    {
        $m = array_merge($m, $k);
    }

    /**
     * 返回字段名
     * @param $m
     * @param $k
     */
    public function initFields(&$m, $k)
    {
        $m = array_keys($k);
    }

    /**
     * 初始化字段
     * @param $former
     * @param $now
     * @param $field
     */
    public function initRkey(&$former, $now, $field)
    {

        if (is_array($former)) {

            if (is_array($field) && count($field) && is_array($now) && count($now)) {

                foreach ($field as $keys => $value) {

                    foreach ($now as $val) {
                        $tmpval = $value;
                        $tmpval['part'] = str_replace(["COMMENT '"], ["COMMENT '{$val}"], $tmpval['part']);
                        $tmp[$val . $keys] = $tmpval;
                    }
                    $former = array_merge($former, $tmp);
                }
            }
        }
    }

    /**
     *  初始化数字
     * @param $former
     * @param $start
     * @param $end
     * @param int $step
     */
    public function initNumber(&$former, $start, $end, $step = 1)
    {
        if ($start < $end && $step > 0) {

            for (; $start <= $end; $start += $step) {
                $former[] = $start;
            }
        } else {
            array_push($former, $start, $end);
        }

    }

    /**
     * 判断库是否存在
     *
     * @param DB对象 $db
     * @param string $dbName
     * @return string
     */
    public function isDbname($dbName)
    {

        $dbName = explode('.', $dbName)[0];
        $database = toArray(sqlExec("SHOW DATABASES", 'select'));
        $database = getMkData($database, 'Database');

        return isset($database[$dbName]) ? true : false;
    }

    /**
     * 判断库是否存在，不存在则创建
     *
     * @param DB对象 $db
     * @param string $dbName
     * @return string
     */
    public function iscatDatabases($dbName)
    {

        $dbName = explode('.', $dbName)[0];
        $str = "{$dbName}库已创建";
        $database = toArray(sqlExec("SHOW DATABASES", 'select'));
        $database = getMkData($database, 'Database');

        try {
            if (!isset($database[$dbName])) {

                $sl = sqlExec("CREATE DATABASE IF NOT EXISTS $dbName DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
                if ($sl) {
                    $str = "创建{$dbName}库成功！";
                } else {
                    $str = "创建{$dbName}库失败！";
                }

            }
        } catch (PDOException $e) {
            $str = "创建{$dbName}库失败错误捕捉：" . $e->getMessage();
        }

        return $str . PHP_EOL;
    }

    /**
     * 检查表是否存在
     * 不存在根据给定字段创建表
     * @param $tableName    库名.表名
     * @param $field    字段参数
     * @dbcfname $dbcfname  数据库配置名称config目录下的database.php
     * @return string
     */
    public function listTable($tableName, $field = [], $dbcfname = '')
    {

        $dbcfname = empty($dbcfname) ? $this->dbhost['database'] : $dbcfname;

        $tableName = explode('.', $tableName);
        $str = "{$tableName[1]}表已存在";
        $tables = toArray(sqlExec("SHOW TABLES FROM {$tableName[0]}", 'select'));
        //$tables = toArray(sqlExec("SHOW TABLES LIKE '{$tableName[1]}'", 'select','adv'));
        $tables = getMkData($tables, 'Tables_in_' . $tableName[0]);

        try {
            if (!isset($tables[$tableName[1]]) && is_array($field) && count($field)) {

                $csql = "CREATE TABLE `{$tableName[1]}` (";
                $arr = [];

                //字段组合
                foreach ($field as $key => $value) {
                    $tmp = [];
                    foreach ($value as $k => $v) {
                        $tmp[] = ($k == 'part') ? $v : " {$k}({$v}) ";
                    }
                    $arr[] = " `{$key}` " . implode(' ', $tmp);
                }

                //主键、索引组合
                if (is_array($this->keys) && count($this->keys)) {

                    $arr[] = "PRIMARY KEY (`" . implode('`,`', $this->keys) . "`)";
                    foreach ($this->keys as $val) {
                        $arr[] = "KEY `{$val}` (`{$val}`)";
                    }
                }

                //拼装sql
                $csql .= implode(',', $arr);
                $csql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8;";

                /**
                 * 原本可以使用use 库名 执行创建表
                 * 由于pdo的缓存扩展未使用，所以这里需要制定配置
                 * 配置需要一开始在database.php里配置好
                 */
                $sl = sqlExec($csql, 'statement', $dbcfname);
                if ($sl) {
                    $str = "创建{$tableName[1]}表成功！";
                } else {
                    $str = "创建{$tableName[1]}表失败！";
                }

            } else {

                $field_s = array_column(sqlExec("desc {$tableName[1]}", 'select', $dbcfname), 'Field');
                $c = array_diff(array_keys($field), $field_s);
                if (count($c)) {

                    $csql = "ALTER TABLE `{$tableName[1]}` ADD (";
                    $arr = [];

                    //字段组合
                    foreach ($c as $key) {
                        $tmp = [];
                        foreach ($field[$key] as $k => $v) {
                            $tmp[] = ($k == 'part') ? $v : " {$k}({$v}) ";
                        }
                        $arr[] = " `{$key}` " . implode(' ', $tmp);
                    }

                    $ksy = array_intersect($c, $this->keys);

                    //主键、索引组合
                    if (is_array($ksy) && count($ksy)) {

                        $arr[] = "PRIMARY KEY (`" . implode('`,`', $ksy) . "`)";
                        foreach ($ksy as $val) {
                            $arr[] = "KEY `{$val}` (`{$val}`)";
                        }
                    }

                    //拼装sql
                    $csql .= implode(',', $arr);
                    $csql .= ");";

                    /**
                     * 原本可以使用use 库名 执行创建表
                     * 由于pdo的缓存扩展未使用，所以这里需要制定配置
                     * 配置需要一开始在database.php里配置好
                     */
                    $sl = sqlExec($csql, 'statement', $dbcfname);
                    if ($sl) {
                        $str = "{$tableName[1]}表添加字段成功！" . implode(',', $c);
                    } else {
                        $str = "{$tableName[1]}表添加字段失败！" . implode(',', $c);
                    }

                }

            }
        } catch (PDOException $e) {
            $str = "创建{$tableName[1]}表失败错误捕捉：" . $e->getMessage();
        }

        return $str . PHP_EOL;

    }

    /**
     * 动态数据库配置
     * @param string $dbhost
     */
    public function iniConfig($dbhost = '', $type = "mysql")
    {

        //['database' => 'adv_data', 'host' => '192.168.252.133', 'port' => '3310', 'username' => 'jfsql', 'password' => '8OX6m6zKM2LX6'];
        $dbhost = empty($dbhost) ? $this->dbhost : $dbhost;
        if ($type == 'mongodb'){

            config(['database.connections.' . $dbhost['host'].$dbhost['database'] => [
                'driver' => $type,
                'host' => trim($dbhost['host']),
                'database' => trim($dbhost['database']),
                'username' => trim($dbhost['username']),
                'password' => trim($dbhost['password']),
                'port' => trim($dbhost['port']),
                'options' => [
                    12=>true,
                    'database'=>'admin',
                ],
            ]]);
        }else{

            config(['database.connections.' . $dbhost['database'] => [
                'driver' => $type,
                'host' => trim($dbhost['host']),
                'database' => trim($dbhost['database']),
                'username' => trim($dbhost['username']),
                'password' => trim($dbhost['password']),
                'port' => trim($dbhost['port']),
                'charset' => 'utf8',
                'collation' => 'utf8_general_ci',
                'prefix' => '',
                'strict' => false,
            ]]);
        }


        //dump(config('database.connections'));

        //return \DB::connection('game_' . $server_id);
    }

    /**
     * 从数组中删除指定值
     * @param $arr
     * @param $val
     */
    public function unsetValue(&$arr, $val)
    {

        $dtk = array_search($val, $arr);
        if (isset($dtk)) {

            unset($arr[$dtk]);
        }
    }

    /**
     * 备份表
     * @param $baktab
     * @param $tab
     * @dbcfname $dbcfname
     * @return mixed
     */
    private function bakTable($baktab, $tab, $dbcfname = 'adv')
    {

        $sql = "DROP TABLE IF EXISTS {$baktab};";
        sqlExec($sql, 'statement', $dbcfname);
        $sql = "CREATE TABLE {$baktab}  LIKE {$tab};";
        sqlExec($sql, 'statement', $dbcfname);
        $sql = "INSERT INTO {$baktab} SELECT * FROM {$tab};";
        return sqlExec($sql, 'statement', $dbcfname);
    }

    /**
     * 回退数据到指定表
     * @param $tab
     * @param $baktab
     * @dbcfname $dbcfname
     * @return mixed
     */
    private function reranTable($tab, $baktab, $dbcfname = 'adv')
    {

        $sql = "truncate table {$tab};";
        sqlExec($sql, 'statement', $dbcfname);

        $tbl = explode('.', $baktab);
        $sql = "SHOW TABLES LIKE '{$tbl[1]}'";
        $arr = sqlExec($sql, 'select', 'adv');
        if (count($arr)) {
            $sql = "INSERT INTO {$tab} SELECT * FROM {$baktab};";
            $arr = sqlExec($sql, 'statement', $dbcfname);
        }
        return $arr;
    }


}