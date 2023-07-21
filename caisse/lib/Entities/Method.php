<?php

namespace Paheko\Plugin\Caisse\Entities;

use Paheko\Plugin\Caisse\POS;
use Paheko\Entity;
use Paheko\ValidationException;
use Paheko\Utils;

use KD2\DB\EntityManager;

class Method extends Entity
{
	const TABLE = POS::TABLES_PREFIX . 'methods';

	protected ?int $id;
	protected string $name = '';
	protected bool $is_cash = false;
	protected ?int $min = null;
	protected ?int $max = null;
	protected ?string $account = null;
	protected bool $enabled = false;


	public function importForm(array $source = null)
	{
		if (null === $source) {
			$source = $_POST;
		}

		if (isset($source['min'])) {
			$source['min'] = Utils::moneyToInteger($source['min']);
		}

		if (isset($source['max'])) {
			$source['max'] = Utils::moneyToInteger($source['max']);
		}

		$source['is_cash'] = !empty($source['is_cash']);
		$source['enabled'] = !empty($source['enabled']);

		parent::importForm($source);
	}


	public function selfCheck(): void
	{
		$this->assert(!empty($this->name) && trim($this->name) !== '', 'Le nom ne peut rester vide.');
	}

	public function delete(): bool
	{
		$db = EntityManager::getInstance(static::class)->DB();

		if ($db->test(POS::TABLES_PREFIX . 'tabs_payments', 'method = ?', $this->id)) {
			throw new ValidationException('Ce moyen de paiement ne peut être supprimé car il est utilisé dans des notes de caisse. Il est par contre possible de le désactiver.');
		}

		return parent::delete();
	}

	public function listProducts(): array
	{
		$db = EntityManager::getInstance(static::class)->DB();

		return $db->get(POS::sql('SELECT c.name AS category_name, p.name, p.price, p.id, CASE WHEN pm.product IS NULL THEN 0 ELSE 1 END AS checked
			FROM @PREFIX_products p
			INNER JOIN @PREFIX_categories c ON c.id = p.category
			LEFT JOIN @PREFIX_products_methods pm ON pm.product = p.id AND pm.method = ?
			ORDER BY c.name, p.name;'), $this->id);
	}

	public function linkProducts(array $products): void
	{
		$db = EntityManager::getInstance(static::class)->DB();
		$db->begin();
		$db->exec(sprintf(POS::sql('DELETE FROM @PREFIX_products_methods WHERE method = %d AND %s;'), $this->id, $db->where('product', 'NOT IN', $products)));

		if (count($products)) {
			$products = array_map(fn ($a) => sprintf('(%d, %d)', $a, $this->id), $products);
			$db->exec(sprintf(POS::sql('REPLACE INTO @PREFIX_products_methods VALUES %s;'), implode(', ', $products)));
		}

		$db->commit();
	}

	/**
	 * Link all products to this method
	 */
	public function linkAllProducts(): void
	{
		$db = EntityManager::getInstance(static::class)->DB();
		$db->exec(sprintf(POS::sql('INSERT OR IGNORE INTO @PREFIX_products_methods SELECT id, %d FROM @PREFIX_products;'), $this->id));
	}
}