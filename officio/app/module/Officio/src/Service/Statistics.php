<?php

namespace Officio\Service;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Settings;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Statistics extends BaseService
{
    /** @var DbAdapterWrapper */
    protected $_debugDb;

    public function initAdditionalServices(array $services)
    {
        $this->_debugDb = $services['debugDb'];
    }

    /**
     * Save statistics into the DB
     *
     * @param $module
     * @param $controller
     * @param $action
     * @param $time
     * @param $memory
     */
    public function save($module, $controller, $action, $time, $memory)
    {
        $identity = $this->_auth->getIdentity();
        $memberId = $identity->member_id ?? 0;

        $arrInsert = array(
            'statistic_date'        => date('c'),
            'statistic_member_id'   => $memberId,
            'statistic_module'      => $module,
            'statistic_controller'  => $controller,
            'statistic_action'      => $action,
            'statistic_ip'          => Settings::getCurrentIp(),
            'statistic_details'     => count($_REQUEST) ? print_r($_REQUEST, true) : null,
            'statistic_gen_time'    => $time,
            'statistic_memory_used' => $memory
        );

        $this->_debugDb->insert('statistics', $arrInsert);
    }


    public function getRecords($loadForDate, $type = 'hits')
    {
        $arrWhere = [];

        $arrWhere[] = new PredicateExpression('DATE(statistic_date) = ?', $loadForDate);

        if ($type == 'users') {
            $arrWhere[] = (new Where())->notEqualTo('statistic_member_id', 0);
        }

        $select = (new Select())
            ->from('statistics')
            ->columns([
                'name' => new Expression('HOUR(statistic_date)'),
                'hits' => new Expression('COUNT(' . ($type == 'hits' ? '*' : 'DISTINCT(statistic_member_id)') . ')')
            ])
            ->where($arrWhere)
            ->group('name');

        return $this->_debugDb->fetchAll($select);
    }

    public function deleteRecords($date)
    {
        $this->_debugDb->delete('statistics', [(new Where())->lessThan('statistic_date', $date)]);
    }


    /**
     * Load specific table size in Mb
     *
     * @param string $strTableName
     * @return float|false
     */
    public function getTableSize($strTableName = 'statistics')
    {
        $select = (new Select())
            ->columns([new Expression('SHOW TABLE STATUS LIKE ?', $strTableName)]);

        $arrData = $this->_debugDb->fetchRow($select);

        /* We return the size in Mb */
        return $arrData ? ($arrData['Data_length'] + $arrData['Index_length']) / 1024 / 1024 : false;
    }


    /**
     * Load Database size in Mb
     * @param bool $booStatistics
     * @return string
     */
    public function getDatabaseSize($booStatistics = true)
    {
        $db        = $booStatistics ? $this->_debugDb : $this->_db2;
        $dbSection = $booStatistics ? 'db_stat' : 'db';

        $select = (new Select())
            ->from(new TableIdentifier('TABLES', 'INFORMATION_SCHEMA'))
            ->columns(['data' => new Expression('SUM(data_length + index_length) / 1024 / 1024')])
            ->where([(new Where())->like('table_schema', $this->_config[$dbSection]['dbname'])]);

        return $db->fetchOne($select);
    }
}
