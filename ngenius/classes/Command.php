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

namespace NGenius;

use NGenius\Model;
use NGenius\Logger;
use NGenius\Config\Config;
use NGenius\Request\VoidRequest;
use NGenius\Request\SaleRequest;
use NGenius\Request\PurchaseRequest;
use NGenius\Request\TokenRequest;
use NGenius\Request\RefundRequest;
use NGenius\Request\CaptureRequest;
use NGenius\Request\OrderStatusRequest;
use NGenius\Request\AuthorizationRequest;
use NGenius\Http\TransferFactory;
use NGenius\Http\TransactionAuth;
use NGenius\Http\TransactionSale;
use NGenius\Http\TransactionPurchase;
use NGenius\Http\TransactionVoid;
use NGenius\Http\TransactionRefund;
use NGenius\Http\TransactionCapture;
use NGenius\Http\TransactionOrderRequest;
use NGenius\Validator\VoidValidator;
use NGenius\Validator\RefundValidator;
use NGenius\Validator\CaptureValidator;
use NGenius\Validator\ResponseValidator;

class Command extends Model
{
    /**
     * Order Authorize.
     *
     * @param array $order
     * @param float $amount
     * @return bool
     */
    public function authorize($order, $amount)
    {
        $authorizationRequest = new AuthorizationRequest();
        $transferFactory = new TransferFactory();
        $transactionAuth = new TransactionAuth();
        $responseValidator = new ResponseValidator();

        $requestData = $authorizationRequest->build($order, $amount);
        $transferObject = $transferFactory->create($requestData);
        $response = $transactionAuth->placeRequest($transferObject);
        return $responseValidator->validate($response);
    }

    /**
     * Order sale.
     *
     * @param array $order
     * @param float $amount
     * @return bool
     */
    public function order($order, $amount)
    {
        $saleRequest = new SaleRequest();
        $transferFactory = new TransferFactory();
        $transactionSale = new TransactionSale();
        $responseValidator = new ResponseValidator();

        $requestData = $saleRequest->build($order, $amount);
        $transferObject = $transferFactory->create($requestData);
        $response = $transactionSale->placeRequest($transferObject);
        return $responseValidator->validate($response);
    }

    /**
     * Order purchase.
     *
     * @param array $order
     * @param float $amount
     * @return bool
     */
    public function purchase($order, $amount)
    {
        $purchaseRequest = new PurchaseRequest();
        $transferFactory = new TransferFactory();
        $transactionPurchase = new TransactionPurchase();
        $responseValidator = new ResponseValidator();

        $requestData = $purchaseRequest->build($order, $amount);
        $transferObject = $transferFactory->create($requestData);
        $response = $transactionPurchase->placeRequest($transferObject);
        return $responseValidator->validate($response);
    }

    /**
     * Order capture.
     *
     * @param array $order
     * @param array $ngenusOrder
     * @return bool
     */
    public function capture($ngenusOrder)
    {
        $captureRequest = new CaptureRequest();
        $transferFactory = new TransferFactory();
        $transactionCapture = new TransactionCapture();
        $captureValidator = new CaptureValidator();

        $requestData = $captureRequest->build($ngenusOrder);
        $transferObject = $transferFactory->create($requestData);
        $response = $transactionCapture->placeRequest($transferObject);
        return $captureValidator->validate($response);
    }

    /**
     * Order void.
     *
     * @param array $order
     * @param array $ngenusOrder
     * @return bool
     */
    public function void($ngenusOrder)
    {
        $voidRequest = new VoidRequest();
        $transferFactory = new TransferFactory();
        $transactionVoid = new TransactionVoid();
        $voidValidator = new VoidValidator();

        $requestData = $voidRequest->build($ngenusOrder);
        $transferObject = $transferFactory->create($requestData);
        $response = $transactionVoid->placeRequest($transferObject);
        return $voidValidator->validate($response);
    }

    /**
     * Order refund.
     *
     * @param array $order
     * @param array $ngenusOrder
     * @return bool
     */
    public function refund($ngenusOrder)
    {

        $refundRequest = new RefundRequest();
        $transferFactory = new TransferFactory();
        $transactionRefund = new TransactionRefund();
        $refundValidator = new RefundValidator();

        $requestData = $refundRequest->build($ngenusOrder);
        $transferObject = $transferFactory->create($requestData);
        $response = $transactionRefund->placeRequest($transferObject);

        return $refundValidator->validate($response);
    }

    /**
     * Update Prestashop Order Payment table
     *
     * @param array $data
     * @return bool
     */
    public static function updatePsOrderPayment($data)
    {
        $logger = new Logger();
        $command = new Command();
        $log = array();
        $order = new \Order($data['id_order']);
        $log['path'] = __METHOD__;
        $orderPayment = new \OrderPayment();
        $orderPayment->order_reference = pSQL($command->getOrderReference($data['id_order']));
        $orderPayment->id_currency = (int) $order->id_currency;;
        $orderPayment->amount = (float) ($data['amount'] / 100);
        $orderPayment->payment_method = pSQL('N-Genius Payment Gateway');
        $orderPayment->transaction_id = pSQL($data['transaction_id']);
        $orderPayment->card_number = pSQL($data['card_number']);
        $orderPayment->card_brand = pSQL($data['card_brand']);
        $orderPayment->card_expiration = pSQL($data['card_expiration']);
        $orderPayment->card_holder = pSQL($data['card_holder']);
        if ($orderPayment->add()) {
            $log['ps_order_payment'] = true;
            $logger->addLog($log);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets Order Reference
     *
     * @param int $orderId
     * @return array|bool
     */
    public static function getOrderReference($orderId)
    {
        $order = new \Order($orderId);
        if (\Validate::isLoadedObject($order)) {
            return $order->reference;
        } else {
            return null;
        }
    }

    /**
     * Add Customer Message
     *
     * @param array $response
     * @param array $order
     * @return bool
     */
    public static function addCustomerMessage($response, $order)
    {
        $logger = new Logger();
        $command = new Command();
        $log = array();
        $log['path'] = __METHOD__;
        $command->addCustomerThread($order);
        $thread = $command->getCustomerThread($order);
        $message = $command->buildCustomerMessage($response, $order);
        $customer_message = new \CustomerMessage();
        $customer_message->id_customer_thread = (int) $thread['id_customer_thread'];
        $customer_message->private = (int) 1;
        $customer_message->message = pSQL($message);
        if ($customer_message->add()) {
            $log['customer_message'] = $message;
            $logger->addLog($log);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add Customer Thread
     *
     * @param array $order
     * @return bool
     */
    public static function addCustomerThread($order)
    {
        $command = new Command();
        if (!$command->getCustomerThread($order)) {
            $customer_thread = new \CustomerThread();
            $customer_thread->id_contact = (int) 0;
            $customer_thread->id_customer = (int) $order->id_customer;
            $customer_thread->id_shop = (int) $order->id_shop;
            $customer_thread->id_order = (int) $order->id;
            $customer_thread->id_lang = (int) $order->id_lang;
            $customer = new \Customer($order->id_customer);
            $customer_thread->email = $customer->email;
            $customer_thread->status = 'open';
            $customer_thread->token = \Tools::passwdGen(12);
            return ($customer_thread->add()) ? (bool) true : (bool) false;
        } else {
            return false;
        }
    }

    /**
     * biuld customer message for order
     *
     * @param array $response
     * @param array $order
     * @return string
     */
    public static function buildCustomerMessage($response, $order)
    {
        $command = new Command();
        $ngeniusOrder = $command->getNgeniusOrder($order->id);

        $message = '';
        if ($ngeniusOrder) {
            $status = 'Status : '.$ngeniusOrder['status'].' | ';
            $state = ' State : '.$ngeniusOrder['state'].' | ';
            $paymentId = null;
            $amount = null;

            if (isset($response['_embedded']['payment'][0])) {
                $paymentIdArr = explode(':', $response['_embedded']['payment'][0]['_id']);
                $paymentId = 'Transaction ID : '.end($paymentIdArr).' | ';
                $amount = $command->getTransactionAmount($response);
            }
            // capture
            if (isset($response['_embedded']['cnp:capture']) && is_array($response['_embedded']['cnp:capture'])) {
                $lastTransaction = end($response['_embedded']['cnp:capture']);
                if (isset($lastTransaction['_links']['self']['href'])) {
                    $transactionArr = explode('/', $lastTransaction['_links']['self']['href']);
                    $paymentId = 'Capture ID : '.end($transactionArr).' | ';
                }
                $amount = $command->getCaptureAmount($lastTransaction);
            }
            // refund
            if (isset($response['_embedded']['cnp:refund']) && is_array($response['_embedded']['cnp:refund'])) {
                $lastTransaction = end($response['_embedded']['cnp:refund']);
                $paymentId = $command->getRefundPaymentId($lastTransaction);
                $amount = $command->getRefundAmount($response, $lastTransaction);
            }
            $created = date('Y-m-d H:i:s');
            return $message.$status.$state.$paymentId.$amount.$created;
        } else {
            return $message;
        }
    }

    /**
     * get transaction amount
     *
     * @param array $response
     * @return string
     */
    public function getRefundPaymentId($lastTransaction)
    {
        $paymentId = null;
        if (isset($lastTransaction['_links']['self']['href'])) {
            $transactionArr = explode('/', $lastTransaction['_links']['self']['href']);
            $paymentId = 'Refunded ID : '.end($transactionArr).' | ';
        }
        return $paymentId;
    }

    /**
     * get transaction amount
     *
     * @param array $response
     * @return string
     */
    public function getTransactionAmount($response)
    {
        $amount = null;
        if (isset($response['_embedded']['payment'][0]['amount'])) {
            $value = $response['_embedded']['payment'][0]['amount']['value'] / 100;
            $currencyCode =  $response['_embedded']['payment'][0]['amount']['currencyCode'];
            $amount = 'Amount : '.$currencyCode.$value.' | ';
        }
        return $amount;
    }

    /**
     * get transaction amount
     *
     * @param array $lastTransaction
     * @return string
     */
    public function getCaptureAmount($lastTransaction)
    {
        $amount = null;
        if (isset($lastTransaction['state']) && ($lastTransaction['state'] == 'SUCCESS') && isset($lastTransaction['amount']['value'])) {
            $value = $lastTransaction['amount']['value'] / 100;
            $currencyCode =  $lastTransaction['amount']['currencyCode'];
            $amount = 'Amount : '.$currencyCode.$value.' | ';
        }
        return $amount;
    }

    /**
     * get refund amount
     *
     * @param array $response
     * @param array $lastTransaction
     * @return string
     */
    public function getRefundAmount($response, $lastTransaction)
    {
        $amount = null;
        foreach ($response['_embedded']['cnp:refund'] as $refund) {
            if (isset($refund['state']) && ($refund['state'] == 'SUCCESS') && isset($refund['amount']['value'])) {
                $value = $refund['amount']['value'] / 100;
                $currencyCode =  $lastTransaction['amount']['currencyCode'];
                $amount = 'Amount : '.$currencyCode.$value.' | ';
            }
        }
        return $amount;
    }

    /**
     * send order confirmation email
     *
     * @param object $order
     * @return bool
     */
    public function sendOrderConfirmationMail($order)
    {
        $command = new Command();
        $logger = new Logger();
        $log = [];
        $log['path'] = __METHOD__;
        $customer = new \Customer((int)$order->id_customer);
        $orderConfirmationData = $command->getNgeniusOrderEmailContent($order->id);
        if ($orderConfirmationData) {
            $data = unserialize($orderConfirmationData['data']);
            $orderLanguage = new \Language((int) $order->id_lang);
            \Mail::Send(
                (int) $order->id_lang,
                'order_conf',
                \Context::getContext()->getTranslator()->trans(
                    'Order confirmation',
                    array(),
                    'Emails.Subject',
                    $orderLanguage->locale
                ),
                $data,
                $customer->email,
                $customer->firstname.' '.$customer->lastname,
                null,
                null,
                null,
                null,
                _PS_MAIL_DIR_,
                false,
                (int) $order->id_shop
            );
            $mailData = array(
                'id_order' => (int) $order->id,
                'email_send' =>(int) 1,
                'sent_at' => date('Y-m-d H:i:s'),
            );
            $command->updateNgeniusOrderEmailContent($mailData);
            $log['order_confirmation_email'] = true;
            $logger->addLog($log);
            return true;
        }
        return false;
    }

    /**
     * Gets Order Status Request
     *
     * @param string $ref
     * @param int|null $storeId
     * @return array
     */
    public function getOrderStatusRequest($ref, $storeId = null)
    {
        $tokenRequest = new TokenRequest();
        $transferFactory = new TransferFactory();
        $transactionOrderRequest = new TransactionOrderRequest();
        $orderStatusRequest = new OrderStatusRequest();
        $requestData =  [
            'token'     => $tokenRequest->getAccessToken(),
            'request'   => $orderStatusRequest->getBuildArray($ref, $storeId),
        ];
        $transferObject =  $transferFactory->create($requestData);
        return $transactionOrderRequest->placeRequest($transferObject);
    }


    /**
     * validate mcp enabled order
     *
     * @param id $orderId
     * @return bool
     */
    public function validateMcpOrder($orderId)
    {
        /*$command = new Command();
        $orderReference = $command->getNgeniusOrder($orderId);
        if (isset($orderReference['reference'])) {
            $response = $command->getOrderStatusRequest($orderReference['reference']);
            $response = json_decode(json_encode($response), true);
            if (isset($response['_embedded']['payment'][0]['mcpResponse'])) {
                return true;
            }
        }*/
        return false;
    }
}
