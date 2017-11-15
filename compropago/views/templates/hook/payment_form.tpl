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
        <h1>{l s="Tiendas disponibles." d='Modules.Compropago.Shop'}</h1>
        <p>{l s="Antes de finalizar seleccione la tienda de su preferencia." d='Modules.Compropago.Shop'}</p>
    </div>
</div>

{/if}

{$locationes}
<form action="{$action}" id="payment-form" method="POST">
  <div class="cprow">
        <div class="cpcolumn">
            {if $showLogo == 1}

                <ul class="providers_list">
                    {foreach $providers as $provider}
                        <li>
                            <input name="compropagoProvider" id="compropago_{$provider->internal_name}" type="radio" value="{$provider->internal_name}">
                            <label class="compropago-provider" for="compropago_{$provider->internal_name}">
                                <img src="{$provider->image_medium}" alt="{$provider->name}">
                            </label>
                        </li>
                    {/foreach}
                </ul>
                
                {if $location == 1}
                    <input name="compropago_latitude" id="compropago_latitude" type="hidden" value="compropago_latitude">
                    <input name="compropago_longitude" id="compropago_longitude" type="hidden" value="compropago_longitude">
                {/if}
            {else}

                <div id="cppayment_store">
                    <select name="compropagoProvider" class="providers_list">
                        {foreach $providers as $provider}
                            <option value="{$provider->internal_name}">{$provider->name}</option>
                        {/foreach}
                    </select>
                    {if $location == 1}
                        <input name="compropago_latitude" id="compropago_latitude" type="hidden" value="compropago_latitude">
                        <input name="compropago_longitude" id="compropago_longitude" type="hidden" value="compropago_longitude">
                    {/if}
                </div>

            {/if}
        </div>
    </div>
    <script>
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(e){
                var latitud = e.coords.latitude;
                var longitud = e.coords.longitude;
                document.getElementById("compropago_latitude").value = latitud;
                document.getElementById("compropago_longitude").value = longitud;
            }, function(errorCode){
                console.log("Error code localization: ");
                console.log(errorCode);
            });
        }
    </script>
</form>

</section>
