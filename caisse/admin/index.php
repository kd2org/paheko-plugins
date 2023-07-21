<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Sessions;

if ($plugin->needUpgrade()) {
	$plugin->upgrade();
}

require __DIR__ . '/_inc.php';

$list = Sessions::list();
$list->loadFromQueryString();

$tpl->assign('current_pos_session', Sessions::getCurrentId());
$tpl->assign('list', $list);
$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
