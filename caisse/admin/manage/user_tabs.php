<?php

namespace Paheko;

use Paheko\Plugin\Caisse\Tabs;

require __DIR__ . '/_inc.php';

$tpl->assign('tabs', Tabs::listForUser((string) qg('q')));

$tpl->display(PLUGIN_ROOT . '/templates/manage/user_tabs.tpl');
