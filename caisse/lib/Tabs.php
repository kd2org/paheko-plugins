<?php

namespace Paheko\Plugin\Caisse;

use Paheko\Config;
use Paheko\DB;
use Paheko\Users\DynamicFields;

use Paheko\Plugin\Caisse\Entities\Tab;
use KD2\DB\EntityManager as EM;

class Tabs
{
	static public function get(int $id): ?Tab
	{
		return EM::findOneById(Tab::class, $id);
	}

	static public function listForSession(int $session_id) {
		return DB::getInstance()->getGrouped(POS::sql('SELECT id, *, COALESCE((SELECT SUM(qty*price) FROM @PREFIX_tabs_items WHERE tab = @PREFIX_tabs.id), 0) AS total FROM @PREFIX_tabs WHERE session = ? ORDER BY closed IS NOT NULL, opened DESC;'), $session_id);
	}

	static public function listForUser(string $q): ?array
	{
		$user = current(self::searchMember($q));

		if (!$user) {
			return null;
		}

		$id = $user->id;

		return DB::getInstance()->get(POS::sql('SELECT * FROM @PREFIX_tabs WHERE user_id = ? ORDER BY opened DESC;'), $id);
	}

	static public function searchMember($q) {
		$db = DB::getInstance();
		$operator = 'LIKE';
		$id_field = DynamicFields::getNameFieldsSQL('u');
		$number_field = 'u.' . $db->quoteIdentifier(DynamicFields::getLoginField());
		$email_field = 'u.' . $db->quoteIdentifier(DynamicFields::getFirstEmailField());

		if (is_numeric(trim($q)))
		{
			$column = $number_field;
			$operator = '=';
		}
		elseif (strpos($q, '@') !== false)
		{
			$column = $email_field;
		}
		else
		{
			$column = $id_field;
		}

		if ($operator == 'LIKE') {
			$q = str_replace(['%', '_'], ['\\%', '\\_'], $q);

			$q = '%' . $q . '%';
			$sql = sprintf('%s %s ? ESCAPE \'\\\'', $column, $operator);
		}
		else {
			$sql = sprintf('%s %s ?', $column, $operator);
		}

		$sql = sprintf('SELECT u.id, %s AS numero, %s AS email, %s AS identite,
			MAX(su.expiry_date) AS expiry_date,
			CASE WHEN su.expiry_date IS NULL THEN 0 WHEN su.expiry_date < date() THEN -1 WHEN su.expiry_date >= date() THEN 1 ELSE 0 END AS status
			FROM users u
			LEFT JOIN services_users su ON su.id_user = u.id
			WHERE %s
			GROUP BY u.id
			ORDER BY identite COLLATE U_NOCASE LIMIT 0, 7;', $number_field, $email_field, $id_field, $sql);

		return $db->get($sql, $q);
	}
}
