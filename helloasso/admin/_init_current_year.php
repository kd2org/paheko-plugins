<?php

namespace Garradin\Plugin\HelloAsso;

use const Garradin\{ROOT, CURRENT_YEAR_ID};

if (HelloAsso::getInstance()->plugin()->getConfig()->accounting) {
	require_once ROOT . '/www/admin/acc/_inc.php';

	if (!CURRENT_YEAR_ID) {
		Utils::redirect(ADMIN_URL . 'acc/years/?msg=OPEN');
	}
	$tpl->assign('chart_id', (int)$current_year->id_chart);
}
else
	$tpl->assign('chart_id', null);