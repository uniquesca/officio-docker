<?php

/**
 * PDF sync fields
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */

namespace Forms\Service\Forms;

use Forms\Service\Forms;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

class FormSynField extends BaseService implements SubServiceInterface
{

    /** @var Forms */
    protected $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Load list of saved fields, sort them by name
     *
     * @param bool $booAsKeyVal
     * @param bool $booReverse
     * @return array
     */
    public function fetchFormFields($booAsKeyVal = false, $booReverse = false)
    {
        $cacheId    = 'pdf_form_synfield';
        $cacheTagId = 'tagPdfSync';
        if (!($arrResult = $this->_cache->getItem($cacheId))) {
            // Not in cache
            $select = (new Select())
                ->from(array('FS' => 'form_syn_field'))
                ->columns(array('syn_field_id', 'field_name'))
                ->order(array('FS.syn_field_id ASC'));

            $arrResult = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $arrResult);
            if ($this->_cache instanceof TaggableInterface) {
                $this->_cache->setTags($cacheId, array($cacheTagId));
            }
        }

        $arrReturnResult = array();
        if ($booAsKeyVal && is_array($arrResult)) {
            foreach ($arrResult as $fieldInfo) {
                if ($booReverse) {
                    $arrReturnResult[$fieldInfo['field_name']] = $fieldInfo['syn_field_id'];
                } else {
                    $arrReturnResult[$fieldInfo['syn_field_id']] = $fieldInfo['field_name'];
                }
            }
        } else {
            $arrReturnResult = $arrResult;
        }

        return $arrReturnResult;
    }

    /**
     * Load list of pdf field ids saved in DB
     *
     * @return array
     */
    public function fetchSynFieldsIds()
    {
        $select = (new Select())
            ->from('form_syn_field')
            ->columns(['syn_field_id']);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get list of field names already saved in DB
     *
     * @param array $arrFieldNames
     * @return array
     */
    public function getAlreadySavedFields($arrFieldNames)
    {
        $arrResult = array();

        if (is_array($arrFieldNames) && count($arrFieldNames)) {
            $select = (new Select())
                ->from('form_syn_field')
                ->columns(['field_name'])
                ->where(['field_name' => $arrFieldNames]);

            $arrResult = $this->_db2->fetchCol($select);
        }

        return $arrResult;
    }

    /**
     * Get field ids by their names
     *
     * @param array $arrFieldNames
     * @return array
     */
    public function getFieldIdsByNames($arrFieldNames)
    {
        $arrResult = array();

        if (is_array($arrFieldNames) && count($arrFieldNames)) {
            $select = (new Select())
                ->from('form_syn_field')
                ->columns(['syn_field_id'])
                ->where(['field_name' => $arrFieldNames]);

            $arrResult = $this->_db2->fetchCol($select);
        }

        return $arrResult;
    }
}
