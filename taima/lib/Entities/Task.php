<?php

namespace Garradin\Plugin\Taima\Entities;

use Garradin\Accounting\Accounts;

use Garradin\Entity;
use Garradin\Utils;

class Task extends Entity
{
	const TABLE = 'plugin_taima_tasks';

	protected ?int $id;
	protected string $label;
	protected ?int $value;
	protected ?string $account;

	public function importForm(?array $source = null)
	{
		if (null === $source) {
			$source = $_POST;
		}

		if (isset($source['account']) && is_array($source['account'])) {
			$source['account'] = Accounts::getCodeFromId(key($source['account']));
		}

		if (isset($source['value'])) {
			$source['value'] = Utils::moneyToInteger($source['value']) ?: null;
		}

		return parent::importForm($source);
	}

	public function selfCheck(): void
	{
		$this->assert(isset($this->value, $this->account)
			|| (!isset($this->value) && !isset($this->account)),
			'Il faut spécifier à la fois le compte et la valorisation, ou aucun des deux.');
		parent::selfCheck();
	}
}
