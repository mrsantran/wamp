WAMPServer::connection(Santran\WAMPServer\Connection::class);
WAMPServer::topic('http://api.wamp.ws/procedure#authreq', Santran\WAMPServer\Topics\AuthTopic::class);
WAMPServer::topic('http://api.wamp.ws/procedure#auth', Santran\WAMPServer\Topics\AuthTopic::class);
WAMPServer::topic('hello-topic', Santran\WAMPServer\Topics\HelloTopic::class);