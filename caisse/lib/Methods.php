<?php

namespace Paheko\Plugin\Caisse;

use Paheko\DB;
use Paheko\Utils;
use Paheko\Config;
use KD2\DB\EntityManager as EM;

use Paheko\Plugin\Caisse\Entities\Method;

class Methods
{
	static public function get(int $id): ?Method
	{
		return EM::findOneById(Method::class, $id);
	}

	static public function new(): Method
	{
		$m = new Method;
		$m->enabled = true;
		return $m;
	}

	static public function list(): array
	{
		return EM::getInstance(Method::class)->all('SELECT * FROM @TABLE ORDER BY name;');
	}

	static public function getStatsPerMonth(?int $year = null): array
	{
		$sql = 'SELECT strftime(\'%%Y-%%m\', p.date) AS month, date, m.name AS method, COUNT(p.id) AS count, SUM(amount) AS sum
			FROM @PREFIX_tabs_payments p
			INNER JOIN @PREFIX_methods m ON m.id = p.method
			%s
			GROUP BY strftime(\'%%Y-%%m\', p.date), m.id
			ORDER BY month, m.name;';
		$sql = sprintf($sql, $year ? 'WHERE strftime(\'%Y\', p.date) = ?' : '');
		$sql = POS::sql($sql);

		$args = [];

		if ($year) {
			$args[] = (string)$year;
		}

		return DB::getInstance()->get($sql, $args);
	}

	static public function graphStatsPerMonth(int $year): string
	{
		$sql = 'SELECT strftime(\'%m\', p.date) AS month, m.name, SUM(amount) / 100
			FROM @PREFIX_tabs_payments p
			INNER JOIN @PREFIX_methods m ON m.id = p.method
			WHERE strftime(\'%Y\', p.date) = ?
			GROUP BY strftime(\'%m\', p.date), m.id
			ORDER BY month, m.name;';
		$sql = POS::sql($sql);

		$data = DB::getInstance()->getAssocMulti($sql, (string) $year);
		return POS::barGraph(null, $data);
	}
}
