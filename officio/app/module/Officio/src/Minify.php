<?php

namespace Officio;
use Exception;
use Minify as MinifyParent;
use Minify_CacheInterface;
use Minify_ControllerInterface;
use Psr\Log\LoggerInterface;


class Minify extends MinifyParent {

    /**
     * Any Minify_Cache_* object or null (i.e. no server cache is used)
     *
     * @var Minify_CacheInterface
     */
    private $cache;

    public function __construct(Minify_CacheInterface $cache, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Caches minified sources and provides cache ID
     * @param Minify_ControllerInterface $controller
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function cache(Minify_ControllerInterface $controller, $options = array()) {
        $this->env = $controller->getEnv();

        $options = array_merge($this->getDefaultOptions(), $options);

        $config = $controller->createConfiguration($options);

        $this->sources = $config->getSources();
        $this->selectionId = $config->getSelectionId();
        $this->options = $this->analyzeSources($config->getOptions());

        if (!$this->sources) {
            return array(
                'result' => false,
                'cacheId' => null,
                'recached' => null
            );
        }

        $this->controller = $controller;

        if ($this->options['contentType'] === self::TYPE_CSS && $this->options['rewriteCssUris']) {
            $this->setupUriRewrites();
        }

        if ($this->options['concatOnly']) {
            $this->options['minifiers'][self::TYPE_JS] = false;
            foreach ($this->sources as $source) {
                if ($this->options['contentType'] === self::TYPE_JS) {
                    $source->setMinifier('Minify::nullMinifier');
                } elseif ($this->options['contentType'] === self::TYPE_CSS) {
                    $source->setMinifier(array('Minify_CSSmin', 'minify'));
                    $sourceOpts = $source->getMinifierOptions();
                    $sourceOpts['compress'] = false;
                    $source->setMinifierOptions($sourceOpts);
                }
            }
        }

        // using cache
        // the goal is to use only the cache methods to sniff the length and
        // output the content, as they do not require ever loading the file into
        // memory.
        $cacheId = $this->_getCacheId();
        if (isset($this->options['fileExtension'])) {
            $cacheId .= '.' . $this->options['fileExtension'];
        }
        $fullCacheId = ($this->options['encodeMethod']) ? $cacheId . '.gz' : $cacheId;

        // check cache for valid entry
        $recached = false;
        $cacheIsReady = $this->cache->isValid($fullCacheId, $this->options['lastModifiedTime']);
        if (!$cacheIsReady) {
            // generate & cache content
            try {
                $content = $this->combineMinify();
            } catch (Exception $e) {
                $this->logger && $this->logger->critical($e->getMessage());
                if (! $this->options['quiet']) {
                    $this->errorExit($this->options['errorHeader'], self::URL_DEBUG);
                }
                throw $e;
            }

            $cacheIsReady = $this->cache->store($cacheId, $content);
            if (!$cacheIsReady) {
                throw new Exception('Unable to store cache.');
            }

            if (function_exists('gzencode') && $this->options['encodeMethod']) {
                $cacheIsReady = $this->cache->store($cacheId . '.gz', gzencode($content, $this->options['encodeLevel']));
                if (!$cacheIsReady) {
                    throw new Exception('Unable to store encoded cache.');
                }
            }

            $recached = true;
        }

        if (!$cacheIsReady) {
            throw new Exception('Unable to cache minifed content, cache id is' . $cacheId);
        }

        return array(
            'result' => true,
            'cacheId' => $fullCacheId,
            'recached' => $recached
        );
    }


}
