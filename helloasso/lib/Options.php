<?php

namespace Paheko\Plugin\HelloAsso;

use Paheko\Plugin\HelloAsso\Entities\Option;
use Paheko\Plugin\HelloAsso\Entities\Item;
use Paheko\Plugin\HelloAsso\Entities\Order;
use Paheko\Plugin\HelloAsso\Entities\Chargeable;
use Paheko\DynamicList;
use Paheko\Entities\Users\User;
use Paheko\Entities\Services\Fee;
use Paheko\Entities\Services\Service;

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
			INNER JOIN ' . Order::TABLE . ' ord ON (ord.id = o.id_order)
			INNER JOIN ' . Chargeable::TABLE . ' c ON (c.id = o.id_chargeable)
			LEFT JOIN ' . Fee::TABLE . ' f ON (f.id = c.id_fee)
			LEFT JOIN ' . Service::TABLE . ' s ON (s.id = f.id_service)
			LEFT JOIN ' . User::TABLE . ' u ON (u.id = o.id_user)
		';

		$conditions = 'o.id_order = :id_order';

		$list = new DynamicList($columns, $tables, $conditions);
		$list->setTitle(sprintf('Commande - %d - Articles', $order->id));
		$list->setParameter('id_order', (int)$order->id);

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

	static public function listCountOpti(Order $order): DynamicList
	{
		$list = new DynamicList([], Option::TABLE, 'id_order = :id_order');
		$list->setParameter('id_order', (int)$order->id);

		return $list;
	}
}