<?php
/**
 * Class Description: 响应类
 * User: Walker
 * Job Number: HC343
 * Date: 2018/5/28
 * Time: 15:40
 * Email: showphp@foxmail.com
 */

namespace mysqlPool;


class Response
{
    public static function send($serv,$fd,$data)
    {
        $serv->send($fd,$data);
    }
}