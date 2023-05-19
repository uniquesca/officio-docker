<?php

use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class UpdateDefaultLabels extends AbstractMigration
{
    public function up()
    {
        // Took 19s on local server...
        try {
            $this->table('company_details')
                ->addColumn('default_label_office', 'string', [
                    'limit' => 255,
                    'after' => 'subscription',
                    'null'  => true
                ])
                ->save();

            $this->table('company_details')
                ->addColumn('default_label_trust_account', 'string', [
                    'limit' => 255,
                    'after' => 'default_label_office',
                    'null'  => true
                ])
                ->save();


            $this->getQueryBuilder()
                ->update('company_details')
                ->set(array(
                    'default_label_office'        => 'office',
                    'default_label_trust_account' => 'client_account',
                ))
                ->execute();

        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
