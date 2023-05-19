<?php

namespace Clients\Service\Clients;

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Clients\Service\Clients;
use Officio\Common\Service\Settings;
use Officio\Service\Company;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;


/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CaseVACs extends BaseService implements SubServiceInterface
{
    /** @var Clients */
    protected $_parent;

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Load VAC records list for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getList($companyId)
    {
        $select = (new Select())
            ->from(array('v' => 'client_vac'))
            ->where(['v.company_id' => (int)$companyId])
            ->order('v.client_vac_order');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Get VAC record info by provided id
     *
     * @param int $vacRecordId
     * @return array
     */
    public function getVACRecordById($vacRecordId)
    {
        $select = (new Select())
            ->from(array('v' => 'client_vac'))
            ->where(['v.client_vac_id' => (int)$vacRecordId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Check if current user has access to the VAC record
     *
     * @param int $vacRecordId
     * @return bool
     */
    public function hasAccessToVAC($vacRecordId)
    {
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        } else {
            $arrVACInfo   = $this->getVACRecordById($vacRecordId);
            $booHasAccess = isset($arrVACInfo['company_id']) && $arrVACInfo['company_id'] == $this->_auth->getCurrentUserCompanyId();
        }

        return $booHasAccess;
    }

    /**
     * Calculate max order for all companies
     *
     * @return array
     */
    public function calculateMaxOrderForCompanies()
    {
        $select = (new Select())
            ->from(array('t1' => 'client_vac'))
            ->columns(['company_id'])
            ->join(['tmp' => new Expression('(SELECT company_id, MAX(client_vac_order) maxOrder FROM client_vac GROUP BY company_id)')], 't1.company_id = tmp.company_id AND t1.client_vac_order = tmp.maxOrder', ['maxOrder']);

        return $this->_db2->fetchAssoc($select);
    }

    /**
     * Create/update VAC record
     *
     * @param int $vacRecordId
     * @param string $vacRecordCity
     * @param string $vacRecordCountry
     * @param string $vacRecordLink
     * @return int|false - id of the created/updated record on success or false on error
     */
    public function createUpdateVACRecord($vacRecordId, $vacRecordCity, $vacRecordCountry, $vacRecordLink)
    {
        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            $arrUpdateData = [
                'client_vac_country' => empty($vacRecordCountry) ? null : $vacRecordCountry,
                'client_vac_city'    => empty($vacRecordCity) ? null : $vacRecordCity,
                'client_vac_link'    => empty($vacRecordLink) ? null : $vacRecordLink,
            ];

            $booDefaultCompany = $companyId == $this->_company->getDefaultCompanyId();
            if (empty($vacRecordId)) {
                $arrMaxOrder = $this->calculateMaxOrderForCompanies();

                $arrUpdateData['company_id']       = $companyId;
                $arrUpdateData['client_vac_order'] = isset($arrMaxOrder[$companyId]['maxOrder']) ? $arrMaxOrder[$companyId]['maxOrder'] + 1 : 0;

                $vacRecordId = $this->createVACRecord($arrUpdateData);

                if ($booDefaultCompany) {
                    // Load the list of all companies ids only when we try to create new records
                    $arrCompaniesIds = $this->_company->getAllCompanies(true);
                    foreach ($arrCompaniesIds as $updateCompanyId) {
                        $arrUpdateData['company_id']           = $updateCompanyId;
                        $arrUpdateData['client_vac_parent_id'] = $vacRecordId;
                        $arrUpdateData['client_vac_order']     = isset($arrMaxOrder[$updateCompanyId]['maxOrder']) ? $arrMaxOrder[$updateCompanyId]['maxOrder'] + 1 : 0;

                        $this->createVACRecord($arrUpdateData);
                    }
                }
            } else {
                $this->updateVACRecord($arrUpdateData, ['client_vac_id' => $vacRecordId]);

                if ($booDefaultCompany) {
                    $this->updateVACRecord($arrUpdateData, ['client_vac_parent_id' => $vacRecordId]);
                }
            }
        } catch (Exception $e) {
            $vacRecordId = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $vacRecordId;
    }

    /**
     * Delete VAC record by provided id
     *
     * @param int $vacRecordId
     * @return string error message, empty on success
     */
    public function deleteVACRecord($vacRecordId)
    {
        $strError = '';

        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();
            if ($companyId == $this->_company->getDefaultCompanyId()) {
                // Get the list of all Visa Office fields
                $select = (new Select())
                    ->from(array('f' => 'client_form_fields'))
                    ->columns(['field_id'])
                    ->where(['f.company_field_id' => 'visa_office']);

                $arrVisaOfficeFieldsIds = $this->_db2->fetchCol($select);

                // Get the list of all child VAC records
                $select = (new Select())
                    ->from(array('v' => 'client_vac'))
                    ->columns(['client_vac_id'])
                    ->where(['v.client_vac_parent_id' => (int)$vacRecordId]);

                $arrChildVACRecords = $this->_db2->fetchCol($select);

                if (!empty($arrVisaOfficeFieldsIds) && !empty($arrChildVACRecords)) {
                    // Check which child VAC records are used - mark them as deleted + remove the link
                    $select = (new Select())
                        ->from(array('d' => 'client_form_data'))
                        ->columns(['value'])
                        ->where([
                            'd.field_id' => $arrVisaOfficeFieldsIds,
                            'd.value'    => $arrChildVACRecords,
                        ]);

                    $arrUsedVACRecords = Settings::arrayUnique($this->_db2->fetchCol($select));

                    if (!empty($arrUsedVACRecords)) {
                        $this->updateVACRecord(
                            [
                                'client_vac_parent_id' => null,
                                'client_vac_deleted'   => 'Y'
                            ],
                            ['client_vac_id' => $arrUsedVACRecords]
                        );
                    }
                }


                $this->_db2->delete('client_vac', ['client_vac_id' => (int)$vacRecordId]);
            } else {
                $arrVACInfo = $this->getVACRecordById($vacRecordId);
                if (!empty($arrVACInfo['client_vac_parent_id'])) {
                    $strError = $this->_tr->translate('Default VAC records cannot be deleted.');
                }

                if (empty($strError)) {
                    // Get the list of all Visa Office fields
                    $select = (new Select())
                        ->from(array('f' => 'client_form_fields'))
                        ->columns(['field_id'])
                        ->where([
                            'f.company_id'       => $companyId,
                            'f.company_field_id' => 'visa_office'
                        ]);

                    $arrVisaOfficeFieldsIds = $this->_db2->fetchCol($select);

                    $select = (new Select())
                        ->from(array('d' => 'client_form_data'))
                        ->columns(['value'])
                        ->where([
                            'd.field_id' => $arrVisaOfficeFieldsIds,
                            'd.value'    => (int)$vacRecordId,
                        ]);

                    $arrUsedVACRecords = Settings::arrayUnique($this->_db2->fetchCol($select));

                    if (!empty($arrUsedVACRecords)) {
                        // Check if the value is used - mark as deleted
                        $this->updateVACRecord(['client_vac_deleted' => 'Y'], ['client_vac_id' => $arrUsedVACRecords]);

                        $strError = $this->_tr->translate("This VAC is marked as deleted because it is saved in some case's profile.");
                    } else {
                        // If not used - delete
                        $this->_db2->delete('client_vac', ['client_vac_id' => (int)$vacRecordId]);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Update VAC record's info
     *
     * @param array $arrUpdate
     * @param array $arrWhere
     * @return void
     */
    public function updateVACRecord($arrUpdate, $arrWhere)
    {
        $this->_db2->update('client_vac', $arrUpdate, $arrWhere);
    }

    /**
     * Get child VAC records linked to the specific parent VAC record
     *
     * @param int $vacRecordId
     * @return array
     */
    public function getChildVACRecords($vacRecordId)
    {
        $select = (new Select())
            ->from(array('v' => 'client_vac'))
            ->columns(['company_id', 'client_vac_id'])
            ->where(['v.client_vac_parent_id' => (int)$vacRecordId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Move VAC record up/down for a specific company
     *
     * @param int $companyId
     * @param int $vacRecordId
     * @param bool $booUp
     * @return void
     */
    public function moveVACRecord($companyId, $vacRecordId, $booUp)
    {
        $arrVACRecords = $this->getList($companyId);

        foreach ($arrVACRecords as $key => $arrVACInfo) {
            if ($arrVACInfo['client_vac_id'] == $vacRecordId) {
                if ($booUp) {
                    $order = $arrVACInfo['client_vac_order'] - 1;
                    $order = max($order, 0);
                    $this->updateVACRecord(['client_vac_order' => $order], ['client_vac_id' => $vacRecordId]);

                    if (isset($arrVACRecords[$key - 1])) {
                        $this->updateVACRecord(['client_vac_order' => $order + 1], ['client_vac_id' => $arrVACRecords[$key - 1]['client_vac_id']]);
                    }
                } else {
                    $order = $arrVACInfo['client_vac_order'] + 1;
                    $order = max($order, 0);
                    $this->updateVACRecord(['client_vac_order' => $order], ['client_vac_id' => $vacRecordId]);

                    if (isset($arrVACRecords[$key + 1])) {
                        $order = $order - 1;
                        $order = max($order, 0);
                        $this->updateVACRecord(['client_vac_order' => $order], ['client_vac_id' => $arrVACRecords[$key + 1]['client_vac_id']]);
                    }
                }

                break;
            }
        }
    }

    /**
     * Create a VAC record
     *
     * @param array $arrCompanyVACInfo
     * @return int new record id
     */
    public function createVACRecord($arrCompanyVACInfo)
    {
        return $this->_db2->insert('client_vac', $arrCompanyVACInfo);
    }

    /**
     * Create case VACs during new company creation
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @return array mapping between default and created case vacs' ids
     */
    public function createDefaultCaseVACs($fromCompanyId, $toCompanyId)
    {
        $arrMappingDefaults = array();
        try {
            $arrDefaultVACs = $this->getList($fromCompanyId);

            foreach ($arrDefaultVACs as $arrDefaultVACInfo) {
                $arrCompanyVACInfo = [
                    'company_id'           => $toCompanyId,
                    'client_vac_parent_id' => $arrDefaultVACInfo['client_vac_id'],
                    'client_vac_country'   => $arrDefaultVACInfo['client_vac_country'],
                    'client_vac_city'      => $arrDefaultVACInfo['client_vac_city'],
                    'client_vac_link'      => $arrDefaultVACInfo['client_vac_link'],
                    'client_vac_order'     => $arrDefaultVACInfo['client_vac_order'],
                    'client_vac_deleted'   => 'N',
                ];

                $arrMappingDefaults[$arrDefaultVACInfo['client_vac_id']] = $this->createVACRecord($arrCompanyVACInfo);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMappingDefaults;
    }
}
