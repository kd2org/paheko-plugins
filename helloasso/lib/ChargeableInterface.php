<?php

namespace Paheko\Plugin\HelloAsso;

interface ChargeableInterface
{
	public function getItemId(): ?int;
	public function getLabel(): string;
	public function getAmount(): ?int;
	public function getPriceType(): ?int;
	public function getCustomFields(): ?\stdClass;
	public function setUserId(?int $id): void;
	public function getUserId(): ?int;
}
