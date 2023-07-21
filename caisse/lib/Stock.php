<?php

namespace Paheko\Plugin\Caisse;

use Paheko\DB;
use KD2\DB\EntityManager as EM;

use Paheko\Plugin\Caisse\Entities\StockEvent;

class Stock
{
	static public function get(int $id): ?StockEvent
	{
		return EM::findOneById(StockEvent::class, $id);
	}

	static public function new(): StockEvent
	{
		return new StockEvent;
	}

	static public function listEvents(): array
	{
		return EM::getInstance(StockEvent::class)->all('SELECT * FROM @TABLE ORDER BY date DESC;');
	}

	static public function listValue(): array
	{
		$db = EM::getInstance(StockEvent::class)->DB();
		$list = $db->get(POS::sql('SELECT c.name AS label, COUNT(p.id) AS count, SUM(p.stock * p.price) AS value
			FROM @PREFIX_products p
			INNER JOIN @PREFIX_categories c ON c.id = p.category
			WHERE p.stock IS NOT NULL
			GROUP BY p.category;'));

		$total = (object) ['label' => 'Total', 'count' => 0, 'value' => 0];

		foreach ($list as $row) {
			$total->count += $row->count;
			$total->value += $row->value;
		}

		$list[] = $total;
		return $list;
	}
}
