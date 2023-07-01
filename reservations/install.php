<?php

namespace Garradin;

$db = DB::getInstance();

// Création table
$db->exec(<<<EOF
	CREATE TABLE IF NOT EXISTS plugin_reservations_categories
	(
		id INTEGER NOT NULL PRIMARY KEY,
		nom TEXT NOT NULL,
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

	CREATE UNIQUE INDEX IF NOT EXISTS prc_jour_heure ON plugin_reservations_creneaux (categorie, jour, heure);

	CREATE TABLE IF NOT EXISTS plugin_reservations_personnes
	(
		id INTEGER NOT NULL PRIMARY KEY,
		creneau INTEGER NOT NULL REFERENCES plugin_reservations_creneaux (id) ON DELETE CASCADE,
		date TEXT NOT NULL,
		nom NULL,
		champ NULL
	);

	-- Index unique sur une valeur nulle est impossible
	CREATE UNIQUE INDEX IF NOT EXISTS prp_reservation_nom ON plugin_reservations_personnes (creneau, date, nom);
EOF
);
