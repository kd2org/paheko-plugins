<?php

namespace Garradin;

use Garradin\Users\DynamicList;

$old_version = $plugin->oldVersion();

$db = DB::getInstance();

if (version_compare($old_version, '0.5', '<')) {
	$db->toggleForeignKeys(false);

	$db->exec(sprintf('
	ALTER TABLE plugin_reservations_creneaux RENAME TO plugin_reservations_creneaux_old;
	ALTER TABLE plugin_reservations_personnes RENAME TO plugin_reservations_personnes_old;
	DROP INDEX prp_reservation_nom;

	CREATE TABLE IF NOT EXISTS plugin_reservations_categories
	(
		id INTEGER NOT NULL PRIMARY KEY,
		nom TEXT NOT NULL,
		introduction TEXT NULL,
		description TEXT NULL,
		champ TEXT NULL
	);

	CREATE TABLE IF NOT EXISTS plugin_reservations_creneaux
	(
		id INTEGER NOT NULL PRIMARY KEY, -- Numéro unique
		categorie INTEGER NOT NULL REFERENCES plugin_reservations_categories(id) ON DELETE CASCADE,
		jour TEXT NOT NULL,
		heure TEXT NOT NULL,
		repetition TEXT NOT NULL,
		maximum INT NOT NULL
	);

	CREATE TABLE IF NOT EXISTS plugin_reservations_personnes
	(
		id INTEGER NOT NULL PRIMARY KEY,
		creneau INTEGER NOT NULL REFERENCES plugin_reservations_creneaux (id) ON DELETE CASCADE,
		date TEXT NOT NULL,
		nom NULL,
		champ NULL
	);

	CREATE UNIQUE INDEX IF NOT EXISTS prp_reservation_nom ON plugin_reservations_personnes (creneau, date, nom, champ);

	INSERT INTO plugin_reservations_categories (nom) VALUES (\'Réservations\');
	INSERT INTO plugin_reservations_creneaux SELECT id, 1, jour, heure, repetition, maximum FROM plugin_reservations_creneaux_old;

	INSERT INTO plugin_reservations_personnes SELECT id, creneau, date, CASE WHEN id_membre IS NOT NULL THEN (SELECT %s FROM users WHERE id = id_membre) ELSE nom END, NULL FROM plugin_reservations_personnes_old;

	DROP TABLE plugin_reservations_creneaux_old;
	DROP TABLE plugin_reservations_personnes_old;', DynamicFields::getNameFieldsSQL()));

	// Remise en place du texte
	$db->update('plugin_reservations_categories', ['description' => $plugin->getConfig('text')], 'id = 1');

	// Suppression config
	$db->update('plugins', ['config' => null], 'id = :id', ['id' => $plugin->id]);
}

if (version_compare($old_version, '0.5.1', '<')) {
	$db->exec('DROP INDEX IF EXISTS prc_jour_heure;
		CREATE UNIQUE INDEX IF NOT EXISTS prc_jour_heure ON plugin_reservations_creneaux (categorie, jour, heure);');
}

if (version_compare($old_version, '0.7.0', '<')) {
	$db->beginSchemaUpdate();
	$db->exec('
		DROP TABLE IF EXISTS plugin_reservations_categories_old;
		ALTER TABLE plugin_reservations_categories RENAME TO plugin_reservations_categories_old;
		CREATE TABLE IF NOT EXISTS plugin_reservations_categories
		(
			id INTEGER NOT NULL PRIMARY KEY,
			nom TEXT NOT NULL,
			description TEXT NULL,
			champ TEXT NULL
		);
		INSERT INTO plugin_reservations_categories SELECT id, nom, description, champ FROM plugin_reservations_categories_old;
		DROP TABLE plugin_reservations_categories_old;
	');
	$db->commitSchemaUpdate();
}
