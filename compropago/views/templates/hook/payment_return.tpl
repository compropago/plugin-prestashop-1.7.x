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

{if $status == 'ok'}
	<div class="compropagoDivFrame" id="compropagodContainer" style="width: 100%;">
    <iframe style="width: 100%;"
        id="compropagodFrame"
        src="https://compropago.com/comprobante?confirmation_id={$order_id_co}"
        frameborder="0"
        scrolling="yes"> </iframe>
</div>
<script type="text/javascript">
    function resizeIframe() {
        var container=document.getElementById("compropagodContainer");
        var iframe=document.getElementById("compropagodFrame");
        if(iframe && container){
            var ratio=585/811;
            var width=container.offsetWidth;
            var height=(width/ratio);
            if(height>937){ height=937;}
            iframe.style.width=width + 'px';
            iframe.style.height=height + 'px';
        }
    }
    window.onload = function(event) {
        resizeIframe();
    };
    window.onresize = function(event) {
        resizeIframe();
    };
</script>
{else}
	<p class="warning">
		{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' d='Modules.Compropago.Shop'}
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' d='Modules.Compropago.Shop'}</a>.
	</p>
{/if}
