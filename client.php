<?php
if (!class_exists('Swoole', false))
{
    require_once '/data/www/public/framework/libs/Swoole/Loader.php';
    Swoole\Loader::addNameSpace('Swoole', '/data/www/public/framework/libs/Swoole');
    spl_autoload_register('\\Swoole\\Loader::autoload', true, true);
}
//namespace Tiny;


class Service extends Swoole\Client\SOA
{
    protected $service_name;
    protected $namespace;
    protected $config;

    /**
     * 模调上报的ID
     * @var int
     */
    protected $moduleId = 1000321;

    const ERR_NO_CONF = 7001;

    /**
     * 构造函数
     * @param $service
     * @throws ServiceException
     */
    function __construct($service = 'tiny')
    {
        if (empty($service))
        {
            $service = 'tiny';
        }

        $conf['tiny'] = array(
            "namespace" => "Search",
            "id" => "tiny",
            "servers"=> [
                [
                    "host"=> "127.0.0.1",
                    "port"=> 9001,
                    "weight"=> 100,
                    "status"=> "online"
                ],
                [
                    "host"=> "127.0.0.1",
                    "port"=> 9001,
                    "weight"=> 100,
                    "status"=> "online"
                ],
            ]
        );$conf['user'] = array(
            "namespace" => "Search",
            "id" => "tiny",
            "servers"=> [
                [
                    "host"=> "127.0.0.1",
                    "port"=> 9002,
                    "weight"=> 100,
                    "status"=> "online"
                ]
            ]
        );
        $conf['one'] = array(
                "namespace" => "Search",
                "id" => "tiny",
                "servers"=> [
                    [
                        "host"=> "127.0.0.1",
                        "port"=> 4989,
//                        "port"=> 2989,
                        "weight"=> 100,
                        "status"=> "online"
                    ]
                ]
            );

        $this->addServers($conf[$service]['servers']);


        $this->config = $conf[$service];
        $this->namespace = $conf[$service]['namespace'];

        $key = empty($id) ? 'default' : $id;
        self::$_instances[$key] = $this;
        $this->haveSwoole = extension_loaded('swoole');
        $this->haveSockets = extension_loaded('sockets');

    }

    /**
     * @param $obj Swoole\Client\SOA_Result
     */
    protected function beforeRequest($obj)
    {

    }

    /**
     * @param $obj Swoole\Client\SOA_Result
     */
    protected function afterRequest($obj)
    {

    }

    function call()
    {
        $args = func_get_args();
        return $this->task($this->namespace . '\\' . $args[0], array_slice($args, 1));
    }


    /**
     * RPC调用
     *
     * @param $function
     * @param $params
     * @param $callback
     * @return SOA_Result
     */
    function task1($function, $params = array(), $callback = null)
    {
        echo $function."\n";
        print_r($params);
        $retObj = new \Swoole\Client\RPC_Result($this);
        $send = array('call' => $function, 'params' => $params);
        if (count($this->env) > 0)
        {
            //调用端环境变量
            $send['env'] = $this->env;
        }
        $this->request($send, $retObj);
        $retObj->callback = $callback;
        return $retObj;
    }

    /**
     * 发送请求
     * @param $send
     * @param SOA_result $retObj
     * @return bool
     */
    protected function request1($send, $retObj)
    {
        $retObj->send = $send;
        $this->beforeRequest($retObj);

        $retObj->index = $this->requestIndex ++;
        connect_to_server:
        if ($this->connectToServer($retObj) === false)
        {
            $retObj->code = \Swoole\Client\SOA_Result::ERR_CONNECT;
            return false;
        }
        //请求串号
        $retObj->requestId = self::getRequestId();
        //打包格式
        $encodeType = $this->encode_json ? \Swoole\Protocol\SOAServer::DECODE_JSON : \Swoole\Protocol\SOAServer::DECODE_PHP;
        if ($this->encode_gzip)
        {
            $encodeType |= \Swoole\Protocol\SOAServer::DECODE_GZIP;
        }
        //发送失败了
        if ($retObj->socket->send(\Swoole\Protocol\SOAServer::encode($retObj->send, $encodeType, 0, $retObj->requestId)) === false)
        {
            //连接被重置了，重现连接到服务器
            if ($this->haveSwoole and $retObj->socket->errCode == 104)
            {
                goto connect_to_server;
            }
            $retObj->code = \Swoole\Client\SOA_Result::ERR_SEND;
            unset($retObj->socket);
            return false;
        }
        $retObj->code = \Swoole\Client\SOA_Result::ERR_RECV;
        //加入wait_list
        $this->waitList[$retObj->id] = $retObj;
        return true;
    }

    function getResult($timeout = 0.5)
    {
        if ($this->code == self::ERR_RECV)
        {
            $this->soa_client->wait($timeout);
        }
        return $this->data;
    }

    /**
     * 侦测服务器是否存活
     */
    function ping()
    {
        return $this->task('PING')->getResult() === 'PONG';
    }


    /**
     * 添加服务器
     * @param array $servers
     */
    function addServers(array $servers)
    {
        if (isset($servers['host']))
        {
            self::formatServerConfig($servers);
            $this->servers[] = $servers;
        }
        else
        {
            //兼容老的写法
            foreach ($servers as $svr)
            {
                // 127.0.0.1:8001 的写法
                if (is_string($svr))
                {
                    list($config['host'], $config['port']) = explode(':', $svr);
                }
                else
                {
                    $config = $svr;
                }
                self::formatServerConfig($config);
                $this->servers[] = $config;
            }
        }
    }


    /**
     * @param $config
     * @throws Exception
     */
    static protected function formatServerConfig(&$config)
    {
        if (empty($config['host']))
        {
            throw new Exception("require 'host' option.");
        }
        if (empty($config['port']))
        {
            throw new Exception("require 'port' option.");
        }
        if (empty($config['status']))
        {
            $config['status'] = 'online';
        }
        if (empty($config['weight']))
        {
            $config['weight'] = 100;
        }
    }



}