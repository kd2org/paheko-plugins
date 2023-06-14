<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\DB;

class Chargeable extends Entity
{
	const TABLE = 'plugin_helloasso_chargeables';
	const ITEM_TYPE = 1;
	const OPTION_TYPE = 2;
	const DONATION_ITEM_TYPE = 3;
	const ONLY_ONE_ITEM_FORM_TYPE = 4;
	const FREE_TYPE = 5;
	const CHECKOUT_TYPE = 6;
	const TYPES = [
		self::ITEM_TYPE => 'Item',
		self::OPTION_TYPE => 'Option',
		self::DONATION_ITEM_TYPE => 'Don',
		self::ONLY_ONE_ITEM_FORM_TYPE => 'Don/Vente',
		self::FREE_TYPE => 'Gratuit',
		self::CHECKOUT_TYPE => 'Checkout'
	];
	const TYPE_FROM_FORM = [
		'Donation' => self::ONLY_ONE_ITEM_FORM_TYPE,
		'PaymentForm' => self::ONLY_ONE_ITEM_FORM_TYPE,
		'Payment' => self::ITEM_TYPE,
		'Membership' => self::ITEM_TYPE,
		'Checkout' => self::CHECKOUT_TYPE,
		'Shop' => self::ITEM_TYPE
	];

	protected int		$id;
	protected int		$id_form;
	protected ?int		$id_item; // Is the first item to generate the Chargeable, or null when handling ONLY_ONE_ITEM_FORM_TYPE forms/payments
	protected ?int		$id_credit_account;
	protected ?int		$id_debit_account;
	protected int		$type;
	protected string	$label;
	protected ?int		$amount; // When null, handles all amounts. See Chargeables::isMatchingAnyAmount() for null scenarii.
	protected ?int		$register_user;

	protected ?string	$_form_name = null;
	protected ?string	$_item_name = null;

	public function setForm_name(string $name): void
	{
		$this->_form_name = $name;
	}

	public function getForm_name(): string
	{
		return $this->_form_name;
	}

	public function setItem_name(?string $name): void
	{
		$this->_item_name = $name;
	}

	public function getItem_name(): ?string
	{
		return $this->_item_name;
	}

	public function getItemsIds(): array
	{
		$conditions = 'id_form = :id_form AND label = :label';
		$params = [ (int)$this->id_form, $this->label ];

		if (!$this->isMatchingAnyAmount()) {
			$conditions .= ' AND amount = :amount';
			$params[] = (int)$this->amount;
		}

		return DB::getInstance()->getAssoc(sprintf('SELECT id, id FROM %s WHERE ' . $conditions, Item::TABLE), ...$params);
	}

	public function getOptionsIds(): array
	{
		$conditions = 'o.label = :label';
		$params = [ (int)$this->id_form, $this->label ];

		if (!$this->isMatchingAnyAmount()) {
			$conditions .= ' AND o.amount = :amount';
			$params[] = (int)$this->amount;
		}

		return DB::getInstance()->getAssoc(sprintf('SELECT o.id, o.id FROM %s o INNER JOIN %s i ON (i.id = o.id_item AND i.id_form = :id_form) WHERE ' . $conditions, Option::TABLE, Item::TABLE), ...$params);
	}

	public function isMatchingAnyAmount(): bool
	{
		return (($this->type === Chargeable::ONLY_ONE_ITEM_FORM_TYPE) || ($this->type === Chargeable::DONATION_ITEM_TYPE));
	}

	public function selfCheck(): void
	{
		parent::selfCheck();
		if (!array_key_exists($this->type, Chargeable::TYPES)) {
			throw new \RuntimeException(sprintf('Invalid Chargeable type: %s (Chargeable ID: #%d). Allowed types are: %s.', $this->type, $this->id ?? null, implode(', ', array_keys(Chargeable::TYPES))));
		}
		if (!in_array($this->register_user, [0, 1])) {
			throw new \RuntimeException(sprintf('Invalid Chargeable register_user option: %s (Chargeable ID: #%d). Allowed values are: %s.', $this->register_user, $this->id ?? null, implode(', ', [0, 1])));
		}
		
	}
}
