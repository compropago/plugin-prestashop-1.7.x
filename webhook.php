<?php
/**
 * ComproPago Prestashop WebHook
 * @author Rolando Lucio <rolando@compropago.com>
 * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
 * @since 2.0.0
 */

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/../../config/config.inc.php';
require_once __DIR__.'/../../init.php';
require_once __DIR__.'/../../classes/PrestaShopLogger.php';
require_once __DIR__.'/../../classes/order/Order.php';
require_once __DIR__.'/../../classes/order/OrderHistory.php';

header('Content-Type: application/json');

use CompropagoSdk\Resources\Payments\Cash as sdkCash;
use CompropagoSdk\Resources\Payments\Spei as sdkSpei;


if (!defined('_PS_VERSION_'))
{
	echo json_encode([
		"status"	=> "error",
		"message"	=> "PrestaShop is not loaded",
		"short_id"	=> null,
		"reference"	=> null
	]);
}


class CompropagoWebhook
{
	private $_client;
	private $config;
	private $db;
	private $model;

	public $publicKey;
	public $privateKey;

	/**
	 * CompropagoWebhook constructor.
	 * @throws PrestaShopException
	 */
	public function __construct()
	{
		$this->model = Module::getInstanceByName('compropago');
		$this->db = Db::getInstance();
		$this->config = Configuration::getMultiple([
			'COMPROPAGO_PUBLICKEY',
			'COMPROPAGO_PRIVATEKEY',
			'COMPROPAGO_MODE'
		]);

		$this->publicKey = $this->config['COMPROPAGO_PUBLICKEY'];
		$this->privateKey = $this->config['COMPROPAGO_PRIVATEKEY'];
	}

	/**
	 * Main Validations of the webhook
	 * @throws Exception
	 */
	public function execute()
	{
		$request	= file_get_contents('php://input');
		$orderInfo	= json_decode($request, true);

		if (empty($orderInfo['id']))
		{
			$message = 'Invalid request';
			throw new \Exception($message);
		}

		if ($orderInfo['type'] == 'error')
		{
			$message = "No se pudo procesar la orden: $request";
			throw new \Exception($message);
		}

		if ($orderInfo['short_id'] == "000000")
		{
			die(json_encode([
				"status" => "success",
				"message" => "OK - TEST",
				"short_id" => $orderInfo['short_id'],
				"reference" => null
			]));
		}

		if (!$this->model->verifyTables())
		{
			$message = 'Cant found ComproPago tables';
			throw new \Exception($message);
		}

		$query = "SELECT * FROM " . _DB_PREFIX_ . "compropago_orders WHERE compropagoId = '{$orderInfo['id']}'";
		$row = $this->db->getRow($query);

		if (empty($row))
		{
			$message = 'Order Not found';
			throw new \Exception($message);
		}

		switch ($row['storeExtra'])
		{
			case 'SPEI':
				$this->_client = (new sdkSpei)->withKeys(
					$this->publicKey,
					$this->privateKey
				);
				$this->spei($orderInfo, $row);
				break;
			case 'CASH':
				$this->_client = (new sdkCash)->withKeys(
					$this->publicKey,
					$this->privateKey
				);
				$this->cash($orderInfo, $row);
				break;
			default:
				$message = "Payment method not allowed {$row->storeExtra}";
				throw new \Exception($message);
		}
	}

	/**
	 * Actions for cash orders
	 * @param $orderInfo
	 * @param $row
	 * @throws PrestaShopDatabaseException
	 * @throws Exception
	 */
	private function cash($orderInfo, $row)
	{
		$order = $this->_client->verifyOrder($orderInfo['id']);

		$this->changeStatus($order['order_info']['order_id'], $order['type']);
		$this->registerTransaction(
			$row,
			$orderInfo,
			$order,
			$order['type'],
			$order['id']
		);

		die(json_encode([
			"status"	=> "success",
			"message"	=> "OK - {$order['type']}",
			"short_id"	=> $order['short_id'],
			"reference"	=> $order['order_info']['order_id']
		]));
	}

	/**
	 * Actions for cash orders
	 * @param $orderInfo
	 * @param $row
	 * @throws Exception
	 */
	private function spei($orderInfo, $row)
	{
		$response = $this->_client->verifyOrder($orderInfo['id']);
		$order = $response['data'];

		switch ($order['status'])
		{
			case 'PENDING':
				$status = 'charge.pending';
				break;
			case 'ACCEPTED':
				$status = 'charge.success';
				break;
			case 'EXPIRED':
				$status = 'charge.expired';
				break;
		}

		$this->changeStatus($order['product']['id'], $status);
		$this->registerTransaction(
			$row,
			$orderInfo,
			$order,
			$status,
			$order['id']
		);

		die(json_encode([
			"status"	=> "success",
			"message"	=> "OK - {$status}",
			"short_id"	=> $order['shortId'],
			"reference"	=> $order['product']['id']
		]));
	}

	/**
	 * Change Order status
	 * @param $orderId
	 * @param $status
	 * @throws Exception
	 */
	private function changeStatus($orderId, $status)
	{
		switch ($status)
		{
			case 'charge.success':
				$nomestatus = "COMPROPAGO_SUCCESS";
				break;
			case 'charge.pending':
				$nomestatus = "COMPROPAGO_PENDING";
				break;
			case 'charge.expired':
				$nomestatus = "COMPROPAGO_EXPIRED";
				break;
			default:
				$message = 'Invalid request type';
				throw new \Exception($message);
		}

		$order				= new Order(intval($orderId));
		$history			= new OrderHistory();
		$history->id_order	= (int) $order->id;
		$history->changeIdOrderState(
			(int) Configuration::get($nomestatus),
			(int) ($order->id)
		);
		$history->addWithemail();
		$history->save();
	}

	/**
	 * Add new compropago transaction
	 * @param $row
	 * @param $orderInfo
	 * @param $response
	 * @param $status
	 * @param $cpId
	 * @throws PrestaShopDatabaseException
	 */
	private function registerTransaction($row, $orderInfo, $response, $status, $cpId)
	{
		$recordTime = time();

		$this->db->update(
			'compropago_orders',
			[
				'modified'			=> $recordTime,
				'compropagoStatus'	=> $status
			],
			"compropagoId = '{$cpId}'"
		);

		$ioIn  = base64_encode(serialize($orderInfo));
		$ioOut = base64_encode(serialize($response));

		$this->db->insert('compropago_transactions', [
			'date'					=> $recordTime,
			'ioIn'					=> $ioIn,
			'ioOut'					=> $ioOut,
			'orderId'				=> $row['id'],
			'compropagoId'			=> $cpId,
			'compropagoStatus'		=> $status,
			'compropagoStatusLast'	=> $row['compropagoStatus']
		]);
	}
}

try
{
	$webhook = new CompropagoWebhook();
	$webhook->execute();
}
catch (\Exception $e)
{
	echo $e->getTraceAsString();

	die(json_encode([
		"status" => "error",
		"message" => $e->getMessage(),
		"short_id" => null,
		"reference" => null
	]));
}
