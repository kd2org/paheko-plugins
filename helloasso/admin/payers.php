<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Payers;

require __DIR__ . '/_inc.php';

$payers = Payers::list();

$tpl->assign('list', $payers);
$tpl->display(PLUGIN_ROOT . '/templates/payers.tpl');