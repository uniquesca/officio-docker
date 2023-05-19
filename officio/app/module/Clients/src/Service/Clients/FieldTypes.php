<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FieldTypes extends BaseService implements SubServiceInterface
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
     * Load saved info and save to cache
     *
     * @return array
     */
    public function loadSavedFieldTypes()
    {
        $cacheId = 'field_types';
        if (!($data = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from('field_types');

            $data = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $data);
        }

        return $data;
    }

    /**
     * Load field types and group by text ids
     *
     * @return array
     */
    public function loadSavedFieldTypesByTextIds()
    {
        $cacheId = 'field_types_text_ids';
        if (!($arrGroupedTypes = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from('field_types');

            $data = $this->_db2->fetchAll($select);

            $arrGroupedTypes = array();
            foreach ($data as $arrSavedFieldTypeInfo) {
                $arrGroupedTypes[$arrSavedFieldTypeInfo['field_type_text_id']] = $arrSavedFieldTypeInfo['field_type_id'];

                if (!empty($arrSavedFieldTypeInfo['field_type_text_aliases'])) {
                    $arrAliases = explode(',', $arrSavedFieldTypeInfo['field_type_text_aliases'] ?? '');
                    $arrAliases = array_map('trim', $arrAliases);
                    foreach ($arrAliases as $strAlias) {
                        $arrGroupedTypes[$strAlias] = $arrSavedFieldTypeInfo['field_type_id'];
                    }
                }
            }

            $this->_cache->setItem($cacheId, $arrGroupedTypes);
        }

        return $arrGroupedTypes;
    }

    /**
     * Load field types for specific client type
     *
     * @param string $type
     * @return array
     */
    public function getFieldTypes($type = 'case')
    {
        $arrSavedFieldTypes = $this->loadSavedFieldTypes();

        $arrLabels = array();
        $arrResult = array();

        $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');
        foreach ($arrSavedFieldTypes as $arrSavedFieldTypeInfo) {
            // Filter field type by client type
            // I.e. hide specific field types in specific situations
            switch ($type) {
                case 'case':
                    $booLoadRecord = in_array($arrSavedFieldTypeInfo['field_type_use_for'], array('all', 'case'));
                    break;

                case 'all':
                    $booLoadRecord = in_array($arrSavedFieldTypeInfo['field_type_use_for'], array('case', 'all', 'others'));
                    break;

                default:
                    $booLoadRecord = in_array($arrSavedFieldTypeInfo['field_type_use_for'], array('all', 'others'));
                    break;
            }

            if ($booLoadRecord) {
                // Each company can use own Office label - use it in the field type, if needed
                $label = preg_replace('/%office_label%/', $officeLabel, $arrSavedFieldTypeInfo['field_type_label']);

                $arrResult[] = array(
                    'id'                   => $arrSavedFieldTypeInfo['field_type_id'],
                    'text_id'              => $arrSavedFieldTypeInfo['field_type_text_id'],
                    'label'                => $label,
                    'booCanBeSearched'     => $arrSavedFieldTypeInfo['field_type_can_be_used_in_search'] == 'Y',
                    'booCanBeEncrypted'    => $arrSavedFieldTypeInfo['field_type_can_be_encrypted'] == 'Y',
                    'booWithMaxLength'     => $arrSavedFieldTypeInfo['field_type_with_max_length'] == 'Y',
                    'booWithOptions'       => $arrSavedFieldTypeInfo['field_type_with_options'] == 'Y',
                    'booWithComboOptions'  => $arrSavedFieldTypeInfo['field_type_with_options'] == 'Y' && in_array($arrSavedFieldTypeInfo['field_type_text_id'], ['combo', 'radio', 'multiple_combo']),
                    'booWithDefaultValue'  => $arrSavedFieldTypeInfo['field_type_with_default_value'] == 'Y',
                    'booWithCustomHeight'  => $arrSavedFieldTypeInfo['field_type_with_custom_height'] == 'Y',
                    'booAutoCalcField'     => $arrSavedFieldTypeInfo['field_type_text_id'] == 'auto_calculated',
                    'booWithImageSettings' => $arrSavedFieldTypeInfo['field_type_text_id'] == 'photo',
                );

                // Use for sorting
                $arrLabels[] = $label;
            }
        }

        // Sort by labels
        array_multisort($arrLabels, SORT_ASC, $arrResult);

        return $arrResult;
    }

    /**
     * Load field type info by id
     *
     * @param int $fieldType
     * @param string $memberType
     * @return array
     */
    public function getFieldTypeInfoById($fieldType, $memberType = 'case')
    {
        $arrFieldTypeInfo = [];

        $arrTypes = $this->getFieldTypes($memberType);
        foreach ($arrTypes as $arrTypeInfo) {
            if ($fieldType == $arrTypeInfo['id']) {
                $arrFieldTypeInfo = $arrTypeInfo;
                break;
            }
        }

        return $arrFieldTypeInfo;
    }

    public function getFieldTypeIdByTextId($fieldType, $memberType = 'case', $booAllowEmpty = false)
    {
        $arrTypes = $this->getFieldTypes($memberType);
        $textId   = $booAllowEmpty ? '0' : '1';
        foreach ($arrTypes as $arrTypeInfo) {
            if ($fieldType == $arrTypeInfo['text_id']) {
                $textId = $arrTypeInfo['id'];
                break;
            }
        }

        return $textId;
    }


    public function getStringFieldTypeById($searchId, $memberType = 'all')
    {
        $arrTypes = $this->getFieldTypes($memberType);
        $textId   = 'text';
        foreach ($arrTypes as $arrTypeInfo) {
            if ($searchId == $arrTypeInfo['id']) {
                $textId = $arrTypeInfo['text_id'];
                break;
            }
        }

        return $textId;
    }

    /**
     * Load field type id by its text id
     *
     * NOTE: not used for now - is too slow
     * @param $name
     * @return int
     */
    public function getFieldTypeId($name)
    {
        $arrSavedFieldTypes = $this->loadSavedFieldTypesByTextIds();

        return $arrSavedFieldTypes[$name] ?? 1;
    }

    /**
     * Identify if field type is date or date repeatable
     * @param $fieldType
     * @return bool
     */
    public function isDateField($fieldType)
    {
        return in_array(
            $fieldType,
            array(
                $this->getFieldTypeId('date'),
                $this->getFieldTypeId('rdate')
            )
        );
    }

    /**
     * Identify if field type is date or date repeatable
     * @param $strFieldType
     * @return bool
     */
    public function isDateFieldByTextType($strFieldType)
    {
        return in_array($strFieldType, array('date', 'rdate', 'date_repeatable'));
    }
}