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

class TokenRequest
{
    /**
     * Builds access token request
     *
     * @return array|bool
     * @throws \PrestaShopException
     */
    public function getAccessToken()
    {

        $config = new Config();
        $logger = new Logger();
        $result = array();
        $log = [];
        $log['path'] = __METHOD__;
        $tokenRequestURL = $config->getTokenRequestURL();
        $tokenHeaders = array("Authorization: Basic ".$config->getApiKey(), "Content-Type: application/vnd.ni-identity.v1+json");
        $log['token_request'] = $tokenHeaders;
        $post = http_build_query([]);
        $response = $this->curl("POST", $tokenRequestURL, $tokenHeaders, $post);
        
        try {
            $result = json_decode($response);
            $log['response'] = $result;
            if (isset($result->access_token)) {
                return $result->access_token;
            } else {
                return false;
            }
        } catch (\PrestaShopException $e) {
            return false;
        } finally {
            $logger->addLog($log);
        }
    }

    /**
     * Gets curl.
     *
     * @param string $type
     * @param string $url
     * @param string $headers
     * @param string $post
     * @return array
     */
    public function curl($type, $url, $headers, $post)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($type == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $server_output = curl_exec($ch);
        curl_close($ch);
        return $server_output;
    }
}
