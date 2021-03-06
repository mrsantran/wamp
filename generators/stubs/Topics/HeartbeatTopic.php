<?php
namespace Santran\WAMPServer\Topics;

use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampConnection;
use Santran\WAMPServer\BaseTopic;

class HeartbeatTopic extends BaseTopic
{

    public function subscribe(WampConnection $connection, Topic $topic)
    {
        printf("Client subscribed a topic\n");
    }


    public function publish(WampConnection $connection, Topic $topic, $message, array $exclude, array $eligible)
    {
        printf("Client publish a message to server\n");
    }


    public function call(WampConnection $connection, $id, Topic $topic, array $params)
    {
        /**
         * Active Time for heartbeat ping-pong detecting.
         */
        $connection->activeTime = time();
    }


    public function unSubscribe(WampConnection $connection, Topic $topic)
    {
        printf("Client unSubscribe a topic\n");
    }

}