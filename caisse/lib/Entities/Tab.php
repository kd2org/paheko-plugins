<?php

namespace Garradin\Plugin\Caisse\Entities;

use Garradin\DB;
use Garradin\UserException;

use Garradin\Plugin\Caisse\POS;
use Garradin\Entity;
use Garradin\ValidationException;

class Tab extends Entity
{
	const TABLE = POS::TABLES_PREFIX . 'tabs';

	protected ?int $id;
	protected int $session;
	protected \DateTime $opened;
	protected ?\DateTime $closed;
	protected ?string $name;
	protected ?int $user_id;

	public int $total;

	public function load(array $data): self
	{
		parent::load($data);
		$this->total = $this->total();
		return $this;
	}

	public function total(): int
	{
		$db = DB::getInstance();
		return $db->firstColumn(POS::sql('SELECT SUM(qty*price) FROM @PREFIX_tabs_items WHERE tab = ?;'), $this->id()) ?: 0;
	}

	public function getRemainder(): int
	{
		return (int) DB::getInstance()->firstColumn(POS::sql('SELECT
			COALESCE((SELECT SUM(price * qty) FROM @PREFIX_tabs_items WHERE tab = ?), 0)
			- COALESCE((SELECT SUM(amount) FROM @PREFIX_tabs_payments WHERE tab = ?), 0);'), $this->id, $this->id);
	}

	public function addItem(int $id)
	{
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		$db = DB::getInstance();
		$product = $db->first(POS::sql('SELECT p.*, c.name AS category_name, c.account AS category_account
			FROM @PREFIX_products p
			INNER JOIN @PREFIX_categories c ON c.id = p.category
			WHERE p.id = ?'), $id);

		return $db->insert(POS::tbl('tabs_items'), [
			'tab'           => $this->id,
			'product'       => (int)$product->id,
			'qty'           => (int)$product->qty,
			'price'         => (int)$product->price,
			'name'          => $product->name,
			'category_name' => $product->category_name,
			'description'   => $product->description,
			'account'       => $product->category_account,
		]);
	}

	public function removeItem(int $id)
	{
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		return DB::getInstance()->delete(POS::tbl('tabs_items'), 'id = ? AND tab = ?', $id, $this->id);
	}

	public function updateItemQty(int $id, int $qty)
	{
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		$db = DB::getInstance();
		return $db->update(POS::tbl('tabs_items'),
			['qty' => $qty],
			sprintf('id = %d AND tab = %d', $id, $this->id));
	}

	public function updateItemPrice(int $id, int $price)
	{
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		$db = DB::getInstance();
		return $db->update(POS::tbl('tabs_items'),
			['price' => $price],
			sprintf('id = %d AND tab = %d', $id, $this->id));
	}

	public function listItems()
	{
		return DB::getInstance()->get(POS::sql('SELECT ti.*,
			ti.qty * ti.price AS total,
			GROUP_CONCAT(pm.method, \',\') AS methods
			FROM @PREFIX_tabs_items ti
			LEFT JOIN @PREFIX_products p ON ti.product = p.id
			LEFT JOIN @PREFIX_categories c ON c.id = p.category
			LEFT JOIN @PREFIX_products_methods pm ON pm.product = p.id
			WHERE ti.tab = ?
			GROUP BY ti.id
			ORDER BY ti.id;'), $this->id);
	}

	public function pay(int $method_id, int $amount, ?string $reference)
	{
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		if ('' === trim($reference)) {
			$reference = NULL;
		}

		$options = $this->listPaymentOptions();
		$option = $options[$method_id];

		if (empty($option->amount)) {
			throw new UserException('Ce moyen de paiement ne peut pas être utilisé pour cette note');
		}
		elseif ($amount > $option->amount) {
			$a = $option->amount;
			throw new UserException(sprintf('Ce moyen de paiement ne peut être utilisé pour un montant supérieur à %d,%02d€', (int) ($a/100), (int) ($a%100)));
		}

		if (null !== $reference && $option->is_cash) {
			throw new UserException('Référence indiquée pour un règlement en espèces : vouliez-vous enregistrer un règlement par chèque ?');
		}

		return DB::getInstance()->insert(POS::tbl('tabs_payments'), [
			'tab'         => $this->id,
			'method'      => $method_id,
			'amount'      => $amount,
			'reference'   => $reference,
			'account'     => $option->account,
		]);
	}

	public function removePayment(int $id)
	{
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		return DB::getInstance()->delete(POS::tbl('tabs_payments'), 'id = ? AND tab = ?', $id, $this->id);
	}

	public function listPayments()
	{
		return DB::getInstance()->get(POS::sql('SELECT tp.*,
			m.name AS method_name
			FROM @PREFIX_tabs_payments tp
			INNER JOIN @PREFIX_methods m ON m.id = tp.method
			WHERE tp.tab = ?;'), $this->id);
	}

	public function listPaymentOptions()
	{
		$remainder = $this->getRemainder();
		return DB::getInstance()->getGrouped(POS::sql('SELECT id, *,
			CASE
				WHEN max IS NOT NULL AND max > 0 AND paid >= max THEN 0 -- We cannot use this payment method, we paid the max allowed amount with it
				WHEN max IS NOT NULL AND max > 0 AND payable > max THEN max -- We have to pay more than max allowed, then just return max
				WHEN min IS NOT NULL AND payable < min THEN 0 -- We cannot use as the minimum required amount has not been reached
				ELSE MIN(:left, payable) END AS amount
			FROM (SELECT m.*, SUM(pt.amount) AS paid, SUM(i.qty * i.price) AS payable
				FROM @PREFIX_methods m
				INNER JOIN @PREFIX_products_methods pm ON pm.method = m.id
				INNER JOIN @PREFIX_tabs_items i ON i.product = pm.product AND i.tab = :id
				LEFT JOIN @PREFIX_tabs_payments AS pt ON pt.tab = i.tab AND m.id = pt.method
				WHERE m.enabled = 1
				GROUP BY m.id
			);'), ['id' => $this->id, 'left' => $remainder]);
	}

	public function rename(string $new_name, ?int $user_id) {
		$new_name = trim($new_name);
		$db = DB::getInstance();
		return $db->update(POS::tbl('tabs'), ['name' => $new_name, 'user_id' => $user_id], $db->where('id', $this->id));
	}

	public function renameItem(int $id, string $name) {
		if ($this->closed) {
			throw new \LogicException('Cannot modify a closed tab');
		}

		return DB::getInstance()->update(POS::tbl('tabs_items'), ['name' => trim($name)], sprintf('id = %d AND tab = %d', $id, $this->id));
	}

	public function close() {
		$remainder = $this->getRemainder();

		if ($remainder != 0) {
			throw new UserException(sprintf("Impossible de clôturer la note: reste %s € à régler.", $remainder / 100));
		}

		return DB::getInstance()->preparedQuery(POS::sql('UPDATE @PREFIX_tabs SET closed = datetime(\'now\',\'localtime\') WHERE id = ?;'), [$this->id]);
	}

	public function reopen() {
		return DB::getInstance()->preparedQuery(POS::sql('UPDATE @PREFIX_tabs SET closed = NULL WHERE id = ?;'), [$this->id]);
	}

	public function delete(): bool {
		$db = DB::getInstance();
		if ($db->count(POS::tbl('tabs_items'), 'tab = ?', $this->id) && $db->test(POS::tbl('tabs'), 'closed IS NULL AND id = ?', $this->id)) {
			throw new UserException('Impossible de supprimer une note qui n\'est pas close');
		}

		return parent::delete();
	}
}
