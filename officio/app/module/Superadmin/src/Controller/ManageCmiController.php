<?php

namespace Superadmin\Controller;

use Files\Service\Files;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Import\CsvReader;
use Officio\Service\Company;

/**
 * Manage CMI Settings Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageCmiController extends BaseController
{
    /** @var Company */
    private $_company;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_files = $services[Files::class];
    }

    public function indexAction() {
        return new ViewModel();
    }
    
    public function getCmiListAction() {

        $view = new JsonModel();
        $filter = new StripTags();
        
        $start = (int) $this->findParam('start');
        $limit = (int) $this->findParam('limit');
        $query = $filter->filter($this->findParam('query'));
        
        $cmiList = $this->_company->getCompanyCMI()->searchCMI($query, $start, $limit);
        $totalRecords = $this->_company->getCompanyCMI()->getCMITotalRecords();
        return $view->setVariables(array('success' => true,
                                       'rows' => $cmiList,
                                       'totalCount' => $totalRecords));
    }

    public function importFromCsvAction() {
        
        $error = '';
        $file = '';
        $fileName = '';

        if(!empty($_FILES['import-file']['error'])) {
            switch($_FILES['import-file']['error']) {
                case '1' : $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';        break;
                case '2' : $error = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'; break;
                case '3' : $error = 'The uploaded file was only partially uploaded'; break;
                case '4' : $error = 'No file was uploaded.'; break;
                case '6' : $error = 'Missing a temporary folder'; break;
                case '7' : $error = 'Failed to write file to disk'; break;
                case '8' : $error = 'File upload stopped by extension'; break;
                case '999':
                default: $error = 'No error code available';
            }
        }
        else if(empty($_FILES['import-file']['tmp_name']) || $_FILES['import-file']['tmp_name'] == 'none') {
            $error = 'Please attach file to upload';
        } else {
            $fileName = $_FILES['import-file']['name'] ?? '';
            $fileExt = strtolower(FileTools::getFileExtension($fileName));

            if($fileExt != 'csv') {
                $error = 'Incorrect file format! Please select CSV file.';
            }

            if(empty($error)) {
                $csvReader = new CsvReader();
                $csvReader->read($_FILES['import-file']['tmp_name'], ',');
                $data = $csvReader->cell;
                $recordsCount = $csvReader->rows;

                // Delete temp file
                @unlink($_FILES['import-file']['tmp_name']);
            }
            
            // If there are no records - exit
            if(empty($recordsCount)) {
                $error = 'There are no records available for importing.';
            }
            
            if(empty($error)) {
                
                $arr = array();
                foreach($data as $rec) {
                    
                    if(empty($rec) || !isset($rec[1])) {
                        continue;
                    }
                    
                    $arr[] = array('cmi_id' => $rec[0], 'regulator_id' => $rec[1]);
                    $this->_company->getCompanyCMI()->addCMI($arr);
                }
            }
        }
        
        $arrResult = array ('success' => empty($error), 'file' => $file, 'fileName' => $fileName, 'error' => $error );
        exit(Json::encode($arrResult));
    }
}