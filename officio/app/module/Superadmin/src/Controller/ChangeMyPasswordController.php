<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\AuthHelper;
use Officio\Common\Service\Encryption;

/**
 * ChangeMyPassword Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ChangeMyPasswordController extends BaseController
{

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_authHelper = $services[AuthHelper::class];
        $this->_encryption = $services[Encryption::class];
    }

    /**
     * The default action - show Change My Password page
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $view->setVariable('username', $this->_auth->getCurrentUserUsername());
        $view->setVariable('message', '');

        return $view;
    }
    
    public function updateAction () {
        $username = '';
        $message = '';
        $booError = false;
        try {
            if ($this->getRequest()->isPost() || $this->getRequest()->isXmlHttpRequest()) {
                // collect the data from post
                $filter = new StripTags();

                if ($this->getRequest()->isXmlHttpRequest()) {
                    $oldPassword = $filter->filter(Json::decode($this->findParam('oldPassword'), Json::TYPE_ARRAY));
                    $newPassword = $filter->filter(Json::decode($this->findParam('newPassword'), Json::TYPE_ARRAY));
                } else {
                    $oldPassword = $filter->filter($this->findParam('oldPassword'));
                    $newPassword = $filter->filter($this->findParam('newPassword'));
                }

                $arrErrors = array();
                $memberId  = $this->_auth->getCurrentUserId();
                $username  = $this->_auth->getCurrentUserUsername();
                if (empty($oldPassword)) {
                    $message  = $this->_tr->translate("Old password cannot be empty.");
                    $booError = true;
                } elseif (empty($newPassword)) {
                    $message  = $this->_tr->translate("New password cannot be empty.");
                    $booError = true;
                } elseif (!$this->_authHelper->isPasswordValid($newPassword, $arrErrors, $username, $memberId)) {
                    $message  = implode('<br/>', $arrErrors);
                    $booError = true;
                } else {
                    $arrMemberInfo = $this->_members->getMemberInfo($memberId);

                    if (!$this->_encryption->checkPasswords($oldPassword, $arrMemberInfo['password'])) {
                        $message  = $this->_tr->translate("Sorry, you have entered incorrect old password.");
                        $booError = true;
                    } else {
                        $arrMemberInfo['password'] = $newPassword;

                        $this->_members->updateMemberData(
                            $memberId,
                            array(
                                'password'             => $this->_encryption->hashPassword($newPassword),
                                'password_change_date' => time()
                            )
                        );

                        // Send confirmation email to this user
                        $this->_authHelper->triggerPasswordHasBeenChanged($arrMemberInfo);

                        $message = $this->_tr->translate("Password changed successfully.");
                    }
                }
            }
        } catch (Exception $e) {
            $booError = true;
            $message = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $view = new JsonModel();
            $arrResult = array(
                'success' => !$booError,
                'message' => $message
            );
            $view->setVariables($arrResult);
        } else {
            $view = new ViewModel();
            $view->setVariables(
                [
                    'message'=>$message,
                    'booError'=> $booError,
                    'username'=> $username,
                    'booCanUpdateMyPassword' => true
                ]
            );
        }

        return $view;
    }
}