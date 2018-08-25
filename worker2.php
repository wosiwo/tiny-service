<?php
set_time_limit(0);

require './Tiny/Loader.php';

Tiny\Loader::addNameSpace('Tiny', __DIR__ . '/Tiny');
Tiny\Loader::addNameSpace('Search', __DIR__ . '/Search');
Tiny\Loader::addNameSpace('Community', '/data/www/public/community/common-library');

spl_autoload_register('\\Tiny\\Loader::autoload');

//ini_set();
//sleep(12);   //暂停几秒，使用 strace 捕捉进程
//使用 tinys扩展
//调用扩展类，发送数据


class callBack{


     function  onReceive( $fd, $data){
        echo "php onReceive fd $fd data $data  \n";
         //解析包
         //解析包头

         var_dump(strlen($data));
         $header = unpack(\Tiny\CallBack::HEADER_STRUCT, substr($data, 0, \Tiny\CallBack::HEADER_SIZE));
         print_r($header);

         $buffer[$fd] = substr($data, \Tiny\CallBack::HEADER_SIZE);

         //数据解包
         $request = \Tiny\CallBack::decode($buffer[$fd], $header['type']);
         print_r($request);

        //调用扩展类，发送数据
         $ret = [
             'code' => 0,
             'data' => 'send back',
         ];
         $response = array('errno' => 0, 'data' => $ret);
         echo "test 1 \n";
         var_dump(\Tiny\CallBack::encode($response,$header['type'],$header['uid'],$header['serid']));
//        $this->tinys->send($fd,"testaaaaaaaaaaaaaaaaa");
        $this->tinys->send($fd,\Tiny\CallBack::encode($response,$header['type'],$header['uid'],$header['serid']));
         echo "test 2 \n";
    }
}

$callback = new callBack();


$tinys = new tinys();

//
$callback->tinys = $tinys;

//$tinys->send(1,'send back\n');

$tinys->on('Receive',[$callback,'onReceive']);

$tinys->run("127.0.0.1",4989);



echo "start \n";