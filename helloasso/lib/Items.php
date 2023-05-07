<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities\Payment;
use Garradin\Plugin\HelloAsso\API;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;

use KD2\DB\EntityManager as EM;

class Items
{
	static public function get(int $id): ?Item
	{
		return EM::findOneById(Item::class, $id);
	}

	static public function list($for): DynamicList
	{
		$columns = [
			'id' => [
				'label' => 'Référence',
			],
			'amount' => [
				'label' => 'Montant',
			],
			'type' => [
				'label' => 'Type',
			],
			'label' => [
				'label' => 'Objet',
			],
			'person' => [
				'label' => 'Personne',
			],
			'custom_fields' => [
				'label' => 'Champs',
			],
			'state' => [
				'label' => 'Statut',
			],
			'id_order' => [],
		];

		$tables = Item::TABLE;

		if ($for instanceof Form) {
			unset($columns['custom_fields']);
		}

		$list = new DynamicList($columns, $tables);

		if ($for instanceof Form) {
			$conditions = sprintf('id_form = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('%s - Items', $for->name));
		}
		elseif ($for instanceof Order) {
			$conditions = sprintf('id_order = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('Commande - %d - Items', $for->id));
		}

		$list->setModifier(function ($row) {
			$row->state = Item::STATES[$row->state] ?? 'Inconnu';
			$row->type = Item::TYPES[$row->type] ?? 'Inconnu';

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

	static public function sync(string $org_slug): void
	{
		$params = [
			'pageSize'  => HelloAsso::getPageSize(),
		];

		$page_count = 1;

		for ($i = 1; $i <= $page_count; $i++) {
			$params['pageIndex'] = $i;
			$result = API::getInstance()->listOrganizationItems($org_slug, $params);
			$page_count = $result->pagination->totalPages;

			foreach ($result->data as $order) {
				self::syncItem($order);
			}

			if (HelloAsso::isTrial()) {
				break;
			}
		}
	}

	static protected function syncItem(\stdClass $data): void
	{
		$entity = self::get($data->id) ?? new Item;

		$entity->set('raw_data', json_encode($data));

		$data = self::transform($data);

		if (!$entity->exists()) {
			$entity->set('id', $data->id);
			$entity->set('id_order', $data->order_id);
			$entity->set('id_form', Forms::getId($data->org_slug, $data->form_slug));
		}

		$entity->set('amount', $data->amount);
		$entity->set('state', $data->state);
		$entity->set('type', $data->type);
		$entity->set('person', $data->user_name ?? $data->payer_name);
		$entity->set('label', $data->name ?? Forms::getName($entity->id_form));
		$entity->set('custom_fields', count($data->fields) ? json_encode($data->fields) : null);

		// FIXME: handle options

		$entity->save();
	}

	static protected function transform(\stdClass $data): \stdClass
	{
		$data->id = (int) $data->id;
		$data->order_id = (int) $data->order->id;
		$data->payer_name = isset($data->payer) ? Payment::getPayerName($data->payer) : null;
		$data->payer_infos = isset($data->payer) ? Payment::getPayerInfos($data->payer) : null;
		$data->user_name = isset($data->user) ? Payment::getPayerName($data->user) : null;
		$data->amount = (int) $data->amount;
		$data->form_slug = $data->order->formSlug;
		$data->org_slug = $data->order->organizationSlug;
		$data->fields = [];

		if (!empty($data->user)) {
			$data->fields = Payment::getPayerInfos($data->user);
		}

		if (!empty($data->customFields)) {
			foreach ($data->customFields as $field) {
				$data->fields[$field->name] = $field->answer;
			}
		}

		return $data;
	}
	
	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s;', Item::TABLE);
		DB::getInstance()->exec($sql);
	}
}
