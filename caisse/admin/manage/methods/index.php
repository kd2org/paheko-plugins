<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Methods;

require __DIR__ . '/../_inc.php';

$tpl->assign('list', Methods::list());

$tpl->display(PLUGIN_ROOT . '/templates/manage/methods/index.tpl');
