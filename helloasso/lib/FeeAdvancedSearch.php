<?php

namespace Paheko\Plugin\HelloAsso;

use Paheko\DynamicList;
use Paheko\Users\DynamicFields;
use Paheko\AdvancedSearch as A_S;
use Paheko\DB;
use Paheko\Utils;

class FeeAdvancedSearch extends A_S
{
	/**
	 * Returns list of columns for search
	 * @return array
	 */
	public function columns(): array
	{
		$columns = [];

		$columns['id'] = [
			'select' => 'f.id'
		];

		$columns['label'] = [
			'label'    => 'Libellé',
			'type'     => 'text',
			'null'     => false,
			'select'   => 'f.label',
			'where'    => 'f.label %s'
		];

		$columns['description'] = [
			'label'    => 'Description',
			'type'     => 'text',
			'null'     => true,
			'select'   => 'f.description',
			'where'    => 'f.description %s'
		];

		$columns['service_label'] = [
			'label'    => 'Libellé',
			'type'     => 'text',
			'null'     => false,
			'select'   => 's.label',
			'where'    => 's.label %s'
		];

		$columns['service_description'] = [
			'label'    => 'Description',
			'type'     => 'text',
			'null'     => true,
			'select'   => 's.description',
			'where'    => 's.description %s'
		];

		return $columns;
	}

	public function schemaTables(): array
	{
		return [
			'services_fees' => 'Tarifs des activités',
			'services' => 'Activités',
			'services_users' => 'Inscriptions aux activités'
		];
	}

	public function tables(): array
	{
		return $this->schemaTables();
	}

	public function simple(string $query, bool $allow_redirect = false): \stdClass
	{
		//$db = DB::getInstance();

		if (is_numeric(trim($query)))
		{
			$fields = [
				'id' => [
					'column' => 'id',
					'operator' => '= ?'
				]
			];
			$column = 'id';
		}
		else
		{
			$fields = [
				'label' => [
					'column' => 'label',
					'operator' => 'LIKE %?%'
				],
				'service_label' => [
					'column' => 'service_label',
					'operator' => 'LIKE %?%'
				],
				'service_description' => [
					'column' => 'service_description',
					'operator' => 'LIKE %?%'
				],
			];
			$column = 'label';
		}

		if ($allow_redirect) {
			die('NOT YET IMPLEMENTED');

			/*$c = $column;

			if ($column == 'identity') {
				$c = DynamicFields::getNameFieldsSQL();
			}

			// Try to redirect to user if there is only one user
			if ($operator == '= ?') {
				$sql = sprintf('SELECT id, COUNT(*) AS count FROM users WHERE %s = ?;', $c);
				$single_query = (int) $query;
			}
			else {
				$sql = sprintf('SELECT id, COUNT(*) AS count FROM users WHERE %s LIKE ?;', $c);
				$single_query = '%' . trim($query) . '%';
			}

			if (($row = $db->first($sql, $single_query)) && $row->count == 1) {
				Utils::redirect('!users/details.php?id=' . $row->id);
			}*/
		}

		$groups = [];
		$groups[0]['operator'] = 'OR';
		foreach ($fields as $field) {
			$groups[0]['conditions'][] = [
				'column' => $field['column'],
				'operator' => $field['operator'],
				'values' => [$query]
			];
		}

		return (object) [
			'groups' => $groups,
			'order' => $column,
			'desc'  => false,
		];
	}

	public function make(string $query): DynamicList
	{
		$tables = 'services_fees AS f
			INNER JOIN services s ON (s.id = f.id_service)
		';
		return $this->makeList($query, $tables, 'label', false, ['id', 'label', 'service_label']);
	}

	public function defaults(): \stdClass
	{
		return (object) ['groups' => [[
			'operator' => 'AND',
			'conditions' => [
				[
					'column'   => 'label',
					'operator' => 'LIKE %?%',
					'values'   => [''],
				],
			],
		]]];
	}
}
