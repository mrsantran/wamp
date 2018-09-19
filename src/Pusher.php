<?php

namespace Santran\WAMP;

use Santran\WAMP\RatchetWsServer;

/**
 * Echo Server Example.
 */
class Pusher extends RatchetWsServer
{
    public function onEntry($entry)
    {
        $this->sendAll($entry[1]);
    }
}
