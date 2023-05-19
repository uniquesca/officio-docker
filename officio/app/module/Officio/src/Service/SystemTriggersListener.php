<?php

namespace Officio\Service;


/**
 * Service has to implement this interface, so it's initialized at the bootstrap which would guarantee, that
 * it'll
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
interface SystemTriggersListener
{

    /**
     * @return array List of services listening for SystemTriggers
     */
    public function getSystemTriggerListeners();

}
