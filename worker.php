<?php


require './Tiny/Loader.php';

Tiny\Loader::addNameSpace('Tiny', __DIR__ . '/Tiny');
Tiny\Loader::addNameSpace('Search', __DIR__ . '/Search');
Tiny\Loader::addNameSpace('Community', '/data/www/public/community/common-library');

spl_autoload_register('\\Tiny\\Loader::autoload');


$env = get_cfg_var('env.name');

Tiny\FrameWork::getInstance();


if (empty($env))
{
    $env = 'product';
}
define('ENV_NAME', $env);


Tiny\Server::setPidFile(__DIR__ . '/logs/server.pid');

echo "test \n";

Tiny\Server::start(function ()
{

    $setting = array(
        //TODO： 实际使用中必须调大进程数
        'worker_num' => 4,
        'max_request' => 1000,
        'dispatch_mode' => 3,
        'daemonize' => true,
        'log_file' => __DIR__ . '/logs/swoole.log',
        'open_length_check' => 1,
        'package_max_length' => Tiny\Server::$packet_maxlen,
        'package_length_type' => 'N',
        'package_body_offset' => Tiny\Server::HEADER_SIZE,
        'package_length_offset' => 0,
        'watch_path' => __DIR__ . '/Tiny',
    );



    if (ENV_NAME == 'product')
    {
        $setting['worker_num'] = 64;
        //重定向PHP错误日志
        ini_set('error_log', __DIR__ . '/logs/php_errors.log');
    }
    else
    {
        //重定向PHP错误日志到logs目录
        ini_set('error_log', __DIR__ . '/logs/php_errors.log');
    }

    //设置为512M
    ini_set('memory_limit', '512M');

    $listenHost = '0.0.0.0';
    if (ENV_NAME == 'product')
    {
        $iplist = swoole_get_local_ip();
        //监听局域网IP
        foreach ($iplist as $k => $v)
        {
            if (substr($v, 0, 7) == '192.168')
            {
                $listenHost = $v;
            }
        }
    } elseif (ENV_NAME == 'test')
    {
        $iplist = swoole_get_local_ip();
        //监听局域网IP
        foreach ($iplist as $k => $v)
        {
            if (substr($v, 0, 6) == '172.16')
            {
                $listenHost = $v;
            }
        }
    }
    echo "test1 \n";

    $env_port = getenv('PORT');
    $server = Tiny\Server::autoCreate($listenHost, $env_port ? intval($env_port) : 9001);
//    $server->setProtocol($AppSvr);
    $server->setProcessName("TinyServer");
    $server->run($setting);
});