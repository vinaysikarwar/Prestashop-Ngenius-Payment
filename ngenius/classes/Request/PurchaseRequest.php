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

namespace NGenius\Request;

use NGenius\Logger;
use NGenius\Config\Config;
use NGenius\Request\AbstractRequest;

class PurchaseRequest extends AbstractRequest
{
    /**
     * Builds ENV sale request array
     *
     * @param array $order
     * @param float $amount
     * @return array
     */
    public function getBuildArray($order, $amount)
    {
        $config = new Config();
        $logger = new Logger();
        $log = [];
        $log['path'] = __METHOD__;
        $storeId = isset(\Context::getContext()->shop->id) ? (int)\Context::getContext()->shop->id : null;
        $data = [
            'data' => [
                'action' => 'PURCHASE',
                'amount' => [
                    'currencyCode' =>  $order['amount']['currencyCode'],
                    'value' => strval($order['amount']['value']),
                ],
                'merchantAttributes' => [
                    'redirectUrl' => $order['merchantAttributes']['redirectUrl'],
                    'skipConfirmationPage' => true,
                ],
                'billingAddress'    => [
                    'firstName'     =>  $order['billingAddress']['firstName'],
                    'lastName'      =>  $order['billingAddress']['lastName'],
                    'address1'      =>  $order['billingAddress']['address1'],
                    'city'          =>  $order['billingAddress']['city'],
                    'countryCode'   =>  $order['billingAddress']['countryCode'],
                ],
                'merchantOrderReference' => $order['merchantOrderReference'],
                'emailAddress' => $order['emailAddress'],
                'merchantDefinedData' => [
                    'Plugin' => 'PrestaShop-1.7 | V-5.0.0'
                ],
            ],
            'method' => "POST",
            'uri' => $config->getOrderRequestURL($order['amount']['currencyCode'], $storeId)
        ];
        $log['sale_request'] = json_encode($data);
        $logger->addLog($log);
        return $data;
    }
}
