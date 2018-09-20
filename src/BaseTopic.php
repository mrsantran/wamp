<?php

namespace Santran\WAMPServer;

use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampConnection;

abstract class BaseTopic {

    abstract function subscribe(WampConnection $connection, Topic $topic);

    abstract function publish(WampConnection $connection, Topic $topic, $message, array $exclude, array $eligible);

    abstract function call(WampConnection $connection, $id, Topic $topic, array $params);

    abstract function unSubscribe(WampConnection $connection, Topic $topic);

    /**
     * Broadcast message to clients
     *
     * @param \Ratchet\Wamp\Topic $topic
     * @param mixed               $msg
     * @param array               $exclude
     * @param array               $eligible
     *
     * @return void
     */
    protected function broadcast(Topic $topic, $msg, $exclude = [], $eligible = []) {
        if (count($exclude) > 0) {
            $this->broadcastExclude($topic, $msg, $exclude);
        } elseif (count($eligible) > 0) {
            $this->broadcastEligible($topic, $msg, $eligible);
        } else {
            $topic->broadcast($msg);
        }
    }

    /**
     * Broadcast message only to clients which
     * are not in the exclude array (blacklist)
     *
     * @param \Ratchet\Wamp\Topic $topic
     * @param mixed               $msg
     * @param array               $exclude
     *
     * @return void
     */
    protected function broadcastExclude(Topic $topic, $msg, $exclude) {
        foreach ($topic->getIterator() as $client) {
            if (!in_array($client->WAMP->sessionId, $exclude)) {
                $client->event($topic, $msg);
            }
        }
    }

    /**
     * Broadcast message only to clients which
     * are in the eligible array (whitelist)
     *
     * @param \Ratchet\Wamp\Topic $topic
     * @param mixed               $msg
     * @param array               $eligible
     *
     * @return void
     */
    protected function broadcastEligible(Topic $topic, $msg, $eligible) {
        foreach ($topic->getIterator() as $client) {
            if (in_array($client->WAMP->sessionId, $eligible)) {
                $client->event($topic, $msg);
            }
        }
    }

}
