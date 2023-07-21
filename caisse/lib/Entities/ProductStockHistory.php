<?php

namespace Paheko\Plugin\Caisse\Entities;

use Paheko\Entity;
use Paheko\Plugin\Caisse\POS;
use KD2\DB\EntityManager as EM;

class ProductStockHistory extends Entity
{
	const TABLE = POS::TABLES_PREFIX . 'products_stock_history';

	protected ?int $id;
	protected int $product;
	protected int $change;
	protected \DateTime $date;
	protected ?int $item;
	protected ?int $event;
}