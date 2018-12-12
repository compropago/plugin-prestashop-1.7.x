<?php

use CompropagoSdk\Resources\Payments\Cash as sdkCash;
use CompropagoSdk\Resources\Payments\Spei as sdkSpei;


class CompropagoValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * Convert Array to stdClass
	 */
	private function array2object(Array $array)
	{
		$object = new stdClass();
		foreach ($array as $key => $value)
			$object->$key = $value;

		return $object;
	}
	
	public function postProcess()
	{
		$cart = $this->context->cart;

		if (
			$cart->id_customer == 0 ||
			$cart->id_address_delivery == 0 ||
			$cart->id_address_invoice == 0 ||
			!$this->module->active
		) Tools::redirect('index.php?controller=order&step=1');

		/**
		 * Check that this payment option is still available
		 * in case the customer changed his address just before
		 * the end of the checkout process
		 */
		$authorized = false;            
		foreach (Module::getPaymentModules() as $module)
		{
			if ($module['name'] == 'compropago')
			{
				$authorized = true;
				break;
			}
		}

		if (!$authorized)
		{
			die($this->module->l('This payment method is not available.', 'validation'));
		}

		$customer = new Customer($cart->id_customer);
		$address = new Address($cart->id_address_invoice);

		if (!Validate::isLoadedObject($customer))
		{
			Tools::redirect('index.php?controller=order&step=1');
		}

		$currency = $this->context->currency;
		$total = (float) $cart->getOrderTotal(true, Cart::BOTH);
		
		$compropagoStore = (!isset($_POST['compropagoProvider']) || empty($_POST['compropagoProvider']))
			? 'SEVEN_ELEVEN'
			: $_POST['compropagoProvider'];
		
		$mailVars = [
			'{check_name}'			=> Configuration::get('CHEQUE_NAME'),
			'{check_address}'		=> Configuration::get('CHEQUE_ADDRESS'),
			'{check_address_html}'	=> str_replace("\n", '<br />', Configuration::get('CHEQUE_ADDRESS'))
		];

		$result = $this->module->validateOrder(
			(int) $cart->id,
			Configuration::get('COMPROPAGO_PENDING'),
			$total,
			$this->module->displayName,
			NULL,
			$mailVars,
			(int) $currency->id,
			false,
			$customer->secure_key
		);
		$OrderName = "Ref:{$this->module->currentOrder} " . Configuration::get('PS_SHOP_NAME');
		
		if ($compropagoStore == "SPEI")
		{
			# SPEI order
			$order = [
				"product" => [
					"id"		=> "{$this->module->currentOrder}",
					"price"		=> $total,
					"name"		=> $OrderName,
					"url"		=> "",
					"currency"	=> $currency->iso_code
				],
				"customer" => [
					"name"		=> $customer->firstname . ' ' . $customer->lastname,
					"email"		=> $customer->email,
					"phone"		=> ""
				],
				"payment" =>  [
					"type"		=> "SPEI"
				]
			];

			try
			{
				$client = (new sdkSpei)->withKeys(
					Configuration::get('COMPROPAGO_PUBLICKEY'),
					Configuration::get('COMPROPAGO_PRIVATEKEY')
				);
				$response = ($client->createOrder($order))['data'];
			}
			catch (\Exception $e)
			{
				die($this->module->l($e->getMessage(), 'validation'));
			}

			$recordTime = time();
			$ioIn = base64_encode(serialize($this->array2object($response)));
			$ioOut = base64_encode(serialize($order));

			$cpOrderRecord = [
				'date'					=> $recordTime,
				'modified'				=> $recordTime,
				'compropagoId'			=> $response['id'],
				'compropagoShortId'		=> $response['shortId'],
				'compropagoStatus'		=> $response['status'],
				'storeCartId'			=> $cart->id,
				'storeOrderId'			=> $this->module->currentOrder,
				'storeExtra'			=> 'SPEI',
				'ioIn'					=> $ioIn,
				'ioOut'					=> $ioOut
			];

			$cpTransactionRecord = [
				'orderId'				=> $this->module->currentOrder,
				'shortId'				=> $response['shortId'],
				'date'					=> $recordTime,
				'compropagoId'			=> $response['id'],
				'compropagoStatus'		=> $response['status'],
				'compropagoStatusLast'	=> $response['status'],
				'ioIn'					=> $ioIn,
				'ioOut'					=> $ioOut
			];
		}
		else
		{
			# Cash order
			$order = [
				'order_id'				=> $this->module->currentOrder,
				'order_name'			=> $OrderName,
				'order_price'			=> $total,
				'customer_name'			=> "{$customer->firstname} {$customer->lastname}",
				'customer_email'		=> $customer->email,
				'payment_type'			=> $compropagoStore,
				'currency'				=> $currency->iso_code,
				'image_url'				=> null,
				'app_client_name'		=> 'prestashop',
				'app_client_version'	=> _PS_VERSION_,
				'cp'					=> $address->postcode
			];

			try
			{
				$client = (new sdkCash)->withKeys(
					Configuration::get('COMPROPAGO_PUBLICKEY'),
					Configuration::get('COMPROPAGO_PRIVATEKEY')
				);
				$response = $client->createOrder($order);
			}
			catch (Exception $e)
			{
				die($this->module->l('This payment method is not available.', 'validation') . '<br />' . $e->getMessage());
			}

			if ( !isset($response['type']) || empty($response['type']) )
			{
				die($this->module->l('Update the API version, send mail to soporte@compropago.com', 'validation'));
			}

			if ( $response['type'] != 'charge.pending' )
			{
				die($this->module->l('This payment method is not available.', 'validation'));
			}

			if (!$this->module->verifyTables())
			{
				die($this->module->l('This payment method is not available.', 'validation') . '<br />ComproPago Tables Not Found');
			}

			$recordTime = time();
			$ioIn = base64_encode(serialize($response));
			$ioOut = base64_encode(serialize($this->array2object($order)));

			$cpOrderRecord = [
				'date'				=> $recordTime,
				'modified'			=> $recordTime,
				'compropagoId'		=> $response['id'],
				'compropagoShortId'	=> $response['short_id'],
				'compropagoStatus'	=> $response['type'],
				'storeCartId'		=> $cart->id,
				'storeOrderId'		=> $this->module->currentOrder,
				'storeExtra'		=> 'CASH',
				'ioIn'				=> $ioIn,
				'ioOut'				=> $ioOut
			];

			$cpTransactionRecord = [
				'orderId'				=> $response['order_info']['order_id'],
				'shortId'				=> $response['short_id'],
				'date'					=> $recordTime,
				'compropagoId'			=> $response['id'],
				'compropagoStatus'		=> $response['type'],
				'compropagoStatusLast'	=> $response['type'],
				'ioIn'					=> $ioIn,
				'ioOut'					=> $ioOut
			];
		}

		try
		{
			Db::getInstance()->insert('compropago_orders', $cpOrderRecord);
			Db::getInstance()->insert('compropago_transactions', $cpTransactionRecord);
		}
		catch (Exception $e)
		{
			die($this->module->l('This payment method is not available.', 'validation') . '<br />' . $e->getMessage());
		}
		
		# Redirect to order confirmation
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.((int) $cart->id).'&id_module='.((int) $this->module->id)."&id_order={$this->module->currentOrder}&key={$customer->secure_key}&compropagoId={$response['id']}");
	}
}
