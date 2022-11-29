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

class VoidRequest
{
    /**
     * Builds ENV void request
     *
     * @param array $order
     * @param array $ngenusOrder
     * @return array|bool
     */
    public function build($ngenusOrder)
    {
        $config = new Config();
        $logger = new Logger();
        $tokenRequest = new TokenRequest();
        $log = [];
        $log['path'] = __METHOD__;
        $log['is_configured'] = false;
        $storeId = isset(\Context::getContext()->shop->id) ? (int)\Context::getContext()->shop->id : null;
        if ($config->isComplete()) {
            $log['is_configured'] = true;
            $data = [
                'token' => $tokenRequest->getAccessToken(),
                'request' => [
                    'data' => [],
                    'method' => "PUT",
                    'uri' => $config->getOrderVoidURL($ngenusOrder['reference'], $ngenusOrder['id_payment'], $storeId)
                ]
            ];
            $log['void_request'] = json_encode($data);
            $logger->addLog($log);
            return $data;
        } else {
            return false;
        }
    }
}
