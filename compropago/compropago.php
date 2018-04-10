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

use CompropagoSdk\Client;
use CompropagoSdk\Factory\Factory;
use CompropagoSdk\Tools\Validations;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ .'/vendor/autoload.php';

class Compropago extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $publicKey;
    public $privateKey;
    public $execMode;
    public $cpCash;
    public $cpCashTitle;
    public $cpSpei;
    public $cpSpeiTitle;
    public $stores;
    public $client;
    public $extra_mail_vars;
    public $isActive = true;

    public function __construct()
    {
        $this->name             = 'compropago';
        $this->tab              = 'payments_gateways';
        $this->version          = '2.1.1';
        $this->author           = 'ComproPago';
        $this->controllers      = array('payment', 'validation');
        $this->currencies       = true;
        $this->currencies_mode  = 'checkbox';

        if (Tools::isSubmit('btnSubmit')){
            $config = [
                'COMPROPAGO_PUBLICKEY'  => Tools::getValue('COMPROPAGO_PUBLICKEY'), 
                'COMPROPAGO_PRIVATEKEY' => Tools::getValue('COMPROPAGO_PRIVATEKEY'), 
                'COMPROPAGO_MODE'       => Tools::getValue('COMPROPAGO_MODE'), 
                'COMPROPAGO_CASH'       => Tools::getValue('COMPROPAGO_CASH'),
                'COMPROPAGO_SPEI'       => Tools::getValue('COMPROPAGO_SPEI'),
                'COMPROPAGO_CASH_TITLE' => Tools::getValue('COMPROPAGO_CASH_TITLE'),
                'COMPROPAGO_SPEI_TITLE' => Tools::getValue('COMPROPAGO_SPEI_TITLE'),
                'COMPROPAGO_PROVIDER'   => Tools::getValue('COMPROPAGO_PROVIDER')
            ];
        } else {
            $config = Configuration::getMultiple([
                'COMPROPAGO_PUBLICKEY', 
                'COMPROPAGO_PRIVATEKEY', 
                'COMPROPAGO_MODE',                 
                'COMPROPAGO_CASH',
                'COMPROPAGO_SPEI',
                'COMPROPAGO_CASH_TITLE',
                'COMPROPAGO_SPEI_TITLE',
                'COMPROPAGO_PROVIDER'
            ]);
        }

        if (isset($config['COMPROPAGO_PUBLICKEY'])) {
            $this->publicKey = $config['COMPROPAGO_PUBLICKEY'];
        }

        if (isset($config['COMPROPAGO_PRIVATEKEY'])) {
            $this->privateKey = $config['COMPROPAGO_PRIVATEKEY'];
        }

        $this->execMode = (isset($config['COMPROPAGO_MODE'])) ? $config['COMPROPAGO_MODE'] : false;
        $this->cpCash   = (isset($config['COMPROPAGO_CASH'])) ? $config['COMPROPAGO_CASH'] : false;
        $this->cpSpei   = (isset($config['COMPROPAGO_SPEI'])) ? $config['COMPROPAGO_SPEI'] : false;
        
        # Most load selected
        $this->stores = explode(',',$config['COMPROPAGO_PROVIDER']);
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName      = $this->l('ComproPago', array(), 'Modules.compropago.Admin');
        $this->description      = $this->l('Este módulo te permite aceptar pagos en efectivo en México.', array(), 'Modules.compropago.Admin');
        $this->confirmUninstall = $this->l('Estás seguro de que quieres desinstalar el plugin?', array(), 'Modules.compropago.Admin');
        

        # Validate if exist the public and the private keys
        if ((!isset($this->publicKey) || !isset($this->privateKey) || empty($this->publicKey) || empty($this->privateKey))){
            $this->warning = $this->l('The Public Key and Private Key must be configured before using this module.');
        }

        $this->setComproPago($this->execMode);

        if($this->context->employee){

            $hook_data = $this->hookRetro(true, $this->publicKey, $this->privateKey, $this->execMode);

            if($hook_data[0]){
                $this->warning = $this->l($hook_data[1]);
                $this->stop = $hook_data[2];
            }
        }
        
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.compropago.Admin');
        }
    }

    /**
     * Config ComproPago SDK instance
     * @param boolean $moduleLive
     * @return boolean
     */
    private function setComproPago($moduleLive)
    {
        try{
            $this->client = new Client($this->publicKey, $this->privateKey, $moduleLive);
            return true;
        }catch (\Exception $e) {
            return false;
        }
    }

    /**
    * Install the module configurations
    * @return installation
    */
    public function install()
    {
        if (version_compare(phpversion(), '5.5.0', '<')) {
            return false;
        }

        try{
            $this->getDefaultProviders();
            $this->installOrderStates();
            $this->installTables();
        } catch (Exception $e) {
            echo 'Excepción capturada: ',  $e->getMessage(), "\n";
        }

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayHeader');
    }

    /**
    * Get the providers by default
    * @return array
    */
    public function getDefaultProviders()
    {
    
        global $currency;
        $this->client = new Client();
        $providers = $this->client->api->listDefaultProviders();
        $options = [];

        foreach ($providers as $provider){
            $options[] = $provider->internal_name;
        }
        $defaultProvider = implode(",", $options);
        Configuration::updateValue('COMPROPAGO_PROVIDER', $defaultProvider);
        Configuration::updateValue('COMPROPAGO_CASH', 0);
        Configuration::updateValue('COMPROPAGO_CASH_TITLE', "Pagos en efectivo - ");
        Configuration::updateValue('COMPROPAGO_SPEI', 0);
        Configuration::updateValue('COMPROPAGO_SPEI_TITLE', "Transferencia bancaria - ");
        
        Configuration::updateValue('COMPROPAGO_WEBHOOK',Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/webhook.php');
    }

    /**
     * Vertify is compropago tables exists
     * @return boolean
     * @since 2.0.0
     */
    public function verifyTables()
    {   
        if(!Db::getInstance()->execute("SHOW TABLES LIKE '"._DB_PREFIX_ ."compropago_orders'") || !Db::getInstance()->execute("SHOW TABLES LIKE '"._DB_PREFIX_ ."compropago_transactions'"))
        {
            return false;
        }
    return true;    
    }

    /**
    * Install the tables to save the ComproPago order
    */
    public function installTables()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'compropago_orders`;');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'compropago_transactions`;');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'compropago_webhook_transactions`;');
        
        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_  . 'compropago_orders` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `date` int(11) NOT NULL,
            `modified` int(11) NOT NULL,
            `compropagoId` varchar(50) NOT NULL,
            `compropagoShortId` varchar(50) NOT NULL,
            `compropagoStatus`varchar(50) NOT NULL,
            `storeCartId` varchar(255) NOT NULL,
            `storeOrderId` varchar(255) NOT NULL,
            `storeExtra` varchar(255) NOT NULL,
            `ioIn` mediumtext,
            `ioOut` mediumtext,
            PRIMARY KEY (`id`), UNIQUE KEY (`compropagoId`)
            )ENGINE=MyISAM DEFAULT CHARSET=utf8  DEFAULT COLLATE utf8_general_ci  AUTO_INCREMENT=1 ;');

        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'compropago_transactions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `orderId` int(11) NOT NULL,
            `shortId` varchar(10) NOT NULL,
            `date` int(11) NOT NULL,
            `compropagoId` varchar(50) NOT NULL,
            `compropagoStatus` varchar(50) NOT NULL,
            `compropagoStatusLast` varchar(50) NOT NULL,
            `ioIn` mediumtext,
            `ioOut` mediumtext,
            PRIMARY KEY (`id`)
            )ENGINE=MyISAM DEFAULT CHARSET=utf8  DEFAULT COLLATE utf8_general_ci  AUTO_INCREMENT=1 ;');

        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'compropago_webhook_transactions` (
            `id` integer not null auto_increment,
            `webhookId` varchar(50) not null,
            `webhookUrl` varchar(300) not null,
            `updated` integer not null,
            `status` varchar(50) not null,
            primary key(id)
            )ENGINE=MyISAM DEFAULT CHARSET=utf8  DEFAULT COLLATE utf8_general_ci  AUTO_INCREMENT=1 ;');
    }

    /**
     * Install ComproPago Order Status
     * @return boolean
     */
    protected function installOrderStates()
    {
        $valuesInsertPending   = array(
            'invoice'       => 0,
            'send_email'    => 0,
            'module_name'   => pSQL($this->name),
            'color'         => '#FFFF7F',
            'unremovable'   => 0,
            'hidden'        => 0,
            'logable'       => 1,
            'delivery'      => 0,
            'shipped'       => 0,
            'paid'          => 0,
            'deleted'       => 0
        );

        $valuesInsertSuccess   = array(
            'invoice'       => 0,
            'send_email'    => 0,
            'module_name'   => pSQL($this->name),
            'color'         => '#CCFF00',
            'unremovable'   => 0,
            'hidden'        => 0,
            'logable'       => 1,
            'delivery'      => 0,
            'shipped'       => 0,
            'paid'          => 0,
            'deleted'       => 0
        );

        $valuesInsertExpired   = array(
            'invoice'       => 0,
            'send_email'    => 0,
            'module_name'   => pSQL($this->name),
            'color'         => '#FF3300',
            'unremovable'   => 0,
            'hidden'        => 0,
            'logable'       => 1,
            'delivery'      => 0,
            'shipped'       => 0,
            'paid'          => 0,
            'deleted'       => 0
        );

        if (!Db::getInstance()->insert('order_state', $valuesInsertPending)) {
            return false;
        }
        $idOrder = (int) Db::getInstance()->Insert_ID();
        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            Db::getInstance()->insert('order_state_lang', array(
                'id_order_state'    => $idOrder,
                'id_lang'           => $language['id_lang'],
                'name'              => $this->l('ComproPago - Pendiente'),
                'template'          => '',
            ));
        }

        Configuration::updateValue('COMPROPAGO_PENDING', $idOrder);
        unset($idOrder);
        
        if (!Db::getInstance()->insert('order_state', $valuesInsertSuccess)) {
            return false;
        }
        $idOrder = (int) Db::getInstance()->Insert_ID();
        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            Db::getInstance()->insert('order_state_lang', array(
                'id_order_state'    => $idOrder,
                'id_lang'           => $language['id_lang'],
                'name'              => $this->l('ComproPago - Exitoso'),
                'template'          => '',
            ));
        }

        Configuration::updateValue('COMPROPAGO_SUCCESS', $idOrder);
        unset($idOrder);

        if (!Db::getInstance()->insert('order_state', $valuesInsertExpired)) {
            return false;
        }
        $idOrder = (int) Db::getInstance()->Insert_ID();
        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            Db::getInstance()->insert('order_state_lang', array(
                'id_order_state'    => $idOrder,
                'id_lang'           => $language['id_lang'],
                'name'              => $this->l('ComproPago - Expirado'),
                'template'          => '',
            ));
        }
        Configuration::updateValue('COMPROPAGO_EXPIRED', $idOrder);
        unset($idOrder);

    }
    
    /**
     * Uninstall Module
     * @return boolean
     */
    public function uninstall()
    {   
        return Configuration::deleteByName('COMPROPAGO_PUBLICKEY')
            && Configuration::deleteByName('COMPROPAGO_PRIVATEKEY')
            && Configuration::deleteByName('COMPROPAGO_MODE')
            && Configuration::deleteByName('COMPROPAGO_WEBHOOK')
            && Configuration::deleteByName('COMPROPAGO_SPEI')
            && Configuration::deleteByName('COMPROPAGO_CASH')
            && Configuration::deleteByName('COMPROPAGO_PROVIDER')
            && Configuration::deleteByName('COMPROPAGO_PENDING')
            && Configuration::deleteByName('COMPROPAGO_SUCCESS')
            && Configuration::deleteByName('COMPROPAGO_EXPIRED')
            && $this->uninstallTables()
            && parent::uninstall();
    }

    public function uninstallTables()
    {
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'compropago_orders`;');
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'compropago_transactions`;');
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'compropago_webhook_transactions`;');
    }

    /**
     * Generacion de retro alimentacion de configuracion al guardar
     * necesita activarse en getcontent ... Evaluar
     * @return array
     */
    public function hookRetro($enabled, $publickey, $privatekey, $live)
    {
        $error = array(false,'','yes');
        if($enabled){
            if(!empty($publickey) && !empty($privatekey) ){
                try{
                    $client = new Client($publickey, $privatekey, $live);
                    $compropagoResponse = Validations::evalAuth($client);
                    //eval keys
                    if(!Validations::validateGateway($client)){
                        $error[1] = 'Invalid Keys, The Public Key and Private Key must be valid before using this module.';
                        $error[0] = true;
                    }else{
                        if($compropagoResponse->mode_key != $compropagoResponse->livemode){
                            $error[1] = 'Your Keys and Your ComproPago account are set to different Modes.';
                            $error[0] = true;
                        }else{
                            if($live != $compropagoResponse->livemode){
                                $error[1] = 'Your Store and Your ComproPago account are set to different Modes.';
                                $error[0] = true;
                            }else{
                                if($live != $compropagoResponse->mode_key){
                                    $error[1] = 'ComproPago ALERT:Your Keys are for a different Mode.';
                                    $error[0] = true;
                                }else{
                                    if(!$compropagoResponse->mode_key && !$compropagoResponse->livemode){
                                        $error[1] = 'WARNING: ComproPago account is Running in TEST Mode, NO REAL OPERATIONS';
                                        $error[0] = true;
                                    }
                                }
                            }
                        }
                    }
                }catch (Exception $e) {
                    $error[2] = 'no';
                    $error[1] = $e->getMessage();
                    $error[0] = true;
                }
            }else{
                $error[1] = 'The Public Key and Private Key must be set before using ComproPago';
                $error[2] = 'no';
                $error[0] = true;
            }
        }else{
            $error[1] = 'ComproPago is not Enabled';
            $error[2] = 'no';
            $error[0] = true;
        }
        return $error;
    }

    /**
    * Install the css files to the views
    */
    public function hookDisplayHeader($params)
    {
        // CSS FILES
        $this->context->controller->addCSS(strtolower($this->_path).'views/assets/css/cp-style.css', 'all');
        $this->context->controller->addCSS(strtolower($this->_path).'views/assets/css/ps-default.css', 'all');
        $this->context->controller->addJS($this->_path.'views/assets/js/ps-default.js', 'all');
        $this->context->controller->addJS($this->_path.'views/assets/js/providers.js', 'all');
    }
    
    /**
     * Validate module config form
     */
    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('COMPROPAGO_PUBLICKEY')) {
                $this->_postErrors[] = $this->l('The Public Key is required');
            }elseif (!Tools::getValue('COMPROPAGO_PRIVATEKEY')){
                $this->_postErrors[] = $this->l('The Private Key is required');
            }
        }
    }

    /**
     *Refresh configed data after module config updated
     */
    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {   
            Configuration::updateValue('COMPROPAGO_PUBLICKEY', Tools::getValue('COMPROPAGO_PUBLICKEY'));
            Configuration::updateValue('COMPROPAGO_PRIVATEKEY', Tools::getValue('COMPROPAGO_PRIVATEKEY'));
            Configuration::updateValue('COMPROPAGO_WEBHOOK', Tools::getValue('COMPROPAGO_WEBHOOK'));
            Configuration::updateValue('COMPROPAGO_MODE', Tools::getValue('COMPROPAGO_MODE'));
            Configuration::updateValue('COMPROPAGO_CASH', Tools::getValue('COMPROPAGO_CASH'));
            Configuration::updateValue('COMPROPAGO_CASH_TITLE', Tools::getValue('COMPROPAGO_CASH_TITLE'));
            Configuration::updateValue('COMPROPAGO_SPEI', Tools::getValue('COMPROPAGO_SPEI'));
            Configuration::updateValue('COMPROPAGO_SPEI_TITLE', Tools::getValue('COMPROPAGO_SPEI_TITLE'));
            $prov = implode(',',Tools::getValue('COMPROPAGO_PROVIDERS_selected'));
            Configuration::updateValue('COMPROPAGO_PROVIDER', $prov);
        } 

        if ($this->stop) {
            if (!Tools::getValue('COMPROPAGO_PUBLICKEY') && !Tools::getValue('COMPROPAGO_PRIVATEKEY')) {
                return false;
            } else {
                try {
                    $this->client->api->createWebhook(Tools::getValue('COMPROPAGO_WEBHOOK'));
                    $this->_html .= $this->displayConfirmation($this->l('Opciones actualizadas'));
                } catch (\Exception $e) {
                    if ($e->getMessage() != 'Error: conflict.urls.create') {
                        $this->_html .= $this->displayError($e->getMessage());
                    }
                }
            }
            $this->_html .= $this->displayError($this->warning);
        } else {
            try {
                $this->client->api->createWebhook(Tools::getValue('COMPROPAGO_WEBHOOK'));
                $this->_html .= $this->displayConfirmation($this->l('Opciones actualizadas'));
            } catch (\Exception $e) {
                if ($e->getMessage() != 'Error: conflict.urls.create') {
                    $this->_html .= $this->displayError($e->getMessage());
                }
            }
        }
    }

    /**
    * Display an alert to back ofice
    */
    private function _displayCompropago()    
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    /**
     * Show Errors & load description, and after submit information at admin configuration page
     * @return mixed
     */
    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->_displayCompropago();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    /**
    * Check against SDK if module is valid for use
    * @return boolean
    */
    public function checkCompropago()
    {
        try {
            return Validations::validateGateway($this->client);
        }catch (\Exception $e) {
            return true;
        }
    }

    /**
    * Render Cash payment method
    * @return boolean
    */
    public function getPaymentCash()
    {
       $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->l(Configuration::get('COMPROPAGO_CASH_TITLE')." ", array(), "Modules.compropago.Admin"))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/compropago-efectivo-v2.png'))
            ->setForm($this->generateCashForm());
            return $newOption;
    }

    /**
    * Render Spei payment method
    * @return boolean
    */
    public function getPaymentSpei()
    {
       $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->l(Configuration::get('COMPROPAGO_SPEI_TITLE')." ", array(), "Modules.compropago.Admin"))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/compropago-spei-v2.png'))
            ->setForm($this->generateSpeiForm());
            return $newOption;
    }

    /**
    * Get the view of options payment
    * @return array()
    */
    public function hookPaymentOptions($params)
    {
        if (!empty($this->publicKey) && !empty($this->privateKey)) {


            if (!$this->active) {
                return;
            }
            if (!$this->checkCurrency($params['cart'])) {
                return;
            }
            
            if($this->cpCash == "1") {
                $payment_options[] = $this->getPaymentCash();
            }

            if($this->cpSpei == "1") {
                $payment_options[] = $this->getPaymentSpei();
            }
            
        return $payment_options;
        }
    }   

    /**
    * Return the payment order
    * @return array()
    */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (in_array($state, array(Configuration::get('COMPROPAGO_PENDING'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')))) {
            
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'shop_name'     => $this->context->shop->name,
                'order_id_co'   => $_REQUEST['compropagoId'],
                'checkName'     => $this->checkName,
                'checkAddress'  => Tools::nl2br($this->address),
                'status'        => 'ok',
                'id_order'      => $params['order']->id
            ));
            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $this->smarty->assign('reference', $params['order']->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }
        return $this->fetch('module:compropago/views/templates/hook/payment_return.tpl');
    }


    /**
    * Get the local currency
    * @return boolean
    */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);
        if ($currency_order->iso_code=='MXN' || $currency_order->iso_code=='USD' || $currency_order->iso_code=='EUR' || $currency_order->iso_code=='GBP') {
            return true;
        }
        return false;
    }

    /**
    * Back office form
    * @return array() 
    */
    public function renderForm()
    {
        try{
            if (Configuration::get('COMPROPAGO_SUCCESS') == false 
                || Configuration::get('COMPROPAGO_PENDING') == false 
                || Configuration::get('COMPROPAGO_EXPIRED') == false)
            {
                $this->installOrderStates();
            }
            if (!$this->verifyTables()){
                $this->installTables();
            }
            
            $providers = $this->client->api->listDefaultProviders();
            $options = [];

            foreach ($providers as $provider){
                $options[] = [
                    'id_option' => $provider->internal_name,
                    'name'      => $provider->name
                ];
            }


            $config_form = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l(' Configuración'),
                        'image' => '../modules/compropago/icon.png'
                    ),
                    'input' => array(
                        array(
                            'type'     => 'text',
                            'label'    => $this->l('Llave Pública'),
                            'name'     => 'COMPROPAGO_PUBLICKEY',
                            'class'    => 'input fixed-width-xxl',
                            'required' => true
                        ),
                        array(
                            'type'     => 'text',
                            'label'    => $this->l('Llave Privada'),
                            'name'     => 'COMPROPAGO_PRIVATEKEY',
                            'class' => 'input fixed-width-xxl',
                            'required' => true
                        ),
                        array(
                            'type'     => 'switch',
                            'label'    => $this->l('Live Mode'),
                            'desc'     => $this->l('Estas en modo activo o en pruebas?, Cambia tus llaves de acuerdo al modo').':<a href="https://compropago.com/panel/configuracion" target="_blank">'.$this->l('Panel ComproPago').'</a>.',
                            'name'     => 'COMPROPAGO_MODE',
                            'is_bool'  => true,
                            'required' => true,
                            'values'   => array(
                                array(
                                    'id'    => 'active_on_bv',
                                    'value' => true,
                                    'label' => $this->l('Modo Activo')
                                ),
                                array(
                                    'id'    => 'active_off_bv',
                                    'value' => false,
                                    'label' => $this->l('Modo Pruebas')
                                )
                            )
                        ),
                        array(
                            'type'     => 'hidden',
                            'name'     => 'COMPROPAGO_WEBHOOK',
                            'required' => false
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    )
                ),
            );
            $cash_form = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('- Pago en efectivo '),
                        'image' => '../modules/compropago/compropago-efectivo-v2.png'
                    ),
                    'input' => array(
                         array(
                            'type'     => 'switch',
                            'label'    => $this->l('Habilitar'),
                            'desc'     => $this->l('Seleccione esta opción para activar los pagos en efectivo'),
                            'name'     => 'COMPROPAGO_CASH',
                            'is_bool'  => true,
                            'required' => true,
                            'values'   => array(
                                array(
                                    'id'    => 'active_on_bv',
                                    'value' => true,
                                    'label' => $this->l('Modo Activo')
                                ),
                                array(
                                    'id'    => 'active_off_bv',
                                    'value' => false,
                                    'label' => $this->l('Modo Pruebas')
                                )
                            )
                        ),
                        array(
                            'type'     => 'text',
                            'label'    => $this->l('Titulo'),
                            'name'     => 'COMPROPAGO_CASH_TITLE',
                            'class'    => 'input fixed-width-xxl',
                            'style'    => 'background:#000',
                            'required' => true
                        ),
                        array(
                            'type'     => 'swap',
                            'multiple' => true,
                            'label'    => $this->l('Tiendas'),
                            'desc'     => $this->l('Selecciona las tiendas que quieres mostrar'),
                            'name'     => 'COMPROPAGO_PROVIDERS',
                            'options'  => array(
                                'query' => $options, 
                                'id'    => 'id_option', 
                                'name'  => 'name'
                            )
                        ),
                        ///END OF FIELDS
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    )
                ),
            );
            $spei_form = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('- Transferencias SPEI'),
                        'image' => '../modules/compropago/compropago-spei-v2.png'
                    ),
                    'input' => array(
                        array(
                            'type'     => 'switch',
                            'label'    => $this->l('Habilitar'),
                            'desc'     => $this->l('Seleccione esta opción para activar los pagos vía SPEI'),
                            'name'     => 'COMPROPAGO_SPEI',
                            'is_bool'  => true,
                            'required' => true,
                            'values'   => array(
                                array(
                                    'id'    => 'active_on_bv',
                                    'value' => true,
                                    'label' => $this->l('Modo Activo')
                                ),
                                array(
                                    'id'    => 'active_off_bv',
                                    'value' => false,
                                    'label' => $this->l('Modo Pruebas')
                                )
                            )
                        ),
                        array(
                            'type'     => 'text',
                            'label'    => $this->l('Titulo'),
                            'name'     => 'COMPROPAGO_SPEI_TITLE',
                            'class'    => 'input fixed-width-xxl',
                            'required' => true
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    )
                ),
            );
            $helper = new HelperForm();
            $helper->show_toolbar = false;
            $helper->table = $this->table;
            $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
            $helper->default_form_language = $lang->id;
            $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
            $this->fields_form = array();
            $helper->id = (int)Tools::getValue('id_carrier');
            $helper->identifier = $this->identifier;
            $helper->submit_action = 'btnSubmit';
            $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->tpl_vars = array(
                'fields_value'  => $this->getConfigFieldsValues(),
                'languages'     => $this->context->controller->getLanguages(),
                'id_language'   => $this->context->language->id
            );
            
            return $helper->generateForm(array($config_form, $cash_form, $spei_form));
        } catch (\Exception $e) {
            die("Error al crear el formulario: " . $e->message);
        }
    }

    /**
    * Data to put in the back office
    * @return array 
    */
    public function getConfigFieldsValues()
    {
        $prov = explode(',',Configuration::get('COMPROPAGO_PROVIDER') );
        return array(
            'COMPROPAGO_PUBLICKEY'  => Tools::getValue('COMPROPAGO_PUBLICKEY', Configuration::get('COMPROPAGO_PUBLICKEY')),
            'COMPROPAGO_PRIVATEKEY' => Tools::getValue('COMPROPAGO_PRIVATEKEY', Configuration::get('COMPROPAGO_PRIVATEKEY')),
            'COMPROPAGO_MODE'       => Tools::getValue('COMPROPAGO_MODE', Configuration::get('COMPROPAGO_MODE')),
            'COMPROPAGO_CASH'       => Tools::getValue('COMPROPAGO_CASH', Configuration::get('COMPROPAGO_CASH')),
            'COMPROPAGO_SPEI'       => Tools::getValue('COMPROPAGO_SPEI', Configuration::get('COMPROPAGO_SPEI')),
            'COMPROPAGO_CASH_TITLE' => Tools::getValue('COMPROPAGO_CASH_TITLE', Configuration::get('COMPROPAGO_CASH_TITLE')),
            'COMPROPAGO_SPEI_TITLE' => Tools::getValue('COMPROPAGO_SPEI_TITLE', Configuration::get('COMPROPAGO_SPEI_TITLE')),
            'COMPROPAGO_WEBHOOK'    => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/webhook.php',
            'COMPROPAGO_PROVIDERS'  => Tools::getValue('COMPROPAGO_PROVIDERS_selected', $prov),
        );
    }

    /**
    * This method generate the checkout form
    * @return view 
    */
    protected function generateCashForm()
    {   
        global $currency;
        $providers = $this->client->api->listProviders( 0, $currency->iso_code);
        $default = explode(",", Configuration::get("COMPROPAGO_PROVIDER"));
        $f_providers = [];
        foreach ($default as $def) {
            foreach ($providers as $prov) {
                if ($def == $prov->internal_name) {
                    $f_providers [] = $prov;
                }
            }
        }

        if ($f_providers[0] == NULL){
            $provflag = false;
            $f_providers = 0;
        } else {
            $provflag = true;
        }

        $this->context->smarty->assign(array(
            'action'    => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'providers' => $f_providers,
            'flag'      => $provflag,
        ));
        return $this->display(__FILE__, './views/templates/hook/payment_cash.tpl');
    }

    /**
    * This method generate the checkout form
    * @return view 
    */
    protected function generateSpeiForm()
    {   

        $this->context->smarty->assign(array(
            'action'    => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'type'      => "SPEI",
            
        ));
        return $this->display(__FILE__, './views/templates/hook/payment_spei.tpl');
    }
}
