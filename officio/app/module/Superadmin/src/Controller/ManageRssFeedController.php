<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;

/**
 * Manage RSS feed Settings Controller
 *
 * @author    Uniques Software Corp.
 * @copyright  Uniques
 */
class ManageRssFeedController extends BaseController
{
    public function indexAction()
    {
    }

    public function getListAction()
    {
        $select = (new Select())
            ->from('rss_black_list')
            ->order('domain');

        $domains = $this->_db2->fetchAll($select);

        $select = (new Select())
            ->from('rss_black_list')
            ->columns(['count' => new Expression('COUNT(id)')]);

        $count = $this->_db2->fetchOne($select);

        return new JsonModel(array('results' => $domains, 'count' => $count));
    }

    public function deleteAction()
    {
        try {
            $ids = Json::decode($this->findParam('ids'), Json::TYPE_ARRAY);

            if (!is_array($ids)) {
                $ids = array($ids);
            }

            $this->_db2->delete('rss_black_list', ['id' => $ids]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array("success" => $booSuccess));
    }

    public function editAction()
    {
        $id     = (int)$this->findParam('id');
        $domain = $this->findParam('domain');

        $error = '';

        if (filter_var(gethostbyname($domain), FILTER_VALIDATE_IP) === false) {
            $error = $this->_tr->translate('Please provide correct and existing domain name.');
        }

        if (!$error) {
            $data = array(
                'domain' => $domain,
            );

            if ($id) {
                $data['id'] = $id;
            }

            if ($id) {
                $this->_db2->update('rss_black_list', $data, ['id' => $id]);
            } else {
                $this->_db2->insert('rss_black_list', $data);
            }
        }

        return new JsonModel(array("success" => empty($error), "message" => $error));
    }
}
