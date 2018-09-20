<?php
namespace Santran\WAMPServer;

use Exception;
use Ratchet\Wamp\WampConnection;
use Santran\WAMPServer\BaseConnection;

class Connection extends BaseConnection
{

    public function open(WampConnection $connection)
    {
        printf("A connection Open\n");
    }


    public function close(WampConnection $connection)
    {
        printf("A connection close\n");
    }


    public function error(WampConnection $connection, Exception $exception)
    {
        if (str_is('*Tried to write to closed stream*', $exception->getMessage())) {
            /**
             * @var \Santran\WAMPServer\WAMPServer $wamp
             */
            $wamp = app('wamp');
            $wamp->pusher->removeSubscriber($connection);
        }

        /**
         * Close the connection if connection should be closed after exception
         */
        //$connection->close();

        if ($exception->getMessage()) {
            throw new Exception($exception);
        }
    }

}
