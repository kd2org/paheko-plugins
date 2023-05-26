<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Option;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities\Payment;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\ChargeableInterface;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;
use Garradin\Entities\Accounting\Transaction;
use Garradin\Accounting\Years;
use Garradin\Plugin\HelloAsso\Payments;

use KD2\DB\EntityManager as EM;

class Items
{
	const TRANSACTION_PREFIX = 'Item';
	const TRANSACTION_NOTE = 'Générée automatiquement par l\'extension ' . HelloAsso::PROVIDER_LABEL . '.';
	const DONATION_LABEL = 'Don';
	const CHECKOUT_LABEL = 'Paiement orphelin - commande #%d (%s)';

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
			'id_transaction' => [
				'label' => 'Écriture'
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
			'options' => [
				'label' => 'Options',
				'select' => "(CASE WHEN has_options THEN 'oui' ELSE '-' END)"
			], // sprintf("(SELECT (CASE WHEN COUNT(id) > 0 THEN 'oui' ELSE '-' END) FROM %s o WHERE o.id_item = %s.id)", Option::TABLE, Item::TABLE)
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

	static public function sync(string $org_slug, bool $accounting = true): void
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
				self::syncItem($order, $accounting);
			}

			if (HelloAsso::isTrial()) {
				break;
			}
		}
	}

	static protected function syncItem(\stdClass $data, bool $accounting): Item
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
		$entity->set('label', self::generateLabel($data, (int)$entity->id_form));
		$entity->set('custom_fields', count($data->fields) ? json_encode($data->fields) : null);
		$entity->set('has_options', (int)isset($data->options));

		$entity->save();

		$optionEntities = [];
		if (isset($data->options)) {
			foreach ($data->options as $option) {
				$optionEntities[] = self::syncOption($option, $data, $entity->id, $accounting);
			}
		}
		// Creating a transaction only if payment is unique and already done (not pending) and accounts sets
		if ($accounting && !$entity->id_transaction && (count($data->payments) === 1 && $data->payments[0]->state === Payments::AUTHORIZED_STATUS))
		{
			if ($entity->amount) {
				if ($data->order->formType !== 'Checkout') { // All cases except Checkout
					self::accountChargeable((int)$entity->id_form, $entity, Chargeable::TYPE_FROM_FORM[$data->order->formType], (int)$data->payments[0]->id, new \DateTime($data->payments[0]->date));
				}
				else
				{
					if (!$payment = Payments::get((int)$data->payments[0]->id)) {
						throw new \RuntimeException(sprintf('Payment #%d matching checkout item #%d not found.', $data->payments[0]->id, $entity->id));
					}
					if (isset($payment->id_credit_account) && $payment->id_credit_account && $payment->id_debit_account) {// This feature will be available once the ChekoutIntent callback is fixed
						$transaction = self::createTransaction($entity, [$payment->id_credit_account, $payment->id_debit_account], (int)$data->payments[0]->id, $payment->date);
						$payment->set('id_transaction', $transaction->id);
					}
					elseif (self::accountChargeable((int)$entity->id_form, $entity, Chargeable::TYPE_FROM_FORM[$data->order->formType], (int)$data->payments[0]->id, new \DateTime($data->payments[0]->date))) {
						$payment->set('id_transaction', $entity->id_transaction);
					}
					$payment->save();
				}
			}
			if (isset($data->options)) {
				foreach ($optionEntities as $option) {
					self::accountChargeable((int)$entity->id_form, $option, Chargeable::OPTION_TYPE, (int)$data->payments[0]->id, new \DateTime($data->payments[0]->date));
				}
			}
		}

		return $entity;
	}

	static protected function syncOption(\stdClass $data, \stdClass $full_data, int $id_item, bool $accounting): Option
	{
		$option = EM::findOne(Option::class, 'SELECT * FROM @TABLE WHERE id_item = :id_item AND label = :name AND amount = :amount', $id_item, $data->name, $data->amount) ?? new Option;
		$option->set('raw_data', json_encode($data));
		$data = self::transformOption($data);
		
		if (!$option->exists()) {
			$option->set('id_item', (int)$full_data->id);
			$option->set('id_order', (int)$full_data->order_id);
		}
		$option->set('amount', $data->amount);
		$option->set('label', $data->name ?? Forms::getName($option->id_form));
		$option->set('custom_fields', count($data->fields) ? json_encode($data->fields) : null);
		$option->save();

		return $option;
	}

	static protected function accountChargeable(int $id_form, ChargeableInterface $entity, int $type, int $id_payment, \DateTime $date): bool
	{
		$amount = ($type === Chargeable::ONLY_ONE_ITEM_FORM_TYPE ? null : $entity->getAmount());
		$chargeable = Chargeables::get($id_form, $type, $entity->getLabel(), $amount);
		if (null === $chargeable) {
			$chargeable = Chargeables::createChargeable($id_form, $entity, $type);
		}
		elseif ($chargeable->id_credit_account && $chargeable->id_debit_account) {
			$transaction = self::createTransaction($entity, [(int)$chargeable->id_credit_account, (int)$chargeable->id_debit_account], $id_payment, $date);
			$entity->id_transaction = (int)$transaction->id;
			$entity->save();
			return true;
		}
		return false;
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

	static protected function transformOption(\stdClass $data): \stdClass
	{
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

	static protected function createTransaction(ChargeableInterface $entity, array $accounts, int $id_payment, \DateTime $date): Transaction
	{
		if (!$id_year = Years::getOpenYearIdMatchingDate($date)) {
			throw new \RuntimeException(sprintf('No opened accounting year matching the item date "%s"!', $date->format('Y-m-d')));
		}
		// ToDo: check accounts validity (right number for the Transaction type)

		$transaction = new Transaction();
		$transaction->type = Transaction::TYPE_REVENUE;
		$transaction->reference = (string)Payments::getId($id_payment);

		$source = [
			'status' => Transaction::STATUS_PAID,
			'label' => self::TRANSACTION_PREFIX . ' - ' . $entity->getLabel(),
			'notes' => self::TRANSACTION_NOTE,
			'payment_reference' => $id_payment,
			'date' => \KD2\DB\Date::createFromInterface($date),
			'id_year' => (int)$id_year,
			'amount' => $entity->getAmount() / 100,
			'simple' => [
				Transaction::TYPE_REVENUE => [
					'credit' => [ (int)$accounts[0] => null ],
					'debit' => [ (int)$accounts[1] => null ]
			]]
			// , 'id_user'/'id_creator' => ...
		];

		$transaction->importForm($source);

		if (!$transaction->save()) {
			throw new \RuntimeException(sprintf('Cannot record item/option transaction. Item/option ID: %d.', $entity->id));
		}
		return $transaction;
	}

	static protected function generateLabel(\stdClass $data, int $id_form): string
	{
		if ($data->order->formType === 'Checkout') {
			$payment = Payments::getByOrderId((int)$data->order->id);
			if (null === $payment) {
				throw new \RuntimeException('No payment matching retreived checkout item #%d (order #%d).', $data->id, $data->order->id);
			}
			return sprintf(self::CHECKOUT_LABEL, $payment->id, $payment->label);
		}
		elseif (!isset($data->name))
		{
			if ($data->type === 'Donation' && $data->order->formType !== 'Donation') {
				return self::DONATION_LABEL;
			}
			else {
				return Forms::getName($id_form);
			}
		}
		else {
			return $data->name;
		}
	}

	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s;', Item::TABLE);
		DB::getInstance()->exec($sql);
	}
}
