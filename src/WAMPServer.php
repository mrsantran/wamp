<?php

namespace Santran\WAMPServer;

use Illuminate\Container\Container;
use Ratchet\ConnectionInterface as Conn;
use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampServerInterface;
use Santran\WAMPServer\Exceptions\ChannelNotFoundException;
use Santran\WAMPServer\Exceptions\WAMPServerException;
use Santran\WAMPServer\Handlers\ConnectionEventHandler;
use Santran\WAMPServer\Handlers\TopicEventHandler;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class WAMPServer implements WampServerInterface {

    /**
     * The route collection instance.
     * We're using the Symfony component to use custom parameters
     * in a topicname
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected $topicEventHandlers;

    /**
     * Instance of the ConnectionEventHandler.
     * We'll use it for the Ratchet open, close and error events
     * @var ConnectionEventHandler instance
     */
    protected $connectionEventHandler;

    /**
     * Instance of WAMPServerPublish to publish messages from the server
     *
     * @var \Santran\WAMPServer\WAMPServerPublish instance
     */
    public $pusher;

    /**
     * Value from the config file
     *
     * @var bool $enablePush
     */
    protected $enablePush;

    /**
     * ZMQSocket instance
     *
     * @var ZMQSocket $socket
     */
    protected $socket;

    /**
     * The inversion of control container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $container;

    public function __construct(Container $container = null) {
        $this->container = $container;

        $this->topicEventHandlers = new RouteCollection;

        $this->enablePush = config('wamp.enablePush');

        if ($this->enablePush) {
            //this is a optional dependency
            //if we enabled push in the config file make
            //shure reaxt/zmq ist loaded
//            if (!class_exists('\React\ZMQ\Context')) {
//                throw new WAMPServerException("react/zmq dependency is required if push is enabled");
//            }

            $this->pusher = new WAMPServerPusher;
        }
    }

    /**
     * Create and add a new handler to the
     * RouteCollection a.k.a topicEventHandlers
     *
     * @param string $pattern
     * @param string $controller
     *
     * @return void
     */
    public function topic($pattern, $controller) {
        if (is_subclass_of($controller, '\Santran\WAMPServer\BaseTopic')) {
            $topicEventHandler = new TopicEventHandler($pattern, ['_controller' => $this->getCallback($controller)]);
            $this->topicEventHandlers->add($pattern, $topicEventHandler);
        } else {
            throw new WAMPServerException($controller . " has to extend BaseTopic");
        }
    }

    /**
     * Create a new connection handler instance
     *
     * @param string $controller
     *
     * @return void
     */
    public function connection($controller) {
        if (is_subclass_of($controller, '\Santran\WAMPServer\BaseConnection')) {
            $this->connectionEventHandler = new ConnectionEventHandler($this->getCallback($controller));
        } else {
            throw new WAMPServerException($controller . " has to extend BaseConnection");
        }
    }

    /**
     * Push a message to a client
     * This function get's fired e.g after a ajax request and not
     * after a websocket request. Because of that we don't have access
     * to all the connections and there for have to connect to the
     * wamp/ratchet server
     *
     * @param string $channel
     * @param array  $message
     *
     * @return void
     */
    public function publish($channel, $message) {
        if (!$this->enablePush) {
            //throw new WAMPServerException("Publish not allowed.");
        }

        $message = array_merge(['topic' => $channel], $message);
        $this->getSocket()->send(json_encode($message));
    }

    /**
     * get zmqSocket to push messages
     *
     * @return ZMQSocket instance
     */
    protected function getSocket() {
        //we don't have to connect the socket
        //for every new message sent
        if (isset($this->socket)) {
            return $this->socket;
        } else {
            return $this->connectZmq();
        }
    }

    /**
     * Connect to socket
     *
     * @return ZMQSocket instance
     */
    protected function connectZmq() {
        $context = new \ZMQContext();
        $this->socket = $context->getSocket(\ZMQ::SOCKET_PUSH, config('wamp.zmqPushId', sprintf('wamp.push.%s', \App::environment())));
        $this->socket->connect("tcp://localhost:" . config('wamp.zmqPort'));

        return $this->socket;
    }

    /**
     * Redirect serverPublish to WAMPServerPusher
     *
     * @param string $message
     *
     * @return void
     */
    public function serverPublish($message) {
        //echo "{$message}    \n";
        $this->pusher->serverPublish($message);
    }

    /**
     * Dispatch the 'request'
     *
     * @param string $event
     * @param array  $variables
     *
     * @return void
     */
    protected function dispatch($event, $variables = []) {
        $eventHandler = $this->findEventHandler($variables);
        $eventHandler->run($event);
    }

    /**
     * Create instance of the given Controller
     *
     * @param  string $controller
     *
     * @return Object
     */
    protected function getCallback($controller) {
        //TODO: check if instance of BaseChannel
        $ioc = $this->container;
        $instance = $ioc->make($controller);

        return $instance;
    }

    /**
     * Find a eventHandler and set all the necessary parameters.
     * This can either be a topic or a connection eventhandler
     *
     * @param array $variables
     *
     * @return mixed (EventInterface instance)
     */
    protected function findEventHandler($variables) {
        if (array_key_exists('topic', $variables)) {
            $eventHandler = $this->findTopicEvent($variables);

            //throw error if no topicHandler is defined
            if (!$eventHandler instanceof TopicEventHandler) {
                throw new WAMPServerException("No TopicHandler defined");
            }
        } else {
            $eventHandler = $this->connectionEventHandler;

            //throw error if no connectionHandler is defined
            if (!$eventHandler instanceof ConnectionEventHandler) {
                throw new WAMPServerException("No ConnectionHandler defined");
            }

            $eventHandler->setWsParameters($variables);
        }

        return $eventHandler;
    }

    /**
     * Find and return a TopicEvent in our Symfony RouteCollection
     *
     * @param array $variables
     *
     * @return \Santran\WAMPServer\TopicEventHandler
     */
    protected function findTopicEvent($variables) {
        $topicName = $this->getTopicName($variables['topic']);

        try {
            $parameters = $this->getUrlMatcher($topicName)->match('/' . $topicName);
        } catch (ExceptionInterface $e) {
            if ($e instanceof ResourceNotFoundException) {
                throw new ChannelNotFoundException("Requested Channel not found, {$variables['topic']}");
            }
        }

        $eventHandler = $this->topicEventHandlers->get($parameters['_route']);

        $eventHandler->setWsParameters($variables);
        $eventHandler->setRequestParameters($parameters);

        return $eventHandler;
    }

    /**
     * Get the name of a topic/channel
     * just for convenience
     *
     * @param \Ratchet\Wamp\Topic $topic
     *
     * @return string
     */
    protected function getTopicName(Topic $topic) {
        return $topic->getId();
    }

    /**
     * Create a new URL matcher instance.
     *
     * @param string $topic
     *
     * @return \Symfony\Component\Routing\Matcher\UrlMatcher
     */
    protected function getUrlMatcher($topicName) {
        $context = new RequestContext($topicName);

        return new UrlMatcher($this->topicEventHandlers, $context);
    }

    //possible actions
    //array('subscribe', 'publish', 'call', 'unsubscribe', 'open', 'close', 'error')
    public function onSubscribe(Conn $connection, $topic) {
        if ($this->enablePush) {
            //register subscribe in case we want to pusher something serverside
            $this->pusher->addSubscriber($connection, $topic);
        }

        $this->dispatch('subscribe', compact('connection', 'topic'));
    }

    public function onPublish(Conn $connection, $topic, $message, array $exclude, array $eligible) {
        $this->dispatch('publish', compact('connection', 'topic', 'message', 'exclude', 'eligible'));
    }

    public function onCall(Conn $connection, $id, $topic, array $params) {
        $this->dispatch('call', compact('connection', 'id', 'topic', 'params'));
    }

    public function onUnSubscribe(Conn $connection, $topic) {
        $this->dispatch('unSubscribe', compact('connection', 'topic'));
    }

    public function onOpen(Conn $connection) {
        $this->dispatch('open', compact('connection'));
    }

    public function onClose(Conn $connection) {
        if ($this->enablePush) {
            // After connection is closed, the subscriber topic must be reset
            $this->pusher->removeSubscriber($connection);
        }

        $this->dispatch('close', compact('connection'));
    }

    public function onError(Conn $connection, \Exception $exception) {
        $this->dispatch('error', compact('connection', 'exception'));
    }

}
