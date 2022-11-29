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

use NGenius\Logger;
use NGenius\Command;
use NGenius\Config\Config;
use NGenius\Request\TokenRequest;
use NGenius\Request\OrderStatusRequest;
use NGenius\Http\TransactionOrderRequest;

class NGeniusRedirectModuleFrontController extends ModuleFrontController
{
    /**
     * Processing of API response
     *
     * @return void
     */
    public function postProcess()
    {
        $logger = new Logger();
        $config = new Config();
        $command = new Command();
        $log = [];
        $log['path'] = __METHOD__;
        $ref = $_REQUEST['ref'];
        $isValidRef = preg_match('/([a-z0-9]){8}-([a-z0-9]){4}-([a-z0-9]){4}-([a-z0-9]){4}-([a-z0-9]){12}$/', $ref);
        if ($isValidRef) {
            $ngeniusOrder = $this->getNgeniusOrder($ref);
            $order = new \Order($ngeniusOrder['id_order']);
            if (Validate::isLoadedObject($order) && $order->id_customer == $this->context->customer->id) {
                $this->updateNgeniusOrderStatusToProcessing($ngeniusOrder, $order);
                $order->setCurrentState((int)Configuration::get($config->getOrderStatus().'_PROCESSING'));
                $response = $command->getOrderStatusRequest($ref);
                $response = json_decode(json_encode($response), true);
                $this->processOrder($response, $ngeniusOrder);

                if ((isset($this->getNgeniusOrder($ref)['state']) && $this->getNgeniusOrder($ref)['state'] == 'FAILED') || ($this->getNgeniusOrder($ref)['state'] != 'AUTHORISED' && $this->getNgeniusOrder($ref)['state'] != 'CAPTURED') && $this->getNgeniusOrder($ref)['state'] != 'PURCHASED') {
                    $log['redirected_to'] = 'module/ngenius/failedorder';
                    $logger->addLog($log);
                    Tools::redirect(\Tools::getHttpHost(true) . __PS_BASE_URI__.'module/ngenius/failedorder');
                } else {
                    $redirectLink = $this->module->getOrderConfUrl($order);
                    $log['redirected_to'] = $redirectLink;
                    $logger->addLog($log);
                    Tools::redirectLink($redirectLink);
                }
            }
        }
    }

    /**
     * Process Order.
     *
     * @param array $response
     * @param array $ngeniusOrder
     * @param int|null $cronJob
     * @return bool
     */
    public function processOrder($response, $ngeniusOrder, $cronJob = false)
    {
        $command = new Command();
        $config = new Config();
        $status = null;
        $state = null;
        $order = new \Order($ngeniusOrder['id_order']);
        $captureAmount = 0;
        $transactionId = null;
        if (Validate::isLoadedObject($order)) {
            $paymentId = $this->getPaymentId($response);
            $state = isset($response['_embedded']['payment'][0]['state']) ? $response['_embedded']['payment'][0]['state'] : null;
            switch ($state) {
                case 'CAPTURED':
                    $captureAmount = $this->getCapturedAmount($response, $ngeniusOrder['amount']);
                    $lastTransaction = $this->getLastTransaction($response);
                    $transactionId = $this->getTransactionId($lastTransaction);
                    $status = $config->getOrderStatus().'_COMPLETE';
                    $command->sendOrderConfirmationMail($order);
                    break;

                case 'AUTHORISED':
                    $command->sendOrderConfirmationMail($order);
                    $status = $config->getOrderStatus().'_AUTHORISED';
                    break;

                case 'PURCHASED':
                    $command->sendOrderConfirmationMail($order);
                    $status = $config->getOrderStatus().'_PURCHASED';
                    break;
                
                case 'FAILED':
                    $status = $config->getOrderStatus().'_FAILED';
                    break;
                    
                default:
                    $status = $config->getOrderStatus().'_PENDING';
                    break;
            }
            if (isset($status) && isset($state)) {
                if ($cronJob) {
                    $this->updateNgeniusOrderStatusToProcessing($ngeniusOrder, $order);
                    $order->setCurrentState((int)Configuration::get($config->getOrderStatus().'_PROCESSING'));
                }
                $authResponse = isset($response['_embedded']['payment'][0]['authResponse']) ? $response['_embedded']['payment'][0]['authResponse'] : null;
                $data = [
                    'id_payment' => $paymentId,
                    'capture_amt' => $captureAmount,
                    'status' => $status,
                    'state' => $state,
                    'reference' => $ngeniusOrder['reference'],
                    'id_capture' => $transactionId,
                    'auth_response' => json_encode($authResponse, true),
                ];
                $command->updateNngeniusNetworkinternational($data);
                $command->updatePsOrderPayment($this->getOrderPaymentRequest($response));
                $command->addCustomerMessage($response, $order);
                $order->setCurrentState((int)Configuration::get($status));
                return true;
            }
        }
    }

    /**
     * Gets Captured Amount
     *
     * @param array $response
     * @return string
     */
    public function getCapturedAmount($response, $orderAmount)
    {
        $captureAmount = 0;
        if (isset($response['_embedded']['payment'][0]['_embedded']['cnp:capture']) && is_array($response['_embedded']['payment'][0]['_embedded']['cnp:capture'])) {
            foreach ($response['_embedded']['payment'][0]['_embedded']['cnp:capture'] as $capture) {
                if (isset($capture['state']) && ($capture['state'] == 'SUCCESS') && isset($capture['amount']['value'])) {
                    $captureAmount = $orderAmount;
                }
            }
        }
        return $captureAmount;
    }
    
    /**
     * Gets Last Transaction
     *
     * @param array $response
     * @return string
     */
    public function getLastTransaction($response)
    {
        $lastTransaction = '';
        if (isset($response['_embedded']['payment'][0]['_embedded']['cnp:capture']) && is_array($response['_embedded']['payment'][0]['_embedded']['cnp:capture'])) {
            $lastTransaction = end($response['_embedded']['payment'][0]['_embedded']['cnp:capture']);
        }
        return $lastTransaction;
    }

    /**
     * Gets payment id
     *
     * @param array $response
     * @return string
     */
    public function getPaymentId($response)
    {
        $paymentId = '';
        if (isset($response['_embedded']['payment'][0]['_id'])) {
            $transactionIdRes = explode(":", $response['_embedded']['payment'][0]['_id']);
            $paymentId = end($transactionIdRes);
        }
        return $paymentId;
    }

    /**
     * Gets transaction Id
     *
     * @param array $response
     * @return string
     */
    public function getTransactionId($lastTransaction)
    {
        $transactionId = '';
        if (isset($lastTransaction['_links']['self']['href'])) {
            $transactionArr = explode('/', $lastTransaction['_links']['self']['href']);
            $transactionId = end($transactionArr);
        } elseif ($lastTransaction['_links']['cnp:refund']['href']) {
            $transactionArr = explode('/', $lastTransaction['_links']['cnp:refund']['href']);
            $transactionId = $transactionArr[count($transactionArr)-2];
        }
        return $transactionId;
    }
   
    /**
     * Gets Order Payment Request
     *
     * @param array $response
     * @return array
     */
    public static function getOrderPaymentRequest($response)
    {
        $paymentMethod = isset($response['_embedded']['payment'][0]['paymentMethod']) ? $response['_embedded']['payment'][0]['paymentMethod'] : null;
        if (isset($response['_embedded']['payment'][0]['state'])) {
            $transactionIdRes = explode(":", $response['_embedded']['payment'][0]['_id']);
            $transactionId = end($transactionIdRes);
        }
        return [
            'id_order' => $response['merchantOrderReference'],
            'amount' => $response['amount']['value'],
            'transaction_id' => isset($transactionId) ? $transactionId : null,
            'card_number' => isset($paymentMethod['pan']) ? $paymentMethod['pan'] : null,
            'card_brand' => isset($paymentMethod['name']) ? $paymentMethod['name'] : null,
            'card_expiration' => isset($paymentMethod['expiry']) ? $paymentMethod['expiry'] : null,
            'card_holder' => isset($paymentMethod['cardholderName']) ? $paymentMethod['cardholderName'] : null,
        ];
    }

    /**
     * Gets ngenius order by referance
     *
     * @param string $reference
     * @return array
     */
    public static function getNgeniusOrder($reference)
    {
        $sql = new \DbQuery();
        $sql->select('*')->from("ning_online_payment")->where('reference ="'.pSQL($reference).'"');
        return  \Db::getInstance()->getRow($sql);
    }

    /**
     * Update Ngenius Order Status To Processing
     *
     * @param string $reference
     * @return bool
     */
    public static function updateNgeniusOrderStatusToProcessing($ngenusOrder, $order)
    {
        $command = new Command();
        $config = new Config();
        $ngeniusOrder = [
            'status' => $config->getOrderStatus().'_PROCESSING',
            'reference' => $ngenusOrder['reference'],
        ];
        $command->updateNngeniusNetworkinternational($ngeniusOrder);
        $command->addCustomerMessage(null, $order);
        return true;
    }
}
