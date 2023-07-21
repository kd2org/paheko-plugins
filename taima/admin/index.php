<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;
use Paheko\Plugin\Taima\Entities\Entry;

use Paheko\Utils;

use function Paheko\{f, qg};

use DateTime;

require_once __DIR__ . '/_inc.php';

if ($plugin->needUpgrade()) {
	$plugin->upgrade();
}

Tracking::autoStopRunningTimers();

$csrf_key = 'plugin_taima_sheet';

$form->runIf('add', function () use ($day, $user_id) {
	$entry = new Entry;
	$entry->setDate($day);
	$entry->user_id = $user_id;
	$entry->importForm();
	$entry->setDuration(f('duration'));
	$entry->save();
}, $csrf_key, taima_url($day));

$form->runIf('edit', function () {
	if (!is_array(f('edit')) || !count(f('edit'))) {
		throw new \LogicException('Not an array');
	}

	$id = key(f('edit'));
	$entry = Tracking::get((int) $id);

	if (!$entry) {
		return;
	}

	$entry->importForm();
	$entry->setDuration(f('duration'));
	$entry->save();
}, $csrf_key, taima_url($day));

$form->runIf('delete', function () {
	if (!is_array(f('delete')) || !count(f('delete'))) {
		throw new \LogicException('Not an array');
	}

	$id = key(f('delete'));
	$entry = Tracking::get((int) $id);

	if (!$entry) {
		return;
	}

	$entry->delete();
}, $csrf_key, taima_url($day));

if (qg('start')) {
	$entry = Tracking::get((int) qg('start'));

	if (!$entry) {
		return;
	}
	$entry->start();
	$entry->save();
	Utils::redirect(taima_url($entry->date));
}
elseif (qg('stop')) {
	$entry = Tracking::get((int) qg('stop'));

	if (!$entry) {
		return;
	}
	$entry->stop();
	$entry->save();
	Utils::redirect(taima_url($entry->date));
}

$prev_url = taima_url((clone $day)->modify('-1 week'));
$next_url = taima_url((clone $day)->modify('+1 week'));
$today_url = taima_url($today);

$year = (int) $day->format('o');
$week = (int) $day->format('W');

$weekdays = Tracking::listUserWeekDays($year, $week, $user_id);
$week_total = 0;

// Add URL
array_walk($weekdays, function (&$row) use (&$week_total) {
	$row->url = taima_url($row->day);
	$row->minutes_formatted = Tracking::formatMinutes($row->minutes ?? 0);
	$week_total += $row->minutes;
});

$week_total = Tracking::formatMinutes($week_total);

$entries = Tracking::listUserEntries($day, $user_id);

$tasks = ['' => '--'] + Tracking::listTasks();

$is_today = $day->format('Ymd') == $today->format('Ymd');

$running_timers = Tracking::listUserRunningTimers($user_id, $day);
$animated_icon = Tracking::animatedIcon(32);
$fixed_icon = Tracking::fixedIcon(24);

$tpl->assign(compact('is_today', 'tasks', 'entries', 'week_total', 'weekdays', 'prev_url', 'next_url', 'today_url', 'day', 'year', 'week', 'csrf_key', 'running_timers', 'animated_icon', 'fixed_icon'));

$tpl->display(__DIR__ . '/../templates/index.tpl');
