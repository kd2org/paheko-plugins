<?php

namespace Paheko\Plugin\HelloAsso\Entities;

use Paheko\Entity;
use Paheko\Plugin\HelloAsso\ChargeableInterface;
use Paheko\Plugin\HelloAsso\API;

class Item extends Entity implements ChargeableInterface
{
	const TABLE = 'plugin_helloasso_items';

	protected int			$id;
	protected int			$id_order;
	protected int			$id_form;
	protected ?int			$id_user = null; // ID of the $person if registered
	protected ?int			$id_transaction = null;
	protected ?int			$id_chargeable = null;
	protected string		$type;
	protected string		$state;
	protected int			$price_type;
	protected string		$label;
	protected string		$person; // May be origin (e.g., donator) or destination (e.g., beneficiary) of the item
	protected int			$amount;
	protected int			$has_options;
	protected ?\stdClass	$custom_fields; // Is a mix between real HelloAsso custom fields and plugin generated infos during sync
	protected string		$raw_data;

	const DONATION_TYPE =	'Donation';
	const TYPES = [
		'Donation'        => 'Don',
		'Payment'         => 'Paiement',
		'Registration'    => 'Inscription',
		'Membership'      => 'Adhésion',
		'MonthlyDonation' => 'Don mensuel',
		'MonthlyPayment'  => 'Paiement mensuel',
		'OfflineDonation' => 'Don hors-ligne',
		'Contribution'    => 'Contribution',
		'Bonus'           => 'Bonus',
	];

	const STATES = [
		'Waiting'    => 'En attente',
		'Processed'  => 'Traité',
		'Registered' => 'Enregistré',
		'Deleted'    => 'Supprimé',
		'Refunded'   => 'Remboursé',
		'Unknown'    => 'Inconnu',
		'Canceled'   => 'Annulé',
		'Contested'  => 'Contesté',
	];
	const FIXED_PRICE_TYPE = 0;
	const FREE_PRICE_TYPE = 1;
	const PAY_WHAT_YOU_WANT_PRICE_TYPE = 2;
	const PRICE_TYPES = [
		self::FIXED_PRICE_TYPE => 'Montant fixe',
		self::PAY_WHAT_YOU_WANT_PRICE_TYPE => 'Prix libre',
		self::FREE_PRICE_TYPE => 'Gratuit'
	];

	const API_PRICE_CATEGORIES = [
		API::FIXED_PRICE_CATEGORY => self::FIXED_PRICE_TYPE,
		API::FREE_PRICE_CATEGORY => self::FREE_PRICE_TYPE,
		API::PAY_WHAT_YOU_WANT_PRICE_CATEGORY => self::PAY_WHAT_YOU_WANT_PRICE_TYPE
	];

	public function getChargeableId(): ?int
	{
		return $this->id_chargeable;
	}

	public function setChargeableId(int $id): void
	{
		$this->set('id_chargeable', $id);
	}

	public function getType()
	{
		return $this->type;
	}

	public function getItemId(): ?int
	{
		return $this->id;
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
		return $this->price_type;
	}

	public function getCustomFields(): ?\stdClass
	{
		return $this->custom_fields;
	}

	public function setUserId(?int $id): void
	{
		$this->set('id_user', $id);
	}

	public function getUserId(): ?int
	{
		return $this->id_user;
	}

	public function selfCheck(): void
	{
		parent::selfCheck();
		$this->assert(array_key_exists($this->type, self::TYPES), sprintf('Wrong item (ID: #%d) type: %s. Possible values are: %s.', $this->id ?? null, $this->type, implode(', ', array_keys(self::TYPES))));
		$this->assert(array_key_exists($this->state, self::STATES), sprintf('Wrong item (ID: #%d) status: %s. Possible values are: %s.', $this->id ?? null, $this->state, implode(', ', array_keys(self::STATES))));
		$this->assert(array_key_exists($this->price_type, self::PRICE_TYPES), sprintf('Wrong item (ID: #%d) price type: %s. Possible values are: %s.', $this->id ?? null, $this->price_type, implode(', ', array_keys(self::PRICE_TYPES))));
	}
}
