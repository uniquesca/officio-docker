<?php
namespace Websites\Service;

use Exception;
use Files\Service\Files;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyWebsitesTemplates extends BaseService
{
    /** @var Files */
    protected $_files;

    /** @var CompanyWebsites */
    protected $_companyWebsites;

    public function initAdditionalServices(array $services)
    {
        $this->_files  = $services[Files::class];
        $this->_companyWebsites = $services[CompanyWebsites::class];
    }

    public function init()
    {
        if (!is_dir(self::getPathToTemplates())) {
            $this->_files->createFTPDirectory(self::getPathToTemplates());
        }
    }

    /**
     * @static
     * @return string
     */
    private function getPathToTemplates()
    {
        return $this->_config['directory']['companyWebsiteTemplates'];
    }

    /**
     * @static
     * @param string $templateName
     * @return string
     */
    private function getPathToTemplate($templateName)
    {
        return $this->getPathToTemplates() . DIRECTORY_SEPARATOR . $templateName;
    }

    /**
     * @static
     * @param string $templateName
     * @return string
     */
    private function getPathToSettingsFile($templateName)
    {
        return $this->getPathToTemplate($templateName) . DIRECTORY_SEPARATOR . 'settings.php';
    }

    /**
     * @static
     * @param string $templateName
     * @return string
     */
    private function getPathToController($templateName)
    {
        return $this->getPathToTemplate($templateName) . DIRECTORY_SEPARATOR . 'controller.php';
    }

    /**
     * @static
     * @param string $templateName
     * @return bool
     */
    private function isTemplateImageAvailable($templateName)
    {
        return file_exists($this->getPathToTemplate($templateName) . DIRECTORY_SEPARATOR . 'template.jpg');
    }

    /**
     * @static
     * @return string
     */
    private function getPathToImagesLibrary()
    {
        return $this->getPathToTemplates() . DIRECTORY_SEPARATOR . 'lib';
    }

    /**
     * @static
     * @param string $module
     * @return array
     */
    private function getDefaultImages($module)
    {
        $folder = $this->getPathToImagesLibrary() . DIRECTORY_SEPARATOR . $module;

        if (is_dir($folder)) {
            $images = glob($folder . DIRECTORY_SEPARATOR . '{*.gif,*.jpg,*.jpeg,*.png}', GLOB_NOSORT | GLOB_BRACE);

            return str_replace($folder . DIRECTORY_SEPARATOR, '', $images);
        }

        return array();
    }

    /**
     * @param string $templateName
     * @return bool
     */
    private function isValidTemplate($templateName)
    {
        try {
            $templatePath = $this->getPathToTemplate($templateName);

            if (!is_dir($templatePath)) {
                throw new Exception(sprintf('Template not found in folder: %s', $templatePath));
            }

            if (!file_exists($templatePath . DIRECTORY_SEPARATOR . 'index.php')) {
                throw new Exception('Entry file of template not found (index.php)');
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getTemplates()
    {
        try {
            $select = (new Select())
                ->from('company_websites_templates')
                ->order(array('created_date ASC'));

            $templates = $this->_db2->fetchAll($select);

            foreach ($templates as &$template) {

                // validate template
                if (!$this->isValidTemplate($template['template_name'])) {
                    unset($template);
                    continue;
                }

                // check template image
                $template['imageAvailable'] = $this->isTemplateImageAvailable($template['template_name']);

                // decode options
                if (!empty($template) && !empty($template['options'])) {
                    $template['options'] = Json::decode($template['options'], Json::TYPE_ARRAY);
                }

            }
            unset($template);

            return $templates;

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            return array();
        }
    }

    /**
     * @param int $templateId
     * @return array|bool
     */
    public function getTemplate($templateId)
    {
        try {

            if (empty($templateId)) {
                throw new Exception('Incorrect template ID');
            }

            // try to get data from cache
            if (!($template = $this->_cache->getItem('website_template_' . $templateId))) {

                // get template
                $select = (new Select())
                    ->from('company_websites_templates')
                    ->where(['id' => (int)$templateId]);

                $template = $this->_db2->fetchRow($select);

                // format template data
                if (!empty($template)) {
                    // validate template
                    if (!$this->isValidTemplate($template['template_name'])) {
                        throw new Exception('Corrupted template data');
                    }

                    // variables
                    $template['imageAvailable']     = $this->isTemplateImageAvailable($template['template_name']);
                    $template['path']               = $this->getPathToTemplate($template['template_name']);
                    $template['pathToSettingsFile'] = $this->getPathToSettingsFile($template['template_name']);
                    $template['pathToController']   = $this->getPathToController($template['template_name']);

                    // get default images
                    $template['defaultBackgrounds'] = $this->getDefaultImages('bg');
                    $template['defaultSlides']      = $this->getDefaultImages('sld');

                    if (!empty($template) && !empty($template['options'])) {
                        // decode options
                        $template['options'] = Json::decode($template['options'], Json::TYPE_ARRAY);
                    } else {
                        $template['options'] = array();
                    }
                }

                // save in cache
                $cacheKey = 'website_template_' . $templateId;
                $this->_cache->setItem($cacheKey, $template);
                if ($this->_cache instanceof TaggableInterface) {
                    $this->_cache->setTags($cacheKey, array('website_templates'));
                }
            }

            return $template;

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            return false;
        }
    }

    public function clearCache()
    {
        if ($this->_cache instanceof TaggableInterface) {
            $this->_cache->clearByTags(array('website_templates'));
        }
    }
}
