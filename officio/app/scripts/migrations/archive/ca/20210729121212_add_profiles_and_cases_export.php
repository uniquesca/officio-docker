<?php

use Officio\Migration\AbstractMigration;

class addProfilesAndCasesExport extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => 'allow-export'])
            ->execute();

        $parentRuleId = false;

        $row = $statement->fetch();
        if (!empty($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        $this->table('acl_rule_details')
            ->insert([
                'rule_id'            => $parentRuleId,
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-company',
                'resource_privilege' => 'export-profiles-and-cases',
                'rule_allow'         => 1,
            ])
            ->save();

        $this->table('acl_rule_details')
            ->insert([
                'rule_id'            => $parentRuleId,
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-company',
                'resource_privilege' => 'generate-profiles-and-cases-export-file',
                'rule_allow'         => 1,
            ])
            ->save();

        $this->table('acl_rule_details')
            ->insert([
                'rule_id'            => $parentRuleId,
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-company',
                'resource_privilege' => 'download-exported-profiles-and-cases',
                'rule_allow'         => 1,
            ])
            ->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE resource_id = 'manage-company' AND resource_privilege = 'export-profiles-and-cases'");
        $this->execute("DELETE FROM acl_rule_details WHERE resource_id = 'manage-company' AND resource_privilege = 'generate-profiles-and-cases-export-file'");
        $this->execute("DELETE FROM acl_rule_details WHERE resource_id = 'manage-company' AND resource_privilege = 'download-exported-profiles-and-cases'");
    }
}