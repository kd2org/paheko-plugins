<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\Plugin\HelloAsso\ChargeableInterface;
use KD2\DB\EntityManager;

class Form extends Entity implements ChargeableInterface
{
	const TABLE = 'plugin_helloasso_forms';

	protected int		$id;
	protected string	$org_slug;
	protected string	$org_name;
	protected string	$name;
	protected string	$state;

	protected string	$type;
	protected string	$slug;
	protected int		$need_config;

	protected ?array	$_customFields = null;

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

	public function customFields(): array
	{
		if (null === $this->_customFields) {
			$this->_customFields = EntityManager::getInstance(CustomField::class)->all('SELECT * FROM @TABLE WHERE id_form = :id_form;', (int)$this->id);
		}
		return $this->_customFields;
	}

	public function getCustomFields(): ?\stdClass
	{
		return null;
	}

	public function setUserId(?int $id): void {}

	public function createCustomField(string $name, ?int $id_dynamic_field = null): CustomField
	{
		if ($id_dynamic_field && !EntityManager::findOneById(DynamicField::class, $id_dynamic_field)) {
			throw new \InvalidArgumentException(sprintf('Dynamic field #%d does not exist!', $id_dynamic_field));
		}

		$field = new CustomField();
		$field->set('id_form', (int)$this->id);
		$field->set('id_dynamic_field', $id_dynamic_field);
		$field->set('name', $name);
		$field->save();

		return $field;
	}

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
