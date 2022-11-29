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

use NGenius\Command;
use NGenius\Logger;
use NGenius\Config\Config;

class NGeniusValidationModuleFrontController extends ModuleFrontController
{
    
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $config = new Config();
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'ngenius') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            $this->errors[] = $this->l('This payment method is not available.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        if (!$config->isComplete()) {
            $this->errors[] = $this->l('This payment method is not configured.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }        

        $this->context->smarty->assign([
            'params' => $_REQUEST,
        ]);
        // validate Customer
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        // create order
        $cart     = $this->context->cart;
        $currency = $this->context->currency;
        $total    = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $mailVars = array( );

        if (!isset($cart->id)) {
            $this->errors[] = $this->l('Your Cart is empty!.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
        
        if (!$config->getMultiOutletReferenceId($this->context->currency->iso_code)) {
            $this->errors[] = $this->l('Invalid Combination of Currency & Outlet Id!.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
        
        $this->module->validateOrder(
            $cart->id,
            $config->getInitialStatus(),
            $total,
            $this->module->l($config->getModuleName(), 'validation'),
            null,
            $mailVars,
            (int)$currency->id,
            false,
            $customer->secure_key
        );
        
        $paymentType = \Configuration::get('PAYMENT_ACTION');
        $this->paymentActionProcess($paymentType, $total);
    }

    /**
     * Gets order.
     *
     * @return array
     */
    public function paymentActionProcess($paymentType, $total)
    {
        $command = new Command();
        $order = $this->getOrder();
        switch ($paymentType) {
            case "authorize_capture": // sale
                if ($paymentUrl = $command->order($order, $total)) {
                    \Tools::redirect($paymentUrl);
                } else {
                    $this->errors[] = $this->l('Oops something went wrong!.');
                    $this->redirectWithNotifications('index.php?controller=order&step=1');
                }
                break;
            case "authorize": // authorize
                if ($paymentUrl = $command->authorize($order, $total)) {
                    \Tools::redirect($paymentUrl);
                } else {
                    $this->errors[] = $this->l('Oops something went wrong!.');
                    $this->redirectWithNotifications('index.php?controller=order&step=1');
                }
                break;
            case "authorize_purchase": // purchase
                if ($paymentUrl = $command->purchase($order, $total)) {
                    \Tools::redirect($paymentUrl);
                } else {
                    $this->errors[] = $this->l('Oops something went wrong!.');
                    $this->redirectWithNotifications('index.php?controller=order&step=1');
                }
                break;
            default:
                $this->errors[] = $this->l('Invalid PAYMENT ACTION.');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
                break;
        }
    }

    /**
     * Gets order.
     *
     * @return array
     */
    public function getOrder()
    {
        $cart     = $this->context->cart;
        $address = new Address($cart->id_address_delivery);
        return [
            'action' => null,
            'amount' => [
                'currencyCode' => $this->context->currency->iso_code,
                'value' => (float) $cart->getOrderTotal(true, Cart::BOTH) * 100,
            ],
            'merchantAttributes' => [
                "redirectUrl" => \Tools::getHttpHost(true) . __PS_BASE_URI__.'module/ngenius/redirect',
            ],
            'billingAddress'    => [
                'firstName'     => $address->firstname,
                'lastName'      => $address->lastname,
                'address1'      => $address->address1,
                'city'          => $address->city,
                'countryCode'   => $this->context->country->iso_code,
            ],
            'emailAddress' => $this->context->customer->email,
            'merchantOrderReference' => $this->module->currentOrder,
            'method' => null,
            'uri' => null
        ];
    }
}
