<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Stock;

require __DIR__ . '/../_inc.php';

$tpl->assign('list', Stock::listEvents());
$tpl->assign('value_list', Stock::listValue());

$tpl->display(PLUGIN_ROOT . '/templates/manage/stock/index.tpl');
