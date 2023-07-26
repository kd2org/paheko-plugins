<?php

namespace Paheko\Plugin\HelloAsso\Entities;

use Paheko\DB;
use Paheko\Entity;
use Paheko\ValidationException;
use Paheko\Config;
use Paheko\Entities\Users\Category;

use Paheko\UserException;
use Paheko\Plugin\HelloAsso\NotFoundException;
use Paheko\Plugin\HelloAsso\Users;
use Paheko\Plugin\HelloAsso\Payers;
use Paheko\Plugin\HelloAsso\Payments;

use KD2\DB\EntityManager as EM;

class Order extends Entity
{
	const TABLE = 'plugin_helloasso_orders';

	protected int		$id;
	protected int		$id_form;
	protected ?int		$id_payer;
	protected \DateTime	$date;
	protected ?string	$payer_name;
	protected int		$amount;
	protected int		$status;
	protected string	$raw_data;

	const STATUS_PAID = 1;
	const STATUS_WAITING = 0;
	const STATUSES = [
		self::STATUS_PAID => 'Payée',
		self::STATUS_WAITING => 'En attente'
	];
	const RAW_PAYER_REGISTRATION_MESSAGE = 'Payeur/euse inscrit·e comme membre n°%d (%s).';

	static public function getStatus(\stdClass $order)
	{
		$total = $order->amount->total ?? 0;
		$paid = 0;

		if (isset($order->payments)) {
			foreach ($order->payments as $payment) {
				if ($payment->state == Payments::STATE_OK) {
					$paid += $payment->amount;
				}
			}
		}

		return $paid >= $total ? self::STATUS_PAID : self::STATUS_WAITING;
	}

	public function getRawPayerInfos(): array
	{
		$data = json_decode($this->raw_data);
		return $data ? Payers::formatPersonInfos($data->payer) : [];
	}

	public function getRawPayer(): ?\stdClass
	{
		$data = json_decode($this->raw_data);
		return $data->payer ?? null;
	}

	public function listItems(): array
	{
		return EM::getInstance(Item::class)->all('SELECT * FROM @TABLE WHERE id_order = ? ORDER BY id DESC;', $this->id());
	}

	public function registerRawPayer(): void
	{
		$id_category = (int)Config::getInstance()->default_category;
		if (!DB::getInstance()->test(Category::TABLE, 'id = ?', $id_category)) {
			throw new \RuntimeException(sprintf('Inexisting default category #%d while trying to register order raw payer.', $id_category));
		}
		$raw_payer = $this->getRawPayer();
		if (!$user = Users::findUserMatchingPayer($raw_payer)) {
			try {
				$user = Users::getMappedUser($raw_payer);
			}
			catch (NotFoundException $e) {
				throw new UserException('Catégorie de membre invalide ou non-définit dans la configuration de l\'extension.');
			}
			$user->set('id_category', (int)$id_category);
			$user->save();
		}
		$this->set('id_payer', (int)$user->id);
		$this->save();
		if (!$payment = Payments::getByOrderId((int)$this->id)) {
			throw new \RuntimeException(sprintf('No payment found for order #%d while trying to create its payer User.', $this->id));
		}
		$payment->set('id_payer', (int)$user->id);
		$payment->addLog(sprintf(self::RAW_PAYER_REGISTRATION_MESSAGE, $user->id, $user->nom));
		$payment->save();
	}

	public function selfCheck(): void
	{
		parent::selfCheck();
		$this->assert(array_key_exists($this->status, self::STATUSES), sprintf('Wrong order (ID: #%d) status: %s. Possible values are: %s.', $this->id ?? null, $this->status, implode(', ', array_keys(self::STATUSES))));
	}
}
