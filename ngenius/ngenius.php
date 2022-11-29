<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

ini_set("display_errors",1);
require_once _PS_MODULE_DIR_.'/ngenius/vendor/autoload.php';

use NGenius\Command;
use NGenius\Config\Config;
use NGenius\Logger;
use PrestaShop\PrestaShop\Adapter\StockManager;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Ngenius extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $config = new Config();
        $this->name = 'ngenius';
        $this->tab = 'payments_gateways';
        $this->version = '5.0.3';
        $this->author = 'Network International';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l($config->getModuleDisplayName());
        $this->description = $this->l($config->getModuleDescription());

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall my module?');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $config = new Config();
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        include(dirname(__FILE__).'/sql/install.php');

        Configuration::updateValue('DISPLAY_NAME', $config->getModuleDisplayName());

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('displayBackOfficeOrderActions') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('actionEmailSendBefore') &&
            $this->createOrderState() &&
            $this->addTab() &&
            $this->addNGeniusCronToken();
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        $this->deleteTab();
        $this->deleteNGeniusConfigurations();
        return parent::uninstall();
    }

    /**
     * Email Send
     *
     * @return string|void
     */
    public function hookActionEmailSendBefore($params)
    {
        $command = new Command();
        if ($params['template'] === 'order_conf') {
            $orderId = \Order::getOrderByCartId($params['cart']->id);
            $orderConfirmationData = $command->getNgeniusOrderEmailContent($orderId);
            if ($orderConfirmationData) {
                return true;
            } else {
                $data = isset($params['templateVars']) ? $params['templateVars'] : '';
                $mailData = array(
                    'id_order' => (int) $orderId,
                    'data' => serialize($data),
                );
                $command->addNgeniusOrderEmailContent($mailData);
                return false;
            }
        }
        return true;
    }

    /**
     * Load the configuration form
     *
     * @return string|void
     */
    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit' . $this->name)) {
            $DISPLAY_NAME = strval(Tools::getValue('DISPLAY_NAME'));
            $ENVIRONMENT = strval(Tools::getValue('ENVIRONMENT'));
            $PAYMENT_ACTION = strval(Tools::getValue('PAYMENT_ACTION'));
            $UAT_API_URL = strval(Tools::getValue('UAT_API_URL'));
            $LIVE_API_URL = strval(Tools::getValue('LIVE_API_URL'));
            $OUTLET_REFERENCE_ID = strval(Tools::getValue('OUTLET_REFERENCE_ID'));
            $API_KEY = strval(Tools::getValue('API_KEY'));
            $DEBUG = strval(Tools::getValue('DEBUG'));
            $NING_CRON_SCHEDULE = strval(Tools::getValue('NING_CRON_SCHEDULE'));
            $CURRENCY_OUTLETID =    json_encode(Tools::getValue('CURRENCY_OUTLETID'));

            if (!$DISPLAY_NAME || empty($DISPLAY_NAME) || !Validate::isGenericName($DISPLAY_NAME)) {
                $output .= $this->displayError($this->l('Invalid name for payment gateway'));
            } elseif (!$API_KEY || empty($API_KEY)) {
                $output .= $this->displayError($this->l('Invalid API key'));
            } elseif (!$this->validateCurrencyOutletid($CURRENCY_OUTLETID)) {
                $output .= $this->displayError($this->l('Invalid Combination of Currency & Outlet Id'));
            } else {
                Configuration::updateValue('DISPLAY_NAME', $DISPLAY_NAME);
                Configuration::updateValue('ENVIRONMENT', $ENVIRONMENT);
                Configuration::updateValue('PAYMENT_ACTION', $PAYMENT_ACTION);
                Configuration::updateValue('UAT_API_URL', $UAT_API_URL);
                Configuration::updateValue('LIVE_API_URL', $LIVE_API_URL);
                Configuration::updateValue('OUTLET_REFERENCE_ID', $OUTLET_REFERENCE_ID);
                Configuration::updateValue('API_KEY', $API_KEY);
                Configuration::updateValue('DEBUG', $DEBUG);
                Configuration::updateValue('NING_CRON_SCHEDULE', $NING_CRON_SCHEDULE);
                Configuration::updateValue('CURRENCY_OUTLETID', $CURRENCY_OUTLETID);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }

    public function validateCurrencyOutletid($encCurOut)
    {
        $flag = true;
        $decCurOut = json_decode($encCurOut, true);
        foreach ($decCurOut as $value) {
            if (empty($value['CURRENCY']) || empty($value['OUTLET_ID'])) {
                $flag = false;
            }
        }
        return $flag;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     *
     * @return string
     */
    public function displayForm()
    {
        // Get default language
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper = new HelperForm();
        $config = new Config();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        // Load current value
        $helper->fields_value['DISPLAY_NAME'] = Configuration::get('DISPLAY_NAME');
        $helper->fields_value['ENVIRONMENT'] = Configuration::get('ENVIRONMENT');
        $helper->fields_value['PAYMENT_ACTION'] = Configuration::get('PAYMENT_ACTION');
        $helper->fields_value['API_KEY'] = Configuration::get('API_KEY');
        $helper->fields_value['UAT_API_URL'] = Configuration::get('UAT_API_URL');
        $helper->fields_value['LIVE_API_URL'] = Configuration::get('LIVE_API_URL');
        $helper->fields_value['DEBUG'] = Configuration::get('DEBUG');
        $helper->fields_value['NING_CRON_SCHEDULE'] = Configuration::get('NING_CRON_SCHEDULE');
        $helper->fields_value['CURRENCY_OUTLETID'] = Configuration::get('CURRENCY_OUTLETID');

        $currencyOutletid = Configuration::get('CURRENCY_OUTLETID');
        if (empty($currencyOutletid)) {
            $currencyOutletid = '{"0":{"CURRENCY":"","OUTLET_ID":""}}';
        }

        $token = \Configuration::get('NING_CRON_TOKEN');

        $this->context->smarty->assign([
            'config'      => $helper->fields_value,
            'token'       => Tools::getAdminTokenLite('AdminModules'),
            'currencyOutletid' => json_decode($currencyOutletid, true),
            'moduleName' => $config->getModuleName(),
            'url' => \Tools::getHttpHost(true) . __PS_BASE_URI__.'module/ngenius/cron?token='.$token,
        ]);
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        $config = new Config();
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l($config->getDisplayName()))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        return [
            $option
        ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Order Configuration URL redirect
     *
     * @return string
     */
    public function getOrderConfUrl($order)
    {
        return $this->context->link->getPageLink(
            'order-confirmation',
            true,
            $order->id_lang,
            array(
                'id_cart' => $order->id_cart,
                'id_module' => $this->id,
                'id_order' => $order->id,
                'key' => $order->secure_key
            )
        );
    }

    /**
     * Order Status Update Hook.
     *
     * @param array $params
     * @return bool|void;
     */
    public function hookActionOrderStatusUpdate($params)
    {
        if (!$this->active) {
            return false;
        }

        $current_context = \Context::getContext();
        if ($current_context->controller->controller_type != 'admin') {
            return true;
        }

        $order = new \Order((int)$params['id_order']);
        $command = new Command();
        $config = new Config();
        $status = $config->getOrderStatus();
        if ($this->validateNgeniusOrderSatus($params)) {
            if (!empty($params['id_order']) &&  !empty($params['newOrderStatus']) && Validate::isLoadedObject($params['newOrderStatus'])) {
                $statusFlag = false;
                if ($params['newOrderStatus']->id == \Configuration::get($status.'_FULLY_CAPTURED') && Validate::isLoadedObject($order)) {
                    if ($_SESSION['ngenius_fully_captured'] == 'true') {
                        $_SESSION['ngenius_fully_captured'] = null;
                        $this->addNgeniusFlashMessage($this->trans('You have successfully Captured!'));
                        $statusFlag = true;
                    } else {
                        $this->invalidOrderStatus($order->id);
                    }
                } elseif ($params['newOrderStatus']->id == \Configuration::get($status.'_AUTH_REVERSED') && Validate::isLoadedObject($order)) {
                    if ($_SESSION['ngenius_auth_reversed'] == 'true') {
                        $_SESSION['ngenius_auth_reversed'] = null;
                        $this->addNgeniusFlashMessage($this->trans('You have successfully reversed the authorization!'));
                        $statusFlag = true;
                    } else {
                        $this->invalidOrderStatus($order->id);
                    }
                } elseif ($params['newOrderStatus']->id == \Configuration::get($status.'_FULLY_REFUNDED') && Validate::isLoadedObject($order)) {
                    if ($_SESSION['ngenius_fully_refund'] == 'true') {
                        $_SESSION['ngenius_fully_refund'] = null;
                        $this->reinjectQuantity($params['id_order']);
                        $this->addNgeniusFlashMessage('You have successfully refund the transaction!');
                        $statusFlag = true;
                    } else {
                        $this->invalidOrderStatus($order->id);
                    }
                } elseif ($params['newOrderStatus']->id == \Configuration::get($status.'_PARTIALLY_REFUNDED') && Validate::isLoadedObject($order)) {
                    if ($_SESSION['ngenius_partial_refund'] == 'true') {
                        $_SESSION['ngenius_partial_refund'] = null;
                        $this->addNgeniusFlashMessage('You have partially refund the transaction!');
                        $statusFlag = true;
                    } else {
                        $this->invalidOrderStatus($order->id);
                    }
                } else {
                    $statusFlag = true;
                }
                return $statusFlag;
            }
        } else {
            $this->addNgeniusFlashMessage($this->trans('Error!. Invalid Order Status.'));
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder');
        }
    }

    /**
     * Ngenius Flash Message.
     *
     * @return true
     */
    public function addNgeniusFlashMessage($message)
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['ngenius_flashes'] = $message;
        } elseif (session_status() == PHP_SESSION_NONE) {
            session_start();
            $_SESSION['ngenius_flashes'] = $message;
        } else {
            setcookie('ngenius_flashes', $message);
        }
        return true;
    }


    /**
     * Validate Ngenius Order Satus.
     *
     * @param array $params
     * @return bool;
     */

    public function validateNgeniusOrderSatus($params)
    {
        $config = new Config();
        $status = $config->getOrderStatus();
        $order = new \Order((int)$params['id_order']);
        if (!empty($order->module) && $order->module == $this->name && !empty($params['newOrderStatus']) &&  Validate::isLoadedObject($params['newOrderStatus'])) {
            if ($params['newOrderStatus']->id == \Configuration::get($status.'_PENDING')
                || $params['newOrderStatus']->id == \Configuration::get($status.'_PROCESSING')
                || $params['newOrderStatus']->id == \Configuration::get($status.'_FAILED')
                || $params['newOrderStatus']->id == \Configuration::get($status.'_COMPLETE')
                || $params['newOrderStatus']->id == \Configuration::get($status.'_AUTHORISED')
            ) {
                $_SESSION['validate_order_status'] = null;
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * invalid Order Status
     *
     * @param id $orderId
     *
     */
    public function invalidOrderStatus($orderId)
    {
        $this->addNgeniusFlashMessage($this->trans('Error!. Invalid Order Status!.'));
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $orderId . '&vieworder');
    }

    /**
     * Display Back Office Order Actions Hook.
     *
     * @param array $params
     * @return string|void;
     */
    public function hookDisplayBackOfficeOrderActions($params)
    {

        if (!$this->active) {
            return false;
        }

        if (isset($params['id_order'])) {
            $order = new Order((int)$params['id_order']);
            if ($order->module == $this->name) {
                echo '<script> $(document).ready(function(){ $("#desc-order-partial_refund").hide();}) </script>';
            }
        }

        $message = '';
        if (isset($_SESSION['ngenius_flashes'])) {
            $message = $this->adminDisplayWarning($this->l($_SESSION['ngenius_flashes']));
        }

        if (isset($_SESSION['ngenius_errors'])) {
            $message = $this->context->controller->errors[] = $this->l($_SESSION['ngenius_errors']);
        }

        $_SESSION['ngenius_flashes'] = null;
        $_SESSION['ngenius_errors'] = null;
        return $message;
    }

    /**
     * Display Admin Order Hook.
     *
     * @param array $params
     * @return string|void;
     */
    public function hookDisplayAdminOrder($params)
    {
        if (! $this->active) {
            return false;
        }

        $id_order = (int)$params['id_order'];
        $config = new Config();
        $order = new Order($id_order);
        if ($order->module == $this->name) {

            $command = new Command();
            $ngeniusOrder = $command->getNgeniusOrder($id_order);
            $formAction = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => $params['id_order'], 'vieworder' => 1]);

            // void / capture
            $authorizedOrder = $command->getAuthorizationTransaction($ngeniusOrder);

            if ($authorizedOrder) {
                if (Tools::isSubmit('fullyCaptureNgenius')) {
                    // fully capture

                    if ($command->capture($authorizedOrder)) {
                        $this->addNgeniusFlashMessage('You have Successfully Captured!');
                        Tools::redirectAdmin($formAction);
                    } else {
                        $this->addNgeniusFlashMessage('Oops something went wrong!.');
                        Tools::redirectAdmin($formAction);
                    }
                } elseif (Tools::isSubmit('voidNgenius')) {
                    // void / auth reverse
                    if ($command->void($authorizedOrder)) {
                        $this->addNgeniusFlashMessage('You have successfully reversed the authorization!');
                        Tools::redirectAdmin($formAction);
                    } else {
                        $this->addNgeniusFlashMessage('Oops something went wrong!.');
                        Tools::redirectAdmin($formAction);
                    }
                }
            }

            // refund

            $totalRefunded = '';

            if (Tools::isSubmit('partialRefundNgenius')) {


                if (Tools::getValue('refundAmount') != '' ||  Tools::getValue('refundAmount') != null) {
                    $refundedOrder['amount'] = (float)Tools::getValue('refundAmount');
                    if ($command->validateMcpOrder($params['id_order']) && $refundedOrder['amount'] != $ngeniusOrder['amount']) {
                        $this->addNgeniusFlashMessage('MCP enabled order does not support partial refund. Please enter full amount to refund!.');
                        Tools::redirectAdmin($formAction);
                    } else {
                        $ngeniusOrder['amount'] = $refundedOrder['amount'];
                        if ($command->refund($ngeniusOrder)) {
                            Tools::redirectAdmin($formAction);
                        } else {
                            $this->addNgeniusFlashMessage('error in proceed with your refund.!.');
                            Tools::redirectAdmin($formAction);
                        }
                    }
                }
            } else {
                $totalRefunded = $ngeniusOrder['capture_amt'];
            }

            $logger = new Logger();
            $logger->addLog($ngeniusOrder);

            $this->context->smarty->assign([
                'ngeniusOrder'      => $ngeniusOrder,
                'authorizedOrder'   => $authorizedOrder,
                'refundedOrder'     => $refundedOrder,
                'formAction'        => $formAction,
                'totalRefunded'     => $totalRefunded,
                'moduleDisplayName' => $config->getModuleDisplayName(),
            ]);
            return $this->display(__FILE__, 'views/templates/admin/payment.tpl');
        }
    }

    /**
     * Add new back office tab.
     *
     * @return bool;
     */
    public function addTab()
    {
        $config = new Config();
        if (!\Tab::getIdFromClassName('AdminNgeniusReports')) {
            $tab = new \Tab();
            $langs = \Language::getLanguages(false);
            foreach ($langs as $l) {
                $tab->name[$l['id_lang']] = $this->l($config->getModuleName().' Reports');
            }
            $tab->class_name = 'AdminNgeniusReports';
            $tab->id_parent = \Tab::getIdFromClassName('SELL');
            $tab->module = $this->name;
            $tab->icon = 'payment';
            if ($tab->add()) {
                return true;
            }
        }
        return true;
    }

    /**
     * Add NGenius Cron Token.
     *
     * @return bool;
     */
    public function addNGeniusCronToken()
    {
        \Configuration::updateValue('NING_CRON_TOKEN', bin2hex(random_bytes(16)));
        return true;
    }

    public function createOrderState()
    {
        foreach ($this->getNgeniusOrderStatus() as $state) {
            $orderStateExist = false;
            $status_name = $state['status']; //'PS_OS_NGENIUS';
            $orderStateId = \Configuration::get($status_name);
            $description = $state['label'];
            // save data to sorder_state_lang table
            if ($orderStateId) {
                $orderState = new OrderState($orderStateId);
                if ($orderState->id && !$orderState->deleted) {
                    $orderStateExist = true;
                }
            } else {
                $query = 'SELECT os.`id_order_state` '.
                    'FROM `%1$sorder_state_lang` osl '.
                    'LEFT JOIN `%1$sorder_state` os '.
                    'ON osl.`id_order_state`=os.`id_order_state` '.
                    'WHERE osl.`name`="%2$s" AND os.`deleted`=0';
                $orderStateId =  \Db::getInstance()->getValue(sprintf($query, _DB_PREFIX_, $description));
                if ($orderStateId) {
                    \Configuration::updateValue($status_name, $orderStateId);
                    $orderStateExist = true;
                }
            }

            if (!$orderStateExist) {
                $languages = \Language::getLanguages(false);
                $orderState = new \OrderState();
                foreach ($languages as $lang) {
                    $orderState->name[$lang['id_lang']] = $description;
                }

                $orderState->send_email = $state['send_email'];
                $orderState->template = $state['template'];
                $orderState->invoice = $state['invoice'];
                $orderState->color = $state['color'];
                $orderState->unremovable = 1;
                $orderState->logable = 0;
                $orderState->delivery = $state['delivery'];
                $orderState->hidden = 0;
                $orderState->module_name = $this->name;
                $orderState->shipped = $state['shipped'];
                $orderState->paid = 0;
                $orderState->pdf_invoice = $state['pdf_invoice'];
                $orderState->pdf_delivery = $state['pdf_delivery'];
                $orderState->deleted = 0;

                if ($orderState->add()) {
                    \Configuration::updateValue($status_name, $orderState->id);
                    $orderStateExist = true;
                }
            }
            $file = $this->getLocalPath().'views/img/order_state.gif';
            $newfile = _PS_IMG_DIR_.'os/' . $orderState->id . '.gif';
            copy($file, $newfile);
        }
        return true;
    }

    /**
     * Delete back office tab.
     *
     * @return bool;
     */
    public function deleteTab()
    {
        if ($idTab = \Tab::getIdFromClassName('AdminNgeniusReports')) {
            if ($idTab != 0) {
                $tab = new \Tab($idTab);
                $tab->delete();
            } else {
                return true;
            }
        }
        return true;
    }

    /**
     * Delete NGenius data from ps Configuration.
     *
     * @return bool;
     */
    public function deleteNGeniusConfigurations()
    {
        \Configuration::updateValue('API_KEY', null);
        \Configuration::updateValue('UAT_API_URL', null);
        \Configuration::updateValue('LIVE_API_URL', null);
        \Configuration::updateValue('LIVE_API_URL', null);
        \Configuration::updateValue('DISPLAY_NAME', null);
        \Configuration::updateValue('NING_CRON_TOKEN', null);
        \Configuration::updateValue('CURRENCY_OUTLETID', null);
        \Configuration::updateValue('NING_CRON_SCHEDULE', null);
        return true;
    }

    /**
     * Ngenius Order Status.
     *
     * @return array
     */
    public function getNgeniusOrderStatus()
    {
        $config = new Config();
        $status = $config->getOrderStatus();
        $label = $config->getOrderStatusLabel();
        return [
            [
                'status' => $status.'_PENDING',
                'label' => $label.' Pending',
                'invoice' => 0,
                'send_email' => 0,
                'template' => '',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#4169E1',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_PROCESSING',
                'label' => $label.' Processing',
                'invoice' => 0,
                'send_email' => 0,
                'template' => '',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#32CD32',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_FAILED',
                'label' => $label.' Failed',
                'invoice' => 0,
                'send_email' => 0,
                'template' => '',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#8f0621',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_COMPLETE',
                'label' => $label.' Complete',
                'invoice' => 1,
                'send_email' => 1,
                'template' => 'payment',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#108510',
                'pdf_invoice' => 1,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_AUTHORISED',
                'label' => $label.' Authorised',
                'invoice' => 0,
                'send_email' => 0,
                'template' => '',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#FF8C00',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_FULLY_CAPTURED',
                'label' => $label.' Fully Captured',
                'invoice' => 1,
                'send_email' => 1,
                'template' => 'payment',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#108510',
                'pdf_invoice' => 1,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_AUTH_REVERSED',
                'label' => $label.' Auth Reversed',
                'invoice' => 0,
                'send_email' => 0,
                'template' => '',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#DC143C',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_FULLY_REFUNDED',
                'label' => $label.' Fully Refunded',
                'invoice' => 0,
                'send_email' => 1,
                'template' => 'refund',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#ec2e15',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
            [
                'status' => $status.'_PARTIALLY_REFUNDED',
                'label' => $label.' Partially Refunded',
                'invoice' => 0,
                'send_email' => 1,
                'template' => 'refund',
                'delivery' => 0,
                'shipped' => 0,
                'color' => '#ec2e15',
                'pdf_invoice' => 0,
                'pdf_delivery' => 0,
            ],
        ];
    }

    /**
     * Reinject Quantity to StockAvailable
     *
     * @param int $orderId
     * @return void
     */
    public function reinjectQuantity($orderId)
    {
        $command = new Command();
        $orderItems = \OrderDetail::getList((int)$orderId);
        foreach ($orderItems as $orderItem) {
            $order_detail = $command->getOrderDetailsCore((int) $orderItem['id_order_detail']);
            $order_detail = json_decode(json_encode($order_detail));
            $this->reinjectQuantityCore($order_detail, $orderItem['product_quantity']);
        }
    }


    /**
     * @param OrderDetail $order_detail
     * @param int $qty_cancel_product
     * @param bool $delete
     */
    public function reinjectQuantityCore($order_detail, $qty_cancel_product, $delete = false)
    {
        // Reinject product
        $reinjectable_quantity = (int) $order_detail->product_quantity - (int) $order_detail->product_quantity_reinjected;
        $quantity_to_reinject = $qty_cancel_product > $reinjectable_quantity ? $reinjectable_quantity : $qty_cancel_product;
        /** @since 1.5.0 : Advanced Stock Management */
        $product_to_inject = new \Product($order_detail->product_id, false, (int) $this->context->language->id, (int) $order_detail->id_shop);

        $product = new \Product($order_detail->product_id, false, (int) $this->context->language->id, (int) $order_detail->id_shop);

        if (\Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management && $order_detail->id_warehouse != 0) {

            $manager = \StockManagerFactory::getManager();
            $movements = \StockMvt::getNegativeStockMvts(
                $order_detail->id_order,
                $order_detail->product_id,
                $order_detail->product_attribute_id,
                $quantity_to_reinject
            );
            $left_to_reinject = $quantity_to_reinject;
            foreach ($movements as $movement) {
                if ($left_to_reinject > $movement['physical_quantity']) {
                    $quantity_to_reinject = $movement['physical_quantity'];
                }

                $left_to_reinject -= $quantity_to_reinject;
                if (\Pack::isPack((int) $product->id)) {
                    // Gets items
                    if ($product->pack_stock_type == \Pack::STOCK_TYPE_PRODUCTS_ONLY
                        || $product->pack_stock_type == \Pack::STOCK_TYPE_PACK_BOTH
                        || ($product->pack_stock_type == \Pack::STOCK_TYPE_DEFAULT
                            && \Configuration::get('PS_PACK_STOCK_TYPE') > 0)
                    ) {
                        $products_pack = \Pack::getItems((int) $product->id, (int) \Configuration::get('PS_LANG_DEFAULT'));
                        // Foreach item
                        foreach ($products_pack as $product_pack) {
                            if ($product_pack->advanced_stock_management == 1) {
                                $manager->addProduct(
                                    $product_pack->id,
                                    $product_pack->id_pack_product_attribute,
                                    new \Warehouse($movement['id_warehouse']),
                                    $product_pack->pack_quantity * $quantity_to_reinject,
                                    null,
                                    $movement['price_te'],
                                    true
                                );
                            }
                        }
                    }

                    if ($product->pack_stock_type == \Pack::STOCK_TYPE_PACK_ONLY
                        || $product->pack_stock_type == \Pack::STOCK_TYPE_PACK_BOTH
                        || (
                            $product->pack_stock_type == \Pack::STOCK_TYPE_DEFAULT
                            && (\Configuration::get('PS_PACK_STOCK_TYPE') == \Pack::STOCK_TYPE_PACK_ONLY
                                || \Configuration::get('PS_PACK_STOCK_TYPE') == \Pack::STOCK_TYPE_PACK_BOTH)
                        )
                    ) {
                        $manager->addProduct(
                            $order_detail->product_id,
                            $order_detail->product_attribute_id,
                            new \Warehouse($movement['id_warehouse']),
                            $quantity_to_reinject,
                            null,
                            $movement['price_te'],
                            true
                        );
                    }
                } else {
                    $manager->addProduct(
                        $order_detail->product_id,
                        $order_detail->product_attribute_id,
                        new \Warehouse($movement['id_warehouse']),
                        $quantity_to_reinject,
                        null,
                        $movement['price_te'],
                        true
                    );
                }
            }

            $id_product = $order_detail->product_id;
            if ($delete) {
                $order_detail->delete();
            }
            \StockAvailable::synchronize($id_product);
        } elseif ($order_detail->id_warehouse == 0) {
            \StockAvailable::updateQuantity(
                $order_detail->product_id,
                $order_detail->product_attribute_id,
                $quantity_to_reinject,
                $order_detail->id_shop,
                true,
                array(
                    'id_order' => $order_detail->id_order,
                    'id_stock_mvt_reason' => \Configuration::get('PS_STOCK_CUSTOMER_RETURN_REASON'),
                )
            );
        } else {
            $this->errors[] = $this->trans('This product cannot be re-stocked.', array(), 'Admin.Orderscustomers.Notification');
        }
    }
}
