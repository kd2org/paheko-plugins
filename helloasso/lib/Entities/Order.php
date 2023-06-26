<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\DB;
use Garradin\Entity;
use Garradin\ValidationException;
use Garradin\Config;
use Garradin\Entities\Users\Category;

use Garradin\UserException;
use Garradin\Plugin\HelloAsso\NotFoundException;
use Garradin\Plugin\HelloAsso\Users;
use Garradin\Plugin\HelloAsso\Payments;

use KD2\DB\EntityManager as EM;

class Order extends Entity
{
	const TABLE = 'plugin_helloasso_orders';

	protected int		$id;
	protected int		$id_form;
	protected ?int		$id_user; // The user linked to the order. Is the payer only if the payer is the same person as the beneficiary.
	protected ?int		$id_transaction;
	protected \DateTime	$date;
	protected string	$person;
	protected int		$amount;
	protected int		$status;
	protected string	$raw_data;

	const STATUS_PAID = 1;
	const STATUS_WAITING = 0;
	const STATUSES = [
		self::STATUS_PAID => 'Payée',
		self::STATUS_WAITING => 'En attente'
	];

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
		if (!$category = EM::findOneById(Category::class, $id_category)) {
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
		$this->set('id_user', (int)$user->id);
		$this->save();
		if (!$payment = Payments::getByOrderId((int)$this->id)) {
			throw new \RuntimeException(sprintf('No payment found for order #%d while trying to create its payer User.', $this->id));
		}
		$payment->set('id_author', (int)$user->id);
		$payment->save();
	}

	public function createTransaction(Target $target): Transaction
	{
		if (!$target->id_year) {
			throw new \RuntimeException('Cannot create transaction: no year has been specified');
		}

		if ($this->id_transaction) {
			throw new \RuntimeException('This order already has a transaction');
		}

		$accounts = $target->listAccountsByType();

		if (!isset($accounts[TargetAccount::TYPE_THIRDPARTY])) {

		}

		$transaction = new Transaction;
		$transaction->type = Transaction::TYPE_ADVANCED;
		$transaction->id_creator = null;
		$transaction->id_year = $target->id_year;

		$transaction->date = $this->date;
		$transaction->label = 'Commande ' . HelloAsso::PROVIDER_LABEL . ' n°' . $this->id();
		$transaction->reference = $this->id;

		foreach ($this->listItems() as $item) {
			if (!isset($accounts[$item->type])) {
				throw new \RuntimeException('No account has been specified for this type: ' . $item->type);
			}

			$line = new Line;
			$line->label = $item->label;
			$line->reference = $item->id;
			$line->id_account = $accounts[$item->type];
			$transaction->addLine($line);
		}

		$transaction->save();
		$this->set('id_transaction', $transaction->id());
		$this->save();
	}

	public function selfCheck(): void
	{
		parent::selfCheck();
		if (!array_key_exists($this->status, self::STATUSES)) {
			throw new \UnexpectedValueException(sprintf('Wrong order (ID: #%d) status: %s. Possible values are: %s.', $this->id ?? null, $this->status, implode(', ', array_keys(self::PRICE_TYPES))));
		}
	}
}
