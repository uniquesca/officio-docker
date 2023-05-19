<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;


/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CaseTemplates extends BaseService implements SubServiceInterface
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_parent;

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
     * Load templates list for specific company
     *
     * @param int $companyId
     * @param bool $booIdsOnly
     * @param null $memberTypeId
     * @param bool $booLoadOnlyVisibleTemplates
     * @param bool $booLoadAdditionalInfo
     * @return array
     */
    public function getTemplates($companyId, $booIdsOnly = false, $memberTypeId = null, $booLoadOnlyVisibleTemplates = false, $booLoadAdditionalInfo = true)
    {
        $select = (new Select())
            ->from(array('c' => 'client_types'))
            ->columns(
                array(
                    'case_template_id'                    => 'client_type_id',
                    'case_template_parent_id'             => 'parent_client_type_id',
                    'case_template_name'                  => 'client_type_name',
                    'case_template_needs_ia'              => 'client_type_needs_ia',
                    'case_template_employer_sponsorship'  => 'client_type_employer_sponsorship',
                    'case_template_email_template_id'     => 'email_template_id',
                    'case_template_client_status_list_id' => 'client_status_list_id',
                    'case_template_hidden'                => 'client_type_hidden',
                    'case_template_hidden_for_company'    => 'client_type_hidden_for_company',
                    'case_template_case_reference_as'     => 'client_type_case_reference_as',
                    'case_template_created_on'            => 'client_type_created_on'
                )
            )
            ->where(['c.company_id' => (int)$companyId])
            ->order('c.client_type_created_on');

        if ($booLoadOnlyVisibleTemplates) {
            $select->where->equalTo('c.client_type_hidden', 'N');
            $select->where->equalTo('c.client_type_hidden_for_company', 'N');
        }

        if (!is_null($memberTypeId)) {
            $arrMemberTypes = $this->_parent->getMemberTypes(true, true);
            if (in_array($memberTypeId, $arrMemberTypes)) {
                $select->join(array('k' => 'client_types_kinds'), 'k.client_type_id = c.client_type_id', [], Select::JOIN_LEFT)
                    ->where->equalTo('k.member_type_id', $memberTypeId);
            }
        }

        $arrTemplates = $this->_db2->fetchAll($select);

        // Collect ids
        $arrTemplateIds = array();
        foreach ($arrTemplates as $arrCaseTemplateInfo) {
            $arrTemplateIds[] = $arrCaseTemplateInfo['case_template_id'];
        }

        if ($booIdsOnly) {
            $arrResult = $arrTemplateIds;
        } else {
            if ($booLoadAdditionalInfo) {
                $defaultMemberType = $this->_parent->getMemberTypeIdByName('individual');
                // Load additional info (kinds) from other table
                $arrTypes = $this->getTemplateTypes($arrTemplateIds);
                foreach ($arrTemplates as $key => $arrTemplateInfo) {
                    $arrTemplates[$key]['case_template_form_version_id'] = $this->getCaseTemplateForms($arrTemplateInfo['case_template_id']);

                    if (array_key_exists($arrTemplateInfo['case_template_id'], $arrTypes)) {
                        $arrTemplates[$key]['case_template_type']       = $arrTypes[$arrTemplateInfo['case_template_id']]['ids'];
                        $arrTemplates[$key]['case_template_type_names'] = $arrTypes[$arrTemplateInfo['case_template_id']]['names'];
                    } else {
                        $arrTemplates[$key]['case_template_type']       = array($defaultMemberType);
                        $arrTemplates[$key]['case_template_type_names'] = array('individual');
                    }

                    $arrTemplates[$key]['case_template_categories'] = $this->_parent->getCaseCategories()->getCaseCategoriesMappingForCaseType($arrTemplateInfo['case_template_id'], false);
                }
            }

            $arrResult = $arrTemplates;
        }

        return $arrResult;
    }


    /**
     * Load case template kinds (types)
     *
     * @param array $arrTemplateIds
     * @return array
     */
    public function getTemplateTypes($arrTemplateIds)
    {
        $arrResult = array();
        if (is_array($arrTemplateIds) && count($arrTemplateIds)) {
            $select = (new Select())
                ->from(array('k' => 'client_types_kinds'))
                ->columns(array('client_type_id', 'member_type_id'))
                ->join(array('t' => 'members_types'), 't.member_type_id = k.member_type_id', 'member_type_name', Select::JOIN_LEFT)
                ->where(['k.client_type_id' => $arrTemplateIds])
                ->order('k.member_type_id');

            $arrTypes = $this->_db2->fetchAll($select);
            foreach ($arrTypes as $arrTypeInfo) {
                $arrResult[$arrTypeInfo['client_type_id']]['ids'][] = $arrTypeInfo['member_type_id'];
                $arrResult[$arrTypeInfo['client_type_id']]['names'][] = $arrTypeInfo['member_type_name'];
            }
        }

        return $arrResult;
    }

    /**
     * Load a list of all template types
     *
     * @param bool $booIdsOnly
     * @return array
     */
    public function getAllTemplatesTypes($booIdsOnly = false)
    {
        $arrTypes = $this->_parent->getMemberTypes(true);
        $arrAllowedTypes = array(
            $this->_parent->getMemberTypeIdByName('individual'),
            $this->_parent->getMemberTypeIdByName('employer'),
        );

        $arrCaseTemplateTypes = array();
        foreach ($arrTypes as $arrTypeInfo) {
            if (in_array($arrTypeInfo['member_type_id'], $arrAllowedTypes)) {
                $arrCaseTemplateTypes[] = array(
                    'case_template_type_id' => $arrTypeInfo['member_type_id'],
                    'case_template_type_name' => $arrTypeInfo['member_type_case_template_name']
                );
            }
        }

        if ($booIdsOnly) {
            $arrResult = array_values($this->_settings::arrayColumnAsKey('case_template_type_id', $arrCaseTemplateTypes, 'case_template_type_id'));
        } else {
            $arrResult = $arrCaseTemplateTypes;
        }

        return $arrResult;
    }

    /**
     * Load default client type (will be used during new client creation)
     *
     * @param int $companyId
     * @return string
     */
    public function getDefaultCompanyCaseTemplate($companyId)
    {
        $select = (new Select())
            ->from('client_types')
            ->columns(['client_type_id'])
            ->where(['company_id' => (int)$companyId])
            ->order('client_type_id')
            ->limit(1);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Load specific template info
     *
     * @param int $templateId - template id to load info for
     * @return array template info
     */
    public function getTemplateInfo($templateId)
    {
        $select = (new Select())
            ->from('client_types')
            ->where(['client_types.client_type_id' => (int)$templateId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Check if current user has access to the specific template
     *
     * @param int $templateId - template id to check
     * @return bool true if the user has access, otherwise false
     */
    public function hasAccessToTemplate($templateId)
    {
        $booHasAccess = false;

        if (!empty($templateId) && is_numeric($templateId)) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } else {
                $arrTemplateInfo = $this->getTemplateInfo($templateId);
                if (is_array($arrTemplateInfo) && array_key_exists('company_id', $arrTemplateInfo) && $arrTemplateInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                    $booHasAccess = true;
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Create new case type/template
     *
     * @param int $companyId
     * @param string $templateName
     * @param int $templateCopyId
     * @param array $templateFormVersionIds
     * @param int $templateEmailTemplateId
     * @param int $templateCaseStatusListId
     * @param string $templateCaseReferenceAs
     * @param bool $booCaseTemplateNeedsIA
     * @param bool $booCaseTemplateEmployerSponsorship
     * @param array $arrTemplateTypes
     * @param null $parentClientTypeId
     * @param bool $booCaseTemplateHidden
     * @param null $booCaseTemplateCompanyHidden
     * @param bool $booCopyFromDefaultCompany
     * @return array
     *  int Immigration Program id, empty on error
     *  array of mapped groups
     */
    public function addTemplate(
        $companyId,
        $templateName,
        $templateCopyId,
        $templateFormVersionIds,
        $templateEmailTemplateId,
        $templateCaseStatusListId,
        $templateCaseReferenceAs,
        $booCaseTemplateNeedsIA,
        $booCaseTemplateEmployerSponsorship,
        $arrTemplateTypes,
        $parentClientTypeId = null,
        $booCaseTemplateHidden = false,
        $booCaseTemplateCompanyHidden = null,
        $booCopyFromDefaultCompany = false
    ) {
        $arrGroupsMapping = [];

        try {
            $arrInsert = array(
                'company_id'                       => $companyId,
                'parent_client_type_id'            => empty($parentClientTypeId) ? null : $parentClientTypeId,
                'client_status_list_id'            => empty($templateCaseStatusListId) ? null : $templateCaseStatusListId,
                'email_template_id'                => empty($templateEmailTemplateId) ? null : $templateEmailTemplateId,
                'client_type_name'                 => $templateName,
                'client_type_case_reference_as'    => $templateCaseReferenceAs,
                'client_type_needs_ia'             => $booCaseTemplateNeedsIA ? 'Y' : 'N',
                'client_type_employer_sponsorship' => $booCaseTemplateEmployerSponsorship ? 'Y' : 'N',
                'client_type_hidden'               => $booCaseTemplateHidden ? 'Y' : 'N',
                'client_type_created_on'           => date('c')
            );

            if (!is_null($booCaseTemplateCompanyHidden)) {
                $arrInsert['client_type_hidden_for_company'] = $booCaseTemplateCompanyHidden ? 'Y' : 'N';
            }

            $templateId = $this->_db2->insert('client_types', $arrInsert);

            $this->assignFormVersions($templateId, $templateFormVersionIds);

            foreach ($arrTemplateTypes as $templateType) {
                $this->_db2->insert(
                    'client_types_kinds',
                    [
                        'client_type_id' => $templateId,
                        'member_type_id' => $templateType
                    ]
                );
            }

            // create copy of all groups + fields from a specific template
            if ($templateCopyId) {
                // Create a copy of groups
                $select = (new Select())
                    ->from('client_form_groups')
                    ->where(['client_type_id' => $templateCopyId]);

                $arrTemplateGroups = $this->_db2->fetchAll($select);

                $arrGroupsMapping = array();
                if (count($arrTemplateGroups)) {
                    foreach ($arrTemplateGroups as $s) {
                        $sourceGroupId = $s['group_id'];

                        unset($s['group_id']);

                        if (empty($companyId)) {
                            // This is a copy from the default group for the default company
                            unset($s['parent_group_id']);
                        } elseif ($booCopyFromDefaultCompany) {
                            // Create a copy from the default group
                            $s['parent_group_id'] = $sourceGroupId;
                        }

                        $s['client_type_id'] = $templateId;
                        $s['company_id']     = $companyId;

                        $arrGroupsMapping[$sourceGroupId] = $this->_db2->insert('client_form_groups', $s);
                    }
                }

                if (!empty($arrGroupsMapping)) {
                    // Create a copy of fields in these groups (just an order)
                    $select = (new Select())
                        ->from('client_form_order')
                        ->where([
                            (new Where())->in('group_id', array_keys($arrGroupsMapping))
                        ]);

                    $arrFieldsOrder = $this->_db2->fetchAll($select);

                    $oFields = $this->getParent()->getFields();
                    if (count($arrFieldsOrder)) {
                        $arrFieldsMapping = array();
                        if ($booCopyFromDefaultCompany) {
                            $select = (new Select())
                                ->from('client_form_fields')
                                ->columns(array('field_id', 'parent_field_id'))
                                ->where([
                                    (new Where())
                                        ->isNotNull('parent_field_id')
                                        ->equalTo('company_id', (int)$companyId)
                                ]);

                            $arrCompanyFields = $this->_db2->fetchAll($select);

                            foreach ($arrCompanyFields as $arrCompanyFieldRow) {
                                $arrFieldsMapping[$arrCompanyFieldRow['parent_field_id']] = $arrCompanyFieldRow['field_id'];
                            }
                        }

                        foreach ($arrFieldsOrder as $s) {
                            // Use the correct fields mapping if we create a copy from the default company to a regular one
                            if ($booCopyFromDefaultCompany) {
                                if (isset($arrFieldsMapping[$s['field_id']])) {
                                    $s['field_id'] = $arrFieldsMapping[$s['field_id']];
                                } else {
                                    // Don't create a record if there is no field mapping
                                    continue;
                                }
                            }

                            $oFields->placeFieldInGroup(
                                $arrGroupsMapping[$s['group_id']],
                                $s['field_id'],
                                $s['use_full_row'] == 'Y',
                                $s['field_order']
                            );
                        }
                    }

                    // And set the same access rights to the newly created groups
                    $select = (new Select())
                        ->from('client_form_group_access')
                        ->where([
                            (new Where())->in('group_id', array_keys($arrGroupsMapping))
                        ]);

                    $arrGroupsAccess = $this->_db2->fetchAll($select);

                    foreach ($arrGroupsAccess as $arrGroupsAccessInfo) {
                        if (isset($arrGroupsMapping[$arrGroupsAccessInfo['group_id']])) {
                            $oFields->createGroupAccessRecord(
                                $arrGroupsAccessInfo['role_id'],
                                $arrGroupsMapping[$arrGroupsAccessInfo['group_id']],
                                $arrGroupsAccessInfo['status']
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $templateId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return [$templateId, $arrGroupsMapping];
    }

    /**
     * Update Case Type (Immigration Program) info
     * Update specific settings only if they were provided (not null)
     *
     * @param int $templateId
     * @param string|null $templateName
     * @param array|null $templateFormVersionIds
     * @param int|null $templateEmailTemplateId
     * @param int|null $templateCaseStatusListId
     * @param string|null $templateCaseReferenceAs
     * @param bool|null $booCaseTemplateNeedsIA
     * @param bool|null $booCaseTemplateEmployerSponsorship
     * @param array|null $arrTemplateTypes
     * @param bool|null $booCaseTemplateHidden
     * @param bool|null $booCaseTemplateHiddenForCompany
     * @return bool true on success
     */
    public function updateTemplate($templateId, $templateName, $templateFormVersionIds, $templateEmailTemplateId, $templateCaseStatusListId, $templateCaseReferenceAs, $booCaseTemplateNeedsIA, $booCaseTemplateEmployerSponsorship, $arrTemplateTypes, $booCaseTemplateHidden, $booCaseTemplateHiddenForCompany)
    {
        try {
            // Update case type main settings
            $arrUpdate = [];

            if (!is_null($templateName)) {
                $arrUpdate['client_type_name'] = $templateName;
            }

            if (!is_null($templateCaseReferenceAs)) {
                $arrUpdate['client_type_case_reference_as'] = $templateCaseReferenceAs;
            }

            if (!is_null($booCaseTemplateNeedsIA)) {
                $arrUpdate['client_type_needs_ia'] = $booCaseTemplateNeedsIA ? 'Y' : 'N';
            }

            if (!is_null($booCaseTemplateEmployerSponsorship)) {
                $arrUpdate['client_type_employer_sponsorship'] = $booCaseTemplateEmployerSponsorship ? 'Y' : 'N';
            }

            if (!is_null($booCaseTemplateHidden)) {
                $arrUpdate['client_type_hidden'] = $booCaseTemplateHidden ? 'Y' : 'N';
            }

            if (!is_null($templateCaseStatusListId)) {
                $arrUpdate['client_status_list_id'] = empty($templateCaseStatusListId) ? null : $templateCaseStatusListId;
            }

            if (!is_null($templateEmailTemplateId)) {
                $arrUpdate['email_template_id'] = empty($templateEmailTemplateId) ? null : $templateEmailTemplateId;
            }

            if (!is_null($booCaseTemplateHiddenForCompany)) {
                $arrUpdate['client_type_hidden_for_company'] = $booCaseTemplateHiddenForCompany ? 'Y' : 'N';
            }

            if (!empty($arrUpdate)) {
                $this->_db2->update('client_types', $arrUpdate, ['client_type_id' => (int)$templateId]);
            }

            // Update/assign form versions
            if (!is_null($templateFormVersionIds)) {
                $this->assignFormVersions($templateId, $templateFormVersionIds, true);
            }

            // Update/assign case types
            if (!is_null($arrTemplateTypes)) {
                $this->_db2->delete('client_types_kinds', ['client_type_id' => (int)$templateId]);
                foreach ($arrTemplateTypes as $templateType) {
                    $this->_db2->insert(
                        'client_types_kinds',
                        [
                            'client_type_id' => $templateId,
                            'member_type_id' => $templateType
                        ]
                    );
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete the Immigration Program by id
     *
     * @param int $templateId
     * @return bool true on success
     */
    public function deleteTemplate($templateId)
    {
        try {
            $arrTables = array(
                'client_types'
            );

            foreach ($arrTables as $table) {
                $this->_db2->delete($table, ['client_type_id' => (int)$templateId]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create default case templates for specific company
     *
     * @param $fromCompanyId
     * @param $toCompanyId
     * @param $arrMappingDefaultCaseStatusLists
     * @return array
     */
    public function createCompanyDefaultCaseTemplates($fromCompanyId, $toCompanyId, $arrMappingDefaultCaseStatusLists)
    {
        $arrMappingTemplates = array();

        // Get default Case Templates
        $arrDefaultTemplates = $this->getTemplates($fromCompanyId);

        // Create a copy and save a mapping
        foreach ($arrDefaultTemplates as $arrTemplateInfo) {
            // Skip v1 case template - https://app.clickup.com/t/1p28nbn and https://app.clickup.com/t/34564v2
            if ($arrTemplateInfo['case_template_name'] == 'Generic') {
                continue;
            }

            $arrTemplateTypes = $this->getTemplateTypes(array($arrTemplateInfo['case_template_id']));
            $arrTemplateTypes = array_key_exists($arrTemplateInfo['case_template_id'], $arrTemplateTypes) ? $arrTemplateTypes[$arrTemplateInfo['case_template_id']]['ids'] : array();

            $companyCaseStatusListId = $arrMappingDefaultCaseStatusLists[$arrTemplateInfo['case_template_client_status_list_id']] ?? null;

            list($newTemplateId,) = $this->addTemplate(
                $toCompanyId,
                $arrTemplateInfo['case_template_name'],
                0,
                [],
                0,
                $companyCaseStatusListId,
                $arrTemplateInfo['case_template_case_reference_as'],
                $arrTemplateInfo['case_template_needs_ia'] == 'Y',
                $arrTemplateInfo['case_template_employer_sponsorship'] == 'Y',
                $arrTemplateTypes,
                $arrTemplateInfo['case_template_id'],
                $arrTemplateInfo['case_template_hidden'] == 'Y',
                false
            );

            if (!empty($newTemplateId)) {
                $arrMappingTemplates[$arrTemplateInfo['case_template_id']] = $newTemplateId;
            }
        }

        return $arrMappingTemplates;
    }

    /**
     * Load list of Immigration Programs for specific cases
     *
     * @param array $arrCaseIds
     * @return array
     */
    public function getCasesTemplates($arrCaseIds)
    {
        $templates = array();
        if (is_array($arrCaseIds) && count($arrCaseIds)) {
            $select = (new Select())
                ->from('clients')
                ->columns(array('member_id', 'client_type_id'))
                ->where(['member_id' => $arrCaseIds]);

            $result = $this->_db2->fetchAll($select);

            foreach ($result as $row) {
                $templates[$row['member_id']] = $row['client_type_id'];
            }
        }

        return $templates;
    }

    /**
     * Load a list of assigned forms for a specific Immigration Program
     *
     * @param int $arrCaseTemplateId
     * @param bool $booImplode
     * @return array|string
     */
    public function getCaseTemplateForms($arrCaseTemplateId, $booImplode = true)
    {
        $select = (new Select())
            ->from('client_types_forms')
            ->columns(['form_version_id'])
            ->where(['client_type_id' => $arrCaseTemplateId]);

        $arrFormVersionIds = $this->_db2->fetchCol($select);

        if ($booImplode && is_array($arrFormVersionIds) && !empty($arrFormVersionIds)) {
            $arrFormVersionIds = implode(';', $arrFormVersionIds);
        }

        return $arrFormVersionIds;
    }


    /**
     * Load case template info by name for specific company
     *
     * @param string $templateName
     * @param int $companyId
     * @return array
     */
    public function getCasesTemplateInfoByName($templateName, $companyId)
    {
        $arrCaseTemplateInfo = array();
        if (strlen($templateName)) {
            $select = (new Select())
                ->from('client_types')
                ->where(
                    [
                        'company_id'       => (int)$companyId,
                        'client_type_name' => $templateName
                    ]
                );

            $arrCaseTemplateInfo = $this->_db2->fetchRow($select);
        }

        return $arrCaseTemplateInfo;
    }

    /**
     * Assign form versions to specific Immigration Programs
     *
     * @param int $templateId
     * @param array $formVersionIds
     * @param bool $booUpdate true to delete before insert.
     * @return bool true on success
     */
    public function assignFormVersions($templateId, $formVersionIds, $booUpdate = false)
    {
        try {
            if (!empty($templateId) && !empty($formVersionIds)) {
                if ($booUpdate) {
                    $this->_db2->delete('client_types_forms', ['client_type_id' => $templateId]);
                }

                foreach ($formVersionIds as $formVersionId) {
                    if (!empty($formVersionId) && is_numeric($formVersionId)) {
                        $this->_db2->insert(
                            'client_types_forms',
                            [
                                'client_type_id'  => $templateId,
                                'form_version_id' => $formVersionId
                            ]
                        );
                    }
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if template is created from default template
     *
     * @param int $templateId - template id to check
     * @return bool true if created from default template
     */
    public function isCreatedFromDefaultTemplate($templateId)
    {
        $booCreatedFromDefaultTemplate = false;

        if (!empty($templateId) && is_numeric($templateId)) {
            $arrTemplateInfo = $this->getTemplateInfo($templateId);
            if (isset($arrTemplateInfo['parent_client_type_id'])) {
                $arrParentTemplateInfo = $this->getTemplateInfo($arrTemplateInfo['parent_client_type_id']);
                if ($arrParentTemplateInfo['company_id'] == $this->_company->getDefaultCompanyId()) {
                    $booCreatedFromDefaultTemplate = true;
                }
            }
        }

        return $booCreatedFromDefaultTemplate;
    }

    /**
     * Get count of company not hidden case templates
     *
     * @param int $companyId
     * @return int
     */
    public function getCompanyVisibleCaseTemplatesCount($companyId)
    {
        $select = (new Select())
            ->from('client_types')
            ->columns(['count' => new Expression('COUNT(*)')])
            ->where(
                [
                    'company_id'                     => (int)$companyId,
                    'client_type_hidden'             => 'N',
                    'client_type_hidden_for_company' => 'N'
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    /**
     * Get case templates by parent case template id
     *
     * @param int $parentCaseTemplateId
     * @param bool $booIdsOnly true to load ids only or false to load all details
     * @return array
     */
    public function getCaseTemplatesByParentId($parentCaseTemplateId, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from('client_types')
            ->columns($booIdsOnly ? ['client_type_id'] : [Select::SQL_STAR])
            ->where(['parent_client_type_id' => (int)$parentCaseTemplateId]);

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }
}
