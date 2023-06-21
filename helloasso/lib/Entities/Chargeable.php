<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\DB;
use KD2\DB\EntityManager;
use Garradin\Entities\Services\Fee;
use Garradin\Entities\Services\Service;
use Garradin\Entities\Services\Service_User;
use Garradin\Entities\Users\DynamicField;
use Garradin\Users\Users;

use Garradin\Plugin\HelloAsso\HelloAsso as HA;

class Chargeable extends Entity
{
	const TABLE = 'plugin_helloasso_chargeables';

	const SIMPLE_TYPE = 1;
	const DONATION_ITEM_TYPE = 2;
	const ONLY_ONE_ITEM_FORM_TYPE = 3;
	const FREE_TYPE = 4;
	const PAY_WHAT_YOU_WANT_TYPE = 5;
	const CHECKOUT_TYPE = 6;
	const TYPES = [
		self::SIMPLE_TYPE => 'Item/Option classique',
		self::DONATION_ITEM_TYPE => 'Don',
		self::ONLY_ONE_ITEM_FORM_TYPE => 'Don/Vente',
		self::FREE_TYPE => 'Gratuit',
		self::PAY_WHAT_YOU_WANT_TYPE => 'Prix libre',
		self::CHECKOUT_TYPE => 'Checkout'
	];
	const TYPE_FROM_FORM = [
		'Donation' => self::ONLY_ONE_ITEM_FORM_TYPE,
		'PaymentForm' => self::ONLY_ONE_ITEM_FORM_TYPE,
		'Payment' => self::SIMPLE_TYPE,
		'Membership' => self::SIMPLE_TYPE,
		'Checkout' => self::CHECKOUT_TYPE,
		'Shop' => self::SIMPLE_TYPE
	];

	const ITEM_TARGET_TYPE = 1;
	const OPTION_TARGET_TYPE = 2;
	const TARGET_TYPES = [
		self::ITEM_TARGET_TYPE => 'Item',
		self::OPTION_TARGET_TYPE => 'Option'
	];
	const TARGET_TYPE_FROM_CLASS = [
		Item::class => self::ITEM_TARGET_TYPE,
		Option::class => self::OPTION_TARGET_TYPE,
		Form::class => self::ITEM_TARGET_TYPE // Same behavior for Form and Item
	];

	protected int		$id;
	protected int		$id_form;
	protected ?int		$id_item; // Is the first item to generate the Chargeable, or null when handling ONLY_ONE_ITEM_FORM_TYPE forms/payments
	protected ?int		$id_credit_account;
	protected ?int		$id_debit_account;
	protected ?int		$id_category;
	protected ?int		$id_fee;
	protected ?int		$target_type;
	protected int		$type;
	protected string	$label;
	protected ?int		$amount; // When null, handles all amounts. See Chargeables::isMatchingAnyAmount() for null scenarii.
	protected ?int		$need_config; // Is zero until user fill the configuration form

	protected ?string	$_form_name = null;
	protected ?string	$_item_name = null;

	protected ?Fee		$_fee = null;
	protected ?Service	$_service = null;

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
		return (($this->type === Chargeable::ONLY_ONE_ITEM_FORM_TYPE) || ($this->type === Chargeable::DONATION_ITEM_TYPE) || ($this->type === Chargeable::PAY_WHAT_YOU_WANT_TYPE));
	}

	public function registerToService(int $id_user, \DateTime $date, bool $paid)
	{
		if (!$this->id_fee) {
			throw new \RuntimeException(sprintf('No fee associated to current chargeable #%d while trying to register user #%d to a service.', $this->id, $id_user));
		}
		if (!$this->service()) {
			throw new \RuntimeException(sprintf('No service associated to fee #%d for chargeable #%d.', $this->id_fee, $this->id));
		}
		if (!Users::idExists($id_user)) {
			throw new \RuntimeException(sprintf('Inexisting user #%d.', $id_user));
		}
		$source = [
			'id_user' => (int)$id_user,
			'id_service' => (int)$this->service()->id,
			'id_fee' => (int)$this->id_fee,
			'paid' => $paid,
			'amount' => $this->amount,
			//'expected_amount' => $this->amount,
			'date' => $date
		];
		return Service_User::createFromForm([ $id_user => HA::PROVIDER_LABEL . ' synchronization' ], $id_user, false, $source); // Second parameter should be HelloAsso user ID (to understand the plugin auto-registered the member)
	}

	public function fee(): ?Fee
	{
		if (!$this->id_fee) {
			return null;
		}
		if (null === $this->_fee) {
			$this->_fee = EntityManager::findOneById(Fee::class, (int)$this->id_fee);
		}
		return $this->_fee;
	}

	public function service(): ?Service
	{
		if (!$this->id_fee) {
			return null;
		}
		if (null === $this->_service) {
			$this->_service = $this->fee() ? EntityManager::findOneById(Service::class, (int)$this->_fee->id_service) : null;
		}
		return $this->_service;
	}

	public function selfCheck(): void
	{
		parent::selfCheck();
		if (!array_key_exists($this->type, Chargeable::TYPES)) {
			throw new \RuntimeException(sprintf('Invalid Chargeable type: %s (Chargeable ID: #%d). Allowed types are: %s.', $this->type, $this->id ?? null, implode(', ', array_keys(Chargeable::TYPES))));
		}
		if (!in_array($this->need_config, [0, 1])) {
			throw new \RuntimeException(sprintf('Invalid Chargeable need_config option: %s (Chargeable ID: #%d). Allowed values are: %s.', $this->need_config, $this->id ?? null, implode(', ', [0, 1])));
		}
		
	}
}
