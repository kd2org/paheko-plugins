<?php

namespace Paheko;

require_once __DIR__ . '/_inc.php';

$tpl->assign('velos', $velos->listVelosToSell());

$tpl->display(PLUGIN_ROOT . '/templates/vente_tout.tpl');
