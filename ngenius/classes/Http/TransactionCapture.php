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

namespace NGenius\Http;

use NGenius\Command;
use NGenius\Config\Config;
use NGenius\Http\AbstractTransaction;

class TransactionCapture extends AbstractTransaction
{
    /**
     * Processing of API request body
     *
     * @param array $data
     * @return string
     */
    protected function preProcess(array $data)
    {
        return json_encode($data);
    }
   
    /**
     * Processing of API response
     *
     * @param array $responseEnc
     * @return array|bool
     */
    protected function postProcess($responseEnc)
    {
        $response = json_decode($responseEnc, true);
        
        if (isset($response['errors']) && is_array($response['errors'])) {
            return false;
        } else {
            $lastTransaction = '';
            if (isset($response['_embedded']['cnp:capture']) && is_array($response['_embedded']['cnp:capture'])) {
                $lastTransaction = end($response['_embedded']['cnp:capture']);
            }
            if (isset($lastTransaction['state']) && $lastTransaction['state'] == 'SUCCESS') {
                return $this->captureProcess($response, $lastTransaction);
            } else {
                return false;
            }
        }
    }

    /**
     * Processing of capture response
     *
     * @param array $response
     * @param array $lastTransaction
     * @return array|bool
     */
    protected function captureProcess($response, $lastTransaction)
    {
        $config = new Config();
        $command = new Command();
        $capturedAmt = $this->captureAmount($lastTransaction);
        $transactionId = $this->transactionId($lastTransaction);
        $state = isset($response['state']) ? $response['state'] : '';
        $orderReference = isset($response['orderReference']) ? $response['orderReference'] : '';
        $orderStatus = $config->getOrderStatus().'_FULLY_CAPTURED';

        $ngeniusOrder = [
            'capture_amt' => $capturedAmt > 0 ? $capturedAmt / 100 : 0,
            'status' => $orderStatus,
            'state' => $state,
            'reference' => $orderReference,
            'id_capture' => $transactionId,
        ];
        $command->updateNngeniusNetworkinternational($ngeniusOrder);
        $order = new \Order($response['merchantOrderReference']);
        $command->addCustomerMessage(json_decode(json_encode($response), true), $order);
        $_SESSION['ngenius_fully_captured'] = 'true';
        $order->setCurrentState((int)\Configuration::get($orderStatus));
        return [
            'result' => [
                'captured_amt' => $capturedAmt,
                'state' => $state,
                'order_status' => $orderStatus,
                'payment_id' => $transactionId
            ]
        ];
    }

    /**
     * get capture Amount
     *
     * @param array $lastTransaction
     * @return string
     */
    protected function captureAmount($lastTransaction)
    {
        $capturedAmt = 0;
        if (isset($lastTransaction['state']) && ($lastTransaction['state'] == 'SUCCESS') && isset($lastTransaction['amount']['value'])) {
            $capturedAmt = $lastTransaction['amount']['value'];
        }
        return $capturedAmt;
    }

    /**
     * get transaction Id
     *
     * @param array $lastTransaction
     * @return string
     */
    protected function transactionId($lastTransaction)
    {
        $transactionId = '';
        if (isset($lastTransaction['_links']['self']['href'])) {
            $transactionArr = explode('/', $lastTransaction['_links']['self']['href']);
            $transactionId = end($transactionArr);
        }
        return $transactionId;
    }
}
