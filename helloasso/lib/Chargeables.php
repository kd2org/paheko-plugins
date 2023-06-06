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

class Chargeables
{
	static public function get(int $id_form, int $type, string $label, ?int $amount): ?Chargeable
	{
		if (!array_key_exists($type, Chargeable::TYPES)) {
			throw new \RuntimeException('Invalid Chargeable type: %s. Allowed types are: %s.', $type, implode(', ', array_keys(Chargeable::TYPES)));
		}
		$amount_filter = (null === $amount ? 'amount IS NULL' : 'amount = :amount');
		$params = [ $id_form, $type, $label, $amount ];
		if (null === $amount) {
			array_pop($params);
		}
		return EM::findOne(Chargeable::class, 'SELECT * FROM @TABLE WHERE id_form = :id_form AND type = :type AND label = :label AND ' . $amount_filter, ...$params);
	}

	static public function allForDisplay(bool $accounting = true): array
	{
		$params = $accounting ? [ Chargeable::FREE_TYPE ] : [];
		$chargeables = Chargeables::allPlusExtraFields(
			sprintf('
				SELECT c.*, f.name AS _form_name, i.label AS _item_name
				FROM @TABLE c
				LEFT JOIN %s f ON (f.id = c.id_form)
				LEFT JOIN %s i ON (i.id = c.id_item)
				WHERE ' . ($accounting ? '(c.type != :free_type AND c.id_credit_account IS NULL) OR ' : '') . '(c.register_user IS NULL)
				ORDER BY f.name
				',
				Form::TABLE, Item::TABLE),
			['_form_name', '_item_name' ],
			...$params
		);
		$result = [];
		$checkouts = [];
		foreach ($chargeables as $chargeable) {
			$target = ($chargeable->getForm_name() === 'Checkout') ? 'result' : 'checkouts';
			${$target}[$chargeable->getForm_name()][] = $chargeable;
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
			'type' => [
				'select' => 'c.type'
			],
			'item_type' => [
				'label' => 'Type',
				'select' => 'i.type'
			],
			'label' => [
				'label' => 'Libellé',
				'select' => 'c.label'
			],
			'amount' => [
				'label' => 'Montant',
				'select' => 'c.amount'
			],
			'register_user' => [
				'label' => 'Inscrip. Auto',
				'select' => 'c.register_user'
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
			]
		];

		$tables = Chargeable::TABLE . ' c
			INNER JOIN ' . Form::TABLE . ' f ON (f.id = c.id_form)
			LEFT JOIN ' . Item::TABLE . ' i ON (i.id = c.id_item)
			LEFT JOIN ' . Account::TABLE . ' ca ON (ca.id = c.id_credit_account)
			LEFT JOIN ' . Account::TABLE . ' da ON (da.id = c.id_debit_account)
		';

		$list = new DynamicList($columns, $tables);

		$conditions = sprintf('c.id_form = %d', $for->id);
		$list->setConditions($conditions);
		$list->setTitle(sprintf('%s - Items', $for->name));

		$list->setModifier(function ($row) {
			$row->type_label = ($row->id_item !== null) ? (Item::TYPES[$row->item_type] ?? 'Inconnu') : (Form::TYPES[$row->form_type] ?? 'Inconnu');
			if ($row->type === Chargeable::OPTION_TYPE) {
				$row->type_label .= ' - ' . Chargeable::TYPES[$row->type];
			}
			
			$row->register_user = $row->register_user ? 'oui' : ($row->register_user === 0 ? '' : null);
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

	static public function createChargeable(int $id_form, ChargeableInterface $entity, int $type): Chargeable
	{
		if (!array_key_exists($type, Chargeable::TYPES)) {
			throw new \InvalidArgumentException(sprintf('Invalid chargeable type: %s. Allowed types are: %s.', $type, implode(', ', array_keys(Chargeable::TYPES))));
		}
		$chargeable = new Chargeable();
		$chargeable->set('type', $type);
		$chargeable->set('id_form', $id_form);
		$chargeable->set('id_item', $entity->getItemId());
		$chargeable->set('label', $entity->getLabel());
		$chargeable->set('amount', (self::isMatchingAnyAmount($entity, $type) ? null : $entity->getAmount()));
		$chargeable->set('register_user', null);
		$chargeable->save();
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

	static public function setUserRegistrators(array $ids): void
	{
		foreach ($ids as $id) {
			if (!is_int($id)) {
				throw new \InvalidArgumentException(sprintf('User (Chargeable) registrator ID must be an integer. "%s" provided.', $id));
			}
		}
		if (DB::getInstance()->exec(sprintf('UPDATE %s SET register_user = 1 WHERE id IN (%s);', Chargeable::TABLE, implode(', ', $ids))) === false) {
			throw new \RuntimeException(sprintf('Cannot set %s plugin Chargeables\' user registrators.', HelloAsso::PROVIDER_LABEL));
		}
	}

	static function unsetUserRegistrators(array $ids): void
	{
		foreach ($ids as $id) {
			if (!is_int($id)) {
				throw new \InvalidArgumentException(sprintf('User (Chargeable) registrator ID must be an integer. "%s" provided.', $id));
			}
		}
		if (DB::getInstance()->exec(sprintf('UPDATE %s SET register_user = 0 WHERE id IN (%s);', Chargeable::TABLE, implode(', ', $ids))) === false) {
			throw new \RuntimeException(sprintf('Cannot unset %s plugin Chargeables\' user registrators.', HelloAsso::PROVIDER_LABEL));
		}
	}
}
