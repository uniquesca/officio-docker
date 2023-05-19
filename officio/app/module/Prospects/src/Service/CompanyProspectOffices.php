<?php
namespace Prospects\Service;

use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyProspectOffices extends BaseService implements SubServiceInterface
{

    /** @var CompanyProspects */
    private $_parent;

    public function setParent($parent) {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Load assigned offices to specific prospect
     *
     * @param int $prospectId
     * @return array
     */
    public function getProspectOffices($prospectId)
    {
        $select = (new Select())
            ->from('company_prospects_divisions')
            ->columns(['office_id'])
            ->where(['prospect_id' => (int)$prospectId]);

        return $this->_db2->fetchCol($select);
    }


    public function updateProspectOffices($prospectId, $arrOffices)
    {
        $this->_db2->delete('company_prospects_divisions', ['prospect_id' => (int)$prospectId]);

        if (is_array($arrOffices) && count($arrOffices)) {
            foreach ($arrOffices as $officeId) {
                $this->_db2->insert(
                    'company_prospects_divisions',
                    [
                        'prospect_id' => $prospectId,
                        'office_id'   => $officeId,
                    ]
                );
            }
        }
    }
}
