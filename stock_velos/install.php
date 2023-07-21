<?php

namespace Paheko;

use Paheko\Plugin\Stock_Velos\Velos;

$db = DB::getInstance();

// Renommage du plugin
if ($db->test('plugins', $db->where('id', 'rustine_velos')))
{
    $db->delete('plugins_signaux', 'plugin = ?', 'rustine_velos');
    $db->delete('plugins', 'id = ?', 'rustine_velos');
    $db->exec('ALTER TABLE plugin_rustine_velos RENAME TO plugin_stock_velos;');
}

// Création table
$db->exec(<<<EOF
    CREATE TABLE IF NOT EXISTS plugin_stock_velos
    (
        id INTEGER NOT NULL PRIMARY KEY, -- Numéro unique
        etiquette INTEGER, -- Numéro étiquette
        bicycode TEXT,

        source TEXT NOT NULL, -- Don, récup, etc.
        source_details TEXT, -- Détails de la source, comme nom du donneur, etc.

        type TEXT, -- Type de vélo : Ville, VTT, VTC, etc.
        roues TEXT, -- Taille des roues
        genre TEXT, -- homme, femme, mixte

        couleur TEXT, -- Une ou plusieurs couleurs
        modele TEXT, -- Marque / modèle

        prix FLOAT, -- Prix du vélo

        date_entree TEXT,
        etat_entree TEXT,

        date_sortie TEXT,
        raison_sortie TEXT,
        details_sortie TEXT,

        notes TEXT
    );

    CREATE INDEX IF NOT EXISTS prv_etiquette ON plugin_stock_velos (etiquette);
    CREATE INDEX IF NOT EXISTS prv_type ON plugin_stock_velos (type);
    CREATE INDEX IF NOT EXISTS prv_roues ON plugin_stock_velos (roues);
    CREATE INDEX IF NOT EXISTS prv_genre ON plugin_stock_velos (genre);
    CREATE INDEX IF NOT EXISTS prv_couleur ON plugin_stock_velos (couleur);
    CREATE INDEX IF NOT EXISTS prv_date_entree ON plugin_stock_velos (date_entree);
    CREATE INDEX IF NOT EXISTS prv_date_sortie ON plugin_stock_velos (date_sortie);
EOF
);
