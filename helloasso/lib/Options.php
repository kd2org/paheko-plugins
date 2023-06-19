<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Option;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\DynamicList;
use Garradin\Entities\Users\User;
use Garradin\Entities\Services\Fee;
use Garradin\Entities\Services\Service;

class Options
{
	static public function list(Order $order): DynamicList
	{
		$columns = [
			'id' => [
				'select' => 'o.id'
			],
			'id_chargeable' => [
				'label' => 'Référence',
				'select' => 'c.id'
			],
			'id_transaction' => [
				'label' => 'Écriture',
				'select' => 'o.id_transaction'
			],
			'price_type' => [
				'select' => 'o.price_type'
			],
			'amount' => [
				'label' => 'Montant',
				'select' => 'o.amount'
			],
			'label' => [
				'label' => 'Objet',
				'select' => 'o.label'
			],
			'id_user' => [
				'select' => 'o.id_user'
			],
			'user_numero' => [
				'select' => 'u.numero'
			],
			'user_name' => [
				'label' => 'Personne',
				'select' => 'u.nom'
			],
			'custom_fields' => [
				'label' => 'Champs',
				'select' => 'o.custom_fields'
			],
			'service' => [
				'label' => 'Insc. Activité',
				'select' => 's.label'
			]
		];

		$tables = Option::TABLE . ' o
			INNER JOIN ' . Item::TABLE . ' i ON (i.id = o.id_item)
			INNER JOIN ' . Order::TABLE . ' ord ON (ord.id = i.id_order AND ord.id = ' . (int)$order->id . ')
			INNER JOIN ' . Chargeable::TABLE . ' c ON (
				c.id_form = i.id_form AND c.label = o.label AND (
					(o.price_type = ' . Item::PAY_WHAT_YOU_WANT_PRICE_TYPE . ' AND c.amount IS NULL)
					OR (c.amount = o.amount)
				)
			)
			LEFT JOIN ' . Fee::TABLE . ' f ON (f.id = c.id_fee)
			LEFT JOIN ' . Service::TABLE . ' s ON (s.id = f.id_service)
			LEFT JOIN  ' . User::TABLE . ' u ON (u.id = o.id_user)';

		$list = new DynamicList($columns, $tables);
		$list->setTitle(sprintf('Commande - %d - Articles', $order->id));

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