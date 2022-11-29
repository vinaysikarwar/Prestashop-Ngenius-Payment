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

use NGenius\Config\Config;

class AdminNgeniusReportsController extends AdminController
{

    /**
     * function __construct
     *
     * @return void
     */
    
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'ning_online_payment';
        $this->lang = false;
        $this->explicitSelect = false;
        $this->allow_export = false;
        $this->deleted = false;
        $this->_orderBy = 'nid';
        $this->_orderWay = 'DESC';
        $this->list_no_link = true;
        $config = new Config();

        parent::__construct();
        $this->_use_found_rows = false;

        $status = $config->getOrderStatus();
        $label = $config->getOrderStatusLabel();
        
        $this->statusArr = [
            $status.'_PENDING'           => $label.' Pending',
            $status.'_AWAIT_3DS'         => $label.' Await 3DS',
            $status.'_PROCESSING'        => $label.' Processing',
            $status.'_FAILED'            => $label.' Failed',
            $status.'_COMPLETE'          => $label.' Complete',
            $status.'_AUTHORISED'        => $label.' Authorised',
            $status.'_FULLY_CAPTURED'    => $label.' Fully Captured',
            $status.'_AUTH_REVERSED'     => $label.' Auth Reversed',
            $status.'_FULLY_REFUNDED'    => $label.' Fully Refunded',
            $status.'_PARTIALLY_REFUNDED' => $label.' Partially Refunded'
        ];
        
        $this->fields_list = array(
            'id_order' => array(
                'title' => $this->trans('Id', array(), 'Admin.Global'),
                'orderby' => false,
                
            ),
           
            'amount' => array(
                'title' => $this->trans('Amount', array(), 'Admin.Global'),
                'callback' => 'setOrderCurrency',
                'orderby' => false,
            ),
            
            'reference' => array(
                'title' => $this->trans('Reference', array(), 'Admin.Global'),
                'orderby' => false,
            ),

            'action' => array(
                'title' => $this->trans('Action', array(), 'Admin.Global'),
                'orderby' => false,
            ),

            'state' => array(
                'title' => $this->trans('State', array(), 'Admin.Global'),
                'orderby' => false,
            ),
            
            'status' => array(
                'title' => $this->trans('Status', array(), 'Admin.Global'),
                'orderby' => false,
                'callback' => 'renderStatus',
            ),
             
           'capture_amt' => array(
                'title' => $this->trans('Capture Amount', array(), 'Admin.Global'),
                'orderby' => false,
                'callback' => 'setOrderCurrency',
            ),

            'id_payment' => array(
                'title' => $this->trans('Payment Id', array(), 'Admin.Global'),
                'orderby' => false,
            ),
            'created_at' => array(
                'title' => $this->trans('Date', array(), 'Admin.Global'),
                'orderby' => false,
                'type' => 'datetime',
                'filter_key' => 'a!created_at',
            ),
        );
    }

    /**
     * set order currency.
     *
     * @param array $echo
     * @param string $tr
     * @return string
     */
    public static function setOrderCurrency($echo, $tr)
    {
        $order = new \Order($tr['id_order']);
        return \Tools::displayPrice($echo, (int) $order->id_currency);
    }
    
    /**
     * Render List.
     *
     * @return object
     */
    public function renderList()
    {
        $this->_select = ' nid as  id_ning_online_payment';
        return parent::renderList();
    }

    /**
     * Render status.
     *
     * @param string $status
     * @return string
     */
    public function renderStatus($status)
    {
        return $this->statusArr[$status];
    }
}
