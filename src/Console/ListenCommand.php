<?php

namespace Santran\WAMPServer\Console;

use Event;
use Illuminate\Console\Command;
use Santran\WAMPServer\WAMPServer;
use Symfony\Component\Console\Input\InputOption;

class ListenCommand extends Command {

    /**
     * @var \Santran\WAMPServer\Latchet
     */
    protected $wamp;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'wamp:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start listening on specified port for incomming connections';

    /**
     * Create a new command instance.
     *
     * @param $app
     */
    public function __construct($app) {
        $this->wamp = $app->make('wamp');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire() {
        $loop = \React\EventLoop\Factory::create();

        if (config('wamp.enablePush')) {
            $this->enablePush($loop);
        }

        // Set up our WebSocket server for clients wanting real-time updates
        $webSock = new \React\Socket\Server($loop);
        $webSock->listen($this->option('port'), '0.0.0.0'); // Binding to 0.0.0.0 means remotes can connect
        new \Ratchet\Server\IoServer(/**/
                new \Ratchet\Http\HttpServer(/**/
                new \Ratchet\WebSocket\WsServer(/**/
                new \Ratchet\Wamp\WampServer(/**/
                $this->wamp))), $webSock);

        if (config('wamp.allowFlash')) {
            $this->allowFlash($loop);
        }

        Event::fire('wamp.start');

        /**
         * heartbeat
         */
        if (config('wamp.enableHeartbeat', false)) {
            $loop->addPeriodicTimer(config('wamp.heartbeatInterval', 5), function () {
                /**
                 * TODO zombie/half connections check goes here
                 */
                /**
                 * send heart to clients
                 */
                WAMPServer::publish(config('wamp.heartbeatTopic', 'heartbeat'), ['heartbeat']);
            });
        }

        $this->info('Listening on port ' . $this->option('port') . ' zmq port ' . config('wamp.zmqPort'));
        $loop->run();
    }

    /**
     * Allow Flash sockets to connect to our server.
     * For this we have to listen on port 843 and return
     * the flashpolicy
     *
     * @param \React\EventLoop\StreamSelectLoop $loop
     *
     * @return void
     */
    protected function allowFlash($loop) {
        // Allow Flash sockets (Internet Explorer) to connect to our app
        $flashSock = new \React\Socket\Server($loop);
        $flashSock->listen(config('wamp.flashPort'), '0.0.0.0');
        $policy = new \Ratchet\Server\FlashPolicy;
        $policy->addAllowedAccess('*', $this->option('port'));
        $webServer = new \Ratchet\Server\IoServer($policy, $flashSock);

        $this->info('Flash connection allowed');
    }

    /**
     * Enable the option to push messages from
     * the Server to the client
     *
     * @param \React\EventLoop\StreamSelectLoop $loop
     *
     * @return void
     */
    protected function enablePush($loop) {
        // Listen for the web server to make a ZeroMQ push after an ajax request
        $context = new \React\ZMQ\Context($loop);
        $pull = $context->getSocket(\ZMQ::SOCKET_PULL, config('wamp.zmqPullId', sprintf('wamp.pull.%s', \App::environment())));
        $pull->bind('tcp://127.0.0.1:' . config('wamp.zmqPort')); // Binding to 127.0.0.1 means the only client that can connect is itself
        $pull->on('message', [$this->wamp, 'serverPublish']);

        $this->info('Push enabled');
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions() {
        return [
                [
                'port',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The Port on which we listen for new connections',
                config('wamp.socketPort')
            ],
        ];
    }

}
