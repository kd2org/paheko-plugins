<?php

namespace Paheko;

$db = DB::getInstance();

// Suppression table
$db->exec('DROP TABLE IF EXISTS plugin_stock_velos;');