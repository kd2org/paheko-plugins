<?php

namespace Garradin\Plugin\Taima;

use Garradin\Plugin\Taima\Entities\Entry;
use Garradin\Plugin\Taima\Entities\Task;

use Garradin\Entities\Accounting\Transaction;
use Garradin\Entities\Accounting\Line;
use Garradin\Entities\Accounting\Year;
use Garradin\Accounting\Accounts;
use Garradin\Accounting\Transactions;

use Garradin\Config;
use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;
use Garradin\UserException;
use KD2\DB\EntityManager as EM;

use DateTime;

class Tracking
{
	static public function get(int $id)
	{
		return EM::findOneById(Entry::class, $id);
	}

	static public function listUserEntries(DateTime $day, int $user_id)
	{
		$sql = sprintf('SELECT e.*, t.label AS task_label,
			CASE WHEN e.timer_started IS NOT NULL
				THEN IFNULL(e.duration, 0) + (strftime(\'%%s\', \'now\') - e.timer_started) / 60
				ELSE e.duration
			END AS timer_running
			FROM %s e LEFT JOIN %s t ON t.id = e.task_id WHERE date = ? AND user_id = ? ORDER BY id;', Entry::TABLE, Task::TABLE);
		return DB::getInstance()->get($sql, $day->format('Y-m-d'), $user_id);
	}

	static public function autoStopRunningTimers()
	{
		$max = 13*60+37; // 13h37
		$db = DB::getInstance();
		$db->exec(sprintf('UPDATE %s
			SET timer_started = NULL,
				duration = IFNULL(duration, 0) + %d
			WHERE timer_started IS NOT NULL
				AND (strftime(\'%%s\', \'now\') - timer_started) > %2$d*60;', Entry::TABLE, $max));
	}

	static public function listUserWeeks(int $user_id)
	{
		$sql = sprintf('SELECT year, week, SUM(duration) AS duration, COUNT(id) AS entries,
			date(date, \'weekday 0\', \'-6 day\') AS first,
			date(date, \'weekday 0\') AS last
			FROM %s WHERE user_id = ? GROUP BY year, week ORDER BY year DESC, week DESC;', Entry::TABLE);
		return DB::getInstance()->get($sql, $user_id);
	}

	static public function listTasks()
	{
		return DB::getInstance()->getAssoc(sprintf('SELECT id, label FROM %s ORDER BY label COLLATE U_NOCASE;', Task::TABLE));
	}

	static public function listUserRunningTimers(DateTime $except, int $user_id)
	{
		return DB::getInstance()->get(sprintf('SELECT date FROM %s
			WHERE date != ? AND user_id = ? AND timer_started IS NOT NULL;', Entry::TABLE), $except->format('Y-m-d'), $user_id);
	}

	static public function listUserWeekDays(int $year, int $week, int $user_id)
	{
		$weekdays = [];

		$weekday = new DateTime;
		$weekday->setISODate($year, $week);

		$db = DB::getInstance();

		$sql = sprintf('SELECT strftime(\'%%w\', date) - 1 AS weekday,
			SUM(CASE WHEN timer_started IS NOT NULL
				THEN IFNULL(duration, 0) + (strftime(\'%%s\', \'now\') - timer_started) / 60
				ELSE duration
			END) AS total,
			COUNT(timer_started) AS timers
			FROM %s WHERE year = ? AND week = ? AND user_id = ?
			GROUP BY weekday ORDER BY weekday;', Entry::TABLE);

		$filled_days = $db->getGrouped($sql, [$year, $week, $user_id]);

		// SQLite has Sunday as first day of week
		if (isset($filled_days[-1])) {
			$filled_days[6] = $filled_days[-1];
		}

		for ($i = 0; $i < 7; $i++) {
			$weekdays[] = (object) [
				'day'     => clone $weekday,
				'minutes' => array_key_exists($i, $filled_days) ? $filled_days[$i]->total : 0,
				'timers'  => array_key_exists($i, $filled_days) ? $filled_days[$i]->timers : 0,
				'duration' => array_key_exists($i, $filled_days) ? $filled_days[$i]->total : 0,
			];

			$weekday->modify('+1 day');
		}

		return $weekdays;
	}

	static public function getList(?int $id_user = null, ?int $except = null): DynamicList
	{
		$identity = Config::getInstance()->get('champ_identite');
		$columns = [
			'task' => [
				'label' => 'Tâche',
				'select' => 't.label',
				'order' => 't.label COLLATE U_NOCASE %s',
			],
			'notes' => [
				'select' => 'e.notes',
			],
			'year' => [
				'label' => 'Année',
				'select' => 'e.year',
				'order' => 'e.year %s, e.week %1$s',
			],
			'week' => [
				'label' => 'Semaine',
				'select' => 'e.week',
				'order' => 'e.year %s, e.week %1$s',
			],
			'date' => [
				'label' => 'Date',
				'select' => 'e.date',
			],
			'duration' => [
				'label' => 'Durée',
				'select' => 'e.duration',
			],
			'user_name' => [
				'label' => 'Nom',
				'select' => 'm.' . $identity,
			],
			'user_id' => [],
			'id' => ['select' => 'e.id'],
		];

		$tables = 'plugin_taima_entries e
			LEFT JOIN plugin_taima_tasks t ON t.id = e.task_id
			LEFT JOIN membres m ON m.id = e.user_id';

		$conditions = '1';

		if ($except) {
			$conditions = 'e.user_id IS NULL OR e.user_id != ' . $except;
		}
		elseif ($id_user) {
			$conditions = 'e.user_id = ' . $id_user;
		}

		$list = new DynamicList($columns, $tables, $conditions);
		$list->orderBy('date', true);
		return $list;
	}

	static public function listPerInterval(string $grouping = 'week', bool $per_user = false)
	{
		if ($grouping == 'week') {
			$group = 'e.year, e.week';
			$order = 'e.year DESC, e.week DESC';
			$criteria = '(e.year || e.week)';
		}
		elseif ($grouping == 'year') {
			$group = 'e.year';
			$order = 'e.year DESC';
			$criteria = 'e.year';
		}
		elseif ($grouping == 'month') {
			$group = 'e.year, strftime(\'%m\', e.date)';
			$order = 'e.year DESC, strftime(\'%m\', e.date) DESC';
			$criteria = 'strftime(\'%Y%m\', e.date)';
		}

		if ($per_user) {
			$group .= ', e.user_id';
		}
		else {
			$group .= ', e.task_id';
		}

		$identity = Config::getInstance()->get('champ_identite');
		$sql = 'SELECT e.*, t.label AS task_label, m.%s AS user_name, SUM(duration) AS duration, %s AS criteria
			FROM plugin_taima_entries e
			LEFT JOIN plugin_taima_tasks t ON t.id = e.task_id
			LEFT JOIN membres m ON m.id = e.user_id
			GROUP BY %s
			ORDER BY %s, SUM(duration) DESC;';

		$sql = sprintf($sql, $identity, $criteria, $group, $order);

		$db = DB::getInstance();

		$item = $criteria = null;

		foreach ($db->iterate($sql) as $row) {
			if ($criteria != $row->criteria) {
				if ($item !== null) {
					$total = 0;
					foreach ($item['entries'] as $entry) {
						$total += $entry->duration;
					}

					$item['entries'][] = (object) ['task_label' => 'Total', 'duration' => $total];
					yield $item;
				}

				$criteria = $row->criteria;
				$item = (array)$row;
				$item['entries'] = [];
			}

			$item['entries'][] = $row;
		}

		if ($item !== null) {
			$total = 0;
			foreach ($item['entries'] as $entry) {
				$total += $entry->duration;
			}

			$item['entries'][] = (object) ['task_label' => 'Total', 'duration' => $total];
			yield $item;
		}
	}

	static public function getFinancialReport(Year $year, DateTime $start, DateTime $end)
	{
		$sql = 'SELECT
				t.label, SUM(e.duration) / 60 AS hours, SUM(e.duration) / 60 * t.value AS total, t.value AS value,
				t.account AS account_code, a.label AS account_label, a.id AS id_account
			FROM plugin_taima_entries e
			INNER JOIN plugin_taima_tasks t ON t.id = e.task_id
			LEFT JOIN acc_accounts a ON a.id_chart = ? AND a.code = t.account
			WHERE t.value IS NOT NULL AND t.account IS NOT NULL
			AND e.date >= ? AND e.date <= ?
			GROUP BY t.id;';
		return DB::getInstance()->get($sql, $year->id_chart, $start, $end);
	}

	static public function createReport(Year $year, DateTime $start, DateTime $end, int $id_creator): Transaction
	{
		$date = new \DateTime;

		if ($date > $year->end_date) {
			$date = clone $year->end_date;
		}
		elseif ($date < $year->start_date) {
			$date = clone $year->start_date;
		}

		$id_account = (new Accounts($year->id_chart))->getIdFromCode('875');

		if (!$id_account) {
			throw new UserException('Le compte 875 n\'existe pas au plan comptable, merci de le créer');
		}

		$t = Transactions::create([
			'date' => $date,
			'label' => 'Valorisation du bénévolat',
			'notes' => 'Écriture créée par Tāima, extension de suivi du temps',
			'type' => Transaction::TYPE_ADVANCED,
			'id_year' => $year->id(),
			'reference' => 'VALORISATION-TAIMA',
		]);

		$t->id_creator = $id_creator;

		$report = self::getFinancialReport($year, $start, $end);

		foreach ($report as $row) {
			if (!$row->id_account) {
				continue;
			}

			$line = new Line;
			$line->debit = $row->total;
			$line->id_account = $row->id_account;
			$line->label = sprintf('%s (%d heures à %s / h)', $row->label, $row->hours, Utils::money_format($row->value));

			$t->addLine($line);
		}

		$sum = $t->getLinesDebitSum();

		if (!$sum) {
			throw new UserException('Rien ne peut être valorisé : peut-être que des codes de compte sont invalides ?');
		}

		$line = new Line;
		$line->credit = $sum;
		$line->id_account = $id_account;
		$line->label = 'Temps bénévole';
		$t->addLine($line);

		return $t;
	}

	static public function formatMinutes(?int $minutes): string
	{
		if (!$minutes) {
			return '0:00';
		}

		$hours = floor($minutes / 60);
		$minutes -= $hours * 60;

		return sprintf('%d:%02d', $hours, $minutes);
	}
}