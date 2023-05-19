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
class FormFolder extends BaseService implements SubServiceInterface
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
     * Load folder info for specific parent
     *
     * @param int $parentId
     * @return array
     */
    public function getFolderInfoByParent($parentId)
    {
        $select = (new Select())
            ->from(['f' => 'form_folder'])
            ->where(['parent_id' => (int)$parentId]);

        return $this->_db2->fetchAll($select);
    }
}
