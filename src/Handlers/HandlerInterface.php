<?php

namespace Santran\WAMPServer\Handlers;

interface HandlerInterface {

    public function run($event);

    public function setWsParameters($variables);
}
