<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\Plugin\HelloAsso\ChargeableInterface;

class Form extends Entity implements ChargeableInterface
{
	const TABLE = 'plugin_helloasso_forms';

	protected int $id;
	protected string $org_slug;
	protected string $org_name;
	protected string $name;
	protected string $state;

	protected string $type;
	protected string $slug;

	const TYPES = [
		'CrowdFunding' => 'Crowdfunding',
		'Membership'   => 'Adhésion',
		'Event'        => 'Billetteries',
		'Donation'     => 'Dons',
		'PaymentForm'  => 'Ventes',
		'Checkout'     => 'Encaissement',
		'Shop'         => 'Boutique',
	];

	const STATES = [
		'Draft'    => 'brouillon',
		'Public'   => 'public',
		'Private'  => 'privé',
		'Disabled' => 'désactivé',
	];

	public function getItemId(): ?int
	{
		return null;
	}

	public function getLabel(): string
	{
		return $this->name;
	}

	public function getAmount(): ?int
	{
		return null;
	}
}
