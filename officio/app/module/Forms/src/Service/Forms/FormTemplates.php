<?php

namespace Forms\Service\Forms;

use Forms\Service\Forms;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class FormTemplates extends BaseService implements SubServiceInterface
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
     * Load forms count by their version ids
     *
     * @param array $arrFormVersionIds
     * @return string
     */
    public function getFormsCount($arrFormVersionIds)
    {
        $count = 0;

        if (is_array($arrFormVersionIds) && count($arrFormVersionIds)) {
            // Check if some forms are used in 'templates' table
            $select = (new Select())
                ->from('form_templates')
                ->columns(['forms_count' => new Expression('COUNT(*)')]);

            foreach ($arrFormVersionIds as $versionId) {
                $versionId = (int)$versionId;

                $select->where([
                    (new Where())->expression('`body` REGEXP ?', "^(.*)&lt;%form$versionId(.*)$")
                ]);
            }

            $count = $this->_db2->fetchOne($select);
        }

        return $count;
    }

    /**
     * Get forms count in specific folder
     *
     * @param int $folderId
     * @return string
     */
    public function getFormsCountInFolder($folderId)
    {
        $select = (new Select())
            ->from('form_templates')
            ->columns(['forms_count' => new Expression('COUNT(template_id)')])
            ->where(['folder_id' => (int)$folderId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load list of folders in specific folder
     *
     * @param int $folderId
     * @return array
     */
    public function getFormsInFolder($folderId)
    {
        $select = (new Select())
            ->from('form_templates')
            ->where(['folder_id' => (int)$folderId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Create template record
     *
     * @param int $folderId
     * @param string $name
     * @param string $body
     * @return string generated template it
     */
    public function createTemplate($folderId, $name, $body)
    {
        // Save template
        return $this->_db2->insert(
            'form_templates',
            [
                'folder_id' => (int)$folderId,
                'name'      => $name,
                'body'      => $body
            ]
        );
    }

    /**
     * Load template info by its id
     *
     * @param int $templateId
     * @return array
     */
    public function getTemplateById($templateId)
    {
        $select = (new Select())
            ->from('form_templates')
            ->where(['template_id' => (int)$templateId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load list of templates saved in DB
     * @return array
     */
    public function fetchAllRecords()
    {
        $select = (new Select())
            ->from('form_templates');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of templates which have specific form in it
     *
     * @param int $formId
     * @return array
     */
    public function getTemplatesByFormId($formId)
    {
        $select = (new Select())
            ->from('form_templates')
            ->where([(new Where())->like('body', "%&lt;%form" . $formId . " - %")]);

        return $this->_db2->fetchAll($select);
    }

}
