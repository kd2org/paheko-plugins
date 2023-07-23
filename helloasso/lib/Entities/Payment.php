<?php

namespace Paheko\Plugin\HelloAsso\Entities;

use Paheko\Entities\Payments\Payment as PA_Payment;
use Paheko\Plugin\HelloAsso\ChargeableInterface;
use Paheko\Plugin\HelloAsso\Chargeables;
use Paheko\Plugin\HelloAsso\Forms;
use KD2\DB\EntityManager;

class Payment extends PA_Payment implements ChargeableInterface
{
	const TABLE = parent::TABLE;

	// For checkoutIntent scenarii: is the Checkout ID before payment has been made. Once the payment has been made, it become the Payment ID.
	protected ?string		$reference = null;

	public function getChargeableId(): ?int
	{
		if (!isset($this->extra_data->id_chargeable)) {
			return null;
		}
		return $this->extra_data->id_chargeable;
	}

	public function setChargeableId(int $id): void
	{
		$this->setExtraData('id_chargeable', $id);
	}

	public function getType()
	{
		return null;
	}

	public function getItemId(): ?int
	{
		return null;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getAmount(): ?int
	{
		return $this->amount;
	}

	public function getPriceType(): ?int
	{
		return null;
	}

	public function getCustomFields(): ?\stdClass
	{
		return null;
	}

	public function setUserId(?int $id): void
	{
		$this->id_payer = $id;
	}

	public function getUserId(): ?int
	{
		return $this->id_payer;
	}

	public function forceExistance(): void
	{
		$this->_exists = true;
	}

	public function loadFromPahekoPayment(PA_Payment $source): void
	{
		$this->id = $source->id;
		$this->reference = $source->reference;
		$this->id_author = $source->id_author;
		$this->id_payer = $source->id_payer;
		$this->payer_name = $source->payer_name;
		$this->provider = $source->provider;
		$this->type = $source->type;
		$this->status = $source->status;
		$this->label = $source->label;
		$this->amount = $source->amount;
		$this->date = $source->date;
		$this->method = $source->method;
		$this->history = $source->history;
		$this->extra_data = $source->extra_data;

		$this->_exists = $source->exists();
	}

	public function validate(int $amount, ?string $receipt_url = null): bool
	{
		if (!parent::validate($amount, $receipt_url)) {
			return false;
		}

		if (!isset($this->extra_data->id_chargeable) || !$this->extra_data->id_chargeable) {
			throw new \RuntimeException(sprintf('No chargeable while validating the payment #%d!', $this->id));
		}

		$chargeable = Chargeables::getFromEntity((int)Forms::getIdForCheckout(), $this, Chargeable::CHECKOUT_TYPE);
		if ($chargeable->id_credit_account && $chargeable->id_debit_account) {
			$this->createTransaction([ (int)$chargeable->id_credit_account, (int)$chargeable->id_debit_account ]);
		}

		return true;
	}
}
