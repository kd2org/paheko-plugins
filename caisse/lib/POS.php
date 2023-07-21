<?php

namespace Paheko\Plugin\Caisse;

use Paheko\DB;
use Paheko\UserException;
use Paheko\Entities\Accounting\Line;
use Paheko\Entities\Accounting\Transaction;
use Paheko\Entities\Accounting\Year;
use Paheko\Files\Files;
use Paheko\Accounting\Accounts;
use Paheko\Users\Session;

use KD2\Graphics\SVG\Bar;
use KD2\Graphics\SVG\Bar_Data_Set;

class POS
{
	const TABLES_PREFIX = 'plugin_pos_';

	static public function sql(string $query): string
	{
		return str_replace('@PREFIX_', self::TABLES_PREFIX, $query);
	}

	static public function tbl(string $table): string
	{
		return self::TABLES_PREFIX . $table;
	}

	static public function barGraph(?string $title, array $data): string
	{
		$bar = new Bar(1000, 400);
		$bar->setTitle($title);
		$current_group = null;
		$set = null;
		$sum = 0;

		$color = function (string $str): string {
			return sprintf('#%s', substr(md5($str), 0, 6));
		};

		foreach ($data as $group_label => $group) {
			$set = new Bar_Data_Set($group_label);

			foreach ($group as $label => $value) {
				$set->add($value, $label, $color($label));
			}

			$bar->add($set);
		}

		return $bar->output();
	}

	static public function syncAccounting(int $id_creator, Year $year, bool $attach = true): int
	{
		$db = DB::getInstance();
		$db->begin();

		if ($db->test(self::sql('@PREFIX_categories'), 'account IS NULL')) {
			throw new UserException('Des catégories de produits n\'ont pas de compte associé. Merci d\'associer les catégories à des comptes du plan comptable pour pouvoir procéder à la synchronisation.');
		}

		if ($db->test(self::sql('@PREFIX_methods'), 'account IS NULL')) {
			throw new UserException('Des moyens de paiement n\'ont pas de compte associé. Merci de les associer à des comptes du plan comptable pour pouvoir procéder à la synchronisation.');
		}

		$accounts_codes = $db->getAssoc(self::sql('SELECT account, account FROM @PREFIX_categories UNION ALL SELECT account, account FROM @PREFIX_methods;'));
		$accounts_codes['758'] = '758'; // Erreurs de caisse
		$accounts_codes['658'] = '658';
		$accounts = (new Accounts($year->id_chart))->listForCodes($accounts_codes);

		$diff = array_diff_key($accounts_codes, $accounts);

		if (count($diff)) {
			throw new UserException('Les comptes suivants n\'existent pas dans le plan comptable de l\'exercice sélectionné, merci de bien vouloir les créer : ' . implode(', ', $diff));
		}

		$exists = $db->getAssoc('SELECT reference, id FROM acc_transactions WHERE id_year = ? AND reference LIKE \'POS-SESSION-%\';', $year->id);

		$transaction = null;
		$row = null;
		$count = 0;

		$save_transaction = function ($transaction) use ($attach, &$count, $accounts) {
			// In some rare cases, the product may have disappeared (WTF?!), we consider it to be an error
			$error = abs($transaction->getLinesDebitSum()) - $transaction->getLinesCreditSum();
			if ($error != 0) {
				if ($error > 0) {
					$line = Line::create($accounts['758']->id, abs($error), 0, 'Erreur de caisse');
				}
				else {
					$line = Line::create($accounts['658']->id, 0, abs($error), 'Erreur de caisse');
				}

				$transaction->addLine($line);
			}

			$transaction->save();
			$count++;

			if ($attach) {
				$sid = (int) str_replace('POS-SESSION-', '', $transaction->reference);
				$session = Sessions::get($sid);
				$path = $transaction->getAttachementsDirectory();
				$file = Files::createFromString(sprintf('%s/session-%d.html', $path, $sid), $session->export(true, 1));
			}
		};

		foreach (self::iterateSessions($year->start_date, $year->end_date) as $row) {
			// Skip POS sessions already added as transactions
			if (array_key_exists($row->reference, $exists)) {
				continue;
			}

			// Skip lines with no account, they will be treated like errors
			if (empty($row->account)) {
				continue;
			}

			if (empty($accounts[$row->account]->id)) {
				throw new \LogicException($row->account . ': this account has not been found?');
			}

			unset($row->id);
			unset($row->status);

			if ($transaction && $transaction->reference != $row->reference) {
				$save_transaction($transaction, $row);
				$transaction = null;
			}

			if (!$transaction) {
				$transaction = new Transaction;
				$transaction->id_creator = $id_creator;
				$transaction->id_year = $year->id;
				$transaction->import((array) $row);
			}

			$transaction->addLine(Line::create($accounts[$row->account]->id, $row->credit, $row->debit, $row->line_label, $row->line_reference));
		}

		if ($transaction && $row) {
			$save_transaction($transaction, $row);
		}

		$db->commit();

		return $count;
	}

	static public function iterateSessions(\DateTime $start, \DateTime $end, bool $errors_only = false)
	{
		$db = DB::getInstance();

		$errors_only = $errors_only ? 'AND account IS NULL' : '';

		$sql = 'SELECT
			NULL AS id,
			\'Avancé\' AS type,
			NULL AS status,
			\'Session de caisse n°\' || s.id AS label,
			strftime(\'%d/%m/%Y\', s.closed) AS date,
			NULL AS notes,
			\'POS-SESSION-\' || s.id AS reference,
			NULL AS line_id,
			lines.account,
			-- Flip debit/credit if negative
			CASE
				WHEN SUM(lines.debit) < 0 THEN ABS(SUM(lines.debit))
				WHEN SUM(lines.credit) < 0 THEN 0
				ELSE SUM(lines.credit)
			END AS credit,
			CASE
				WHEN SUM(lines.credit) < 0 THEN ABS(SUM(lines.credit))
				WHEN SUM(lines.debit) < 0 THEN 0
				ELSE SUM(lines.debit)
			END AS debit,
			lines.reference AS line_reference,
			NULL AS line_label,
			0 AS reconciled,
			s.id AS sid
			FROM @PREFIX_sessions s
			INNER JOIN (
				SELECT session, account, SUM(price * qty) AS credit, 0 AS debit, NULL AS reference
				FROM @PREFIX_tabs_items ti
				INNER JOIN @PREFIX_tabs t ON t.id = ti.tab
				GROUP BY t.session, account
				UNION ALL
				SELECT session, account, 0 AS credit, SUM(amount) AS debit, reference
				FROM @PREFIX_tabs_payments tp
				INNER JOIN @PREFIX_tabs t ON t.id = tp.tab
				GROUP BY t.session, account, reference
				) AS lines
				ON lines.session = s.id
			WHERE s.closed IS NOT NULL
				AND date(s.closed) >= date(?) AND date(s.closed) <= date(?)
				' . $errors_only . '
			GROUP BY s.id, lines.account, lines.reference
			HAVING SUM(lines.debit) != 0 OR SUM(lines.credit) != 0
			ORDER BY s.id, lines.account, lines.reference;';

		$sql = POS::sql($sql);

		return $db->iterate($sql, $start, $end);
	}

	static public function exportSessionsCSV(\DateTime $start, \DateTime $end, bool $localized_header = false)
	{
		$name = sprintf('Export caisse compta - %s à %s', $start->format('d-m-Y'), $end->format('d-m-Y'));

		header('Content-type: application/csv');
		header(sprintf('Content-Disposition: attachment; filename="%s.csv"', $name));

		$fp = fopen('php://output', 'w');

		if ($localized_header) {
			fputcsv($fp, ['Numéro d\'écriture', 'Type', 'Statut', 'Libellé', 'Date', 'Remarques', 'Numéro pièce comptable',
				'Numéro ligne', 'Compte', 'Crédit', 'Débit', 'Référence ligne', 'Libellé ligne', 'Rapprochement']);
		}
		else {
			fputcsv($fp, ['id', 'type', 'status', 'label', 'date', 'notes', 'reference',
				'line_id', 'account', 'credit', 'debit', 'line_reference', 'line_label', 'reconciled']);
		}

		$id = null;

		$money = function (int $value): string {
			if (!$value) {
				return '0';
			}

			$decimals = substr($value, -2);
			$digits = substr($value, 0, -2) ?: '0';
			return $digits . ',' . $decimals;
		};

		foreach (self::iterateSessions($start, $end) as $row) {
			if (null !== $id && $row->sid === $id) {
				$row->type = $row->status = $row->label = $row->date = $row->reference = null;
			}

			if (null === $id || $row->sid !== $id) {
				$id = $row->sid;
			}

			$row->credit = $money($row->credit);
			$row->debit = $money($row->debit);

			unset($row->sid);
			fputcsv($fp, (array) $row);
		}

		fclose($fp);
	}

}