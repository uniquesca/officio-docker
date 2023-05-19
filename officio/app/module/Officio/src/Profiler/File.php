<?php

namespace Officio\Profiler;

use Laminas\Db\Adapter\Profiler\Profiler;
use Officio\Common\Service\Log;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class File extends Profiler
{

    /** @var Log */
    private $_log;

    /**
     * Constructor
     */
    public function __construct(Log $log)
    {
        $this->_log = $log;
    }


    /**
     * Intercept the query end and log the profiling data.
     */
    public function profilerFinish()
    {
        parent::profilerFinish();
        $this->_log->debugSql($this->getLastProfile());
    }

}