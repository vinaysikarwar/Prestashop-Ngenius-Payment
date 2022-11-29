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
use NGenius\Logger;

class TransactionRefund extends Abstracttransaction
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
     * @param array $response
     * @return array|bool
     */
    protected function postProcess($responseEnc)
    {

        $response = json_decode($responseEnc, true);
        if (isset($response['errors']) && is_array($response['errors'])) {
            return false;
        } else {
            $lastTransaction = '';
            if (isset($response['_embedded']['cnp:refund']) && is_array($response['_embedded']['cnp:refund'])) {
                $lastTransaction = end($response['_embedded']['cnp:refund']);
            }

            $logger = new Logger();
            $logger->addLog("******************");
            $logger->addLog($response);
            $logger->addLog("********************");


            if (isset($lastTransaction['state']) && $lastTransaction['state'] == 'SUCCESS') {
                return $this->refundProcess($response, $lastTransaction);
            } else {
                return false;
            }
        }
    }

    /**
     * Processing refund response
     *
     * @param array $response
     * @return array|bool
     */
    protected function refundProcess($response, $lastTransaction)
    {
        $config = new Config();
        $command = new Command();
        //$captured_amt = $this->capturedAmount($response);
        $captured_amt = $response['amount']['value'];
        $refunded_amt = $this->refundedAmount($response);
        $logger = new Logger();
        $logger->addLog("refund amount");
        $logger->addLog($refunded_amt);
        $last_refunded_amt = $this->lastRefundAmount($lastTransaction);
        $transactionId = $this->transactionId($lastTransaction);
        $orderReference = isset($response['orderReference']) ? $response['orderReference'] : '';
        $state = isset($response['state']) ? $response['state'] : '';
        $captureAmt = $captured_amt > 0 ? $captured_amt / 100 : 0;
        $refundedAmt = $refunded_amt > 0 ? $refunded_amt / 100 : 0;
        if (($captureAmt - $refundedAmt) == 0) {
            $orderStatus = $config->getOrderStatus().'_FULLY_REFUNDED';
            $_SESSION['ngenius_fully_refund'] = 'true';
        } else {
            $orderStatus = $config->getOrderStatus().'_PARTIALLY_REFUNDED';
            $_SESSION['ngenius_partial_refund'] = 'true';
        }
        $ngeniusOrder = [
            'capture_amt' => (float)($captured_amt/100),
            'refunded_amt' => (float)($refunded_amt/100),
            'status' => $orderStatus,
            'state' => $state,
            'reference' => $orderReference
        ];

        $logger = new Logger();
        $logger->addLog("***********************");
        $logger->addLog($ngeniusOrder);
        $logger->addLog("***********************");

        $command->updateNngeniusNetworkinternational($ngeniusOrder);
        //$this->update_ngenius_order_table($ngeniusOrder);


        $order = new \Order($response['merchantOrderReference']);
        $command->addCustomerMessage(json_decode(json_encode($response), true), $order);
        $order->setCurrentState((int)\Configuration::get($orderStatus));
        return [
            'result' => [
                'total_refunded' => $refunded_amt,
                'refunded_amt' => $last_refunded_amt,
                'state' => $state,
                'order_status' => $orderStatus,
                'payment_id' => $transactionId
            ]
        ];
    }

    /**
     * get captured Amount
     *
     * @param array $response
     * @return string
     */
    protected function capturedAmount($response)
    {
        $captured_amt = 0;
        if (isset($response['_embedded']['cnp:capture']) && is_array($response['_embedded']['cnp:capture'])) {
            foreach ($response['_embedded']['cnp:capture'] as $capture) {
                if (isset($capture['state']) && ($capture['state'] == 'SUCCESS') && isset($capture['amount']['value'])) {
                    $captured_amt += $capture['amount']['value'];
                }
            }
            // for temp MCP enabled customers
            $captured_amt = $response['amount']['value'];
        }
        return $captured_amt;
    }

    /**
     * get refunded Amount
     *
     * @param array $response
     * @return string
     */
    protected function refundedAmount($response)
    {
        $refunded_amt = 0;
        if (isset($response['_embedded']['cnp:refund']) && is_array($response['_embedded']['cnp:refund'])) {
            foreach ($response['_embedded']['cnp:refund'] as $refund) {
                if (isset($refund['state']) && ($refund['state'] == 'SUCCESS') && isset($refund['amount']['value'])) {
                    $refunded_amt += $refund['amount']['value'];
                }
            }
        }
        return $refunded_amt;
    }

    /**
     * get last refund amount
     *
     * @param array $lastTransaction
     * @return string
     */
    protected function lastRefundAmount($lastTransaction)
    {
        $last_refunded_amt = 0;
        if (isset($lastTransaction['state']) && ($lastTransaction['state'] == 'SUCCESS') && isset($lastTransaction['amount']['value'])) {
            $last_refunded_amt = $lastTransaction['amount']['value'] / 100;
        }
        return $last_refunded_amt;
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
