{*
* 2007-2015 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<section>
{if $flag == false}

<div class="cprow">
    <div class="cpcolumn">
        <br>
        <h1>{l s="¡Servicio temporalmente fuera de servicio!" d='Modules.Compropago.Shop'}</h1>
    </div>
</div>

{else}

<div class="cprow">
    <div class="cpcolumn">
    <br>
        <h4>{l s="¿Dónde quieres pagar?<sup>*</sup>" d='Modules.Compropago.Shop'}</h4>
    </div>
</div>

{/if}


<form action="{$action}" id="payment-form" method="POST">
  <div class="cprow">
        <div class="cpcolumn">
            

                <div id="cppayment_store">
                    <select name="compropagoProvider" class="providers_list">
                        {foreach $providers as $provider}
                            <option value="{$provider->internal_name}">{$provider->name}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="cppayment_text">
                <br>
                    <p style="font-size:12px; color: #8f8f8f"><sup>*</sup>Comisionistas <a href="https://compropago.com/legal/corresponsales_cnbv.pdf" target="_blank" style="font-size:12px; color: #8f8f8f; font-weight:bold">autorizados por la CNBV</a> como corresponsales bancarios.</p>
                </div> 
            
        </div>
    </div>
</form>

</section>
