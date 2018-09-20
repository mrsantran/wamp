<?php
namespace Santran\WAMPServer\Topics;

use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampConnection;
use Santran\WAMPServer\BaseTopic;

class AuthTopic extends BaseTopic
{

    public function subscribe(WampConnection $connection, Topic $topic)
    {
        //printf("Client subscribed a topic\n");
    }


    public function publish(WampConnection $connection, Topic $topic, $message, array $exclude, array $eligible)
    {
        //printf("Client publish a message to server\n");
    }


    public function call(WampConnection $connection, $id, Topic $topic, array $params)
    {
        if (str_is('*authreq', $topic->getId())) {
            $clientId              = $params[0];
            $publicKey             = md5(mt_rand() . $clientId);
            $connection->authUid   = $clientId;
            $connection->publicKey = $publicKey;

            /**
             * WAMP protocol compatibility
             */
            if (isset( $params[1] ) && $params[1] == 'string') {
                $ret = $publicKey;
            } else {
                $ret = [ $publicKey ];
            }

            $connection->callResult($id, $ret);

        } elseif (str_is('*auth', $topic->getId())) {
            $password      = 'Here is user or sometimes password';
            $signature     = $params[0];
            $signatureReal = base64_encode(hash_hmac('sha256', $connection->publicKey, $password, true));

            if (true/*always true*/) {
                $connection->callResult($id, 'auth success');
            } elseif ($signature == $signatureReal) {
                $connection->callResult($id, 'auth success');
            } else {
                $connection->callResult($id, 'auth fail');
                $connection->close();
            }
        }
    }


    public function unSubscribe(WampConnection $connection, Topic $topic)
    {
        //printf("Client unSubscribe a topic\n");
    }

}