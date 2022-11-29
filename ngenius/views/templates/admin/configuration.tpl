{*
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
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<ul>
    {foreach from=$config key=name item=value}           
        {assign var=name value=value}
    {/foreach}
</ul>
<form id="configuration_form" class="defaultForm form-horizontal ngenius" action="index.php?controller=AdminModules&configure=ngenius&token={$token}" method="post" enctype="multipart/form-data" novalidate="">
    <input type="hidden" name="submitngenius" value="1">
    <div class="panel" id="fieldset_0">
        <div class="panel-heading">Settings</div>
        <div class="form-wrapper">
            <div class="form-group">
                <label class="control-label col-lg-3 required">Display Name</label>
                <div class="col-lg-6">
                    <input type="text" name="DISPLAY_NAME" id="DISPLAY_NAME" value="{$config['DISPLAY_NAME']}" class="" required="required">
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3 required">Environment</label>
                <div class="col-lg-6">
                    <select name="ENVIRONMENT" class="t fixed-width-xl" id="ENVIRONMENT">
                        <option value="sandbox" {($config['ENVIRONMENT'] eq 'sandbox') ? 'selected="selected"' : ''}>Sandbox</option>
                        <option value="live" {($config['ENVIRONMENT'] eq 'live') ? 'selected="selected"' : ''}>Live</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3 required"> Payment Action</label>
                <div class="col-lg-6">
                    <select name="PAYMENT_ACTION" class="t fixed-width-xl" id="PAYMENT_ACTION">
                        <option value="authorize_capture" {($config['PAYMENT_ACTION'] eq 'authorize_capture') ? 'selected="selected"' : ''}>Sale</option>
                        <option value="authorize" {($config['PAYMENT_ACTION'] eq 'authorize') ? 'selected="selected"' : ''}>Authorize</option>
                        <option value="authorize_purchase" {($config['PAYMENT_ACTION'] eq 'authorize_purchase') ? 'selected="selected"' : ''}>Purchase</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3 required"> Sandbox API Url</label>
                <div class="col-lg-6">
                    <input type="text" name="UAT_API_URL" id="UAT_API_URL" value="{($config['UAT_API_URL']) ? $config['UAT_API_URL'] : "https://api-gateway-uat.acme.ngenius-payments.com" }" class="" required="required">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3 required"> Live API Url</label>
                <div class="col-lg-6">
                    <input type="text" name="LIVE_API_URL" id="LIVE_API_URL" value="{($config['LIVE_API_URL']) ? $config['LIVE_API_URL'] : "https://api-gateway.ngenius-payments.com" }" class="" required="required">
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3 required"> API Key</label>
                <div class="col-lg-6">
                    <input type="text" name="API_KEY" id="API_KEY" value="{$config['API_KEY']}" class="" required="required">
                </div>
            </div>
            <div class="cur-out">
                {foreach from=$currencyOutletid key=curoutkey item=curoutval} 
                    <div class="form-group">                    
                        <label class="control-label col-lg-3 required">
                            {if $curoutkey eq 0} Currency / Outlet ID {/if} 
                        </label>                     
                        <div class="col-lg-1">
                            <input type="text" maxlength="3" name="CURRENCY_OUTLETID[{$curoutkey}][CURRENCY]" id="CURRENCY_OUTLETID[{$curoutkey}][CURRENCY]" value="{$curoutval.CURRENCY}" class="" placeholder="CURRENCY" required>
                        </div>
                        <div class="col-lg-4">
                            <input type="text" name="CURRENCY_OUTLETID[{$curoutkey}][OUTLET_ID]" id="CURRENCY_OUTLETID[{$curoutkey}][OUTLET_ID]" value="{$curoutval.OUTLET_ID}" class="" placeholder="OUTLET ID" required>
                        </div>                    
                        <div class="col-lg-1">
                            {if $curoutkey eq 0}
                                <a class="btn add-btn" title="Add New Currency"><i class="process-icon-new"></i></a>
                            {else}
                                <a class="btn remove-lnk" title="Remove Currency"><i class="process-icon-close" style="color:red;"></i></a>
                            {/if}
                        </div>              
                    </div>
                {/foreach}
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3 required"> Debug</label>
                <div class="col-lg-6">
                    <select name="DEBUG" class="fixed-width-xl" id="DEBUG">
                        <option value="1" {($config['DEBUG'] eq '1') ? 'selected="selected"' : ''}>Yes</option>
                        <option value="0" {($config['DEBUG'] eq '0') ? 'selected="selected"' : ''}>No</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3 required">  Cron Schedule</label>
                <div class="col-lg-6">
                    <select name="NING_CRON_SCHEDULE" class="t fixed-width-xl" id="NING_CRON_SCHEDULE">
                        <option value="300" {($config['NING_CRON_SCHEDULE'] eq '300') ? 'selected="selected"' : ''}>Every 5 mins</option>
                        <option value="600" {($config['NING_CRON_SCHEDULE'] eq '600') ? 'selected="selected"' : ''}>Every 10 mins</option>
                        <option value="900" {($config['NING_CRON_SCHEDULE'] eq '900') ? 'selected="selected"' : ''}>Every 15 mins</option>
                        <option value="1800" {($config['NING_CRON_SCHEDULE'] eq '1800') ? 'selected="selected"' : ''}>Every 30 mins</option>
                        <option value="3600" {($config['NING_CRON_SCHEDULE'] eq '3600') ? 'selected="selected"' : ''}>Every hour</option>
                    </select>
                </div>
            </div>
        </div><!--/.form-wrapper-->
        <div class="panel-footer">
            <button type="submit" value="1" id="configuration_form_submit_btn" name="submitngenius" class="btn btn-default pull-left">
                <i class="process-icon-save"></i> Save
            </button>
        </div>
    </div>
</form>

<div class="panel clearfix">
    <h3>{$moduleName} cron task</h3>
    <p><b>Please add the below cron job in your cron module or server.</b></p>
    <p><b>This cron will run the Query API to retrieve the status of incomplete requests from Payment Gateway and update the order status in Prestashop.</b></p>
    <p><b>It is recommended to run this cron every minutes.</b> </p>
          <p><br/><b><a>*/1 * * * * curl "{$url}"</a> </b></p>       
        </div>
<script type="text/javascript">
    $(document).ready(function () {
      var max_input = 50;
      var x = {count($currencyOutletid) - 1};

      $('.add-btn').click(function (e) {
         
          console.log(x);
        e.preventDefault();
        console.log(x);
        if (x < max_input) { 
          x++;
          $('.cur-out').append(`
            <div class="form-group">
                <label class="control-label col-lg-3"> </label>                     
                <div class="col-lg-1">
                    <input type="text" maxlength="3" name="CURRENCY_OUTLETID[`+x+`][CURRENCY]" id="CURRENCY_OUTLETID[`+x+`][CURRENCY]" value="" class="" placeholder="CURRENCY" required>
                </div>
                <div class="col-lg-4">
                    <input type="text" name="CURRENCY_OUTLETID[`+x+`][OUTLET_ID]" id="CURRENCY_OUTLETID[`+x+`][OUTLET_ID]" value="" class="" placeholder="OUTLET ID" required>
                </div>
                <div class="col-lg-1">
                    <a class="btn remove-lnk" title="Remove Currency"><i class="process-icon-close" style="color:red;"></i></a>
                 </div>
            </div>
          `); 
        }
      });
      
      $('.cur-out').on("click", ".remove-lnk", function (e) {
        e.preventDefault();
        $(this).parent('div').parent('div').remove();
        x--; 
      })
 
    });
  </script>