<?php

use Phinx\Migration\AbstractMigration;

class UpdateClientFormOrder extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_groups`
            ADD COLUMN `cols_count` INT(1) UNSIGNED NOT NULL DEFAULT 3 AFTER `order`;");

        $this->execute("ALTER TABLE `client_form_order`
            ADD COLUMN `use_full_row` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `field_id`;");

        $this->execute("ALTER TABLE `client_form_order`
            ADD COLUMN `field_order` INT(3) UNSIGNED NOT NULL DEFAULT 1 AFTER `use_full_row`;");

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('client_form_order')
            ->order(array('group_id', 'row', 'col'));
        $arrOrdersInfo = $db->fetchAll($select);

        $previousGroupId = 0;
        $order = 0;

        foreach ($arrOrdersInfo as $arrOrderInfo) {
            if ($previousGroupId != $arrOrderInfo['group_id']) {
                $order = 0;
            } else {
                $order++;
            }

            $previousGroupId = $arrOrderInfo['group_id'];
            $db->update(
                'client_form_order',
                array('field_order' => $order),
                $db->quoteInto('order_id = ?', $arrOrderInfo['order_id'], 'INT')
            );
        }

        $this->execute("ALTER TABLE `client_form_order`
            DROP COLUMN `row`,
            DROP COLUMN `col`;");

        $this->execute("ALTER TABLE `client_form_order`
            DROP FOREIGN KEY `FK_client_form_order_1`,
            DROP FOREIGN KEY `FK_client_form_order_2`;");

        $this->execute("ALTER TABLE `client_form_order`
            ADD CONSTRAINT `FK_client_form_order_1` FOREIGN KEY (`group_id`) REFERENCES `client_form_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            ADD CONSTRAINT `FK_client_form_order_2` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_groups`
            DROP COLUMN `cols_count`;");

        $this->execute("ALTER TABLE `client_form_order`
            ADD COLUMN `row` TINYINT(3) UNSIGNED NULL DEFAULT '1' AFTER `field_id`;");

        $this->execute("ALTER TABLE `client_form_order`
            ADD COLUMN `col` TINYINT(3) UNSIGNED NULL DEFAULT '1' AFTER `row`;");

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('client_form_order')
            ->order(array('group_id', 'field_order'));
        $arrOrdersInfo = $db->fetchAll($select);

        $previousGroupId = 0;
        $row = 1;
        $col = 0;

        foreach ($arrOrdersInfo as $arrOrderInfo) {
            if ($previousGroupId != $arrOrderInfo['group_id']) {
                $row = 1;
                $col = 0;
            }

            if ($col == 3) {
                $col = 1;
                $row++;
            } else {
                $col++;
            }

            $previousGroupId = $arrOrderInfo['group_id'];
            $db->update(
                'client_form_order',
                array(
                    'row' => $row,
                    'col' => $col
                ),
                $db->quoteInto('order_id = ?', $arrOrderInfo['order_id'], 'INT')
            );
        }

        $this->execute("ALTER TABLE `client_form_order`
            DROP COLUMN `use_full_row`,
            DROP COLUMN `field_order`;");

        $this->execute("ALTER TABLE `client_form_order`
            DROP FOREIGN KEY `FK_client_form_order_1`,
            DROP FOREIGN KEY `FK_client_form_order_2`;");

        $this->execute("ALTER TABLE `client_form_order`
            ADD CONSTRAINT `FK_client_form_order_1` FOREIGN KEY (`group_id`) REFERENCES `client_form_groups` (`group_id`),
            ADD CONSTRAINT `FK_client_form_order_2` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`);");
    }
}