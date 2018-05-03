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

<form action="{$action}" id="payment-form" method="POST">
  <div class="cprow">
        <div class="cpcolumn">
            <p>
            Realiza tu pago a través de SPEI para cualquier banco, es muy rápido y sencillo.<br>
            Crea la orden y recibirás las instrucciones a tu email para dar de alta la cuenta y realizar la transferencia desde tu banca en línea.
            </p>
            <input type="hidden" name="compropagoProvider" value="SPEI">
        </div>
    </div>
</form>

