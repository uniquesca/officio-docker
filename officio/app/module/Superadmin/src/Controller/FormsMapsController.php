<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Forms\Service\Forms;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;

/**
 * Forms Maps Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FormsMapsController extends BaseController
{
    /** @var StripTags */
    private $_filter;

    /** @var Clients */
    protected $_clients;

    /** @var Forms */
    protected $_forms;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
        $this->_forms   = $services[Forms::class];
        $this->_filter  = new StripTags();
    }

    /**
     * The default action - return mapped fields in json format
     */
    public function indexAction()
    {
        $view = new JsonModel();
        try {
            $sort  = $this->_filter->filter($this->findParam('sort'));
            $dir   = $this->findParam('dir');
            $start = (int)$this->findParam('start');
            $limit = (int)$this->findParam('limit');

            $arrFormattedMappedFields = array();

            // Get assigned forms for this member
            $arrResult       = $this->_forms->getFormMap()->getMappedFields($sort, $dir, $start, $limit);
            $arrMappedFields = $arrResult['rows'];
            $totalCount      = $arrResult['totalCount'];

            // ENUM(
            // 'main_applicant','sponsor','employer','spouse',
            // 'parent1','parent2','parent3','parent4',
            // 'sibling1','sibling2','sibling3','sibling4','sibling5',
            // 'child1','child2','child3','child4','child5','child6','child7','child8','child9','child10',
            // 'other1','other2'
            // )
            $arrFamilyMembers          = $this->_clients->getFamilyMembers();
            $arrSyncProfileMappingType = $this->_clients->getFields()->getProfileSyncMappingType();

            foreach ($arrMappedFields as $assignedMapInfo) {
                // Get readable fields for "profile fields"
                $profileFieldMember = $arrFamilyMembers[$assignedMapInfo['to_profile_family_member_id']] ?? '';
                $profileFieldName   = '';
                if(!empty($profileFieldMember) && !empty($assignedMapInfo['to_profile_field_id'])) {
                    if(!isset($arrMemberFields[$profileFieldMember])) {
                        $arrMemberFields[$profileFieldMember] = $this->_clients->getFields()->getProfileSyncFields($profileFieldMember);
                    }

                    foreach ($arrMemberFields[$profileFieldMember] as $arrSyncProfileFieldInfo) {
                        if($arrSyncProfileFieldInfo['id'] == $assignedMapInfo['to_profile_field_id']) {
                            $profileFieldName = $arrSyncProfileFieldInfo['value'];
                            break;
                        }
                    }
                }

                $profileMappingType = '';
                if(!empty($profileFieldMember) && !empty($assignedMapInfo['form_map_type'])) {
                    foreach ($arrSyncProfileMappingType as $arrSyncProfileMapInfo) {
                        if($arrSyncProfileMapInfo['id'] == $assignedMapInfo['form_map_type']) {
                            $profileMappingType = $arrSyncProfileMapInfo['value'];
                            break;
                        }
                    }
                }

                $arrFormattedMappedFields[] = array(
                    'map_id'        => (int)$assignedMapInfo['form_map_id'],
                    'bidirectional' => !empty($assignedMapInfo['form_map_id_direction']),

                    'from_family_member_id'   => $assignedMapInfo['from_family_member_id'],
                    'from_family_member_name' => $arrFamilyMembers[$assignedMapInfo['from_family_member_id']] ?? '',
                    'from_field_name'         => $assignedMapInfo['field_from'],

                    'to_family_member_id'   => $assignedMapInfo['to_family_member_id'],
                    'to_family_member_name' => $arrFamilyMembers[$assignedMapInfo['to_family_member_id']] ?? '',
                    'to_field_name'         => $assignedMapInfo['field_to'],

                    'profile_field_member' => $profileFieldMember,
                    'profile_field_name'   => $profileFieldName,
                    'profile_mapping_type' => $profileMappingType,
                );
            }
        } catch (Exception $e) {
            $totalCount               = 0;
            $arrFormattedMappedFields = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return json result
        $arrResult = array(
            'rows'       => $arrFormattedMappedFields,
            'totalCount' => $totalCount
        );

        return $view->setVariables($arrResult);
    }


    public function manageAction()
    {
        $view   = new JsonModel();
        $errMsg = '';

        try {
            // Get all parameters
            $mapId = (int)Json::decode($this->findParam('map_id', 0), Json::TYPE_ARRAY);

            $fromMember = Json::decode($this->findParam('from_member'), Json::TYPE_ARRAY);
            $fromField  = Json::decode($this->findParam('from_field'), Json::TYPE_ARRAY);

            $toMember = Json::decode($this->findParam('to_member'), Json::TYPE_ARRAY);
            $toField  = Json::decode($this->findParam('to_field'), Json::TYPE_ARRAY);

            $toProfileMember = Json::decode($this->findParam('to_profile_member'), Json::TYPE_ARRAY);
            $toProfileField  = Json::decode($this->findParam('to_profile_field'), Json::TYPE_ARRAY);
            $toProfileType   = Json::decode($this->findParam('to_profile_type'), Json::TYPE_ARRAY);

            $booTwoDirectional = Json::decode($this->findParam('two_directional'), Json::TYPE_ARRAY);


            // Check if family members are correct
            $arrFamilyMembers = $this->_clients->getFamilyMembers();

            if (empty($errMsg) && !isset($arrFamilyMembers[$fromMember])) {
                $errMsg = $this->_tr->translate('Incorrectly selected family member [from]');
            }

            if (empty($errMsg) && !isset($arrFamilyMembers[$toMember])) {
                $errMsg = $this->_tr->translate('Incorrectly selected family member [to - pdf form]');
            }

            // Check if "Profile fields" are correct (2 fields must be selected, 3d - is optional)
            if (empty($errMsg) && !empty($toProfileMember) && !isset($arrFamilyMembers[$toProfileMember])) {
                $errMsg = $this->_tr->translate('Incorrectly selected family member [profile field section]');
            }

            // Make sure that 2 other fields will be empty if the main field is empty
            if (empty($errMsg) && empty($toProfileMember)) {
                $toProfileField = $toProfileType = '';
            }

            if (empty($errMsg) && !empty($toProfileMember)) {
                $booCorrectProfileField = false;
                $arrSyncProfileFields   = $this->_clients->getFields()->getProfileSyncFields($toProfileMember);
                foreach ($arrSyncProfileFields as $arrSyncProfileFieldInfo) {
                    if($arrSyncProfileFieldInfo['id'] == $toProfileField) {
                        $booCorrectProfileField = true;
                        break;
                    }
                }

                if(!$booCorrectProfileField) {
                    $errMsg = $this->_tr->translate('Incorrectly selected profile field [profile field section]');
                }
            }

            if (empty($errMsg) && !empty($toProfileMember) && !empty($toProfileType)) {
                $booCorrectProfileMapping  = false;
                $arrSyncProfileMappingType = $this->_clients->getFields()->getProfileSyncMappingType();
                foreach ($arrSyncProfileMappingType as $arrSyncProfileMapInfo) {
                    if($arrSyncProfileMapInfo['id'] == $toProfileType) {
                        $booCorrectProfileMapping = true;
                        break;
                    }
                }

                if(!$booCorrectProfileMapping) {
                    $errMsg = $this->_tr->translate('Incorrectly selected mapping type [profile field section]');
                }
            }


            // Check if fields are correct
            $arrSyFieldsIds = $this->_forms->getFormSynField()->fetchSynFieldsIds();

            if (is_array($arrSyFieldsIds)) {
                if (empty($errMsg) && !in_array($fromField, $arrSyFieldsIds)) {
                    $errMsg = $this->_tr->translate('Incorrectly selected field [from]');
                }

                if (empty($errMsg) && !in_array($toField, $arrSyFieldsIds)) {
                    $errMsg = $this->_tr->translate('Incorrectly selected field [to]');
                }
            } else {
                $errMsg = $this->_tr->translate('There are no sync fields in db');
            }


            if (empty($errMsg)) {
                if (empty($mapId)) {
                    //Create a new map
                    $arrToInsert = array(
                        'from_family_member_id' => $fromMember,
                        'from_syn_field_id'     => $fromField,
                        'to_family_member_id'   => $toMember,
                        'to_syn_field_id'       => $toField,

                        'to_profile_family_member_id' => empty($toProfileMember) ? null : $toProfileMember,
                        'to_profile_field_id'        => $toProfileField,
                        'form_map_type'           => empty($toProfileType) ? null : $toProfileType,
                    );

                    $this->_db2->insert('form_map', $arrToInsert);

                    if($booTwoDirectional) {
                        $arrToInsert = array(
                            'from_family_member_id' => $toMember,
                            'from_syn_field_id'     => $toField,
                            'to_family_member_id'   => $fromMember,
                            'to_syn_field_id'       => $fromField,

                            'to_profile_family_member_id' => empty($toProfileMember) ? null : $toProfileMember,
                            'to_profile_field_id'        => $toProfileField,
                            'form_map_type'           => empty($toProfileType) ? null : $toProfileType,
                        );

                        $this->_db2->insert('form_map', $arrToInsert);
                    }
                }

                // Clean saved records in cache
                $this->_cache->removeItem('pdf_form_map');
            }
        } catch (Exception $e) {
            $errMsg = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return json result
        $arrResult = array(
            'success' => empty($errMsg),
            'message' => $errMsg
        );
        return $view->setVariables($arrResult);
    }

    public function deleteAction()
    {
        $view   = new JsonModel();
        $errMsg = '';

        $arrMapIds = Json::decode($this->findParam('arr_map_id', '[]'), Json::TYPE_ARRAY);
        // Check if this user has access to these forms
        if (empty($errMsg) && (!is_array($arrMapIds) || count($arrMapIds) == 0)) {
            $errMsg = $this->_tr->translate('Incorrectly selected maps');
        }


        if (empty($errMsg)) {
            $this->_forms->getFormMap()->deleteMaps($arrMapIds);

            // Clean saved records in cache
            $this->_cache->removeItem('pdf_form_map');
        }

        // Return json result
        $booSuccess = empty($errMsg);
        $arrResult  = array('success' => $booSuccess, 'message' => $errMsg);
        return $view->setVariables($arrResult);
    }


    public function familyMembersListAction()
    {
        $view             = new JsonModel();
        $arrFamilyMembers = $this->_clients->getFamilyMembers(false);

        // Return json result
        $arrResult = array('rows' => $arrFamilyMembers, 'totalCount' => count($arrFamilyMembers));
        return $view->setVariables($arrResult);
    }

    public function fieldsListAction()
    {
        $view           = new JsonModel();
        $arrSavedFields = $this->_forms->getFormSynField()->fetchFormFields();

        $arrFormattedFields = array();
        if(is_array($arrSavedFields) && count($arrSavedFields) > 0) {
            foreach ($arrSavedFields as $savedFieldInfo) {
                $arrFormattedFields[] = array(
                    'id'    => $savedFieldInfo['syn_field_id'],
                    'value' => $savedFieldInfo['field_name']
                );
            }
        }

        // Return json result
        $arrResult = array('rows' => $arrFormattedFields, 'totalCount' => count($arrFormattedFields));
        return $view->setVariables($arrResult);
    }

    public function profileFieldsListAction()
    {
        $view         = new JsonModel();
        $familyMember = $this->_filter->filter(Json::decode($this->findParam('family_member_id'), Json::TYPE_ARRAY));

        $arrSyncProfileFields = $this->_clients->getFields()->getProfileSyncFields($familyMember);

        // Return json result
        $arrResult = array('rows' => $arrSyncProfileFields, 'totalCount' => count($arrSyncProfileFields));
        return $view->setVariables($arrResult);
    }


    public function profileMappingTypesAction()
    {
        $view                      = new JsonModel();
        $arrSyncProfileMappingType = $this->_clients->getFields()->getProfileSyncMappingType();

        // Return json result
        $arrResult = array(
            'rows'       => $arrSyncProfileMappingType,
            'totalCount' => count($arrSyncProfileMappingType)
        );
        return $view->setVariables($arrResult);
    }

}
