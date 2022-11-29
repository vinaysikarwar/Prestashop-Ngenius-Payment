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

use NGenius\Logger;

abstract class AbstractTransaction
{

    /**
     * Places request to gateway. Returns result as ENV array.
     *
     * @param TransferInterface $transferObject
     * @return array|bool
     * @throws Exception
     */
    public function placeRequest(TransferFactory $transferObject)
    {
        $logger = new Logger();
        $log = [];
        $log['path'] = __METHOD__;
        $data = $this->preProcess($transferObject->getBody());
        $result = array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $transferObject->getUri());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $transferObject->getHeaders());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($transferObject->getMethod() == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        if ($transferObject->getMethod() == "PUT") {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
                
        $response = curl_exec($ch);
        
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode == '409') {
            $result = json_decode($response);
            $_SESSION['ngenius_errors'] = reset($result->errors)->message;
            return false;
        }
        curl_close($ch);
        
        $result = json_decode($response);

        try {
            $log['response'] = $response;
            return $this->postProcess($response);
        } catch (Exception $e) {
            return false;
        } finally {
            $logger->addLog($log);
        }
    }
   
    /**
     * Processing of API request body
     *
     * @param array $data
     * @return string|array
     */
    abstract protected function preProcess(array $data);

    /**
     * Processing of API response
     *
     * @param array $response
     * @return array|bool
     */
    abstract protected function postProcess($response);
}
