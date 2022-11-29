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

namespace NGenius\Config;

use NGenius\Command;

class Config
{
    /**
     * Config tags
     */
    const TOKEN_ENDPOINT        = "/identity/auth/access-token";
    const ORDER_ENDPOINT        = "/transactions/outlets/%s/orders";
    const FETCH_ENDPOINT        = "/transactions/outlets/%s/orders/%s";
    const CAPTURE_ENDPOINT      = "/transactions/outlets/%s/orders/%s/payments/%s/captures";
    const VOID_AUTH_ENDPOINT    = "/transactions/outlets/%s/orders/%s/payments/%s/cancel";
    const REFUND_ENDPOINT       = "/transactions/outlets/%s/orders/%s/payments/%s/captures/%s/refund";
    const SANDBOX = 'sandbox';
    const LIVE    = 'live';
    

    /**
     * Gets Display Name.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDisplayName($storeId = null)
    {
        return \Configuration::get('DISPLAY_NAME', $storeId = null);
    }

    /**
     * Get XML data
     *
     * @return string Token
     */
    public function getConfig()
    {
        $file = _PS_MODULE_DIR_ . 'ngenius/bankconfig.xml';
        if (file_exists($file)) {
            return new \SimpleXMLElement(\Tools::file_get_contents($file));
        } else {
            return false;
        }
    }

    /**
     * Gets Module Name.
     *
     * @return string
     */
    public function getModuleName()
    {
        $config = $this->getConfig();
        if (!empty($config->name)) {
            return $config->name;
        }
        return false;
    }

    /**
     * Gets Module Display Name.
     *
     * @return string
     */
    public function getModuleDisplayName()
    {
        $config = $this->getConfig();
        if (!empty($config->displayName)) {
            return $config->displayName;
        }
        return false;
    }

    /**
     * Gets Module Description.
     *
     * @return string
     */
    public function getModuleDescription()
    {
        $config = $this->getConfig();
        if (!empty($config->description)) {
            return $config->description;
        }
        return false;
    }

    /**
     * Gets Order Status.
     *
     * @return string
     */
    public function getOrderStatus()
    {
        $config = $this->getConfig();
        if (!empty($config->orderStatus)) {
            return $config->orderStatus;
        }
        return false;
    }

    /**
     * Gets Order Status Label.
     *
     * @return string
     */
    public function getOrderStatusLabel()
    {
        $config = $this->getConfig();
        if (!empty($config->orderStatusLabel)) {
            return $config->orderStatusLabel;
        }
        return false;
    }

    /**
     * Get Sandbox API URL
     *
     * @return string URL
     */
    public function getSandboxApiUrl()
    {
        return \Configuration::get('UAT_API_URL');
    }

    /**
     * Get Live API URL
     *
     * @return string URL
     */
    public function getLiveApiUrl()
    {
        return \Configuration::get('LIVE_API_URL');
    }

       
    /**
     * Gets Api Key.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return \Configuration::get('API_KEY', $storeId);
    }

    /**
     * Gets Debug On.
     *
     * @param int|null $storeId
     * @return int
     */
    public function isDebugMode($storeId = null)
    {
        return (bool) \Configuration::get('DEBUG', $storeId);
    }

    /**
     * Gets Outlet Reference ID.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOutletReferenceId($orderRef, $storeId = null)
    {
        $command = new Command();
        $ngOrder = $command->getNgeniusOrderReference($orderRef);
        if ($ngOrder['outlet_id']) {
            return $ngOrder['outlet_id'];
        }
        return false;
    }

    /**
     * Gets Outlet Reference ID.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMultiOutletReferenceId($currency, $storeId = null)
    {
        $decCurOut = json_decode(\Configuration::get('CURRENCY_OUTLETID', $storeId), true);
        foreach ($decCurOut as $value) {
            if ($value['CURRENCY'] == $currency) {
                return $value['OUTLET_ID'];
            }
        }
        return false;
    }

    /**
     * Gets Initial Status.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getInitialStatus($storeId = null)
    {
        $config = new Config();
        return \Configuration::get($config->getOrderStatus().'_PENDING', $storeId);
    }

    /**
     * Gets value of configured environment.
     * Possible values: yes or no.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool) \Configuration::get('ENABLED', $storeId);
    }

    /**
     * Retrieve apikey and outletReferenceId empty or not
     *
     * @return bool
     */
    public function isComplete($storeId = null)
    {
        return (!empty(Config::getApiKey($storeId))) ? (bool) true : (bool) false;
    }

    /**
     * Gets Environment.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironment($storeId = null)
    {
        return \Configuration::get('ENVIRONMENT', $storeId);
    }

    
    /**
     * Gets Api Url.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl($storeId = null)
    {
        $value = $this->getLiveApiUrl();
        if($this->getEnvironment($storeId) == Config::SANDBOX){
            $value = $this->getSandboxApiUrl();
        }
        return $value;
    }
    
    /**
     * Gets Token Request URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTokenRequestURL($storeId = null)
    {
        $token_endpoint = Config::TOKEN_ENDPOINT;
        return $this->getApiUrl($storeId) . $token_endpoint;
    }

    /**
     * Gets Order Request URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderRequestURL($currency, $storeId = null)
    {
        $order_endpoint = Config::ORDER_ENDPOINT;
        $endpoint = sprintf($order_endpoint, $this->getMultiOutletReferenceId($currency, $storeId));
        return $this->getApiUrl($storeId) . $endpoint;
    }

    /**
     * Gets Fetch Request URL.
     *
     * @param int|null $storeId
     * @param string $orderRef
     * @return string
     */
    public function getFetchRequestURL($orderRef, $storeId = null)
    {
        $fetch_endpoint = Config::FETCH_ENDPOINT;
        $endpoint = sprintf($fetch_endpoint, $this->getOutletReferenceId($orderRef, $storeId), $orderRef);
        return $this->getApiUrl($storeId) . $endpoint;
    }

    /**
     * Gets Debug On.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugOn($storeId = null)
    {
        return (bool) $this->getValue(Config::DEBUG, $storeId);
    }

    /**
     * Gets Order Capture URL.
     *
     * @param int|null $storeId
     * @param string $orderRef
     * @param string $paymentRef
     * @return string
     */
    public function getOrderCaptureURL($orderRef, $paymentRef, $storeId = null)
    {
        $capture_endpoint = Config::CAPTURE_ENDPOINT;
        $endpoint = sprintf($capture_endpoint, $this->getOutletReferenceId($orderRef, $storeId), $orderRef, $paymentRef);
        return $this->getApiUrl($storeId) . $endpoint;
    }

    /**
     * Gets Order Void URL.
     *
     * @param int|null $storeId
     * @param string $orderRef
     * @param string $paymentRef
     * @return string
     */
    public function getOrderVoidURL($orderRef, $paymentRef, $storeId = null)
    {
        $void_endpoint = Config::VOID_AUTH_ENDPOINT;
        $endpoint = sprintf($void_endpoint, $this->getOutletReferenceId($orderRef, $storeId), $orderRef, $paymentRef);
        return $this->getApiUrl() . $endpoint;
    }

    /**
     * Gets Refund Void URL.
     *
     * @param int|null $storeId
     * @param string $orderRef
     * @param string $paymentRef
     * @param string $transactionId
     * @return string
     */
    public function getOrderRefundURL($orderRef, $paymentRef, $transactionId, $storeId = null)
    {
        $refund_endpoint = Config::REFUND_ENDPOINT;
        $endpoint = sprintf($refund_endpoint, $this->getOutletReferenceId($orderRef, $storeId), $orderRef, $paymentRef, $transactionId);
        return $this->getApiUrl() . $endpoint;
    }
}
