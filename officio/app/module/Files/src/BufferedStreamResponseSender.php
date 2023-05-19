<?php

namespace Files;

use Laminas\Mvc\ResponseSender\AbstractResponseSender;
use Laminas\Mvc\ResponseSender\SendResponseEvent;

class BufferedStreamResponseSender extends AbstractResponseSender
{

    public function __invoke(SendResponseEvent $event)
    {
        $response = $event->getResponse();
        if (!$response instanceof BufferedStream) {
            return $this;
        }

        $this->sendHeaders($event);
        $this->sendStream($event);
        $event->stopPropagation();
        return $this;
    }

    public function sendStream(SendResponseEvent $event)
    {
        if ($event->contentSent()) {
            return $this;
        }
        ob_end_flush();
        $event->setContentSent();
        return $this;
    }

}
