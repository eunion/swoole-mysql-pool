<?php
namespace mysqlPool;

use mysqlPool\exception\PoolException;
use mysqlPool\Response;

class Pool
{
    protected $maxConn = 100; //最大连接数
    protected $minConn = 5; // 最小连接数
    protected $timeout = 60; // 连接时间
    protected $maxWaitNum = 0; // 最大等待数

    private $poolConnNum = 0; // 连接池连接数
    private $isInited = false; // 是否初始化
    private $idlePool; // 空闲连接
    private $waitQueue; // 等待队列

    public static $instance; //连接池对象单例

    private function __construct()
    {
        $this->idlePool = new \SplQueue();
        $this->waitQueue = new \SplQueue();
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    public static function getInstance()
    {
        if (self::$instance instanceof self){
            return self::$instance;
        }else{
            self::$instance = new self();
            return self::$instance;
        }
    }


    /**
     * 初始化连接池
     */
    public function init()
    {
        if ($this->isInited == false) {

            for ($i = 0; $i < $this->minConn; $i++) {
                $this->addConnection();
            }
            $this->isInited = true;
        }

    }

    public function initTimeTick()
    {
        swoole_timer_tick(100,function (){
            if (count($this->waitQueue->count() == 0)){
                return false;
            }else{
                if ($this->idlePool->count() == 0){
                    return false;
                }else{
                    $serv_data = $this->waitQueue->pop();
                    $db = $this->getConnection($serv_data[0],$serv_data[1],$serv_data[2]);
                    Response::send($serv_data[0],$serv_data[1],$db->query($serv_data[2]));
                }
            }
        });
    }

    /**
     *  获取连接对外接口
     * @param $serv
     * @param $fd
     * @param $sql sql语句
     * @return mixed|null
     * @throws PoolException
     */
    public function getConnection($serv,$fd,$sql)
    {
        if ($this->idlePool->count() == 0){
            // 空闲连接池数为0
            if ($this->poolConnNum < $this->maxConn){
                // 申请新的连接
                $this->addConnection();
            }else{
                if ($this->waitQueue->count() < $this->maxWaitNum){
                    $this->waitQueue->push(array($serv,$fd,$sql));
                }else{
                    Response::send($serv,$fd,'wait queue exceed');
                }
            }
        }else{
            $db = $this->getDbFromPool();
            Response::send($serv,$fd,$db->query($sql));
            return $db;
        }
    }



    /**
     *  回收连接
     * @param $db
     */
    public function recycle($db)
    {
        $this->idlePool->push($db);
    }



    /**
     * 创建新连接
     */
    protected function addConnection()
    {
        try{
            $db = new Db();
        }catch (\PDOException $PDOException){
            return false;
        }
        $this->poolConnNum++;
        $this->idlePool->push($db);
    }

    /**
     * 从连接池取出连接
     */
    protected function getDbFromPool()
    {
        $db = $this->idlePool->pop();
        if ($db->ping()){
            // 连接可用
            // 检测引用次数
            if ($db->isExceedQuoteMax()){
                $this->destroyConnection($db);
                return null;
            }
            $db->incrQuoteNum();
            return $db;
        }else{
            // 销毁连接，连接数减一
            $this->destroyConnection($db);
            return null;
        }

    }

    public function getIdleCount()
    {
        return $this->idlePool->count();
    }

    public function getConnectCount()
    {
        return $this->poolConnNum;
    }

    /**
     *  销毁连接
     * @param $db
     */
    public function destroyConnection($db)
    {
        unset($db);
        $this->poolConnNum--;
    }

}