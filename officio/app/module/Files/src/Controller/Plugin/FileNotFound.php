<?php

namespace Files\Controller\Plugin;

use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class FileNotFound extends AbstractPlugin
{
    /**
     * Sets response to indicate that a file was not found
     */
    public function __invoke()
    {
        /** @var Response $response */
        $response = $this->controller->getResponse();
        $response->setStatusCode(404);
        $response->setContent('File not found');

        return $response;
    }

}