<?php

namespace Paheko\Plugin\Caisse\Entities;

use Paheko\Email\Emails;
use Paheko\DB;
use Paheko\Config;
use Paheko\Template;
use Paheko\UserException;
use Paheko\Utils;
use Paheko\Users\DynamicFields;

use const Paheko\PLUGIN_ROOT;

use Paheko\Plugin\Caisse\POS;
use Paheko\Plugin\Caisse\Tabs;
use Paheko\Entity;
use Paheko\ValidationException;

use KD2\Mail_Message;

class Session extends Entity
{
	const TABLE = POS::TABLES_PREFIX . 'sessions';

	protected ?int $id;
	protected \DateTime $opened;
	protected ?\DateTime $closed;
	protected string $open_user;
	protected int $open_amount;
	protected ?int $close_amount;
	protected ?string $close_user;
	protected ?int $error_amount;

	public function hasOpenNotes() {
		return DB::getInstance()->test(POS::tbl('tabs'), 'session = ? AND closed IS NULL', $this->id);
	}

	public function close(string $user_name, int $amount, ?bool $confirm_error, array $payments, ?string $send_email)
	{
		$db = DB::getInstance();

		if ($this->hasOpenNotes()) {
			throw new UserException('Il y a des notes qui ne sont pas clôturées.');
		}

		$payments = array_map(function ($a) { return (int) $a; }, $payments);
		$payments = implode(',', $payments);

		$check_payments = $db->firstColumn(sprintf(POS::sql('SELECT COUNT(*) FROM @PREFIX_tabs_payments tp
			INNER JOIN @PREFIX_tabs t ON t.id = tp.tab AND t.session = ?
			INNER JOIN @PREFIX_methods m ON m.id = tp.method
			WHERE tp.id NOT IN (%s) AND m.is_cash = 0 LIMIT 1;'), $payments), $this->id);

		if ($check_payments) {
			throw new UserException('Certains paiements n\'ont pas été cochés comme vérifiés');
		}

		$expected_total = $this->getCashTotal() + $this->open_amount;
		$error_amount = $amount - $expected_total;

		if ($error_amount != 0 && !$confirm_error) {
			throw new UserException('Une erreur de caisse existe, il faut confirmer le recomptage de la caisse');
		}

		$db->begin();

		$db->preparedQuery(POS::sql('UPDATE @PREFIX_sessions SET
			closed = datetime(\'now\', \'localtime\'),
			close_amount = ?,
			close_user = ?,
			error_amount = ?
			WHERE id = ?'), [$amount, $user_name, $error_amount, $this->id]);

		// Update stock
		$db->preparedQuery(POS::sql('INSERT INTO @PREFIX_products_stock_history (product, change, date, item, event)
			SELECT p.id, -SUM(ti.qty), ti.added, ti.id, NULL
				FROM @PREFIX_tabs_items ti
				INNER JOIN @PREFIX_products p ON p.id = ti.product
				INNER JOIN @PREFIX_tabs t ON t.id = ti.tab
				INNER JOIN @PREFIX_sessions s ON s.id = t.session
				WHERE s.closed IS NOT NULL AND ti.product IS NOT NULL AND s.id = ? AND p.stock IS NOT NULL
				GROUP BY ti.id, ti.product;'), $this->id);

		$select = sprintf('FROM @PREFIX_tabs_items ti
			INNER JOIN @PREFIX_tabs t ON t.id = ti.tab
			WHERE ti.product = @PREFIX_products.id
			AND t.session = %d', $this->id);
		$db->preparedQuery(POS::sql(sprintf('UPDATE @PREFIX_products SET stock = -(SELECT SUM(ti.qty) %s) + stock WHERE stock IS NOT NULL AND id IN (SELECT DISTINCT ti.product %1$s);', $select)));

		$db->commit();

		if ($send_email) {
			$msg = new Mail_Message;
			$msg->setHeader('Subject', sprintf('Clôture de caisse n°%d du %s', $this->id, date('d/m/Y à H:i')));
			$msg->setHeader('To', $send_email);
			$msg->setHeader('From', Emails::getFromHeader());
			$msg->addPart('text/html', $this->export(true, 1), sprintf('session-%d.html', $this->id));
			$msg->setBody('Voir les détails dans le contenu HTML ci-joint.');
			Emails::sendMessage(Emails::CONTEXT_SYSTEM, $msg);
		}
	}

	public function getTotal()
	{
		return DB::getInstance()->firstColumn(POS::sql('SELECT SUM(tp.amount) FROM @PREFIX_tabs_payments tp
			INNER JOIN @PREFIX_tabs t ON tp.tab = t.id AND t.session = ?'), $this->id);
	}

	public function listPayments()
	{
		return DB::getInstance()->get(POS::sql('SELECT tp.*, t.name AS tab_name,
			m.name AS method_name
			FROM @PREFIX_tabs_payments tp
			INNER JOIN @PREFIX_tabs t ON tp.tab = t.id AND t.session = ?
			INNER JOIN @PREFIX_methods m ON m.id = tp.method
			ORDER BY m.id, tp.date;'), $this->id);
	}

	public function listPaymentTotals()
	{
		return DB::getInstance()->get(POS::sql('SELECT SUM(tp.amount) AS total,
			m.name AS method_name
			FROM @PREFIX_tabs_payments tp
			INNER JOIN @PREFIX_tabs t ON tp.tab = t.id AND t.session = ?
			INNER JOIN @PREFIX_methods m ON m.id = tp.method
			GROUP BY m.id
			ORDER BY method_name;'), $this->id);
	}

	public function listTabsTotals()
	{
		return DB::getInstance()->get(POS::sql('SELECT *,
			(SELECT SUM(qty * price) FROM @PREFIX_tabs_items WHERE tab = t.id) AS total
			FROM @PREFIX_tabs t WHERE session = ? ORDER BY opened;'), $this->id);
	}

	public function listTabsWithItems()
	{
		$db = DB::getInstance();
		$tabs = $db->get(POS::sql('SELECT *, total - paid AS remainder
			FROM (SELECT *,
				(SELECT SUM(qty * price) FROM @PREFIX_tabs_items WHERE tab = t.id) AS total,
				(SELECT SUM(amount) FROM @PREFIX_tabs_payments WHERE tab = t.id) AS paid
				FROM @PREFIX_tabs t WHERE session = ? ORDER BY opened
			);'), $this->id);

		foreach ($tabs as &$tab) {
			$t = Tabs::get($tab->id);
			$tab->items = $t->listItems();
		}

		return $tabs;
	}

	public function listTotalsByCategory()
	{
		return DB::getInstance()->get(POS::sql('SELECT
			SUM(ti.qty * ti.price) AS total,
			ti.category_name,
			c.account
			FROM @PREFIX_tabs_items ti
			LEFT JOIN @PREFIX_products p ON ti.product = p.id
			LEFT JOIN @PREFIX_categories c ON c.id = p.category
			WHERE ti.tab IN (SELECT id FROM @PREFIX_tabs WHERE session = ?)
			GROUP BY category_name;'), $this->id);

	}

	public function getCashTotal()
	{
		return DB::getInstance()->firstColumn(POS::sql('
			SELECT SUM(amount) FROM @PREFIX_tabs_payments p
			INNER JOIN @PREFIX_tabs t ON t.id = p.tab
			INNER JOIN @PREFIX_methods m ON m.id = p.method
			WHERE t.session = ? AND m.is_cash = 1;'), $this->id);
	}

	public function listPaymentWithoutCash()
	{
		return DB::getInstance()->get(POS::sql('
			SELECT p.*, m.name AS method_name FROM @PREFIX_tabs_payments p
			INNER JOIN @PREFIX_tabs t ON t.id = p.tab
			INNER JOIN @PREFIX_methods m ON m.id = p.method
			WHERE t.session = ? AND m.is_cash = 0
			ORDER BY p.date;'), $this->id);
	}

	public function listMissingUsers() {
		return DB::getInstance()->get(POS::sql('SELECT * FROM @PREFIX_tabs WHERE user_id IS NULL AND session = ?;'), $this->id);
	}

	public function export(bool $details = false, int $print = 0)
	{
		$tpl = Template::getInstance();
		$tpl->assign('pos_session', $this);
		$tpl->assign('payments', $this->listPayments());
		$tpl->assign('payments_totals', $this->listPaymentTotals());
		$tpl->assign('tabs', $this->listTabsWithItems());
		$tpl->assign('totals_categories', $this->listTotalsByCategory());
		$tpl->assign('total', $this->getTotal());
		$tpl->assign('missing_users_tabs', $this->listMissingUsers());

		$tpl->assign('title', sprintf('Session de caisse n°%d du %s', $this->id, Utils::date_fr($this->opened)));

		$tpl->assign('print', (bool) $print);
		$tpl->assign('details', $details);
		$tpl->assign('id_field', DynamicFields::getFirstNameField());

		if ($print == 2) {
			$tpl->PDF(PLUGIN_ROOT . '/templates/session_export.tpl', sprintf('Session de caisse numéro %d du %s', $this->id, Utils::date_fr($this->opened, 'd-m-Y')));
		}
		elseif ($print) {
			return $tpl->fetch(PLUGIN_ROOT . '/templates/session_export.tpl');
		}
		else {
			return $tpl->fetch(PLUGIN_ROOT . '/templates/session.tpl');
		}
	}

	public function openTab(): Tab
	{
		if (!$this->exists()) {
			throw new \LogicException('Cannot open tab for unsaved session');
		}

		$tab = new Tab;
		$tab->session = $this->id();
		$tab->opened = new \DateTime;
		$tab->save();
		return $tab;
	}
}