<?php

namespace Officio;

use Officio\Common\Json;

class AssetCopier
{

    private $_from;
    private $_to;

    public function __construct($from, $to)
    {
        $this->_from = $from;
        $this->_to   = $to;
    }

    private function getFolderItemsRecursively($path, $relativePath = '')
    {
        $contents = scandir($path);
        $items    = [];
        foreach ($contents as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($item)) {
                $items = array_merge($items, $this->getFolderItemsRecursively($path . '/' . $item, $relativePath . '/' . $item));
            } else {
                $itemFullPath = realpath($path . '/' . $item);
                $items[]      = [
                    'filename' => $relativePath . '/' . $item,
                    'size'     => filesize($itemFullPath)
                ];
            }
        }
        return $items;
    }

    private function folderHash($path)
    {
        $items = $this->getFolderItemsRecursively($path);
        return md5(Json::encode($items));
    }

    private function recursiveCopy($from, $to)
    {
        $items = scandir($from);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($from . '/' . $item)) {
                mkdir($to . '/' . $item);
                $this->recursiveCopy($from . '/' . $item, $to . '/' . $item);
            } else {
                $fromPath = $from . '/' . $item;
                $toPath   = $to . '/' . $item;
                copy($fromPath, $toPath);
            }
        }
    }

    public function copy()
    {
        if (is_dir($this->_to)) {
            $destinationHash = $this->folderHash($this->_to);
            $sourceHash      = $this->folderHash($this->_from);
            if ($destinationHash == $sourceHash) {
                return;
            }

            if (stripos(PHP_OS, 'WIN') === 0) {
                exec(sprintf("rd /s /q %s", escapeshellarg($this->_to)));
            } else {
                exec(sprintf("rm -rf %s", escapeshellarg($this->_to)));
            }
        }
        mkdir($this->_to);

        $this->recursiveCopy($this->_from, $this->_to);
    }

}