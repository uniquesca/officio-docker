<?php

use Officio\Migration\AbstractMigration;

class RemoveCaseTypesFromFieldsAccess extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('client_form_field_access');

        if ($table->hasColumn('client_type_id')) {
            $this->execute('ALTER TABLE `client_form_field_access` DROP FOREIGN KEY `FK_client_form_field_access_client_types`;');
            $this->execute("ALTER TABLE `client_form_field_access` DROP COLUMN `client_type_id`");

            $arrSavedAccess = $this->fetchAll('SELECT * FROM client_form_field_access');

            $arrGroupedAccess = array();
            foreach ($arrSavedAccess as $arrSavedAccessRow) {
                $arrGroupedAccess[$arrSavedAccessRow['role_id'] . '_' . $arrSavedAccessRow['field_id']][$arrSavedAccessRow['status']][] = $arrSavedAccessRow['access_id'];
            }

            // leave only 1 record for the role/field pair
            // if there is F - leave it, otherwise leave 1 R
            $arrIdsToDelete = array();
            foreach ($arrGroupedAccess as $arrAccesses) {
                if (isset($arrAccesses['F'])) {
                    if (isset($arrAccesses['R'])) {
                        // delete all "read"
                        $arrIdsToDelete = array_merge($arrAccesses['R'], $arrIdsToDelete);
                    }

                    if (count($arrAccesses['F']) > 1) {
                        // delete "full" all except of 1
                        array_shift($arrAccesses['F']);
                        $arrIdsToDelete = array_merge($arrAccesses['F'], $arrIdsToDelete);
                    }
                } else {
                    if (count($arrAccesses['R']) > 1) {
                        // delete all "read" except of 1
                        array_shift($arrAccesses['R']);
                        $arrIdsToDelete = array_merge($arrAccesses['R'], $arrIdsToDelete);
                    }
                }
            }

            if (!empty($arrIdsToDelete)) {
                $query = sprintf(
                    "DELETE FROM `client_form_field_access` WHERE access_id IN (%s)",
                    implode(',', $arrIdsToDelete)
                );

                $this->execute($query);
            }
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_field_access` ADD COLUMN `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_id`;");
        $this->execute("ALTER TABLE `client_form_field_access` ADD CONSTRAINT `FK_client_form_field_access_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;");

        // Took 1681.2992s on the local server...

        $builder      = $this->getQueryBuilder();
        $statement    = $builder
            ->select(['company_id', 'client_type_id'])
            ->from('client_types')
            ->execute();

        $arrClientTypes = $statement->fetchAll('assoc');

        $builder = $this->getQueryBuilder();
        $statement    = $builder
            ->select('*')
            ->from(['a' => 'client_form_field_access'])
            ->leftJoin(['f' => 'client_form_fields'], ['a.field_id = f.field_id', 'company_id'])
            ->whereNull(['a.client_type_id'])
            ->execute();

        $arrFields = $statement->fetchAll('assoc');

        foreach ($arrFields as $arrFieldInfo) {
            if (isset($arrClientTypes[$arrFieldInfo['company_id']])) {
                $builder = $this->getQueryBuilder();
                $builder->update('client_form_field_access')
                    ->set(['client_type_id' => $arrClientTypes[$arrFieldInfo['company_id']]['client_type_id']])
                    ->where(['access_id' => $arrFieldInfo['access_id']])
                    ->execute();
            } else {
                echo '<br>Not found: ' . $arrFieldInfo['field_id'] . '<br>';
            }
        }

        echo 'Done. Processed: ' . count($arrFields) . PHP_EOL;
    }
}