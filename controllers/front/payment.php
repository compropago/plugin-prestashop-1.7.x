<?php

/**
 * @since 1.5.0
 */
class CompropagoPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 * @throws PrestaShopException
	 */
	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
		{
			Tools::redirect('index.php?controller=order');
		}

		$this->context->smarty->assign([
			'nbProducts'		=> $cart->nbProducts(),
			'cust_currency'		=> $cart->id_currency,
			'currencies'		=> $this->module->getCurrency((int)$cart->id_currency),
			'total'				=> $cart->getOrderTotal(true, Cart::BOTH),
			'isoCode'			=> $this->context->language->iso_code,
			'checkName'			=> $this->module->checkName,
			'checkAddress'		=> Tools::nl2br($this->module->address),
			'this_path'			=> $this->module->getPathUri(),
			'this_path_check'	=> $this->module->getPathUri(),
			'this_path_ssl'		=> Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		]);

		$this->setTemplate('payment_execution.tpl');
	}
}
