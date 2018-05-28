<?php
/**
 * User: walker
 * Date: 2018/5/10
 * Time: 23:45
 */

require('./vendor/autoload.php');

use mysqlPool\Pool;
use mysqlPool\exception\PoolException;

$server = new \swoole_server('0.0.0.0',9501,SWOOLE_BASE,SWOOLE_SOCK_TCP);
$server->set([
    'work_num' => 4
]);
$server->on('WorkerStart',function($serv,$work_id){
    Pool::getInstance()->init();
});
$server->on('connect',function ($serv,$fd){
    echo "connect \n";
});
$server->on('receive',function ($serv,$fd,$from_id,$sql){
    Pool::getInstance()->getConnection($serv,$fd,$sql);
    echo 'pool connect total:'.Pool::getInstance()->getConnectCount()."\n";
    echo 'idle count:'.Pool::getInstance()->getIdleCount()."\n";
});
$server->start();
