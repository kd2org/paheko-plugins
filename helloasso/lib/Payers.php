<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Entities\Users\User;
use Garradin\DynamicList;
use KD2\DB\EntityManager;
use Garradin\DB;

use Garradin\Plugin\HelloAsso\Entities\Order;

class Payers
{
	static public function get(int $id): ?User
	{
		return EntityManager::findOneById(User::class, $id);
	}

	static function getRawData($reference): ?\stdClass
	{
		if (is_string($reference)) {
			$conditions = 'json_extract(raw_data, \'$.payer.email\') = :email';
			$params = [ $reference ];
		}
		else {
			$conditions = 'json_extract(raw_data, \'$.payer.firstName\') = :first_name AND json_extract(raw_data, \'$.payer.lastName\') = :last_name';
			$params = [ $reference['first_name'], $reference['last_name'] ];
		}
		if (!$result = DB::getInstance()->firstColumn('SELECT json_extract(raw_data, \'$.payer\') FROM ' . Order::TABLE . ' WHERE ' . $conditions, ...$params)) {
			return null;
		}
		return json_decode($result);
	}

	static public function list(): DynamicList
	{
		$columns = [
			'id' => [
				'select' => 'u.id'
			],
			'number' => [
				'label' => 'Membre',
				'select' => 'u.numero'
			],
			'name' => [
				'label' => 'Nom',
				'select' => 'u.nom'
			],
			'email' => [
				'label' => 'Courriel',
				'select' => 'u.email'
			],
			'id_order' => [],
			'raw_data' => []
		];

		$tables = Order::TABLE . ' o
			LEFT JOIN ' . User::TABLE . ' u ON (u.id = o.id_user)';

		$list = new DynamicList($columns, $tables);

		$list->setModifier(function (&$row) {
			if (!$row->id) {
				$data = json_decode($row->raw_data);
				if (!isset($data->payer)) {
					throw new \RuntimeException(sprintf('No payer for order #%d!', $row->id_order));
				}
				$row->name = Users::guessUserName($data->payer);
				$row->email = $data->payer->email;
				if (Users::getUserMatchField()[1] === 'email') {
					$row->ref = $row->email;
				}
				else {
					$row->first_name = $data->payer->firstName;
					$row->last_name = $data->payer->lastName;
				}
			}
		});

		$list->groupBy('u.email');

		return $list;
	}
}
