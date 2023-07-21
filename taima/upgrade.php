<?php

namespace Paheko;

use Paheko\Plugin\Taima\Tracking;

$old_version = $plugin->oldVersion();
$db = DB::getInstance();

// Fix year for dates that are in the last week of previous year
// eg. 2022-01-01 is in week 52 of year 2021
if (version_compare($old_version, '0.4', '<')) {
	$dates = ['2022-01-01', '2022-01-02', '2021-01-01', '2021-01-02', '2021-01-03'];
	$db->exec(sprintf('UPDATE plugin_taima_entries SET year = year - 1 WHERE %s;', $db->where('date', $dates)));

	$db->exec('ALTER TABLE plugin_taima_tasks ADD COLUMN value INTEGER NULL;');
	$db->exec('ALTER TABLE plugin_taima_tasks ADD COLUMN account TEXT NULL;');
}

if (version_compare($old_version, '0.4.1', '<')) {
	$db->exec('ALTER TABLE plugin_taima_entries RENAME TO plugin_taima_entries_old;
		CREATE TABLE IF NOT EXISTS plugin_taima_entries (
		id INTEGER NOT NULL PRIMARY KEY,
		user_id INTEGER NULL REFERENCES users (id) ON DELETE CASCADE,
		task_id INTEGER NULL REFERENCES plugin_taima_tasks(id) ON DELETE SET NULL,
		year INTEGER NOT NULL CHECK (LENGTH(year) = 4),
		week INTEGER NOT NULL CHECK (week >= 1 AND week <= 53),
		date TEXT NOT NULL,
		notes TEXT,
		duration INTEGER NULL, -- duration of timer, in minutes
		timer_started INTEGER NULL -- date time for the start of the timer, is null if no timer is running
	);
	INSERT INTO plugin_taima_entries SELECT * FROM plugin_taima_entries_old;
	DROP TABLE plugin_taima_entries_old;');
}

// Change ON DELETE CASCADE to ON DELETE SET NULL
if (version_compare($old_version, '0.6.0', '<')) {
	$db->beginSchemaUpdate();
	$db->exec('ALTER TABLE plugin_taima_entries RENAME TO plugin_taima_entries_old;

	CREATE TABLE IF NOT EXISTS plugin_taima_entries (
		id INTEGER NOT NULL PRIMARY KEY,
		user_id INTEGER NULL REFERENCES users (id) ON DELETE SET NULL,
		task_id INTEGER NULL REFERENCES plugin_taima_tasks(id) ON DELETE SET NULL,
		year INTEGER NOT NULL CHECK (LENGTH(year) = 4),
		week INTEGER NOT NULL CHECK (week >= 1 AND week <= 53),
		date TEXT NOT NULL,
		notes TEXT,
		duration INTEGER NULL, -- duration of timer, in minutes
		timer_started INTEGER NULL -- date time for the start of the timer, is null if no timer is running
	);
	INSERT INTO plugin_taima_entries SELECT * FROM plugin_taima_entries_old;
	DROP TABLE plugin_taima_entries_old;');

	$db->commitSchemaUpdate();

}

$plugin->registerSignal('menu.item', [Tracking::class, 'menuItem']);
$plugin->registerSignal('home.button', [Tracking::class, 'homeButton']);
