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

use NGenius\Config\Config;

class Model
{
    
    /**
     * Place Ngenius Order
     *
     * @param array $data
     * @return bool
     */
    public function placeNgeniusOrder($data)
    {
        $insertData = array(
            'id_cart' => (int) $data['id_cart'],
            'id_order' => (int) $data['id_order'],
            'amount' => (float) ($data['amount'] / 100),
            'currency' => pSQL($data['currency']),
            'reference' => pSQL($data['reference']),
            'action' => pSQL($data['action']),
            'status' => pSQL($data['status']),
            'state' => pSQL($data['state']),
            'outlet_id' => pSQL($data['outlet_id']),
            'id_payment' => null,
            'capture_amt' => null,
        );
        return (\Db::getInstance()->insert("ning_online_payment", $insertData)) ? (bool) true : (bool) false;
    }

    /**
     * Gets Ngenius Order
     *
     * @param int $orderId
     * @return bool
     */
    public static function getNgeniusOrder($orderId)
    {
        $sql = new \DbQuery();
        $sql->select('*')
            ->from("ning_online_payment")
            ->where('id_order ="'.pSQL($orderId).'"');
        return \Db::getInstance()->getRow($sql);
    }

    /**
     * Gets Ngenius Order
     *
     * @param int $orderId
     * @return bool
     */
    public static function getNgeniusOrderReference($orderRef)
    {
        $sql = new \DbQuery();
        $sql->select('*')
            ->from("ning_online_payment")
            ->where('reference ="'.pSQL($orderRef).'"');
        return \Db::getInstance()->getRow($sql);
    }

    /**
     * Update Nngenius Networkinternational order table
     *
     * @param array $data
     * @return bool
     */
    public static function updateNngeniusNetworkinternational($data)
    {
        \Db::getInstance()->update(
            'ning_online_payment',
            $data,
            'reference = "'.pSQL($data['reference']).'"'
        );
    }

    /**
     * Gets Customer Thread
     *
     * @param array $order
     * @return array|bool
     */
    public static function getCustomerThread($order)
    {
        $sql = new \DbQuery();
        $sql->select('*')->from("customer_thread")->where('id_order ="'.(int) $order->id.'"');
        if ($thread = \Db::getInstance()->getRow($sql)) {
            return $thread;
        } else {
            return false;
        }
    }

    /**
     * set Ngenius Order Email Content
     *
     * @param array $data
     * @return bool
     */
    public function addNgeniusOrderEmailContent($data)
    {
        return (\Db::getInstance()->insert("ning_order_email_content", $data)) ? (bool) true : (bool) false;
    }

    /**
     * Gets Ngenius Order Email Content
     *
     * @param int $customerId
     * @param int $savedCardId
     * @return bool
     */
    public function getNgeniusOrderEmailContent($idOrder)
    {
        $sql = new \DbQuery();
        $sql->select('*')
            ->from("ning_order_email_content")
            ->where('id_order ="'.pSQL($idOrder).'"');
        return \Db::getInstance()->getRow($sql);
    }

    /**
     * Update Ngenius Order Email Content
     *
     * @param array $data
     * @return bool
     */
    public static function updateNgeniusOrderEmailContent($data)
    {
        return \Db::getInstance()->update(
            'ning_order_email_content',
            $data,
            'id_order = "'.pSQL($data['id_order']).'"'
        );
    }

    /**
     * Set Ngenius cron schedule
     *
     * @return bool
     */
    public function addNgeniusCronSchedule()
    {
        $seconds = \Configuration::get('NING_CRON_SCHEDULE');
        $created_at = date("Y-m-d h:i:s");
        $scheduled_at = date("Y-m-d H:i:00", (strtotime(date($created_at)) + $seconds));
        $data = [
            'created_at' => $created_at,
            'scheduled_at' => $scheduled_at,
        ];
        return (\Db::getInstance()->insert("ning_cron_schedule", $data)) ? (bool) true : (bool) false;
    }

    /**
     * Gets Ngenius cron schedule
     *
     * @return bool
     */
    public function getNgeniusCronSchedule()
    {
        $sql = new \DbQuery();
        $sql->select('*')
            ->from("ning_cron_schedule")
            ->where('status ="'.pSQL('pending').'"');
        if ($result = \Db::getInstance()->getRow($sql)) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Update Ngenius cron schedule
     *
     * @param array $data
     * @return bool
     */
    public static function updateNgeniusCronSchedule($data)
    {
        return \Db::getInstance()->update(
            'ning_cron_schedule',
            $data,
            'id = "'.pSQL($data['id']).'"'
        );
    }

    /**
     * Gets Ngenius cron schedule
     *
     * @return bool
     */
    public function validateNgeniusCronSchedule()
    {
        $sql = new \DbQuery();
        $sql->select('*')
            ->from("ning_cron_schedule")
            ->where('status ="'.pSQL('pending').'" AND scheduled_at <= "'.date("Y-m-d h:i:s").'"');
        if ($result = \Db::getInstance()->executeS($sql)) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Gets Authorization Transaction
     *
     * @param array $ngeniusOrder
     * @return array|bool
     */
    public static function getAuthorizationTransaction($ngeniusOrder)
    {
        if (!empty($ngeniusOrder['id_payment']) && !empty($ngeniusOrder['reference']) && $ngeniusOrder['state'] == 'AUTHORISED') {
            return $ngeniusOrder;
        } else {
            return false;
        }
    }

    /**
     * Gets Refunded Transaction
     *
     * @param array $ngeniusOrder
     * @return array|bool
     */
    public static function getRefundedTransaction($ngeniusOrder)
    {
        if (isset($ngeniusOrder['id_capture']) &&  !empty($ngeniusOrder['id_capture']) && $ngeniusOrder['capture_amt'] > 0 && $ngeniusOrder['state'] == 'CAPTURED') {
            return $ngeniusOrder;
        } else {
            return false;
        }
    }

    /**
     * Gets Delivery Transaction
     *
     * @param array $ngeniusOrder
     * @return array|bool
     */
    public static function getDeliveryTransaction($ngeniusOrder)
    {
        if (isset($ngeniusOrder['id_payment']) &&  !empty($ngeniusOrder['id_capture']) && $ngeniusOrder['capture_amt'] > 0) {
            return $ngeniusOrder;
        } else {
            return false;
        }
    }

    /**
     * Gets Order Details Core
     *
     * @param int $customerId
     * @param int $savedCardId
     * @return bool
     */
    public function getOrderDetailsCore($idOrderDetail)
    {
        $sql = new \DbQuery();
        $sql->select('*')
            ->from("order_detail")
            ->where('id_order_detail ="'.pSQL($idOrderDetail).'"');
        return \Db::getInstance()->getRow($sql);
    }
}
