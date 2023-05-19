<?php

namespace Rss\Reader\Extension\LMS;

use Laminas\Feed\Reader\Extension\AbstractEntry;

class Entry extends AbstractEntry
{
    public function getLmsCpdHours()
    {
        if (isset($this->data['lmsCpdHours'])) {
            return $this->data['lmsCpdHours'];
        }

        $lmsCpdHours = $this->xpath->evaluate(
            'string(' . $this->getXpathPrefix() . "/lms:cpdHours)"
        );

        if (! $lmsCpdHours) {
            $lmsCpdHours = null;
        }

        $this->data['lmsCpdHours'] = $lmsCpdHours;
        return $this->data['lmsCpdHours'];
    }

    /**
     * @inheritDoc
     */
    protected function registerNamespaces()
    {
        $this->xpath->registerNamespace(
            'lms',
            'https://learn.officio.ca/'
        );
    }
}