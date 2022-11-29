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

class TransactionVoid extends Abstracttransaction
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
        $config = new Config();
        $command = new Command();
        $response = json_decode($responseEnc, true);
        if (isset($response['errors']) && is_array($response['errors'])) {
            return false;
        } else {
            $transactionId = '';
            if (isset($response['_links']['self']['href'])) {
                $transactionArr = explode('/', $response['_links']['self']['href']);
                $transactionId = end($transactionArr);
            }
            $state = isset($response['state']) ? $response['state'] : '';
            $orderReference = isset($response['orderReference']) ? $response['orderReference'] : '';
            $orderStatus = $config->getOrderStatus().'_AUTH_REVERSED';
            $ngeniusOrder = [
                'status' => $orderStatus,
                'state' => $state,
                'reference' => $orderReference,
                
            ];
            $command->updateNngeniusNetworkinternational($ngeniusOrder);
            $order = new \Order($response['merchantOrderReference']);
            $command->addCustomerMessage(json_decode(json_encode($response), true), $order);
            $_SESSION['ngenius_auth_reversed'] = 'true';
            $order->setCurrentState((int)\Configuration::get($orderStatus));
            return [
                'result' => [
                    'state' => $state,
                    'order_status' => $orderStatus,
                    'id_capture' => $transactionId,
                ]
            ];
        }
    }
}
