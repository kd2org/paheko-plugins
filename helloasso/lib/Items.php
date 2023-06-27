<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Option;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Plugin\HelloAsso\HelloAsso as HA;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;
use Garradin\ValidationException;
use Garradin\Entities\Accounting\Transaction;
use Garradin\Accounting\Years;
use Garradin\Entities\Users\User;
use Garradin\Plugin\HelloAsso\Payments;
use Garradin\Entities\Services\Fee;
use Garradin\Entities\Services\Service;

use KD2\DB\EntityManager as EM;

//use Garradin\Plugin\HelloAsso\Mock\MockItems;

class Items
{
	const TRANSACTION_PREFIX = 'Item';
	const TRANSACTION_NOTE = 'Générée automatiquement par l\'extension ' . HA::PROVIDER_LABEL . '.';
	const DONATION_LABEL = 'Don';
	const CHECKOUT_LABEL = 'Commande #%d (%s)';

	static protected array	$_userIdsByLoginCache = []; // Used when userMatchField is different from the Paheko login field
	static protected array	$_exceptions = [];

	static public function get(int $id): ?Item
	{
		return EM::findOneById(Item::class, $id);
	}

	static public function list($for): DynamicList
	{
		$columns = [
			'id' => [
				'select' => 'i.id'
			],
			'id_chargeable' => [
				'label' => 'Référence',
				'select' => 'c.id'
			],
			'id_transaction' => [
				'label' => 'Écriture'
			],
			'amount' => [
				'label' => 'Montant',
				'select' => 'i.amount'
			],
			'type' => [
				'label' => 'Type',
				'select' => 'i.type'
			],
			'label' => [
				'label' => 'Objet',
				'select' => 'i.label'
			],
			'person' => [
				'label' => 'Personne'
			],
			'id_user' => [],
			'user_name' => [
				'label' => 'Membre correspondant*',
				'select' => 'u.nom'
			],
			'numero' => [
				'select' => 'u.numero'
			],
			'options' => [
				'label' => 'Options',
				'select' => "(CASE WHEN has_options THEN 'oui' ELSE '-' END)"
			], // sprintf("(SELECT (CASE WHEN COUNT(id) > 0 THEN 'oui' ELSE '-' END) FROM %s o WHERE o.id_item = %s.id)", Option::TABLE, Item::TABLE)
			'custom_fields' => [
				'label' => 'Champs'
			],
			'service' => [
				'label' => 'Insc. Activité',
				'select' => 's.label'
			],
			'state' => [
				'label' => 'Statut'
			],
			'id_order' => [],
		];

		$tables = Item::TABLE . ' i
			INNER JOIN ' . Chargeable::TABLE . ' c ON (
				c.id_form = i.id_form AND c.label = i.label AND (
					(c.type = ' . Chargeable::DONATION_ITEM_TYPE . ' AND c.amount IS NULL)
					OR (i.price_type = ' . Item::PAY_WHAT_YOU_WANT_PRICE_TYPE . ' AND c.amount IS NULL)
					OR (c.type != ' . Chargeable::DONATION_ITEM_TYPE . ' AND c.amount = i.amount)
				)
			)
			LEFT JOIN ' . Fee::TABLE . ' f ON (f.id = c.id_fee)
			LEFT JOIN ' . Service::TABLE . ' s ON (s.id = f.id_service)
			LEFT JOIN ' . User::TABLE . ' u ON (u.id = i.id_user)';

		$list = new DynamicList($columns, $tables);

		if ($for instanceof Order) {
			$conditions = sprintf('id_order = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('Commande - %d - Articles', $for->id));
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
				$row->custom_fields = implode("\n", array_map(
					function ($v, $k) { return "$k: $v"; },
					$row->custom_fields,
					array_keys($row->custom_fields)
				));
			}
		});

		$list->orderBy('id', true);
		return $list;
	}

	static public function sync(string $org_slug, bool $accounting = true): void
	{
		self::initSync();
		$params = [
			'pageSize'  => HA::getPageSize(),
		];

		$page_count = 1;

		for ($i = 1; $i <= $page_count; $i++) {
			$params['pageIndex'] = $i;
			$result = API::getInstance()->listOrganizationItems($org_slug, $params);
			$page_count = $result->pagination->totalPages;

			//$result->data = MockItems::donationAndOptions();
			//$result->data = MockItems::multipleSubscriptions();

			foreach ($result->data as $order) {
				try {
					self::syncItem($order, $accounting);
				}
				catch (SyncException $e) { self::catchSyncException($e); }
			}

			if (HA::isTrial()) {
				break;
			}
		}
	}

	static protected function syncItem(\stdClass $data, bool $accounting): Item
	{
		$item = self::get($data->id) ?? new Item;
		$item->set('raw_data', json_encode($data));

		$data = self::transform($data);

		self::setItem($item, $data);
		$item->save();

		// Different try/catch blocks because we want to do all steps even if an exception occured
		try {
			Users::syncRegistration($data, (int)$item->id_form, $item, Chargeables::getType($item, $data->order->formType));
		}
		catch (SyncException $e) { self::catchSyncException($e); }

		$optionEntities = self::syncOptions($data, $item, $accounting);

		try {
			self::handleAccounting($item, $data, $optionEntities, $accounting);
		}
		catch (SyncException $e) { self::catchSyncException($e); }

		return $item;
	}

	static protected function setItem(Item $item, \stdClass $data): void
	{
		// ToDo: add some cache for those checks
		if (!EM::getInstance(Order::class)->col(sprintf('SELECT id FROM @TABLE WHERE id = :id_order;'), $data->order_id)) {
			throw new SyncException(sprintf('Tried to synchronized the item (ID: %d) of an inexisting (never synchronized?) order #%d.', $data->id, $data->order_id));
		}
		$id_form = Forms::getId($data->org_slug, $data->form_slug);
		if (!EM::getInstance(Form::class)->col(sprintf('SELECT id FROM @TABLE WHERE id = :id_order;'), $id_form)) {
			throw new SyncException(sprintf('Tried to synchronized the item (ID: %d) of an inexisting (never synchronized?) order #%d.', $data->id, $id_form));
		}

		if (!$item->exists()) {
			$item->set('id', $data->id);
			$item->set('id_order', $data->order_id);
			$item->set('id_form', $id_form);
		}

		$item->set('amount', $data->amount);
		$item->set('state', $data->state);
		$item->set('price_type', Item::API_PRICE_CATEGORIES[$data->priceCategory]);
		$item->set('type', $data->type);
		$item->set('person', $data->beneficiary_label ?? $data->payer_name);
		$item->set('label', self::generateLabel($data, (int)$item->id_form));
		$item->set('custom_fields', count($data->fields) ? (object)$data->fields : null);
		$item->set('has_options', (int)isset($data->options));

		$identifier = Users::guessUserIdentifier($data->beneficiary);
		if ($identifier && ($id_user = Users::getUserId($identifier))) {
			$item->set('id_user', $id_user);
		}
	}

	static protected function syncOptions(\stdClass $data, Item $item, int $accounting): array
	{
		if (!isset($data->options)) {
			return [];
		}

		$optionEntities = [];
		foreach ($data->options as $option) {
			try {
				$optionEntities[] = self::syncOption($option, $data, $item->id_form, $item->id, $accounting);
			}
			catch (SyncException $e) { self::catchSyncException($e); }
		}
		return $optionEntities;
	}

	static protected function syncOption(\stdClass $data, \stdClass $full_data, int $id_form, int $id_item, bool $accounting): Option
	{
		$option = EM::findOne(Option::class, 'SELECT * FROM @TABLE WHERE id_item = :id_item AND label = :name AND amount = :amount', $id_item, $data->name, $data->amount) ?? new Option;
		$option->set('raw_data', json_encode($data));
		$data = self::transformOption($data);
		
		if (!$option->exists()) {
			$option->set('id_item', (int)$full_data->id);
		}
		$option->set('price_type', Item::API_PRICE_CATEGORIES[$data->priceCategory]);
		$option->set('amount', $data->amount);
		$option->set('label', $data->name ?? Forms::getLabel($id_form));
		$option->set('custom_fields', count($data->fields) ? (object)$data->fields : null);
		$identifier = Users::guessUserIdentifier($full_data->beneficiary);
		if ($identifier && ($id_user = Users::getUserId($identifier))) {
			$option->set('id_user', $id_user);
		}
		$option->save();

		Users::syncRegistration($full_data, $id_form, $option, Chargeables::getType($option, $full_data->order->formType));

		return $option;
	}

	static protected function handleAccounting(Item $item, \stdClass $data, array $optionEntities, int $accounting): void
	{
		// Creating a transaction only if payment is unique and already done (not pending) and accounts sets
		if ($accounting && !$item->id_transaction && (count($data->payments) === 1 && $data->payments[0]->state === Payments::AUTHORIZED_STATUS))
		{
			if ($item->amount && $item->price_type !== Item::FREE_PRICE_TYPE) {
				if ($data->order->formType !== 'Checkout') { // All cases except Checkout
					self::accountChargeable((int)$item->id_form, $item, Chargeables::getType($item, $data->order->formType), (int)$data->payments[0]->id, new \DateTime($data->payments[0]->date));
				}
				else // Checkout case
				{
					if (!$payment = Payments::get((int)$data->payments[0]->id)) {
						throw new \RuntimeException(sprintf('Payment #%d matching checkout item #%d not found.', $data->payments[0]->id, $item->id));
					}
					if (isset($payment->id_credit_account) && $payment->id_credit_account && $payment->id_debit_account) {// This feature will be available once the ChekoutIntent callback is fixed
						$transaction = self::createTransaction($item, [$payment->id_credit_account, $payment->id_debit_account], (int)$data->payments[0]->id, $payment->date);
						$payment->set('id_transaction', $transaction->id);
					}
					elseif (self::accountChargeable((int)$item->id_form, $item, Chargeable::CHECKOUT_TYPE, (int)$data->payments[0]->id, new \DateTime($data->payments[0]->date))) {
						$payment->set('id_transaction', $item->id_transaction);
					}
					$payment->save();
				}
			}
			if (isset($data->options)) {
				foreach ($optionEntities as $option) {
					if ($option->amount && $option->price_type !== Item::FREE_PRICE_TYPE) {
						self::accountChargeable((int)$item->id_form, $option, Chargeable::SIMPLE_TYPE, (int)$data->payments[0]->id, new \DateTime($data->payments[0]->date));
					}
				}
			}
		}
	}

	static protected function accountChargeable(int $id_form, ChargeableInterface $entity, int $type, int $payment_ref, \DateTime $date): bool
	{
		$chargeable = Chargeables::getFromEntity($id_form, $entity, $type);
		if ($entity->getAmount() && $chargeable->id_credit_account && $chargeable->id_debit_account) {
			$transaction = self::createTransaction($entity, [(int)$chargeable->id_credit_account, (int)$chargeable->id_debit_account], $payment_ref, $date);
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
		$data->payer_name = isset($data->payer) ? Payers::getPersonName($data->payer) : null;
		$data->payer_infos = isset($data->payer) ? Payers::formatPersonInfos($data->payer) : null;
		$data->amount = (int) $data->amount;
		$data->form_slug = $data->order->formSlug;
		$data->org_slug = $data->order->organizationSlug;

		$data->fields = [];

		if (!empty($data->customFields)) {
			foreach ($data->customFields as $field) {
				$data->fields[$field->name] = $field->answer;
			}
		}

		$data->beneficiary = isset($data->user) ? (object)array_merge($data->fields, (array)$data->user) : $data->payer;
		if (!isset($data->beneficiary->email) && ($data->payer->firstName === $data->beneficiary->firstName) && ($data->payer->lastName === $data->beneficiary->lastName) && !empty($data->payer->email)) {
			$data->beneficiary->email = $data->payer->email;
		}
		$data->beneficiary_label = isset($data->user) ? Payers::getPersonName($data->user) : null;

		return $data;
	}

	static protected function transformOption(\stdClass $data): \stdClass
	{
		$data->fields = [];

		if (!empty($data->user)) {
			$data->fields = Payers::formatPersonInfos($data->user);
		}

		if (!empty($data->customFields)) {
			foreach ($data->customFields as $field) {
				$data->fields[$field->name] = $field->answer;
			}
		}

		return $data;
	}

	static protected function createTransaction(ChargeableInterface $entity, array $accounts, int $payment_ref, \DateTime $date): Transaction
	{
		if (!$id_year = Years::getOpenYearIdMatchingDate($date)) {
			throw new \RuntimeException(sprintf('No opened accounting year matching the item date "%s"!', $date->format('Y-m-d')));
		}
		// ToDo: check accounts validity (right number for the Transaction type)

		$transaction = new Transaction();
		$transaction->type = Transaction::TYPE_REVENUE;
		$transaction->reference = (string)Payments::getId($payment_ref); // aka $payment->id

		$source = [
			'status' => Transaction::STATUS_PAID,
			'label' => self::TRANSACTION_PREFIX . ' - ' . $entity->getLabel(),
			'notes' => self::TRANSACTION_NOTE,
			'payment_reference' => $payment_ref,
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

	static protected function initSync(): void
	{
		Users::initSync();
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
				return Forms::getLabel($id_form);
			}
		}
		else {
			return $data->name;
		}
	}

	static protected function catchSyncException(SyncException $e): void
	{
		self::$_exceptions[] = $e;
	}

	static public function getExceptions(): array
	{
		return self::$_exceptions;
	}

	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s;', Item::TABLE);
		DB::getInstance()->exec($sql);
	}
}
