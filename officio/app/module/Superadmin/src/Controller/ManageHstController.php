<?php

namespace Superadmin\Controller;

use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\GstHst;

/**
 * Manage GST/HST Settings Controller
 *
 * @author    Uniques Software Corp.
 */

class ManageHstController extends BaseController
{
    /** @var  GstHst */
    protected $_gstHst;

    public function initAdditionalServices(array $services)
    {
        $this->_gstHst = $services[GstHst::class];
    }

    public function indexAction() { }
    
    public function getProvincesAction() {
        $view = new JsonModel();
        $booOfficio = Json::decode($this->findParam('booOfficio'), Json::TYPE_ARRAY);
        
        $arrResult = array();
        
        $arrProvinces = $this->_gstHst->getProvincesList($booOfficio);
        foreach ($arrProvinces as $arrProvinceInfo) {
            $arrResult[] = array(
               'province_id'    => $arrProvinceInfo['province_id'],
               'province_label' => $arrProvinceInfo['province'],
               'tax_rate'       => $arrProvinceInfo['rate'],
               'tax_label'      => $arrProvinceInfo['tax_label'],
               'tax_type'       => $arrProvinceInfo['tax_type'],
               'is_system'      => $arrProvinceInfo['is_system'] == 'Y' ? 'Yes' : 'No',
            );
        }

        return $view->setVariables($arrResult);
    }
    
    
    public function updateProvinceAction() {
        $view       = new JsonModel();
        $strMessage = '';

        $booOfficio  = (bool)Json::decode($this->findParam('booOfficio'), Json::TYPE_ARRAY);
        $arrProvince = Json::decode($this->findParam('arrProvince'), Json::TYPE_ARRAY);
        
        
        if(empty($strMessage) && (empty($arrProvince) || !is_array($arrProvince))) {
            $strMessage = 'Incorrect data';
        }
        
        if(empty($strMessage) && empty($arrProvince['province_label'])) {
            $strMessage = 'Incorrect province label';
        }
        
        if(empty($strMessage) && empty($arrProvince['tax_rate'])) {
            $strMessage = 'Incorrect tax rate';
        }
        
        if(empty($strMessage) && empty($arrProvince['tax_label'])) {
            $strMessage = 'Incorrect tax name';
        }

        if (empty($strMessage) && !in_array($arrProvince['tax_type'], array('exempt', 'included', 'excluded'))) {
            $strMessage = 'Incorrectly selected tax type';
        }

        // If all is okay - save data
        if(empty($strMessage)) {
            $filter = new StripTags();

            $arrUpdate[$arrProvince['province_id']] = array(
                'province'  => $filter->filter($arrProvince['province_label']),
                'rate'      => (double)$arrProvince['tax_rate'],
                'tax_label' => $filter->filter($arrProvince['tax_label']),
                'tax_type'  => $arrProvince['tax_type']
            );
            
            $booResult = $this->_gstHst->saveProvinces($arrUpdate, $booOfficio);
            $strMessage = $booResult ? '' : $this->_tr->translate('Internal error occurred. Please contact to web site administrator.'); 
        }
        
        // Return result in json format
        $arrResult = array(
            'success' => empty($strMessage), 
            'message' => $strMessage 
        );

        return $view->setVariables($arrResult);
    }
}