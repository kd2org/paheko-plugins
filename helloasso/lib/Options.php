<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Option;
use Garradin\DynamicList;
use Garradin\Entities\Users\User;

class Options
{
	static public function list($for): DynamicList
	{
		$columns = [
			'id' => [
				'select' => 'o.id'
			],
			'id_transaction' => [
				'label' => 'Écriture'
			],
			'amount' => [
				'label' => 'Montant',
			],
			'label' => [
				'label' => 'Objet',
			],
			'id_user' => [],
			'user_numero' => [
				'select' => 'u.numero'
			],
			'user_name' => [
				'label' => 'Personne',
				'select' => 'u.nom'
			],
			'custom_fields' => [
				'label' => 'Champs',
			]
		];

		$tables = Option::TABLE . ' o
			LEFT JOIN  ' . User::TABLE . ' u ON (u.id = o.id_user)';

		$list = new DynamicList($columns, $tables);

		$conditions = sprintf('id_order = %d', $for->id);
		$list->setConditions($conditions);
		$list->setTitle(sprintf('Commande - %d - Items', $for->id));

		$list->setModifier(function ($row) {
			if (isset($row->custom_fields)) {
				$row->custom_fields = json_decode($row->custom_fields, true);
			}
		});

		$list->setExportCallback(function (&$row) {
			$row->amount = $row->amount ? Utils::money_format($row->amount, '.', '', false) : null;

			// Serialize custom fields as a text field
			if (isset($row->custom_fields)) {
				$row->custom_fields = implode("\n", array_map(function ($v, $k) { return "$k: $v"; },
					$row->custom_fields, array_keys($row->custom_fields)));
			}
		});

		$list->orderBy('id', true);
		return $list;
	}
}