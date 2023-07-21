<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Entities\Entry;
use Paheko\Plugin\Taima\Entities\Task;

use Paheko\Entities\Accounting\Transaction;
use Paheko\Entities\Accounting\Line;
use Paheko\Entities\Accounting\Year;
use Paheko\Accounting\Accounts;
use Paheko\Accounting\Transactions;

use Paheko\DB;
use Paheko\DynamicList;
use Paheko\Plugins;
use Paheko\Utils;
use Paheko\UserException;
use Paheko\Users\DynamicFields;
use Paheko\Users\Session;
use Paheko\UserTemplate\CommonFunctions;

use KD2\DB\EntityManager as EM;

use DateTime;

class Tracking
{
	const ANIMATED_ICON = '<svg width="%s" viewBox="0 0 22 22" class="taima-icon-%d" style="%s">
			<style>
				svg.taima-icon-%2$d { animation: taima-spinner 5s linear infinite; fill: %s; stroke: %4$s; vertical-align: middle; }
				@keyframes taima-spinner { to {transform: rotate(360deg);} }
			</style>
			<circle cx="11" cy="11" r="10" stroke-width="2" fill="none" />
			<path class="icon-timer-hand" d="M12.8 10.2L11 2l-1.8 8.2-.2.8c0 1 1 2 2 2s2-1 2-2c0-.3 0-.6-.2-.8z" />
		</svg>';

	const FIXED_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="%s" viewBox="0 0 22 22" id="img" fill="none" stroke="currentColor" >
		<circle cx="11" cy="11" r="10" stroke-width="2" />
		<path id="hand" d="M12.8 10.2L11 2l-1.8 8.2-.2.8c0 1 1 2 2 2s2-1 2-2c0-.3 0-.6-.2-.8z" fill="currentColor" />
	</svg>';

	static public function animatedIcon(string $size, string $color = '', string $style = ''): string
	{
		static $i = 1;
		return sprintf(self::ANIMATED_ICON, $size, $i++, $style, $color ?: 'rgb(var(--gSecondColor))');
	}

	static public function fixedIcon(string $size): string
	{
		return sprintf(self::FIXED_ICON, $size);
	}

	static public function homeButton(array $params, array &$buttons): void
	{
		$url = Plugins::getPrivateURL('taima');
		$running_timers = self::hasRunningTimers(Session::getUserId());

		$params = [
			'label' => $running_timers ? 'Suivi : chrono en cours' : 'Suivi du temps',
			'href' => $url,
		];

		if ($running_timers) {
			$params['icon_html'] = self::animatedIcon('100%', 'rgb(var(--gHoverLinkColor))');
		}
		else {
			$params['icon'] = $url . 'icon.svg';
		}

		$buttons['taima'] = CommonFunctions::linkbutton($params);
	}

	static public function menuItem(array $params, array &$list): void
	{
		$icon = '';

		if (self::hasRunningTimers(Session::getUserId())) {
			$icon = self::animatedIcon(16, '', 'float: right');
		}

		$list['plugin_taima'] = sprintf('<a href="%sp/taima/">Suivi du temps%s</a>', \Paheko\ADMIN_URL, $icon);
	}

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

	static public function hasRunningTimers(int $user_id): bool
	{
		return DB::getInstance()->test(Entry::TABLE, 'user_id = ? AND timer_started IS NOT NULL', $user_id);
	}

	static public function listUserRunningTimers(?int $user_id, ?DateTime $except = null): array
	{
		if (!$user_id) {
			return [];
		}

		$params = [$user_id];
		$where = ['user_id = ?', 'timer_started IS NOT NULL'];

		if ($except) {
			$where[] = 'date != ?';
			$params[] = $except->format('Y-m-d');
		}

		$sql = sprintf('SELECT date FROM %s WHERE %s;', Entry::TABLE, implode(' AND ', $where));

		return DB::getInstance()->get($sql, ...$params);
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
				'select' => DynamicFields::getNameFieldsSQL('u'),
			],
			'user_id' => [],
			'id' => ['select' => 'e.id'],
		];

		$tables = 'plugin_taima_entries e
			LEFT JOIN plugin_taima_tasks t ON t.id = e.task_id
			LEFT JOIN users u ON u.id = e.user_id';

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

		$id_field = DynamicFields::getNameFieldsSQL('u');
		$sql = 'SELECT e.*, t.label AS task_label, %s AS user_name, SUM(duration) AS duration, %s AS criteria
			FROM plugin_taima_entries e
			LEFT JOIN plugin_taima_tasks t ON t.id = e.task_id
			LEFT JOIN users u ON u.id = e.user_id
			GROUP BY %s
			ORDER BY %s, SUM(duration) DESC;';

		$sql = sprintf($sql, $id_field, $criteria, $group, $order);

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