<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Option;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Plugin\HelloAsso\HelloAsso as HA;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;
use Garradin\Entities\Users\User;

use KD2\DB\EntityManager as EM;

class Orders
{
	static public function get(int $id): ?Order
	{
		return EM::findOneById(Order::class, $id);
	}

	static public function list($associate): DynamicList
	{
		$columns = [
			'o.id' => [ // Cannot use "id" because it is ambiguous since we use several tables
				'label' => 'Référence',
				'select' => 'o.id'
			],
			'date' => [
				'label' => 'Date',
				'select' => 'o.date'
			],
			'form_name' => [],
			'label' => [
				'label' => 'Libellé',
				'select' => '\'dummy\'',
				'order' => 'o.date' // To avoid an unindexed ORDER BY (label is dynamically-generated), fallback to date ORDER BY
			],
			'amount' => [
				'label' => 'Montant',
				'select' => 'o.amount'
			],
			'id_user' => [
				'label' => 'Personne',
				'select' => 'o.id_user'
			],
			'person' => [
				'select' => 'o.person'
			],
			'status' => [
				'label' => 'Statut',
			],
			'id_payment' => [
				'label' => 'Paiement',
				'select' => 'json_extract(o.raw_data, \'$.payments[0].id\')'
			]
		];

		$tables = Order::TABLE . ' o';

		if ($associate instanceof Chargeable) {
			if ($associate->target_type === Chargeable::OPTION_TARGET_TYPE) {
				$target_table = Option::TABLE;
				$target_index = 'plugin_helloasso_item_options_order';
				$ids = $associate->getOptionsIds();
			}
			else {
				$target_table = Item::TABLE;
				$target_index = 'plugin_helloasso_items_order';
				$ids = $associate->getItemsIds();
			}
			$tables .= ' -- Force the use of index otherwise the GROUP BY and ORDER BY clauses create temporary sorting tables
				INDEXED BY plugin_helloasso_orders_id_date
				LEFT JOIN ' . $target_table . ' target INDEXED BY ' . $target_index . ' ON (target.id_order = o.id)';
			$conditions = 'target.id IN (:ids)';
			$title = sprintf('%s - Commandes', $associate->label);
		}
		if (!($associate instanceof Form)) {
			$tables .= "\n" . 'INNER JOIN ' . Form::TABLE . ' f ON (f.id = o.id_form)';
			$columns['form_name'] = [ 'label' => 'Formulaire', 'select' => 'f.label' ];
			if ($associate instanceof Chargeable) { // Do not want to display the form name since a Chargeable is for only one form
				unset($columns['form_name']['label']);
			}
		}

		if ($associate instanceof Form) {
			$conditions = sprintf('id_form = %d', $associate->id);
			$title = sprintf('%s - Commandes', $associate->label);
		}
		elseif ($associate instanceof User) {
			$conditions = sprintf('id_user = %d', $associate->id);
			$title = sprintf('%s - Commandes', $associate->nom);
			unset($columns['id_user']);
		}
		elseif ($associate instanceof \stdClass) { // Happens when the payer is not a member
			if (Users::getUserMatchField()['type'] === Users::USER_MATCH_EMAIL) {
				$conditions = sprintf('json_extract(o.raw_data, \'$.payer.email\') = \'%s\'', $associate->email);
			}
			else {
				$conditions = sprintf('json_extract(o.raw_data, \'$.payer.firstName\') = \'%s\' AND json_extract(o.raw_data, \'$.payer.lastName\') = \'%s\'', $associate->firstName, $associate->lastName);
			}
			$title = sprintf('%s - Commandes', Users::guessUserName($associate));
			unset($columns['id_user']);
		}

		$list = new DynamicList($columns, $tables, $conditions);
		$list->setTitle($title);
		
		if ($associate instanceof Chargeable) {
			$list->setParameter('ids', implode(', ', array_map(function ($item) { return (int)$item; }, $ids)));
			$list->groupBy('o.id, o.date');
			$list->orderBy(['o.id', 'date'], [true, true]);
		}
		else {
			$list->orderBy('date', true);
		}

		$list->setModifier(function (&$row) use ($associate) {
			$row->id = $row->{'o.id'}; // See column comment below
			$row->status = Order::STATUSES[$row->status];
			$row->label = (($associate instanceof Form) ? $associate->label : $row->form_name) . ' - ' . $row->person;
			if (!(($associate instanceof User) || ($associate instanceof \stdClass)) && $row->id_user) {
				$row->author = EM::findOneById(User::class, (int)$row->id_user);
			}
		});

		$list->setExportCallback(function (&$row) {
			$row->amount = $row->amount ? Utils::money_format($row->amount, '.', '', false) : null;
		});

		return $list;
	}

	static public function sync(string $org_slug): void
	{
		$params = [
			'pageSize'  => HA::getPageSize(),
		];

		$page_count = 1;

		for ($i = 1; $i <= $page_count; $i++) {
			$params['pageIndex'] = $i;
			$result = API::getInstance()->listOrganizationOrders($org_slug, $params);
			$page_count = $result->pagination->totalPages;

			foreach ($result->data as $order) {
				self::syncOrder($order);
			}

			if (HA::isTrial()) {
				break;
			}
		}
	}

	static protected function syncOrder(\stdClass $data): void
	{
		$entity = self::get($data->id) ?? new Order;

		$entity->set('raw_data', json_encode($data));

		$data = self::transform($data);

		if (!$entity->exists()) {
			$entity->set('id', $data->id);
			$entity->set('id_form', Forms::getId($data->org_slug, $data->form_slug));
		}

		$entity->set('id_user', isset($data->payer) ? Users::getUserId(Users::guessUserIdentifier($data->payer)): null); // The user may subscribe by himself/herself a long time after his/her order
		$entity->set('amount', $data->amount);
		$entity->set('status', $data->status);
		$entity->set('date', $data->date);
		$entity->set('person', $data->payer_name);

		$entity->save();
	}

	static protected function transform(\stdClass $data): \stdClass
	{
		$data->id = (int) $data->id;
		$data->date = new \DateTime($data->date);
		$data->status = Order::getStatus($data);
		$data->payer_name = isset($data->payer) ? Payers::getPersonName($data->payer) : null;
		$data->payer_infos = isset($data->payer) ? Payers::formatPersonInfos($data->payer) : null;
		$data->amount = (int) ($data->amount->total ?? 0);
		$data->form_slug = $data->formSlug;
		$data->org_slug = $data->organizationSlug;

		return $data;
	}
	
	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s;', Order::TABLE);
		DB::getInstance()->exec($sql);
	}

	static public function listCountOpti($associate): DynamicList
	{
		$table = Order::TABLE;

		if ($associate instanceof User) {
			$conditions = sprintf('id_user = %d', (int)$associate->id);
		}
		elseif ($associate instanceof Chargeable) {

			if ($associate->target_type === Chargeable::OPTION_TARGET_TYPE) {
				$table = Option::TABLE;
				$ids = $associate->getOptionsIds();
			}
			else {
				$table = Item::TABLE;
				$ids = $associate->getItemsIds();
			}
			$conditions = 'id IN (' . implode(', ', array_map(function ($item) { return (int)$item; }, $ids)) . ')';
		}
		elseif ($associate instanceof \stdClass) { // Happens when the payer is not a member

			if (Users::getUserMatchField()['type'] === Users::USER_MATCH_EMAIL) {
				$conditions = sprintf('json_extract(raw_data, \'$.payer.email\') = \'%s\'', $associate->email);
			}
			else {
				$conditions = sprintf('json_extract(raw_data, \'$.payer.firstName\') = \'%s\' AND json_extract(raw_data, \'$.payer.lastName\') = \'%s\'', $associate->firstName, $associate->lastName);
			}
		}

		$list = new DynamicList([], $table, $conditions);
		if ($associate instanceof Chargeable) {
			$list->setCount('COUNT(DISTINCT id_order)');
		}
		return $list;
	}
}
