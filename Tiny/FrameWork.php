<?php
namespace Tiny;


FrameWork::$app_path = dir(__DIR__);

/**
 * Class FrameWork
 * @package Tiny
 *
 * @method \Swoole\Platform\Linux os
 */
class FrameWork{
    static $Instance = null;
    static $app_path = null;
    static $php = null;

    public $os = null;
    public $db = null;
    public $redis = null;
    function __construct(){
        $this->os = new Platform\Linux();
    }
    /**
     * 初始化
     * @return Swoole
     */
    static function getInstance()
    {
        if (!self::$php)
        {
            self::$php = new FrameWork;
        }
        return self::$php;
    }


}