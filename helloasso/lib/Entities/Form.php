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

	public function getPriceType(): ?int
	{
		return null;
	}

	public function setUserId(?int $id): void {}

	public function selfCheck(): void
	{
		parent::selfCheck();
		if (!array_key_exists($this->type, self::TYPES)) {
			throw new \UnexpectedValueException(sprintf('Wrong form (ID: #%d) type: %s. Possible values are: %s.', $this->id ?? null, $this->type, implode(', ', array_keys(self::TYPES))));
		}
		if (!array_key_exists($this->state, self::STATES)) {
			throw new \UnexpectedValueException(sprintf('Wrong form (ID: #%d) status: %s. Possible values are: %s.', $this->id ?? null, $this->state, implode(', ', array_keys(self::STATES))));
		}
	}
}
