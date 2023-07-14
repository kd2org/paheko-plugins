<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\ChargeableInterface;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\HelloAsso;

use Garradin\Entities\Accounting\Account;

use KD2\DB\EntityManager as EM;
use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;
use Garradin\Entities\Users\Category;
use Garradin\Entities\Services\Fee;
use Garradin\Entities\Services\Service;

class Chargeables
{
	static public function get(int $id_form, int $target_type, int $type, string $label, ?int $amount): ?Chargeable
	{
		if (!array_key_exists($target_type, Chargeable::TARGET_TYPES)) {
			throw new \RuntimeException('Invalid Chargeable target type: %s. Allowed types are: %s.', $target_type, implode(', ', array_keys(Chargeable::TARGET_TYPES)));
		}
		if (!array_key_exists($type, Chargeable::TYPES)) {
			throw new \RuntimeException('Invalid Chargeable type: %s. Allowed types are: %s.', $type, implode(', ', array_keys(Chargeable::TYPES)));
		}
		$amount_filter = (null === $amount ? 'amount IS NULL' : 'amount = :amount');
		$params = [ $id_form, $target_type, $type, $label, $amount ];
		if (null === $amount) {
			array_pop($params);
		}
		return EM::findOne(Chargeable::class, 'SELECT * FROM @TABLE WHERE id_form = :id_form AND target_type = :target_type AND type = :type AND label = :label AND ' . $amount_filter, ...$params);
	}

	static public function getFromEntity(int $id_form, ChargeableInterface $entity, int $type): Chargeable
	{
		if ($entity->id_chargeable) {
			return EM::findOneById(Chargeable::class, (int)$entity->id_chargeable);
		}

		$amount = (self::isMatchingAnyAmount($entity, $type) ? null : $entity->getAmount());

		if ($chargeable = self::get($id_form, Chargeable::TARGET_TYPE_FROM_CLASS[get_class($entity)], $type, $entity->getLabel(), $amount)) {
			$entity->set('id_chargeable', (int)$chargeable->id);
			$entity->save();

			return $chargeable;
		}
		return self::createChargeable($id_form, $entity, $type);
	}

	static public function allForDisplay(bool $accounting = true): array
	{
		$params = $accounting ? [ Chargeable::FREE_TYPE ] : [];
		$chargeables = Chargeables::allPlusExtraFields(
			sprintf('
				SELECT c.*, f.label AS _form_label, i.label AS _item_label
				FROM @TABLE c
				LEFT JOIN %s f ON (f.id = c.id_form)
				LEFT JOIN %s i ON (i.id = c.id_item)
				WHERE ' . ($accounting ? '(c.type != :free_type AND c.id_credit_account IS NULL) OR ' : '') . '(c.need_config = 1)
				ORDER BY f.label
				',
				Form::TABLE, Item::TABLE),
			['_form_label', '_item_label' ],
			...$params
		);
		$result = [];
		$checkouts = [];
		foreach ($chargeables as $chargeable) {
			$target = ($chargeable->getForm_label() === 'Checkout') ? 'result' : 'checkouts';
			${$target}[$chargeable->getForm_label()][] = $chargeable;
		}
		$result = array_merge($checkouts, $result); // We place checkouts at the end of the array for display purpose
		return $result;
	}

	static public function allPlusExtraFields(string $query, array $extra_fields, ...$params): array
	{
		$res = self::iterateWithExtraFields($query, $extra_fields, ...$params);
		$out = [];

		foreach ($res as $row) {
			$out[] = $row;
		}

		return $out;
	}

	static public function list(Form $for): DynamicList
	{
		$columns = [
			'id' => [
				'label' => 'Référence',
				'select' => 'c.id'
			],
			'target_type' => [
				'select' => 'c.target_type'
			],
			'type' => [
				'select' => 'c.type'
			],
			'item_type' => [
				'label' => 'Type',
				'select' => 'i.type',
				'order' => 'c.id_item' // Displayed type is Form name if id_item is null
			],
			'label' => [
				'label' => 'Libellé',
				'select' => 'c.label'
			],
			'amount' => [
				'label' => 'Montant',
				'select' => 'c.amount'
			],
			'id_category' => [
				'select' => 'c.id_category'
			],
			'category' => [
				'label' => 'Insc. Catégorie',
				'select' => 'cat.name'
			],
			'service' => [
				'label' => 'Insc. Activité',
				'select' => 's.label'
			],
			'credit_account' => [
				'label' => 'Recette',
				'select' => 'ca.code'
			],
			'id_credit_account' => [
				'select' => 'c.id_credit_account'
			],
			'debit_account' => [
				'label' => 'Encaissement',
				'select' => 'da.code'
			],
			'id_debit_account' => [
				'select' => 'c.id_debit_account'
			],
			'id_form' => [
				'select' => 'c.id_form'
			],
			'form_type' => [
				'select' => 'f.type'
			],
			'id_item' => [
				'select' => 'c.id_item'
			],
			'need_config' => [
				'select' => 'c.need_config'
			]
		];

		$tables = Chargeable::TABLE . ' c
			INNER JOIN ' . Form::TABLE . ' f ON (f.id = c.id_form)
			LEFT JOIN ' . Item::TABLE . ' i ON (i.id = c.id_item)
			LEFT JOIN ' . Account::TABLE . ' ca ON (ca.id = c.id_credit_account)
			LEFT JOIN ' . Account::TABLE . ' da ON (da.id = c.id_debit_account)
			LEFT JOIN ' . Category::TABLE . ' cat ON (cat.id = c.id_category)
			LEFT JOIN ' . Fee::TABLE . ' fee ON (fee.id = c.id_fee)
			LEFT JOIN ' . Service::TABLE . ' s ON (s.id = fee.id_service)
		';

		$list = new DynamicList($columns, $tables);

		$conditions = sprintf('c.id_form = %d', $for->id);
		$list->setConditions($conditions);
		$list->setTitle(sprintf('%s - Items', $for->label));

		$list->setModifier(function ($row) {
			$row->type_label = ($row->id_item !== null) ? (Item::TYPES[$row->item_type] ?? 'Inconnu') : (Form::TYPES[$row->form_type] ?? 'Inconnu');
			if ($row->target_type === Chargeable::OPTION_TARGET_TYPE) {
				$row->type_label .= ' - ' . Chargeable::TARGET_TYPES[$row->target_type];
			}
			
			$row->category = $row->category ?? ($row->need_config === 1 ? null : '-');
			$row->service = $row->service ?? '-';
		});

		$list->setExportCallback(function (&$row) {
			$row->amount = $row->amount ? Utils::money_format($row->amount, '.', '', false) : null;
		});

		$list->orderBy('id', true);
		return $list;
	}

	static public function iterateWithExtraFields(string $query, ?array $extra_fields, ...$params): iterable
	{
		$db = DB::getInstance();
		$query = str_replace('@TABLE', Chargeable::TABLE, $query);
		$res = $db->preparedQuery($query, $params);

		while ($row = $res->fetchArray(\SQLITE3_ASSOC)) {
			$data = $row;
			foreach ($extra_fields as $field)
				if (array_key_exists($field, $row))
					unset($data[$field]);
			$obj = new Chargeable();
			$obj->exists(true);
			$obj->load($data);
			if ($extra_fields) {
				foreach ($extra_fields as $field) {
					if (!array_key_exists($field, $row)) {
						throw new \LogicException(sprintf('Specified extra field "%s" not provided to the query.', $field));
					}
					$obj->{'set'.ucfirst(substr($field, 1))}($row[$field]);
				}
			}
			yield $obj;
		}

		$res->finalize();
	}

	static public function getType(ChargeableInterface $entity, string $raw_form_type): int
	{
		if ($entity->getPriceType() === Item::FREE_PRICE_TYPE) {
			return Chargeable::FREE_TYPE;
		}
		if (Chargeable::TARGET_TYPE_FROM_CLASS[get_class($entity)] === Chargeable::OPTION_TARGET_TYPE) {
			return Chargeable::SIMPLE_TYPE;
		}
		$form_type = Chargeable::TYPE_FROM_FORM[$raw_form_type];
		if ($form_type !== Chargeable::ONLY_ONE_ITEM_FORM_TYPE && $entity->getPriceType() === Item::PAY_WHAT_YOU_WANT_PRICE_TYPE) {
			return Chargeable::PAY_WHAT_YOU_WANT_TYPE;
		}
		if ($form_type === Chargeable::SIMPLE_TYPE && $entity->type === Item::DONATION_TYPE)
			return Chargeable::DONATION_ITEM_TYPE;
		return $form_type;
	}

	static public function createChargeable(int $id_form, ChargeableInterface $entity, int $type): Chargeable
	{
		if (!array_key_exists($type, Chargeable::TYPES)) {
			throw new \InvalidArgumentException(sprintf('Invalid chargeable type: %s. Allowed types are: %s.', $type, implode(', ', array_keys(Chargeable::TYPES))));
		}
		$chargeable = new Chargeable();
		$chargeable->set('type', $type);
		$chargeable->set('target_type', Chargeable::TARGET_TYPE_FROM_CLASS[get_class($entity)]);
		$chargeable->set('id_form', $id_form);
		$chargeable->set('id_item', $entity->getItemId());
		$chargeable->set('id_category', null);
		$chargeable->set('label', $entity->getLabel());
		$chargeable->set('amount', (self::isMatchingAnyAmount($entity, $type) ? null : $entity->getAmount()));
		$chargeable->set('need_config', 1);
		$chargeable->save();

		$entity->set('id_chargeable', (int)$chargeable->id);
		$entity->save();

		return $chargeable;
	}

	static public function isMatchingAnyAmount(ChargeableInterface $entity, int $type): bool
	{
		return (($type === Chargeable::ONLY_ONE_ITEM_FORM_TYPE) || ($type === Chargeable::DONATION_ITEM_TYPE) || ($entity->getPriceType() === Item::PAY_WHAT_YOU_WANT_PRICE_TYPE));
	}

	static public function setAccounts(array $source): void
	{
		foreach ($source as $id => $accounts) {
			if (DB::getInstance()->exec(sprintf('UPDATE %s SET id_credit_account = %d, id_debit_account = %d WHERE id = %d;', Chargeable::TABLE, $accounts['credit'], $accounts['debit'], (int)$id)) === false) {
				throw new \RuntimeException(sprintf('Cannot update %s plugin Items\' accounting accounts.', HelloAsso::PROVIDER_LABEL));
			}
		}
	}

	static public function listCountOpti(Form $for): DynamicList
	{
		$list = new DynamicList([], Chargeable::TABLE);

		$conditions = sprintf('id_form = %d', $for->id);
		$list->setConditions($conditions);

		return $list;
	}
}
