<?php

namespace Paheko\Plugin\HelloAsso\Entities;

use Paheko\Entities\Search;
use Paheko\AdvancedSearch;
use Paheko\CSV;
use Paheko\DB;
use Paheko\DynamicList;
use Paheko\Entity;
use Paheko\UserException;

use Paheko\Accounting\AdvancedSearch as Accounting_AdvancedSearch;
use Paheko\Users\AdvancedSearch as Users_AdvancedSearch;

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
			$class = 'Paheko\Plugin\HelloAsso\FeeAdvancedSearch';
		}
		elseif ($this->target == self::TARGET_ACCOUNTING) {
			$class = 'Paheko\Accounting\AdvancedSearch';
		}
		else {
			$class = 'Paheko\Users\AdvancedSearch';
		}

		if (null === $this->_as || !is_a($this->_as, $class)) {
			$this->_as = new $class;
		}

		return $this->_as;
	}
}
