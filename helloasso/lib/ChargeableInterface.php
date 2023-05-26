<?php

namespace Garradin\Plugin\HelloAsso;

interface ChargeableInterface
{
	public function getItemId(): ?int;
	public function getLabel(): string;
	public function getAmount(): ?int;
}
