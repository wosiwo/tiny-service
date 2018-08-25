// php server.php

workers = []
fork manager 进程出来
	for(i=0;i<64;i++){
		pid = fork();
		conn= epolCreate()
		if(pid==0){
			//
			callWoker(conn)
		}
		workers[] = [conn,pid]

	}
	function callWoker(conn){
		
		while(1){
			event = epol(conn)
			if('recive'==event){
				data = read(conn)
				data = parseData(data)
				//调用php执行
				call_user_func(data['sock'],data['func'],data['params'])
			}
		}
	}
master 进程
	循环穿件reactor线程
	ptids = []
	for(i=0;i<64;i++){
		conn= epolCreate()
		ptid = pthreadCreate();

		if(pid==0){
			pthreadLoop(conn)
		}
		ptids[] = [conn,pid]
	}
	//循环监听端口
	connd = epolCreate()
	epool_bind(connd,'0.0.0.0',8888)
	count = count(ptids)
	while (1) {
		ret = epol(conn)
		if('connection'==ret.event){
			ptidIndex = rand(0,count-1)
			pthreadRow = ptids[ptidIndex]

			ptid = pthreadRow[1]
			conn = pthreadRow[0]
			epool_send(conn,ret.sock)
		}
	}
	function pthreadLoop(conn,sock){
		count = count(workers)
		while(1){
			ret = epol(conn)
			if('recive'==ret){
				data = read(conn)
				workerIndex = rand(0,count-1)
				workerRow = workers[workerIndex]
				epool_send(conn,sock,workerRow[0],workerRow[1])

			}
		}
	}

#扩展中的send
	function send(){
		epool_send(sock,result)
	}
	



// php代码部分

	//业务代码
	function someFunc(params){
		return 'xxxx'
	}

	//框架代码
	class server{

		function recive($sock,$func,$params){
			$ret = call_user_func($func,$params);
			$this->send($sock,$ret)
		}
		function send($ret){
			$this->swoole->send($sock,$ret)
		}
	}



