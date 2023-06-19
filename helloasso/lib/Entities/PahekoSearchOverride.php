<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entities\Search;
use Garradin\AdvancedSearch;
use Garradin\CSV;
use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Entity;
use Garradin\UserException;

use Garradin\Accounting\AdvancedSearch as Accounting_AdvancedSearch;
use Garradin\Users\AdvancedSearch as Users_AdvancedSearch;

use KD2\DB\DB_Exception;

/*
 * Overridden because getAdvancedSearch() types are hard-coded and cannot be configured
 */

class PahekoSearchOverride extends Search
{
	const TARGET_FEE = 'fees';

	const TARGETS = [
		parent::TARGET_USERS => parent::TARGETS[parent::TARGET_USERS],
		parent::TARGET_ACCOUNTING => parent::TARGETS[parent::TARGET_ACCOUNTING],
		self::TARGET_FEE => 'Activités'
	];

	public function getAdvancedSearch(): AdvancedSearch
	{
		if ($this->target == self::TARGET_FEE) {
			$class = 'Garradin\Plugin\HelloAsso\FeeAdvancedSearch';
		}
		elseif ($this->target == self::TARGET_ACCOUNTING) {
			$class = 'Garradin\Accounting\AdvancedSearch';
		}
		else {
			$class = 'Garradin\Users\AdvancedSearch';
		}

		if (null === $this->_as || !is_a($this->_as, $class)) {
			$this->_as = new $class;
		}

		return $this->_as;
	}
}
