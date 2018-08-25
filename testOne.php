<?php

require './client.php';
//spl_autoload_register('\\Tiny\\Loader::autoload');
//
//Tiny\Loader::addNameSpace('Tiny', __DIR__ . '/Tiny');


//$ret = Tiny\Search::search();

$service = Service::getInstance('one');
//$serviceUser = Service::getInstance('user');

$ret = $service->call('Test::search')->getResult();
var_dump($ret);

//Search\Test::search()