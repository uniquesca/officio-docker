<?php

namespace Files\Model;

use Uniques\Php\StdLib\FileTools;

class FileInfo
{

    public $name;
    public $path;
    public $mime;
    public $local;
    public $content;

    public function __construct($name, $path, $local, $mime = '', $content = '')
    {
        $this->name = $name;
        $this->path = $path;
        $this->local = $local;
        $this->mime = empty($mime) ? FileTools::getMimeByFileName($name) : $mime;
        $this->content = $content;
    }

}