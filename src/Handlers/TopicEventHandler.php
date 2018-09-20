<?php

namespace Santran\WAMPServer\Handlers;

use Symfony\Component\Routing\Route as BaseRoute;

class TopicEventHandler extends BaseRoute implements HandlerInterface {

    /**
     * different Parameters set by ratchet e.g connection, topic
     *
     * @var array
     */
    protected $wsParameters;

    /**
     * different Parameters from the actual request
     *
     * @var array
     */
    protected $requestParameters;

    /**
     * final parameters which will be passed
     * to the controller
     *
     * @var array
     */
    protected $mergedParameters;

    /**
     * Execute the handler
     *
     * @param string $event
     *
     * @return void
     */
    public function run($event) {
        $this->callController($event);
    }

    /**
     * Call the registered controller with the right event
     *
     * @param $event
     *
     * @return mixed
     */
    protected function callController($event) {
        $parameters = $this->getMergedParameters();

        return call_user_func_array([$this->requestParameters['_controller'], $event], $parameters);
    }

    /**
     * get the final parameters for the actual
     * call to the controller
     *
     * @return array
     */
    protected function getMergedParameters() {
        $variables = $this->compile()->getVariables();

        // To get the parameter array, we need to spin the names of the variables on
        // the compiled route and match them to the parameters that we got when a
        // route is matched by the router, as routes instances don't have them.
        $parameters = [];

        foreach ($variables as $variable) {
            $parameters[$variable] = $this->requestParameters[$variable];
        }

        return $this->mergedParameters = array_merge($this->wsParameters, $parameters);
    }

    /**
     * Set the Ratchet variables
     *
     * @param  array $parameters
     *
     * @return void
     */
    public function setWsParameters($parameters) {
        $this->wsParameters = $parameters;
    }

    /**
     * Set the matching request parameters array on the handler
     *
     * @param  array $parameters
     *
     * @return void
     */
    public function setRequestParameters($parameters) {
        $this->requestParameters = $parameters;
    }

}
