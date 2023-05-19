<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddMissingOffices extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var \Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from(array('c' => 'company'), array('company_id'))
                ->joinLeft(array('d' => 'divisions'), 'c.company_id = d.company_id', '')
                ->where('d.company_id IS NULL');

            $arrCompanyIds = $db->fetchCol($select);

            foreach ($arrCompanyIds as $companyId) {
                $db->insert(
                    'divisions',
                    array(
                        'company_id' => $companyId,
                        'name'       => 'Main',
                    )
                );
                $divisionId = $db->lastInsertId('divisions');

                $select = $db->select()
                    ->from('members', 'member_id')
                    ->where('company_id = ?', $companyId);

                $arrMemberIds = $db->fetchCol($select);

                foreach ($arrMemberIds as $memberId) {
                    $db->insert(
                        'members_divisions',
                        array(
                            'member_id'   => $memberId,
                            'division_id' => $divisionId
                        )
                    );
                }
            }

            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }

            echo 'Done.' . PHP_EOL;

        } catch (\Exception $e) {
            echo 'Fatal error' . $e->getTraceAsString();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            throw $e;
        }
    }

    public function down()
    {
    }
}