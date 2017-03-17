<?php
namespace Tiny;
use Community\tool;

define("LIBPATH", __DIR__);


class Server{
    protected static $beforeStopCallback;
    protected static $beforeReloadCallback;
    static $defaultOptions = array(
        'd|daemon' => '启用守护进程模式',
        'h|host?' => '指定监听地址',
        'p|port?' => '指定监听端口',
        'help' => '显示帮助界面',
        'b|base' => '使用BASE模式启动',
        'w|worker?' => '设置Worker进程的数量',
        'r|thread?' => '设置Reactor线程的数量',
        't|tasker?' => '设置Task进程的数量',
    );

    static $options = array();
    public $runtimeSetting = array();
    static $swooleMode;
    static $optionKit;
    static $pidFile;

    public static $packet_maxlen       = 2465792; //2M默认最大长度
    const HEADER_SIZE           = 16;


    function __construct($host, $port, $ssl = false){
        $flag = $ssl ? (SWOOLE_SOCK_TCP | SWOOLE_SSL) : SWOOLE_SOCK_TCP;
        if (!empty(self::$options['base']))
        {
            self::$swooleMode = SWOOLE_BASE;
        }
        elseif (extension_loaded('swoole'))
        {
            self::$swooleMode = SWOOLE_PROCESS;
        }
        $this->callBack = new \Tiny\CallBack($host, $port);
        $this->callBack->server = $this;

        $this->sw = new \swoole_server($host, $port, self::$swooleMode, $flag);
        $this->host = $host;
        $this->port = $port;

    }

    /**
     * @param $protocol
     * @throws \Exception
     */
    function setProtocol($protocol)
    {

    }
    function __call($func, $params)
    {
        return call_user_func_array(array($this->sw, $func), $params);
    }

    /**
     * 自动推断扩展支持
     * 默认使用swoole扩展,其次是libevent,最后是select(支持windows)
     * @param      $host
     * @param      $port
     * @param bool $ssl
     * @return Server
     */
    static function autoCreate($host, $port, $ssl = false)
    {
        if (class_exists('\\swoole_server', false))
        {
            return new self($host, $port, $ssl);
        }
        elseif (function_exists('event_base_new'))
        {
//            return new EventTCP($host, $port, $ssl);
        }
        else
        {
//            return new SelectTCP($host, $port, $ssl);
        }
    }

    /**
     * 设置PID文件
     * @param $pidFile
     */
    static function setPidFile($pidFile)
    {
        self::$pidFile = $pidFile;
    }

    function run($setting){
        $this->runtimeSetting = $setting;
        if (self::$pidFile)
        {
            $this->runtimeSetting['pid_file'] = self::$pidFile;
        }

        $version = explode('.', SWOOLE_VERSION);

        $this->sw->set($setting);

        //1.7.0
        if ($version[1] >= 7)
        {
            $this->sw->on('ManagerStart', function ($serv)
            {
                Console::setProcessName($this->getProcessName() . ': manager');
            });
        }

        $this->sw->on('Start', array($this, 'onMasterStart'));
        $this->sw->on('Shutdown', array($this, 'onMasterStop'));
        $this->sw->on('ManagerStop', array($this, 'onManagerStop'));
        $this->sw->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->sw->on('Connect', array($this->callBack, 'onConnect'));
        $this->sw->on('Receive', array($this->callBack, 'onReceive'));
        $this->sw->on('Close', array($this->callBack, 'onClose'));
        $this->sw->on('WorkerStop', array($this->callBack, 'onShutdown'));

        echo "test2 \n";
        tool::debugError('test2');

        $this->sw->start();
    }


    function connection_info($fd)
    {
        return $this->sw->connection_info($fd);
    }

    function onMasterStart($serv)
    {
        Console::setProcessName($this->getProcessName() . ': master -host=' . $this->host . ' -port=' . $this->port);
        if (!empty($this->runtimeSetting['pid_file']))
        {
            file_put_contents(self::$pidFile, $serv->master_pid);
        }
        if (method_exists($this->callBack, 'onMasterStart'))
        {
            $this->callBack->onMasterStart($serv);
        }
    }

    function onMasterStop($serv)
    {
        if (!empty($this->runtimeSetting['pid_file']))
        {
            unlink(self::$pidFile);
        }
        if (method_exists($this->callBack, 'onMasterStop'))
        {
            $this->callBack->onMasterStop($serv);
        }
    }

    function onManagerStop(){

    }

    function onWorkerStart($serv, $worker_id)
    {
        if ($worker_id >= $serv->setting['worker_num'])
        {
            Console::setProcessName($this->getProcessName() . ': task');
        }
        else
        {
            Console::setProcessName($this->getProcessName() . ': worker');
        }
        if (method_exists($this->callBack, 'onStart'))
        {
            $this->callBack->onStart($serv, $worker_id);
        }
        if (method_exists($this->callBack, 'onWorkerStart'))
        {
            $this->callBack->onWorkerStart($serv, $worker_id);
        }
    }




    function send($client_id, $data)
    {
        return $this->sw->send($client_id, $data);
    }



    /**
     * 设置进程名称
     * @param $name
     */
    function setProcessName($name)
    {
        $this->processName = $name;
    }

    /**
     * 获取进程名称
     * @return string
     */
    function getProcessName()
    {
        if (empty($this->processName))
        {
            global $argv;
            return "php {$argv[0]}";
        }
        else
        {
            return $this->processName;
        }
    }

    /**
     * 显示命令行指令
     */
    static function start($startFunction)
    {
        if (empty(self::$pidFile))
        {
            throw new \Exception("require pidFile.");
        }
        $pid_file = self::$pidFile;
        if (is_file($pid_file))
        {
            $server_pid = file_get_contents($pid_file);
        }
        else
        {
            $server_pid = 0;
        }

        if (!self::$optionKit)
        {
            Loader::addNameSpace('GetOptionKit', LIBPATH . '/module/GetOptionKit/src/GetOptionKit');
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }

        $kit = self::$optionKit;
        foreach(self::$defaultOptions as $k => $v)
        {
            //解决Windows平台乱码问题
            if (PHP_OS == 'WINNT')
            {
                $v = iconv('utf-8', 'gbk', $v);
            }
            $kit->add($k, $v);
        }
        global $argv;
        $opt = $kit->parse($argv);
        if (empty($argv[1]) or isset($opt['help']))
        {
            goto usage;
        }
        elseif ($argv[1] == 'reload')
        {
            if (empty($server_pid))
            {
                exit("Server is not running");
            }
            if (self::$beforeReloadCallback)
            {
                call_user_func(self::$beforeReloadCallback, $opt);
            }
            FrameWork::$php->os->kill($server_pid, SIGUSR1);
            exit;
        }
        elseif ($argv[1] == 'stop')
        {
            if (empty($server_pid))
            {
                exit("Server is not running\n");
            }
            if (self::$beforeStopCallback)
            {
                call_user_func(self::$beforeStopCallback, $opt);
            }
            FrameWork::$php->os->kill($server_pid, SIGTERM);
            exit;
        }
        elseif ($argv[1] == 'start')
        {
            //已存在ServerPID，并且进程存在
            if (!empty($server_pid) and FrameWork::$php->os->kill($server_pid, 0))
            {
                exit("Server is already running.\n");
            }
        }
        else
        {
            usage:
            $kit->specs->printOptions("php {$argv[0]} start|stop|reload");
            exit;
        }
        self::$options = $opt;
        $startFunction($opt);
    }


}