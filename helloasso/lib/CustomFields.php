<?php

namespace Paheko\Plugin\HelloAsso;

use Paheko\DB;
use Paheko\Plugin\HelloAsso\Entities\CustomField;

class CustomFields
{
	static public function getNamesForForm(int $id_form)
	{
		return DB::getInstance()->getAssoc(sprintf('SELECT id, name FROM %s WHERE id_form = :id_form;', CustomField::TABLE), (int)$id_form);
	}
}
