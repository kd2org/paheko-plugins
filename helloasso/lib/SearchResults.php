<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\UserTemplate\Modifiers;
use Garradin\Users\DynamicFields;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Entities\Payments\Payment;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Entities\Users\User;

class SearchResults
{
	const FORM_TYPE = 'form';
	const ORDER_TYPE = 'order';
	const PAYMENT_TYPE = 'payment';
	const CHARGEABLE_TYPE = 'chargeable';
	const USER_TYPE = 'user';
	const TYPE_LABELS = [
		self::FORM_TYPE => 'Formulaire',
		self::ORDER_TYPE => 'Commande',
		self::PAYMENT_TYPE => 'Paiement',
		self::CHARGEABLE_TYPE => 'Article',
		self::USER_TYPE => 'Membre'
	];

	static public function list(string $searched_text): DynamicList
	{
		$columns = [
			'type' => [
				'label' => 'Type',
				'select' => '"' . self::FORM_TYPE . '"'
			],
			'id' => [
				'label' => 'Référence',
				'select' => 'm.id'
			],
			'label' => [
				'label' => 'Libellé',
				'select' => 'm.label'
			],
			'date' => [
				'label' => 'Date',
				'select' => 'null'
			],
			'payer_name' => [
				'label' => 'Payeur/euse',
				'select' => 'null'
			],
			'beneficiary' => [
				'label' => 'Bénéficiaire',
				'select' => 'null'
			],
			'id_payer' => [
				'select' => 'null'
			],
			'user_number' => [
				'select' => 'u2.numero'
			],
			'email' => []
		];

		$db = DB::getInstance();
		$conditions = '1';
		$user_conditions = null;
		$searched_text = trim($searched_text);
		if (preg_match('/(^\d+$)/', $searched_text) === 1) {
			$conditions = 'm.id = :searched_data';
			$searched_data = (int)$searched_text;
		}
		elseif ($searched_data = Modifiers::parse_date($searched_text)) {
			$conditions = 'date = :searched_data';
		}
		elseif (preg_match('/(^[A-Z0-9+_.-]*@[A-Z0-9+_.-]+$)/i', $searched_text) === 1) {
			$conditions = 'email LIKE :searched_data';
			$user_conditions = 'm.' . $db->quoteIdentifier(DynamicFields::getFirstEmailField()) . ' = :searched_data';
			$searched_data = sprintf("%%%s%%", $searched_text);
		}
		else {
			$searched_text = preg_replace('/[!%_]/', '!$0', trim($searched_text));
			$conditions = 'label LIKE :searched_data ESCAPE \'!\'';
			$searched_data = sprintf("%%%s%%", $searched_text);
		}

		$user_join = 'LEFT JOIN ' . User::TABLE . ' u2 ON (u2.id = id_payer)';
		$user_name_column = $db->quoteIdentifier(DynamicFields::getFirstNameField());

		$tables = Form::TABLE . ' m
			' . $user_join . '
			WHERE ' . $conditions . '

			UNION

			SELECT "' . self::ORDER_TYPE . '" AS "type", m.id AS "id", null AS "label", m.date AS "date", m.payer_name AS "payer_name", u2.' . $user_name_column . ' as "beneficiary", m.id_payer AS "id_payer", u2.numero AS "user_number", json_extract(m.raw_data, \'$.payer.email\') AS "email"
			FROM ' . Order::TABLE . ' m
			' . $user_join . '
			WHERE ' . str_replace('email', 'json_extract(m.raw_data, \'$.payer.email\')', $conditions) . '

			UNION

			SELECT "' . self::PAYMENT_TYPE . '" AS "type", m.id AS "id", m.label AS "label", m.date AS "date", m.payer_name AS "payer_name", u2.' . $user_name_column . ' as "beneficiary", m.id_payer AS "id_payer", u2.numero AS "user_number", json_extract(m.extra_data, \'$.payer.email\') AS "email"
			FROM ' . Payment::TABLE . ' m
			' . $user_join . '
			WHERE ' . str_replace('email', 'json_extract(m.extra_data, \'$.payer.email\')', str_replace('m.id', 'm.reference', $conditions)) . '

			UNION

			SELECT "' . self::CHARGEABLE_TYPE . '" AS "type", m.id AS "id", m.label AS "label", null AS "date", null AS "payer_name", null as "beneficiary", null AS "id_payer", u2.numero AS "user_number", null AS "email"
			FROM ' . Chargeable::TABLE . ' m
			' . $user_join . '
			WHERE ' . $conditions . ' AND m.type != ' . (int)Chargeable::CHECKOUT_TYPE . '

			UNION

			SELECT "' . self::USER_TYPE . '" AS "type", m.id AS "id", m.' . $user_name_column . ' AS "label", m.date_inscription AS "date", null AS "payer_name", null as "beneficiary", null AS "id_payer", u2.numero AS "user_number", m.' . $db->quoteIdentifier(DynamicFields::getFirstEmailField()) . ' AS "email"
			FROM ' . User::TABLE . ' m
			' . $user_join . '
		';

		$list = new DynamicList($columns, $tables, $user_conditions ?? $conditions);

		$list->setParameter('searched_data', $searched_data);
		$list->setModifier(function (&$row) {
			$row->type_label = self::TYPE_LABELS[$row->type];
		});

		return $list;
	}
}
