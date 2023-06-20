<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\Plugin\HelloAsso\ChargeableInterface;
use Garradin\Plugin\HelloAsso\Entities\Item;

class Option extends Entity implements ChargeableInterface
{
	const TABLE = 'plugin_helloasso_item_options';

	protected int			$id;
	protected int			$id_item;
	protected ?int			$id_user;
	protected ?int			$id_transaction;
	protected int			$price_type;
	protected string		$label;
	protected int			$amount;
	protected ?\stdClass	$custom_fields;
	protected string		$raw_data;

	public function getItemId(): ?int
	{
		return $this->id_item;
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

	public function selfCheck(): void
	{
		parent::selfCheck();
		if (!array_key_exists($this->price_type, Item::PRICE_TYPES)) {
			throw new \UnexpectedValueException(sprintf('Wrong option (ID: #%d) price type: %s. Possible values are: %s.', $this->id ?? null, $this->price_type, implode(', ', array_keys(Item::PRICE_TYPES))));
		}
	}
}
