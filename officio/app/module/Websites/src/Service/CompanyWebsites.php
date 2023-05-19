<?php
namespace Websites\Service;

use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Uniques\Php\StdLib\FileTools;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;
use Laminas\Db\Sql\Expression;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyWebsites extends BaseService
{
    /** @var Files */
    private $_files;

    /** @var Company */
    protected $_company;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var CompanyWebsitesTemplates */
    protected $_companyWebsitesTemplates;

    public  function initAdditionalServices(array $services)
    {
        $this->_files =  $services[Files::class];
        $this->_company = $services[Company::class];
        $this->_triggers = $services[SystemTriggers::class];
    }

    public function init() {
        $this->_triggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_COPY_DEFAULT_SETTINGS, [$this, 'onCopyCompanyDefaultSettings']);
    }

    public function getCompanyWebsitesTemplates() {
        if (is_null($this->_companyWebsitesTemplates)) {
            $this->_companyWebsitesTemplates = new CompanyWebsitesTemplates($this->_config, $this->_db2, $this->_auth, $this->_acl, $this->_cache, $this->_log, $this->_tr, $this->_settings, $this->_tools);
            $this->_companyWebsitesTemplates->initAdditionalServices(
                [
                    Files::class => $this->_files,
                    __CLASS__    => $this
                ]
            );
            $this->_companyWebsitesTemplates->init();
        }

        return $this->_companyWebsitesTemplates;
    }

    public function onCopyCompanyDefaultSettings(EventInterface $event) {
        $fromCompanyId = $event->getParam('fromId');
        $toCompanyId = $event->getParam('toId');
        $this->_createDefaultWebsiteSettings($fromCompanyId, $toCompanyId);
    }

    /**
     * @return string
     */
    private static function getPathToFiles()
    {
        return 'public';
    }

    /**
     * @static
     * @return string
     */
    private static function getPathToUploadedFiles()
    {
        return self::getPathToFiles() . '/' . 'website';
    }

    /**
     * @param int $companyId
     * @return array
     * @throws Exception
     */
    public function getCompanyWebsite($companyId)
    {
        try {

            // try to get data from cache
            if (!($website = $this->_cache->getItem('website' . $companyId))) {

                // validate company ID
                if (empty($companyId) && $companyId !== '0' && !$this->_auth->isCurrentUserSuperadmin()) {
                    throw new Exception('Incorrect company ID received');
                }

                // get website data
                $select = (new Select())
                    ->from('company_websites')
                    ->where(['company_id' => (int)$companyId]);

                $website = $this->_db2->fetchRow($select);
                if (!empty($website)) {

                    // decode options
                    $website['options']        = empty($website['options']) ? array() : Json::decode($website['options'], Json::TYPE_ARRAY);
                    $website['external_links'] = empty($website['external_links']) ? array() : Json::decode($website['external_links'], Json::TYPE_ARRAY);

                    // decode map coordinates
                    if ($website['contact_map'] == 'Y' && !empty($website['contact_map_coords'])) {
                        $coords                        = explode(',', $website['contact_map_coords'] ?? '');
                        $website['contact_map_coords'] = array('x' => $coords[0], 'y' => $coords[1]);
                    } else {
                        $website['contact_map_coords'] = array('x' => '', 'y' => '');
                    }
                }

                // save in cache
                $this->_cache->setItem('website' . $companyId, $website);
            }

            return $website;

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            return array();
        }
    }

    /**
     * @param string $entranceName
     * @return array
     */
    public function getCompanyWebsiteByEntrance($entranceName)
    {
        $arrCompanyWebSite = array();
        try {
            // get company ID by entrance name
            $select = (new Select())
                ->from('company_websites')
                ->columns(['company_id'])
                ->where(['entrance_name' => $entranceName]);

            $companyId = $this->_db2->fetchOne($select);

            // return company website
            if (is_numeric($companyId) && !empty($companyId) && $companyId !== '0' || ctype_digit($companyId)) {
                $arrCompanyWebSite = $this->getCompanyWebsite($companyId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCompanyWebSite;
    }

    /**
     * @static
     * @param int $companyId
     * @param string $option
     * @return string
     */
    public static function generateFilePrefix($companyId, $option)
    {
        return $companyId . '.' . md5($option) . '.';
    }

    /**
     * @param int $companyId
     * @param array $data
     * @return array
     */
    public function saveWebsite($companyId, $data)
    {
        try {

            // validate company id
            if (empty($companyId) && !$this->_auth->isCurrentUserSuperadmin()) {
                throw new Exception('Incorrect company ID');
            }

            // validate template id
            if (empty($data['template_id'])) {
                throw new Exception('Incorrect template ID');
            }

            // get previous data
            $website = $this->getCompanyWebsite($companyId);

            // save logo
            if (isset($data['company_logo']) && is_array($data['company_logo'])) {

                // save new name
                $newName = self::generateFilePrefix($companyId, 'company_logo') . FileTools::getFileExtension($data['company_logo']['name']);

                // check logo size
                $size = getimagesize($data['company_logo']['tmp_name']);
                $size = ($size && ($size[0] > 220 || $size[1] > 120)) ? 220 : array();

                // save new file
                $result = $this->_files->saveImage(self::getPathToUploadedFiles(), 'company_logo', $newName, $size, true);
                if ($result['error']) {
                    throw new Exception($result['result']);
                } else {
                    $data['company_logo'] = $newName;
                }
            }

            // save assessment banner
            if (isset($data['assessment_banner']) && is_array($data['assessment_banner'])) {

                // save new name
                $newName = self::generateFilePrefix($companyId, 'assessment_banner') . FileTools::getFileExtension($data['assessment_banner']['name']);

                // save new file
                $result = $this->_files->saveImage(static::getPathToUploadedFiles(), 'assessment_banner', $newName, array(), true);
                if ($result['error']) {
                    throw new Exception($result['result']);
                } else {
                    $data['assessment_banner'] = $newName;
                }
            }

            // save uploads
            foreach ($data['options'] as $name => $option) {
                if (is_array($data['options'][$name])) {
                    foreach ($data['options'][$name] as &$subOpt) {
                        if (is_array($subOpt) && isset($subOpt['tmp_name'])) {

                            // generate new name
                            $newName = self::generateFilePrefix($companyId, $subOpt['name']) . FileTools::getFileExtension($subOpt['name']);

                            // save new file
                            $result = $this->_files->saveImage(self::getPathToUploadedFiles(), $name, $newName, array(), true);
                            if ($result['error']) {
                                throw new Exception($result['result']);
                            }

                            // save new name
                            $subOpt = '/website/' . $newName;
                        }
                    }
                    unset($subOpt);
                }
            }

            $data['options']            = Json::encode($data['options']);
            $data['external_links']     = Json::encode($data['external_links']);
            $data['contact_map_coords'] = implode(',', $data['contact_map_coords']);

            if (empty($data['script_google_analytics'])) {
                $data['script_google_analytics'] = null;
            }

            if (empty($data['script_facebook_pixel'])) {
                $data['script_facebook_pixel'] = null;
            }

            // save website
            if (empty($website)) {
                $data['company_id']    = $companyId;
                $data['created_date']  = date('Y-m-d H:i:s');
                $data['entrance_name'] = $this->generateEntranceName($companyId);

                $this->_db2->insert('company_websites', $data);

            } else {
                $data['updated_date'] = date('Y-m-d H:i:s');

                $this->_db2->update('company_websites', $data, ['company_id' => $companyId]);
            }

            // clear cache
            $this->_cache->removeItem('website' . $companyId);

            $result = array('success' => true);

        } catch (Exception $e) {
            if ($e->getMessage() != 'Logo validation failure') {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }

            $result = array('success' => false, 'message' => $e->getMessage());
        }

        return $result;
    }

    public function getOptions($companyId)
    {
        try {
            $select = (new Select())
                ->from('company_websites')
                ->columns(['options'])
                ->where(['company_id' => (int)$companyId]);

            $options = $this->_db2->fetchOne($select);
            if (empty($options)) {
                return array();
            }

            return Json::decode($options, Json::TYPE_ARRAY);

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array();
    }

    /**
     * @param int $companyId
     * @param string $option
     */
    public function removeImage($companyId, $option)
    {
        try {

            // validate company id
            if (empty($companyId) && !$this->_auth->isCurrentUserSuperadmin()) {
                throw new Exception('Incorrect company ID');
            }

            // validate option
            if (empty($option) || !in_array($option, array('company_logo', 'assessment_banner'))) {
                throw new Exception('Incorrect option');
            }

            // get image name
            $select = (new Select())
                ->from('company_websites')
                ->columns(['cname' => $option])
                ->where(['company_id' => (int)$companyId]);

            $imageName = $this->_db2->fetchOne($select);

            // update website data
            $this->_db2->update('company_websites', [$option => null], ['company_id' => $companyId]);

            // remove image
            $path = self::getPathToUploadedFiles() . '/' . $imageName;
            if (file_exists($path)) {
                unlink($path);
            }

            // clear cache
            $this->_cache->removeItem('website' . $companyId);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param int $companyId
     * @param string $option
     * @return bool
     * @throws Exception
     */
    public function removeOptionImage($companyId, $option)
    {
        try {

            // validate company id
            if (empty($companyId) && !$this->_auth->isCurrentUserSuperadmin()) {
                throw new Exception('Incorrect company ID');
            }

            // validate option
            if (empty($option) || strpos('/lib/', $option) === 0) {
                throw new Exception('Incorrect option');
            }

            // get options
            $options = $this->getOptions($companyId);
            if (!empty($options)) {
                foreach ($options as $key => &$val) {
                    if (is_array($val) && in_array($option, $val)) {
                        $val = array_diff($val, array($option));
                        if (empty($val)) {
                            unset($options[$key]);
                        }
                    } else {
                        if ($val == $option) {
                            unset($val);
                        }
                    }
                }
                unset($val);
            } else {
                throw new Exception("Cannot load template options. Please try again later.");
            }

            // update options
            $this->_db2->update(
                'company_websites',
                ['options' => Json::encode($options)],
                ['company_id' => $companyId]
            );

            // remove file
            $path = self::getPathToFiles() . '/' . $option;
            if (file_exists($path)) {
                unlink($path);
            }

            // clear cache
            $this->_cache->removeItem('website' . $companyId);

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return false;
    }

    /**
     * @return array
     */
    public function getPagesList()
    {
        return array('homepage', 'about', 'canada', 'immigration', 'assessment', 'contact');
    }

    /**
     * @static
     * @param $string
     * @param int $limit
     * @return string
     */
    private static function seoUrl($string, $limit = 100)
    {
        // Strip any HTML tags
        $string = strip_tags($string ?? '');

        if ($limit > 0) {
            $string = substr($string, 0, $limit);
        }
        //Unwanted:  {UPPERCASE} ; / ? : @ & = + $ , . ! ~ * ' ( )
        $string = strtolower($string);

        //Strip any unwanted characters
        $string = preg_replace("/[^a-z0-9_\s-]/", "", $string);

        //Clean multiple dashes or whitespaces
        $string = preg_replace("/[\s-]+/", " ", $string);

        //Convert whitespaces and underscore to dash
        return preg_replace("/[\s_]/", "-", $string);
    }

    /**
     * Generates entrance name which then will be used as part of a website link
     * @param int $companyId
     * @return string
     * @throws Exception
     */
    public function generateEntranceName($companyId)
    {
        try {
            $companyInfo = $this->_company->getCompanyInfo($companyId);
            $name        = $companyInfo['companyName'];

            // format name
            $name = self::seoUrl($name);

            // check if company name is available
            $counter = 1;
            while ($this->isEntranceNameAlreadyUsed($name, $companyId)) {
                $name = 'company' . $companyId . '_' . $counter++;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $name;
    }

    /**
     * Check if company with provided entrance name exists
     * (except of companies with $companyId)
     *
     * @param  $entranceName
     * @param int $companyId
     * @return bool true if entrance name exists
     */
    public function isEntranceNameAlreadyUsed($entranceName, $companyId = 0)
    {
        $select = (new Select())
            ->from('company_websites')
            ->columns(['count' => new Expression('COUNT(id)')])
            ->where([
                'entrance_name' => $entranceName,
                (new Where())->notEqualTo('company_id', (int)$companyId)
            ]);

        $count = $this->_db2->fetchOne($select);

        return !empty($count);
    }

    /**
     * Create default website settings for new company (based on default)
     *
     * @param $fromCompanyId
     * @param $toCompanyId
     */
    public function _createDefaultWebsiteSettings($fromCompanyId, $toCompanyId)
    {
        $select = (new Select())
            ->from('company_websites')
            ->where(['company_id' => (int)$fromCompanyId]);

        $arrDefaultWebsiteSettings = $this->_db2->fetchRow($select);
        if (!empty($arrDefaultWebsiteSettings)) {
            unset($arrDefaultWebsiteSettings['id']);
            $arrDefaultWebsiteSettings['company_id']    = $toCompanyId;
            $arrDefaultWebsiteSettings['entrance_name'] = $this->generateEntranceName($toCompanyId);

            $this->_db2->insert('company_websites', $arrDefaultWebsiteSettings);
        }
    }

    public function changeStatusBuilder($companyId, $switchToOldBuilder)
    {
        try {
            // validate company id
            if (empty($companyId) && !$this->_auth->isCurrentUserSuperadmin()) {
                throw new Exception('Incorrect company ID');
            }

            $this->_db2->update('company_websites', ['old' => $switchToOldBuilder], ['company_id' => $companyId]);
            if (($website = $this->_cache->getItem('website' . $companyId)) !== false) {
                $website['old'] = $switchToOldBuilder;
                $this->_cache->setItem('website' . $companyId, $website);
            }
            return true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Checking if active builder is new
     *
     * @param string $entranceName
     * @return bool true if does not exist or old builder
     */
    public function builderIsNew($entranceName)
    {
        try {
            $select = (new Select())
                ->from('company_websites')
                ->columns(['old'])
                ->where(['entrance_name' => $entranceName]);

            $old = $this->_db2->fetchOne($select);
            if ($old == 1 || ($old !== false && $old == 0)) {
                return $old;
            } else {
                return true;
            }
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Updating old builder data with data of new builder
     *
     * @param int $id
     * @param array $newBuilderPages new builder active pages
     * @param array $newBuilderData data of the new builder
     *
     * @return bool true on success
     */
    public function updateOldBuilderMainData($id, $newBuilderPages = [], $newBuilderData = [])
    {
        $mainData = [];
        if (!empty($newBuilderData) && count($newBuilderData) > 0) {
            if (!empty($newBuilderData['company_name'])) {
                $mainData['company_name'] = $newBuilderData['company_name'];
            }
            if (!empty($newBuilderData['address'])) {
                $mainData['contact_text'] = $newBuilderData['address'];
            }
            if (!empty($newBuilderData['phone'])) {
                $mainData['company_phone'] = $newBuilderData['phone'];
            }
            if (!empty($newBuilderData['fb_script'])) {
                $mainData['script_facebook_pixel'] = $newBuilderData['fb_script'];
            }
            if (!empty($newBuilderData['google_script'])) {
                $mainData['script_google_analytics'] = $newBuilderData['google_script'];
            }
            if (!empty($newBuilderData['title'])) {
                $mainData['title'] = $newBuilderData['title'];
            }
            if (!empty($newBuilderData['assessment_url'])) {
                $mainData['assessment_url'] = $newBuilderData['assessment_url'];
            }
        }
        foreach ($newBuilderPages as $key => $pageData) {
            $mainData[$key . '_name'] = $pageData['name'];
            $mainData[$key . '_on'] = empty($pageData['available']) ? "N" : "Y";
        }
        if (count($mainData) === 0) {
            return false;
        }

        try {
            $res = $this->_db2->update('company_websites', $mainData, ['id' => $id]) > 0;

            if (($website = $this->_cache->getItem('website' . $newBuilderData['company_id'])) !== false) {
                $website = array_merge($website, $mainData);
                $this->_cache->setItem('website' . $newBuilderData['company_id'], $website);
            }
            return $res;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }
}
