<?php

if (!function_exists('initMemory')) {

    /**
     * 初始化内存
     * @param string $size
     */
    function setMemory($size = '-1')
    {
        ini_set('memory_limit', $size);
    }
}

if (!function_exists('initMemory')) {

    /**
     * 初始化时区
     * @param string $size
     */
    function setDistrict($district = 'Asia/Shanghai')
    {

        date_default_timezone_set($district);
    }
}


if (!function_exists('toArray')) {
    /**
     * 数组转换
     * @param $db
     * @return array
     */
    function toArray($db)
    {
        $arr = [];
        foreach ($db as $v) {
            $arr[] = get_object_vars($v);
        }
        return $arr;
    }

}

if (!function_exists('mongoTablist')) {

    /**
     * 获取mongo集合列表（列出所有的表名）
     * @param $mongo
     * @param array $db
     * @return array
     */
    function mongoTablist($mongo, $db = ["bas"])
    {

        $tablist = [];
        $list = $mongo->listCollections($db);
        foreach ($list as $finds) {
            array_push($tablist, $finds->getName());
        }

        return $tablist;
    }

}

if (!function_exists('toLog')) {
    /**
     * 写入日志
     * @param $cent
     * @param $file
     * @param string $dir
     */
    function toLog($cent, $file = "advRemained.log", $dir = "/data/logs", $end = FILE_APPEND)
    {

        if (!file_exists($dir)) {
            exec("mkdir -m 777 -p {$dir}");
        }

        exec("chmod -R 777 {$dir}/*");

        if (empty($end)) {

            file_put_contents("{$dir}/{$file}", $cent);
        } else {

            file_put_contents("{$dir}/{$file}", $cent, $end);
        }

        exec("chmod -R 777 {$dir}/{$file}");
    }

}


if (!function_exists('iniConfig')) {


    /**
     * 动态数据库配置
     * @param string $dbhost
     */
    function iniConfig($dbhost = '', $type = "mysql")
    {

        $dbhost = empty($dbhost) ? '' : $dbhost;
        if (empty($dbhost)) {
            return "无连接信息！";
        }
        if ($type == 'mongodb') {

            config(['database.connections.' . $dbhost['host'] . $dbhost['database'] => [
                'driver' => $type,
                'host' => trim($dbhost['host']),
                'database' => trim($dbhost['database']),
                'username' => trim($dbhost['username']),
                'password' => trim($dbhost['password']),
                'port' => trim($dbhost['port']),
                'options' => [
                    12 => true,
                    'database' => 'admin',
                ],
            ]]);
        } else {

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
}