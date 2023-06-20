<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\DB;
use Garradin\Plugin\HelloAsso\Entities\CustomField;

class CustomFields
{
	static public function getNamesForChargeable(int $id_chargeable)
	{
		return DB::getInstance()->getAssoc(sprintf('SELECT id, name FROM %s WHERE id_chargeable = :id_chargeable;', CustomField::TABLE), (int)$id_chargeable);
	}
}
