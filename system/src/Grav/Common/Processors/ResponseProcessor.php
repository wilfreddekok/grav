<?php
namespace Grav\Common\Processors;

use Grav\Common\Debugger;
use Grav\Common\Response;

class ResponseProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '!response';
    public $title = 'Response';

    public function process() {
        /** @var Response $response */
        $response = new Response();

        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];

        $response->setStandardHeaders();
        $response->setBody($this->container->output);
        $response->appendToBody($debugger->render());

//        $output = $response->getProcessedBody();
        $output = $response->getBody();
        echo $output;

        $this->container->fireEvent('onOutputRendered');
    }

}
