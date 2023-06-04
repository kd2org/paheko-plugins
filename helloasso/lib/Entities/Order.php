<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\DB;
use Garradin\Entity;
use Garradin\ValidationException;

use DateTime;

use KD2\DB\EntityManager as EM;

class Order extends Entity
{
	const TABLE = 'plugin_helloasso_orders';

	protected int $id;
	protected int $id_form;
	protected ?int $id_user;
	protected ?int $id_transaction;
	protected \DateTime $date;
	protected string $person;
	protected int $amount;
	protected int $status;
	protected string $raw_data;

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
				if ($payment->state == Payment::STATE_OK) {
					$paid += $payment->amount;
				}
			}
		}

		return $paid >= $total ? self::STATUS_PAID : self::STATUS_WAITING;
	}

	public function getPayerInfos(): array
	{
		$data = json_decode($this->raw_data);
		return $data ? Payment::formatPersonInfos($data->payer) : [];
	}

	public function listItems(): array
	{
		return EM::getInstance(Item::class)->all('SELECT * FROM @TABLE WHERE id_order = ? ORDER BY id DESC;', $this->id());
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
		$transaction->label = 'Commande HelloAsso n°' . $this->id();
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
