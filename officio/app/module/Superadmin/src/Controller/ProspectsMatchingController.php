<?php

namespace Superadmin\Controller;

use Exception;
use Files\BufferedStream;
use Files\Service\Files;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Prospects Matching Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ProspectsMatchingController extends BaseController
{

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_files = $services[Files::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $error = '';

        try {
            if ($this->getRequest()->isPost()) {
                set_time_limit(60 * 60); // 1 hour
                ini_set('memory_limit', '512M');

                // get file
                $file = !empty($_FILES) && isset($_FILES['prospects']) && !empty($_FILES['prospects']['tmp_name']) && file_exists($_FILES['prospects']['tmp_name']) ? $_FILES['prospects'] : false;
                if (empty($file)) {
                    $error = $this->_tr->translate('Please select file to check');
                } elseif (!in_array(strtolower(FileTools::getFileExtension($file['name']) ?? ''), array('xls', 'xlsx'))) {
                    $error = $this->_tr->translate('Incorrect file was selected. Please select valid Microsoft Excel file');
                }

                // read file
                if (empty($error)) {
                    // read data
                    $objPHPExcel = IOFactory::load($file['tmp_name']);
                    $objPHPExcel->setActiveSheetIndex(0);
                    $aSheet   = $objPHPExcel->getActiveSheet();
                    $startRow = 2; // start reading from second line
                    $rows     = $aSheet->getHighestRow();

                    // get members
                    $members = $this->_members->getMembersForProspectsMatching();

                    // read records
                    for ($i = $startRow; $i <= $rows; $i++) {

                        // get record values
                        $fName = trim($aSheet->getCellByColumnAndRow(0, $i)->getValue());
                        $lName = trim($aSheet->getCellByColumnAndRow(1, $i)->getValue());
                        $email = trim($aSheet->getCellByColumnAndRow(2, $i)->getValue());

                        // match values with current information about member
                        $match   = 0;
                        $company = '';
                        foreach ($members as $member) {
                            $company = $member['companyName'];
                            if ((!empty($member['emailAddress']) && $member['emailAddress'] == $email) ||
                                (!empty($member['lName']) && !empty($member['fName']) && $member['lName'] == $lName && $member['fName'] == $fName)
                            ) {
                                $match = 100;
                                break;
                            } elseif (!empty($member['lName']) && $member['lName'] == $lName) {
                                $match = 50;
                            }
                        }

                        // append two new records if match > 0
                        $aSheet->setCellValueByColumnAndRow(3, $i, $match . '%');
                        if ($match > 0) {
                            $aSheet->setCellValueByColumnAndRow(4, $i, $company);
                        }
                    }

                    // save new file
                    if (strtolower(FileTools::getFileExtension($file['name']) ?? '') == 'xls') {
                        $objWriter = new Xls($objPHPExcel);
                    } else {
                        $objWriter = new Xlsx($objPHPExcel);
                    }

                    $disposition = "attachment; filename=\"{$file['name']}\"";

                    $pointer = fopen('php://output', 'wb');
                    $bufferedStream = new BufferedStream($file['type'], null, $disposition);
                    $bufferedStream->setStream($pointer);

                    $objWriter->save($pointer);

                    return $bufferedStream;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('error', $error);

        $title = $this->_tr->translate('Prospects Matching');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }
}