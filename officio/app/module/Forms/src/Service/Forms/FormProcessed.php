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
class FormProcessed extends BaseService implements SubServiceInterface
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
     * Load content for specific template
     *
     * @param int $templateId
     * @param string $version
     * @return string
     */
    public function getContent($templateId, $version)
    {
        $select = (new Select())
            ->from('form_processed')
            ->columns(['content'])
            ->where([
                'template_id' => (int)$templateId,
                'version'     => $version
            ]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load list of templates for specific folder with specific version
     *
     * @param int $folderId
     * @param string $version
     * @return array
     */
    public function getListByFolderAndVersion($folderId, $version)
    {
        $select = (new Select())
            ->from(array('p' => 'form_processed'))
            ->join(array('t' => 'form_templates'), 't.template_id = p.template_id', 'name')
            ->where([
                't.folder_id' => (int)$folderId,
                'p.version'   => $version
            ]);

        return $this->_db2->fetchAll($select);
    }

}
