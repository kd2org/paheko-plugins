<?php

namespace Paheko;

require_once __DIR__ . '/_inc.php';

$list = $velos->listVelosHistorique();
$list->loadFromQueryString();

$tpl->assign('list', $list);
$tpl->assign('total', $list->count());

$tpl->display(PLUGIN_ROOT . '/templates/historique.tpl');
