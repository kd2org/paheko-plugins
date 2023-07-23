<?php

namespace Paheko\Plugin\HelloAsso;

interface ChargeableInterface
{
	public function getChargeableId(): ?int;
	public function setChargeableId(int $id): void;
	public function getType();
	public function getItemId(): ?int;
	public function getLabel(): string;
	public function getAmount(): ?int;
	public function getPriceType(): ?int;
	public function getCustomFields(): ?\stdClass;
	public function setUserId(?int $id): void;
	public function getUserId(): ?int;
}
