<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\ChargeableInterface;
use Garradin\Plugin\HelloAsso\HelloAsso;
use KD2\DB\EntityManager as EM;
use Garradin\DB;

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

	static public function allPlusExtraFields(string $query, array $extra_fields, ...$params): array
	{
		$res = self::iterateWithExtraFields($query, $extra_fields, ...$params);
		$out = [];

		foreach ($res as $row) {
			$out[] = $row;
		}

		return $out;
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
		$chargeable = new Chargeable();
		$chargeable->set('type', $type);
		$chargeable->set('id_form', $id_form);
		$chargeable->set('id_item', $entity->getItemId());
		$chargeable->set('label', $entity->getLabel());
		$chargeable->set('amount', ($type === Chargeable::ONLY_ONE_ITEM_FORM_TYPE ? null : $entity->getAmount()));
		$chargeable->save();
		return $chargeable;
	}

	static public function setAccounts(array $source): void
	{
		foreach ($source as $id => $accounts) {
			if (DB::getInstance()->exec(sprintf('UPDATE %s SET id_credit_account = %d, id_debit_account = %d WHERE id = %d;', Chargeable::TABLE, $accounts['credit'], $accounts['debit'], (int)$id)) === false) {
				throw new \RuntimeException(sprintf('Cannot update %s plugin Items\' accounting accounts.', HelloAsso::PROVIDER_LABEL));
			}
		}
	}
}
