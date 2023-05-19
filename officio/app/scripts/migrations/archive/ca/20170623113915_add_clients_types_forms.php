<?php

use Officio\Migration\AbstractMigration;

class addClientsTypesForms extends AbstractMigration
{
    public function up()
    {
        $this->execute('
                CREATE TABLE `client_types_forms` (
                `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                `form_version_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                CONSTRAINT `FK_client_types_forms_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_client_types_forms_formversion` FOREIGN KEY (`form_version_id`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COLLATE=\'utf8_general_ci\'
            ENGINE=InnoDB;            
        ');

        $this->execute("
            INSERT INTO `client_types_forms`
            (`client_type_id`, `form_version_id`)
            SELECT ct.client_type_id, ct.form_version_id
            FROM `client_types` as ct
            WHERE form_version_id IS NOT NULL;
        ");

        $this->execute("ALTER TABLE `client_types` DROP FOREIGN KEY `FK_client_types_FormVersion`;");
        $this->execute("ALTER TABLE `client_types` DROP COLUMN `form_version_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_types`
            ADD COLUMN `form_version_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`;
        ");

        $this->execute("ALTER TABLE `client_types`
            ADD CONSTRAINT `FK_client_types_FormVersion` FOREIGN KEY (`form_version_id`) REFERENCES `formversion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE SET NULL;
        ");

        $statement = $this->getQueryBuilder()
            ->select(array('client_type_id', 'form_version_id'))
            ->from('client_types_forms')
            ->whereNotNull('form_version_id')
            ->group('client_type_id')
            ->execute();

        $clientsForms = $statement->fetchAll('assoc');

        foreach ($clientsForms as $item) {
            $this->getQueryBuilder()
                ->update('client_types')
                ->set(['form_version_id' => $item['form_version_id']])
                ->where(['client_type_id' => (int)$item['client_type_id']])
                ->execute();
        }

        $this->execute("DROP TABLE `client_types_forms`;");
    }
}
