<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities\Payment;
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
			'id' => [
				'label' => 'Référence',
				'select' => 'o.id'
			],
			'date' => [
				'label' => 'Date',
			],
			'form_name' => [],
			'label' => [
				'label' => 'Libellé',
				'select' => 'json_extract(raw_data, \'$.formSlug\')'
			],
			'amount' => [
				'label' => 'Montant',
			],
			'id_user' => [
				'label' => 'Personne',
			],
			'person' => [],
			'status' => [
				'label' => 'Statut',
			],
			'id_payment' => [
				'label' => 'Paiement',
				'select' => 'json_extract(raw_data, \'$.payments[0].id\')'
			]
		];

		$tables = Order::TABLE . ' o';

		if (!($associate instanceof Form)) {
			$tables .= "\n" . 'INNER JOIN ' . Form::TABLE . ' f ON (f.id = o.id_form)';
			$columns['form_name'] = [ 'label' => 'Formulaire', 'select' => 'f.name' ];
		}

		if ($associate instanceof Form) {
			$conditions = sprintf('id_form = %d', $associate->id);
			$title = sprintf('%s - Commandes', $associate->name);
		}
		elseif ($associate instanceof User) {
			$conditions = sprintf('id_user = %d', $associate->id);
			$title = sprintf('%s - Commandes', $associate->nom);
			unset($columns['id_user']);
			unset($columns['person']);
		}
		elseif ($associate instanceof \stdClass) { // Happens when the payer is not a member
			if (Users::getUserMatchField()[1] === 'email') {
				$conditions = sprintf('json_extract(raw_data, \'$.payer.email\') = \'%s\'', $associate->email);
			}
			else {
				$conditions = sprintf('json_extract(raw_data, \'$.payer.firstName\') = \'%s\' AND json_extract(raw_data, \'$.payer.lastName\') = \'%s\'', $associate->firstName, $associate->lastName);
			}
			$title = sprintf('%s - Commandes', Users::guessUserName($associate));
			unset($columns['id_user']);
			unset($columns['person']);
		}

		$list = new DynamicList($columns, $tables, $conditions);
		$list->setTitle($title);

		$list->setModifier(function (&$row) use ($associate) {
			$row->status = Order::STATUSES[$row->status];
			if (!(($associate instanceof User) || ($associate instanceof \stdClass)) && $row->id_user) {
				$row->author = EM::findOneById(User::class, (int)$row->id_user);
			}
		});

		$list->setExportCallback(function (&$row) {
			$row->amount = $row->amount ? Utils::money_format($row->amount, '.', '', false) : null;
		});

		$list->orderBy('date', true);
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

		$entity->set('id_user', Users::getUserId(Users::guessUserIdentifier($data->payer))); // The user may subscribe by himself/herself a long time after his/her order
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
		$data->payer_name = isset($data->payer) ? Payment::getPersonName($data->payer) : null;
		$data->payer_infos = isset($data->payer) ? Payment::formatPersonInfos($data->payer) : null;
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
}
