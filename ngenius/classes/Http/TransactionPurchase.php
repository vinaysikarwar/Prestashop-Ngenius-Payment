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

class TransactionPurchase extends AbstractTransaction
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
        $command = new Command();
        $response = json_decode($responseEnc);

        if (isset($response->_links->payment->href)) {
            $order = new \Order($response->merchantOrderReference);
            if ($command->placeNgeniusOrder($this->buildNgeniusData($response, $order))) {
                $command->addCustomerMessage(json_decode(json_encode($response), true), $order);
                return ['payment_url' => $response->_links->payment->href];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Build Ngenius Data Array
     *
     * @param array $response
     * @param array $order
     * @return array
     */
    protected function buildNgeniusData($response, $order)
    {
        $config = new Config();
        $data = [];
        $data['reference']  = isset($response->reference) ? $response->reference : '';
        $data['action']     = isset($response->action) ? $response->action : '';
        $data['state']      = isset($response->_embedded->payment[0]->state) ? $response->_embedded->payment[0]->state : '';
        $data['status']     = $config->getOrderStatus().'_PENDING';
        $data['id_order']   = isset($response->merchantOrderReference) ? $response->merchantOrderReference : '';
        $data['id_cart']    = isset($order->id_cart) ? $order->id_cart : '';
        $data['amount']     = isset($response->amount->value) ? $response->amount->value : '';
        $data['currency']   = isset($response->amount->currencyCode) ? $response->amount->currencyCode : '';
        $data['outlet_id']  = isset($response->outletId) ? $response->outletId : '';
        return $data;
    }
}
