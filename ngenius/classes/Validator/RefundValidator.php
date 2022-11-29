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

namespace NGenius\Validator;

use NGenius\Logger;

class RefundValidator
{
    /**
     * Performs refund validation for transaction
     *
     * @param array $response
     * @return bool
     */
    public function validate($response)
    {
        $logger = new Logger();
        $log = [];
        $log['path'] = __METHOD__;

        if (!isset($response['result']) && !is_array($response['result'])) {
            $log['response_validate'] = false;
            $valid = false;
        } else {
            $log['response_validate'] = true;
            $valid = true;
        }

        $logger->addLog($log);
        return $valid;
    }
}
