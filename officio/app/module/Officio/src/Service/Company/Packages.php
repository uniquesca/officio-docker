<?php

namespace Officio\Service\Company;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Service\Roles;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Packages extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Company */
    private $_parent;

    /** @var Roles */
    protected $_roles;

    /** @var SystemTriggers */
    protected $_systemTriggers;

    public function initAdditionalServices(array $services) {
        $this->_roles = $services[Roles::class];
        $this->_systemTriggers = $services[SystemTriggers::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * If Prospects is not turned on for company -
     * delete all prospect records
     * which were previously created
     *
     * @param int $companyId
     * @param array $arrPackagesBeforeUpdate
     * @param array $arrPackagesAfterUpdate
     * @return bool
     */
    public function enableDisableProspects($companyId, $arrPackagesBeforeUpdate, $arrPackagesAfterUpdate)
    {
        $booResult = true;

        try {
            $action  = '';
            $package = $this->getPackageIdByRuleCheckId('prospects-view');
            if (in_array($package, $arrPackagesBeforeUpdate)) {
                // Prospects was turned on before
                if (!in_array($package, $arrPackagesAfterUpdate)) {
                    // Package 1 was removed, that's mean that Prospects now is turned off
                    $action = 'disable';
                }
            } else {
                // Prospects was NOT turned on before
                if (in_array($package, $arrPackagesAfterUpdate)) {
                    // Package 1 was added, that's mean that Prospects now is turned on
                    $action = 'enable';
                }
            }

            if (!empty($action) && ($action == 'enable')) {
                $this->_systemTriggers->triggerEnableCompanyProspects($companyId);
            }
        } catch (Exception $e) {
            $booResult = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }

    public function getCompanyPackages($companyId)
    {
        $select = (new Select())
            ->from('company_packages')
            ->columns(['package_id'])
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchCol($select);
    }

    public function getPackages($booRequiredOnly = false, $booIdOnly = false)
    {
        $select = (new Select());
        if ($booIdOnly) {
            $select->from('packages')
                ->columns(['package_id']);
        } else {
            $select->from('packages');
        }

        $arrWhere = [];
        $arrWhere['status'] = 1;
        if ($booRequiredOnly) {
            $arrWhere['required'] = 1;
        }

        $select->where($arrWhere);

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load list of rules allowed for the company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyRules($companyId)
    {
        $select = (new Select())
            ->from(array('p' => 'company_packages'))
            ->columns(['package_id'])
            ->where(['p.company_id' => (int)$companyId]);

        $arrPackageIds = $this->_db2->fetchCol($select);

        $arrRulesIds = array_unique($this->getPackagesRules($arrPackageIds));


        if (!empty($arrRulesIds)) {
            // Remove specific rules if website settings don't allow them
            // @Note: don't pass the company id, as company details table isn't filled yet
            $arrExcludedRules = $this->_roles->getCompanyExcludedRules();

            foreach ($arrExcludedRules as $excludedRuleId) {
                if (($key = array_search($excludedRuleId, $arrRulesIds)) !== false) {
                    unset($arrRulesIds[$key]);
                }
            }
        }

        return $arrRulesIds;
    }

    public function getPackagesRules($packageId)
    {
        $arrRulesIds = array();

        if (!empty($packageId)) {
            $select = (new Select())
                ->from(array('d' => 'packages_details'))
                ->columns(array('rule_id'))
                ->join(array('r' => 'acl_rules'), 'r.rule_id = d.rule_id', [])
                ->where(['d.package_id' => $packageId, (new Where())->notEqualTo('r.superadmin_only', 1)]);

            $arrRulesIds = $this->_db2->fetchCol($select);
        }

        return $arrRulesIds;
    }

    /**
     * Get package id by the provided text rule check id
     *
     * @param string $ruleCheckId
     * @return int empty if not found
     */
    public function getPackageIdByRuleCheckId($ruleCheckId)
    {
        $packageId = 0;

        if (!empty($ruleCheckId)) {
            $select = (new Select())
                ->from(['d' => 'packages_details'])
                ->columns(['package_id'])
                ->join(array('r' => 'acl_rules'), 'r.rule_id = d.rule_id', [])
                ->where(['r.rule_check_id' => $ruleCheckId]);

            $packageId = $this->_db2->fetchOne($select);
        }

        return $packageId;
    }

    public function createDefaultPackages($companyId)
    {
        $arrDefaultPackages = $this->getPackages(true, true);
        if (is_array($arrDefaultPackages) && count($arrDefaultPackages) > 0) {
            foreach ($arrDefaultPackages as $packageId) {
                $this->_db2->insert(
                    'company_packages',
                    [
                        'company_id' => $companyId,
                        'package_id' => $packageId
                    ]
                );
            }
        }
    }

    /**
     * Update company pages, update roles access rights if needed
     *
     * @param int $companyId
     * @param array $arrPackagesIds
     * @param bool $booUpdateRolesAccessRights
     * @return bool
     */
    public function updateCompanyPackages($companyId, $arrPackagesIds, $booUpdateRolesAccessRights = true)
    {
        try {
            // Check if we need update packages/roles
            $arrCompanyCurrentPackages = $this->getCompanyPackages($companyId);

            $booNeedUpdate = false;
            foreach ($arrPackagesIds as $newPackageId) {
                if (!in_array($newPackageId, $arrCompanyCurrentPackages)) {
                    $booNeedUpdate = true;
                    break;
                }
            }

            foreach ($arrCompanyCurrentPackages as $oldCompPackageId) {
                if (!in_array($oldCompPackageId, $arrPackagesIds)) {
                    $booNeedUpdate = true;
                    break;
                }
            }

            if ($booNeedUpdate) {
                // Delete all previously saved packages
                $this->_db2->delete('company_packages', ['company_id' => $companyId]);

                // Save all new packages
                foreach ($arrPackagesIds as $packageId) {
                    $this->_db2->insert('company_packages', ['company_id' => $companyId, 'package_id' => $packageId]);
                }


                // Update roles access list
                if ($booUpdateRolesAccessRights) {
                    // 1. Get rules ids for selected packages
                    $arrPackagesRules = array_unique($this->getPackagesRules($arrPackagesIds));

                    if (!empty($arrPackagesRules)) {
                        // Remove specific rules if website settings don't allow them
                        $arrExcludedRules = $this->_roles->getCompanyExcludedRules($companyId);

                        foreach ($arrExcludedRules as $excludedRuleId) {
                            if (($key = array_search($excludedRuleId, $arrPackagesRules)) !== false) {
                                unset($arrPackagesRules[$key]);
                            }
                        }
                    }

                    // 2. Get all company roles
                    $arrRoles = $this->_parent->getCompanyRoles($companyId);
                } else {
                    $arrRoles         = array();
                    $arrPackagesRules = array();
                }

                if (is_array($arrRoles) && count($arrRoles) > 0 &&
                    is_array($arrPackagesRules) && count($arrPackagesRules) > 0
                ) {
                    $arrQuotedRoles  = array();
                    $arrRolesIds     = array();
                    $arrRolesIdsOnly = array();
                    foreach ($arrRoles as $roleInfo) {
                        $arrQuotedRoles[]                      = $roleInfo['role_parent_id'];
                        $arrRolesIds[$roleInfo['role_type']][] = $roleInfo['role_id'];
                        $arrRolesIdsOnly[]                     = $roleInfo['role_id'];
                    }

                    // Remove access to rules which are not in selected packages
                    $this->_db2->delete(
                        'acl_role_access',
                        [
                            'role_id' => $arrQuotedRoles,
                            (new Where())->notIn('rule_id', $arrPackagesRules)
                        ]
                    );

                    // Enable access rights to new functionality in new packages
                    // for specific users (admin which can not edit his role)
                    $booRolesAccessResult = $this->assignRolesAccessRights($arrRoles, $arrPackagesRules);

                    // Enable fields access rights
                    // TODO This doesn't relate to Packages, should be removed out of here
                    $booFieldsResult = $this->assignFieldsAccessRights($companyId, $arrRolesIds, $arrRolesIdsOnly);

                    // Enable or disable Prospects
                    $booProspectsAccessResult = $this->enableDisableProspects($companyId, $arrCompanyCurrentPackages, $arrPackagesIds);

                    // General result
                    $booResult = $booRolesAccessResult && $booFieldsResult && $booProspectsAccessResult;
                } else {
                    $booResult = true;
                }

                $this->_roles->clearCompanyRolesCache($companyId);
            } else {
                // Nothing was changed
                $booResult = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booResult = false;
        }

        return $booResult;
    }

    /**
     * Automatically assign fields access rights for 'username' and 'password' fields
     *
     * @param int $companyId
     * @param array $arrRolesIds
     * @param array $arrRolesIdsOnly
     * @return bool result
     */
    public function assignFieldsAccessRights($companyId, $arrRolesIds, $arrRolesIdsOnly)
    {
        try {
            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);

            // Enable access to the field in relation to the role/package
            $arrDefaultRoles = $this->_parent->getCompanyRoles(0, true);
            if (is_array($arrDefaultRoles) && !empty($arrDefaultRoles)) {
                $usernameFieldId = $clients->getFields()->getCompanyFieldId($companyId, 'username', false);
                $passwordFieldId = $clients->getFields()->getCompanyFieldId($companyId, 'password', false);

                if ($this->canCompanyClientLogin($companyId)) {
                    // So must be access to username and password fields (described in default roles)
                    $select = (new Select())
                        ->from(array('a' => 'client_form_field_access'))
                        ->columns(['role_id', 'field_id', 'status'])
                        ->join(array('r' => 'acl_roles'), 'r.role_id = a.role_id', 'role_type', Select::JOIN_LEFT_OUTER)
                        ->where([
                            (new Where())
                                ->nest()
                                ->equalTo('a.field_id', $usernameFieldId)
                                ->or
                                ->equalTo('a.field_id', $passwordFieldId)
                                ->unnest(),
                            'a.role_id' => $arrDefaultRoles
                        ]);

                    $arrDefaultFieldsAccess = $this->_db2->fetchAll($select);

                    // Allow access to username and password fields
                    if (is_array($arrDefaultFieldsAccess) && !empty($arrDefaultFieldsAccess)) {
                        $arrAccess = array();
                        foreach ($arrDefaultFieldsAccess as $arrDefaultFieldsAccessInfo) {
                            if (isset($arrRolesIds[$arrDefaultFieldsAccessInfo['role_type']]) && is_array($arrRolesIds[$arrDefaultFieldsAccessInfo['role_type']]) && !empty($arrRolesIds[$arrDefaultFieldsAccessInfo['role_type']])) {
                                foreach ($arrRolesIds[$arrDefaultFieldsAccessInfo['role_type']] as $roleId) {
                                    $arrAccess[] = array(
                                        'role_id'  => $roleId,
                                        'field_id' => $arrDefaultFieldsAccessInfo['field_id'],
                                        'status'   => $arrDefaultFieldsAccessInfo['status']
                                    );
                                }
                            }
                        }

                        $clients->getFields()->createFieldAccessRights($arrAccess);
                    }

                } else {
                    // Delete access to username/password fields for current company
                    if (!is_array($usernameFieldId)) {
                        $usernameFieldId = [$usernameFieldId];
                    }

                    if (!is_array($passwordFieldId)) {
                        $passwordFieldId = [$passwordFieldId];
                    }

                    $this->_db2->delete(
                        'client_form_field_access',
                        [
                            'role_id' => $arrRolesIdsOnly,
                            (new Where())
                                ->nest()
                                ->in('field_id', $usernameFieldId)
                                ->or
                                ->in('field_id', $passwordFieldId)
                                ->unnest()
                        ]
                    );
                }
            }

            $booResult = true;

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booResult = false;
        }

        return $booResult;
    }

    /**
     * Automatically assign access rights for role which:
     * a) Is company admin
     * b) Can not edit this role
     *
     * @param array $arrRoles
     * @param array $arrRulesIds
     * @return bool result
     */
    public function assignRolesAccessRights($arrRoles, $arrRulesIds)
    {
        try {
            if (is_array($arrRoles) && count($arrRoles) > 0 &&
                is_array($arrRulesIds) && count($arrRulesIds) > 0
            ) {
                foreach ($arrRoles as $roleInfo) {
                    // Check if this is required role
                    if ($roleInfo['role_type'] == 'admin') {
                        foreach ($arrRulesIds as $rule) {
                            $this->_db2->insert(
                                'acl_role_access',
                                [
                                    'role_id' => $roleInfo['role_parent_id'],
                                    'rule_id' => $rule
                                ],
                                null,
                                false
                            );
                        }
                    }
                }
            }

            $booResult = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booResult = false;
        }

        return $booResult;
    }


    public function canCompanyClientLogin($companyId)
    {
        if (empty($companyId)) {
            return true;
        }

        $arrCompanyPackages = $this->getCompanyPackages($companyId);

        return (is_array($arrCompanyPackages) && !empty($arrCompanyPackages) && in_array($this->_config['site_version']['package']['client_login_allowed'], $arrCompanyPackages));
    }

    /**
     * Load subscription name by its id
     *
     * @param string $subscriptionId
     * @return string
     */
    public function getSubscriptionNameById($subscriptionId)
    {
        $subscriptionName = '';
        if (!empty($subscriptionId)) {
            $select = (new Select())
                ->from('subscriptions')
                ->columns(['subscription_name'])
                ->where(['subscription_id' => $subscriptionId]);

            $subscriptionName = $this->_db2->fetchOne($select);
            $subscriptionName = empty($subscriptionName) ? '' : $subscriptionName;
        }

        return $subscriptionName;
    }

    /**
     * Load list of available subscriptions
     *
     * @param bool $booIdsOnly
     * @param bool $booShowAll
     * @return array
     */
    public function getSubscriptionsList($booIdsOnly = false, $booShowAll = false)
    {
        // We don't want to show specific subscriptions everywhere
        // e.g. "Ultimate Plus" we need to show only for superadmin, when edit the company
        $select = (new Select())
            ->from('subscriptions')
            ->columns($booIdsOnly ? ['subscription_id'] : ['subscription_id', 'subscription_name'])
            ->order('subscription_order');
        if (!$booShowAll) {
            $select->where(
                [
                    (new Where())->notEqualTo('subscription_hidden', 'Y')
                ]);
        }

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load packages list for specific subscription
     * @param string $subscriptionId
     * @return array
     */
    public function getPackagesBySubscriptionId($subscriptionId)
    {
        $arrPackagesIds = array();

        if (!empty($subscriptionId)) {
            $select = (new Select())
                ->from('subscriptions_packages')
                ->columns(['package_id'])
                ->where(['subscription_id' => $subscriptionId]);

            $arrPackagesIds = $this->_db2->fetchCol($select);
        }

        return empty($arrPackagesIds) ? array(1) : $arrPackagesIds;
    }

    /**
     * Load subscription name by provided array of package ids
     * @example
     *  array(1)       => Lite
     *  array(1,2)     => Pro
     *  array(1,3)     => Pro (Pack 1 and 3)
     *  array(1,2,3)   => Ultimate
     *  array(1,2,3,4) => Ultimate Plus
     * @param array $arrPackages
     * @return string
     */
    public function getSubscriptionNameByPackageIds(array $arrPackages)
    {
        $select = (new Select())
            ->from('subscriptions_packages');

        $arrSubscriptionsPackages = $this->_db2->fetchAll($select);

        $arrGrouped = array();
        foreach ($arrSubscriptionsPackages as $arrSubscriptionsPackage) {
            $arrGrouped[$arrSubscriptionsPackage['subscription_id']][] = $arrSubscriptionsPackage['package_id'];
        }

        $packageName = 'lite';
        foreach ($arrGrouped as $subscriptionId => $arrSubscriptionPackages) {
            if (Settings::areArraysEqual($arrPackages, $arrSubscriptionPackages)) {
                $packageName = $subscriptionId;
                break;
            }
        }

        return $packageName;
    }

    /**
     * @param $paymentTerm
     * @param $support
     * @param $price
     * @return float|int $fee
     */
    public function getSupportFee($paymentTerm, $support, $price)
    {
        if ($this->_config['site_version']['version'] == 'australia') {
            $fee = 0;
        } else {
            $fee = ($paymentTerm == 3 || $support == 'N') ? 0 : (float)$price;
        }
        return $fee;
    }

}
