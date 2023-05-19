<?php

namespace Officio\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class MessageBox extends AbstractHelper
{

    public function __invoke($message, $msgType)
    {
        switch ((int)$msgType) {
            // Warning
            case 2:
                $divClass  = 'ui-state-highlight';
                $msgClass  = 'ui-msg-warning';
                break;
            
            // Error
            case 1:
                $divClass  = 'ui-state-error';
                $msgClass  = 'ui-msg-error';
                break;
            

            // Info
            case 0:
            default:
                $divClass  = 'ui-state-highlight';
                $msgClass  = 'ui-msg-info';
                break;
        }

        return '<div class="ui-widget">
                          <div class="ui-corner-all '.$divClass.'" style="padding: 0 .7em; margin: 28px auto; min-width: 350px; width: max-content">
                                <div style="padding: 5px;">
                                    <span class="'.$msgClass.'">'.$message.'</span>
                                </div>
                          </div>
                      </div>';
    }

}
