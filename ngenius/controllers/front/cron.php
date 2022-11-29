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
use NGenius\CronLogger;
use NGenius\Config\Config;

include_once _PS_MODULE_DIR_.'ngenius/controllers/front/redirect.php';

class NGeniusCronModuleFrontController extends NGeniusRedirectModuleFrontController
{
    /**
     * Cron Task.
     *
     * @return void
     */
    public function postProcess()
    {
        $command = new Command();
        $cronLogger = new CronLogger();
        $token = $_REQUEST['token'];
        if (isset($token) && $token == \Configuration::get('NING_CRON_TOKEN')) {
            if ($crondatas = $command->validateNgeniusCronSchedule()) {
                foreach ($crondatas as $crondata) {
                    $data = [
                        'id' => $crondata['id'],
                        'executed_at' => date("Y-m-d h:i:s"),
                        'status' => 'running',
                    ];
                    $command->updateNgeniusCronSchedule($data);
                    if ($this->cronTask()) {
                        $data = [
                            'id' => $crondata['id'],
                            'finished_at' => date("Y-m-d h:i:s"),
                            'status' => 'complete',
                        ];
                        $command->updateNgeniusCronSchedule($data);
                        $command->addNgeniusCronSchedule();
                        $cronLogger->addLog('Successfully run the cron job!.');
                        die;
                    }
                }
            } elseif (!$command->getNgeniusCronSchedule()) {
                $command->addNgeniusCronSchedule();
                $cronLogger->addLog('Successfully add the cron job!.');
                die;
            }
        } else {
            $cronLogger->addLog('Invalid Token!.');
            die;
        }
    }


    /**
     * Cron Task.
     *
     * @return bool|void
     */
    public function cronTask()
    {
        $sql = new DbQuery();
        $config = new Config();
        $command = new Command();
        $cronLogger = new CronLogger();
        $sql->select('*')
            ->from("ning_online_payment")
            ->where(
                'DATE_ADD(created_at, interval 60 MINUTE) < NOW() 
                AND status ="'.pSQL($config->getOrderStatus().'_PENDING').'" 
                AND (id_payment ="" OR id_payment ="null")'
            );
        $ngeniusOrders = \Db::getInstance()->executeS($sql);
        foreach ($ngeniusOrders as $ngeniusOrder) {
            $response = $command->getOrderStatusRequest($ngeniusOrder['reference']);
            $response = json_decode(json_encode($response), true);
            if ($response && isset($response['_embedded']['payment']) && is_array($response['_embedded']['payment'])) {
                if ($this->processOrder($response, $ngeniusOrder, true)) {
                    $cronLogger->addLog(json_encode($this->getNgeniusOrder($ngeniusOrder['reference'])));
                } else {
                    $cronLogger->addLog('Not Processed');
                }
            }
        }
        return true;
    }
}
