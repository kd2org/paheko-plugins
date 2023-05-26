<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\Plugin\HelloAsso\ChargeableInterface;

class Item extends Entity implements ChargeableInterface
{
	const TABLE = 'plugin_helloasso_items';

	protected int $id;
	protected int $id_order;
	protected int $id_form;
	protected ?int $id_user;
	protected ?int $id_transaction;
	protected string $type;
	protected string $state;
	protected string $label;
	protected string $person;
	protected int $amount;
	protected int $has_options;
	protected ?string $custom_fields;
	protected string $raw_data;

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
}
