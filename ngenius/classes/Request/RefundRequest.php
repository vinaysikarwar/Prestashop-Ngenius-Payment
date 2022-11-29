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
use NGenius\Request\TokenRequest;

class RefundRequest
{

    const CNP_CAPTURE = "cnp:capture";
    const CNP_REFUND = "cnp:refund";
    const NGENIUS_EMBEDDED = '_embedded';
    /**
     * Builds ENV refund request
     *
     * @param array $order
      * @param array $ngenusOrder
     * @return array|bool
     */
    public function build($ngenusOrder)
    {
        $tokenRequest = new TokenRequest();
        $config = new Config();
        $logger = new Logger();
        $data = array();
        $log = [];
        $log['path'] = __METHOD__;
        $log['is_configured'] = false;
        $storeId = isset(\Context::getContext()->shop->id) ? (int)\Context::getContext()->shop->id : null;
        $amount = $ngenusOrder['amount'] * 100;
        $token = $tokenRequest->getAccessToken();
        $log['order_data'] = json_encode($ngenusOrder);
        $data['fetch_request_url'] = $config->getFetchRequestURL($ngenusOrder['reference']);
        $data['token'] = $token;

        $response = $this->query_order($data);

        if (isset($response->errors)) {
            return $response->errors[0]->message;
        }

        $payment = $response->_embedded->payment[0];

        $refund_url = $this->get_refund_url($payment);

        if ($config->isComplete()) {
            $log['is_configured'] = true;
            $data = [
                'token' => $token,
                'request' => [
                    'data' => [
                        'amount' => [
                            'currencyCode' => $ngenusOrder['currency'],
                            'value' => strval($amount),
                        ]
                    ],
                    'method' => "POST",
                    'uri' => $refund_url
                ]
            ];
            $logger->addLog($log);
            return $data;
        }
    }

    public function get_refund_url($payment){
        $refund_url = "";
        $cnpcapture = self::CNP_CAPTURE;
        $cnprefund = self::CNP_REFUND;
        if($payment->state == "PURCHASED" && isset($payment->_links->$cnprefund->href)){
            $refund_url = $payment->_links->$cnprefund->href;
        }elseif($payment->state == "CAPTURED" && isset($payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href)){
            $refund_url = $payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href;
        }else {
            if (isset($payment->_links->$cnprefund->href)) {
                $refund_url = $payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href;
            }
        }

        return $refund_url;
    }

    public function query_order($data){

        $authorization = "Authorization: Bearer " . $data['token'];
        $headers = array(
            'Content-Type: application/vnd.ni-payment.v2+json',
            $authorization,
            'Accept: application/vnd.ni-payment.v2+json'
        );

        $ch         = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => $data['fetch_request_url'],
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
        );

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        return json_decode($response);
    }
}
