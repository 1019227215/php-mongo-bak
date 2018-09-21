# Lumen PHP Framework

[![Build Status](https://travis-ci.org/laravel/lumen-framework.svg)](https://travis-ci.org/laravel/lumen-framework)
[![Total Downloads](https://poser.pugx.org/laravel/lumen-framework/d/total.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Stable Version](https://poser.pugx.org/laravel/lumen-framework/v/stable.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Unstable Version](https://poser.pugx.org/laravel/lumen-framework/v/unstable.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![License](https://poser.pugx.org/laravel/lumen-framework/license.svg)](https://packagist.org/packages/laravel/lumen-framework)

Laravel Lumen is a stunningly fast PHP micro-framework for building web applications with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Lumen attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as routing, database abstraction, queueing, and caching.

## Official Documentation

Documentation for the framework can be found on the [Lumen website](http://lumen.laravel.com/docs).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell at taylor@laravel.com. All security vulnerabilities will be promptly addressed.

## License

The Lumen framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

php-laravel备份mongo数据库

需求是本地备份线上mongo数据库，平时只跑oplog； 当线上有新集合或者本地删掉集合后会自动同步线上集合到本地。 
找了很多mongo备份资料，跟需求不一样或者是有bug； 于是用laravel自己写了一个。

提供多种数据备份方式

Back:UpOplog 按oplog备份，集群只有分片能获取到oplog

Back:UpDb 使用mongodump按集合下载，再使用mongorestore导入到指定数据库

Back:ExIm 使用mongoexport按指定条数下载，再使用mongoimport导入到指定数据库

Back:Guard 守护进程监控脚本，当脚本挂掉后立刻拉起；使用crontab执行

* * * * * /usr/local/php/bin/php /data/www/php-script/artisan Back:Guard >> /data/logs/Guard.log

Illuminate\Console\Command 配置基本参数

使用方式

mongo数据备份 可以多服务器多库 orgdb和trgdb是原始库和目标库的关系，需要一一对应（，分割参数 ；分割服务器 |分割库名） ip，端口，库名（|分割多库），用户名，密码；ip2，端口，库名（|分割多库），用户名，密码

例子： 

php artisan Back:UpData --orgdb="host1,port,dbname1|dbname2,user,psd;host2,port,dbname1|dbname2,user,psd" --trgdb="host1,port,dbname1|dbname2,user,psd;host2,port,dbname1|dbname2,user,psd" --shard='-1' --reran=1

用法如下 

nohup /usr/local/php/bin/php /data/www/php-script/artisan Back:UpData --orgdb='127.0.0.1,10011,bas,admin,admin;127.0.0.1,10012,bas,admin,admin;127.0.0.1,10013,bas,admin,admin' --trgdb='10.20.20.66,25001,bas,admin,admin;10.20.20.21,25001,bas,admin,admin;10.20.20.68,25001,bas,admin,admin' > /data/mongobak/logs/sync.log 2>&1 &

首先需要在本地把端口映射好，然后账号需要有集合列表查询和oplog查询权限。这里是按集群分片备份列子，单台只留一个。

远程连接mongo（通过端口映射，在本机查询）

ssh -fN -L 10110:192.168.253.12:27017 -p1101 root@101.132.37.120

ssh -fN -L 本地映射端口:线上mongo地址:线上mongo端口 -p线上机器端口 线上机器账号@线上机器或者跳板机ip

输入密码完成映射

netstat -tunlp 查看端口是否成功映射

mongo 127.0.0.1:10010  执行mongo命令

代码只是为了实现需求，可以根据自己需求随意修改；有任何疑问欢迎到群里相互进步

QQ群：234466427
