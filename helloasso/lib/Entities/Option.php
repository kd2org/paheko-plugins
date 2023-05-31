<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;
use Garradin\Plugin\HelloAsso\ChargeableInterface;

class Option extends Entity implements ChargeableInterface
{
	const TABLE = 'plugin_helloasso_item_options';

	protected int			$id;
	protected int			$id_item;
	protected int			$id_order; // Redundant but needed by DynamicList since it does not handle JOIN statement
	protected ?int			$id_user;
	protected ?int			$id_transaction;
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

	public function setUserId(?int $id): void
	{
		$this->set('id_user', $id);
	}
}
