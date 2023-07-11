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
			'user_email' => [
				'label' => 'Courriel', // ToDo: use DynamicFields instead of u.email
				'select' => 'u.email'
			],
			'payer_email' => [
				'select' => 'json_extract(o.raw_data, \'$.payer.email\')'
			],
			'id_order' => [],
			'raw_data' => []
		];

		$tables = Order::TABLE . ' o
			LEFT JOIN ' . User::TABLE . ' u ON (u.id = o.id_user)';

		$list = new DynamicList($columns, $tables);

		$list->setModifier(function (&$row) {
			$row->email = $row->user_email ?? $row->payer_email;
			if (!$row->id) {
				$data = json_decode($row->raw_data);
				if (!isset($data->payer)) {
					throw new \RuntimeException(sprintf('No payer for order #%d!', $row->id_order));
				}
				$row->name = Users::guessUserName($data->payer);
				if (Users::getUserMatchField()['type'] === Users::USER_MATCH_EMAIL) {
					$row->ref = $row->email;
				}
				else {
					$row->first_name = $data->payer->firstName;
					$row->last_name = $data->payer->lastName;
				}
			}
		});

		$list->groupBy('payer_email');
		$list->orderBy('payer_email', true);

		return $list;
	}

	static public function getPersonName(\stdClass $person)
	{
		$names = [!empty($person->company) ? $person->company . ' — ' : null, $person->firstName ?? null, $person->lastName ?? null];
		$names = array_filter($names);

		$names = implode(' ', $names);

		if (!empty($person->city)) {
			$names .= sprintf(' (%s)', $person->city);
		}

		return $names;
	}

	static public function formatPersonInfos(\stdClass $person): array
	{
		$data = [];

		foreach (API::PAYER_FIELDS as $key => $name) {
			if (!isset($person->$key)) {
				continue;
			}

			$value = $person->$key;

			if ($key == 'dateOfBirth') {
				$value = new \DateTime($value);
			}

			$data[$name] = $value;
		}

		return $data;
	}
}
