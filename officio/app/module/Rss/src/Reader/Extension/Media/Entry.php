<?php

namespace Rss\Reader\Extension\Media;

use Laminas\Feed\Reader\Extension\AbstractEntry;

class Entry extends AbstractEntry
{
    public function getMediaContentImage()
    {
        if (isset($this->data['mediaContentImage'])) {
            return $this->data['mediaContentImage'];
        }

        $mediaContentImage = $this->xpath->evaluate(
            'string(' . $this->getXpathPrefix() . "/media:content[@medium='image']/@url)"
        );

        if (! $mediaContentImage) {
            $mediaContentImage = null;
        }

        $this->data['mediaContentImage'] = $mediaContentImage;
        return $this->data['mediaContentImage'];
    }

    /**
     * @inheritDoc
     */
    protected function registerNamespaces()
    {
        $this->xpath->registerNamespace(
            'media',
            'https://www.rssboard.org/media-rss'
        );
    }
}