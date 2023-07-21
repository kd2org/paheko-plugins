<?php

namespace Paheko\Plugin\Caisse;

use Paheko\DB;
use KD2\DB\EntityManager as EM;

use Paheko\Plugin\Caisse\Entities\Category;

class Categories
{
	static public function get(int $id): ?Category
	{
		return EM::findOneById(Category::class, $id);
	}

	static public function new(): Category
	{
		return new Category;
	}

	static public function list(): array
	{
		return EM::getInstance(Category::class)->all('SELECT * FROM @TABLE ORDER BY name;');
	}
}
