<?php

namespace Paheko\Plugin\HelloAsso;

class Sync implements \JsonSerializable
{
	const ORDERS_STEP = 'Order';
	const PAYMENTS_STEP = 'Payment';
	const ITEMS_STEP = 'Item';
	const COMPLETED_STEP = 'Completed';
	const STEPS = [
		self::ORDERS_STEP => 'Commandes',
		self::PAYMENTS_STEP => 'Paiements',
		self::ITEMS_STEP => 'Articles',
		self::COMPLETED_STEP => 'Finalisé'
	];

	protected array		$_steps;
	protected ?string	$step = null;
	protected ?int		$page = null;
	protected ?\DateTime	$date = null;

	public function __construct()
	{
		$this->_steps = self::STEPS;
		$this->step = key($this->_steps);
	}

	public function getStep(): ?string
	{
		return $this->step;
	}

	public function getPage(): ?int
	{
		return $this->page;
	}

	public function setPage(?int $page): void
	{
		$this->page = $page;
	}

	public function getDate(): ?\DateTime
	{
		return $this->date;
	}

	public function setDate(?\DateTime $date): void
	{
		$this->date = $date;
	}

	public function goNextStep(): void
	{
		if (key($this->_steps) === self::COMPLETED_STEP) {
			return ;
		}
		next($this->_steps);
		$this->step = key($this->_steps);
	}

	public function reset(): void
	{
		reset($this->_steps);
		$this->step = key($this->_steps);
		$this->page = null;
	}

	public function isCompleted(): bool
	{
		return $this->step === Sync::COMPLETED_STEP;
	}

	static public function loadFromStdClass(\stdClass $obj): self
	{
		$sync = new self();

		while ($sync->getStep() !== $obj->step) {
			$sync->goNextStep();
		}
		$sync->page = $obj->page;
		$sync->date = new \DateTime($obj->date);
		return $sync;
	}

	public function jsonSerialize()
	{
		$obj = new \stdClass();
		$obj->step = $this->step;
		$obj->page = $this->page;
		$obj->date = $this->date->format(\DATE_ISO8601);

		return $obj;
	}
}
