<?php

namespace Forms\Service\Forms;

use Forms\Service\Forms;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class FormLanding extends BaseService implements SubServiceInterface
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
     * Load list of form landing for specific parent
     * @param $parentId
     * @return array
     */
    public function getLandingByParentId($parentId)
    {
        $select = (new Select())
            ->from('form_landing')
            ->where(['parent_id' => (int)$parentId])
            ->order('folder_name ASC');

        return $this->_db2->fetchAll($select);
    }

}
