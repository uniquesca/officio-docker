<?php

use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class SetTaOffices extends AbstractMigration
{
    public function up()
    {
        // Took 1s on local server...
        try {
            $statement = $this->getQueryBuilder()
                ->select(array('division_id', 'company_id'))
                ->from('divisions')
                ->execute();

            $arrOffices = $statement->fetchAll('assoc');

            $statement = $this->getQueryBuilder()
                ->select(array('company_ta_id', 'company_id'))
                ->from('company_ta')
                ->execute();

            $arrTa = $statement->fetchAll('assoc');

            $arrTADivisions = [];
            foreach ($arrTa as $arrTaInfo) {
                foreach ($arrOffices as $arrOfficeInfo) {
                    if ($arrOfficeInfo['company_id'] == $arrTaInfo['company_id']) {
                        $arrTADivisions[] = sprintf(
                            '(%d, %d)',
                            $arrTaInfo['company_ta_id'],
                            $arrOfficeInfo['division_id']
                        );
                    }
                }
            }

            if (!empty($arrTADivisions)) {
                $this->query('INSERT INTO company_ta_divisions (company_ta_id, division_id) VALUES ' . implode(',', $arrTADivisions));
            }
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
