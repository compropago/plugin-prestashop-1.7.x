<?php
/*
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
*/

/**
 * @since 1.5.0
 */

use CompropagoSdk\Factory\Factory;

class CompropagoValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;            
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'compropago') {
                $authorized = true;
                break;
            }
        }
        
        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_invoice);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        

        $compropagoStore = (!isset($_POST['compropagoProvider']) || empty($_POST['compropagoProvider'])) ? 'SEVEN_ELEVEN' : $_POST['compropagoProvider'];
        $compropagoLatitude = (!isset($_POST['compropago_latitude']) || empty($_POST['compropago_latitude'])) ? '' : $_POST['compropago_latitude'];
        $compropagoLongitude = (!isset($_POST['compropago_longitude']) || empty($_POST['compropago_longitude'])) ? '' : $_POST['compropago_longitude'];

        $mailVars = array(
            '{check_name}' => Configuration::get('CHEQUE_NAME'),
            '{check_address}' => Configuration::get('CHEQUE_ADDRESS'),
            '{check_address_html}' => str_replace("\n", '<br />', Configuration::get('CHEQUE_ADDRESS')));

        $result = $this->module->validateOrder((int)$cart->id, Configuration::get('COMPROPAGO_PENDING'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
        $OrderName = 'Ref:' . $this->module->currentOrder . " " . Configuration::get('PS_SHOP_NAME');
   
        $order_info = [
                    'order_id'           => $this->module->currentOrder,
                    'order_name'         => $OrderName,
                    'order_price'        => $total,
                    'customer_name'      => $customer->firstname . ' ' . $customer->lastname,
                    'customer_email'     => $customer->email,
                    'payment_type'       => $compropagoStore,
                    'currency'           => $currency->iso_code,
                    'image_url'          => null,
                    'app_client_name'    => 'prestashop',
                    'app_client_version' => _PS_VERSION_,
                    'latitude'           => $compropagoLatitude,
                    'longitude'          => $compropagoLongitude,
                    'cp'                 => $address->postcode
                    ];

        $order = Factory::getInstanceOf('PlaceOrderInfo', $order_info);


        try {
            $response = $this->module->client->api->placeOrder($order);
        } catch (Exception $e) {
            die($this->module->l('This payment method is not available.', 'validation') . '<br>' . $e->getMessage());
        }
        
        if ($response->type != 'charge.pending') {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        if (!$this->module->verifyTables()) {
            die($this->module->l('This payment method is not available.', 'validation') . '<br>ComproPago Tables Not Found');
        }

        try {
            $recordTime = time();
            $ioIn = base64_encode(serialize($response));
            $ioOut = base64_encode(serialize($order));

            Db::getInstance()->insert('compropago_orders', array(
                'date'             => $recordTime,
                'modified'         => $recordTime,
                'compropagoId'     => $response->id,
                'compropagoStatus' => $response->type,
                'storeCartId'      => $cart->id,
                'storeOrderId'     => $this->module->currentOrder,
                'storeExtra'       => 'COMPROPAGO_PENDING',
                'ioIn'             => $ioIn,
                'ioOut'            => $ioOut
            ));

            Db::getInstance()->insert('compropago_transactions', array(
                'orderId'              => $response->order_info->order_id,
                'date'                 => $recordTime,
                'compropagoId'         => $response->id,
                'compropagoStatus'     => $response->type,
                'compropagoStatusLast' => $response->type,
                'ioIn'                 => $ioIn,
                'ioOut'                => $ioOut
            ));

        } catch (Exception $e) {
            die($this->module->l('This payment method is not available.', 'validation') . '<br>' . $e->getMessage());
        }
        
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key.'&compropagoId=' . $response->id);
    }
}












