<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;

class Chargeable extends Entity
{
	const TABLE = 'plugin_helloasso_chargeables';
	const ITEM_TYPE = 1;
	const OPTION_TYPE = 2;
	const DONATION_ITEM_TYPE = 3;
	const ONLY_ONE_ITEM_FORM_TYPE = 4;
	const CHECKOUT_TYPE = 5;
	const TYPES = [ self::ITEM_TYPE => 'Item', self::OPTION_TYPE => 'Option', self::DONATION_ITEM_TYPE => 'Don', self::ONLY_ONE_ITEM_FORM_TYPE => 'Don/Vente', self::CHECKOUT_TYPE => 'Checkout' ];
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
	protected int		$register_user;

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
