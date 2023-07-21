<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\HelloAsso;
use Paheko\Plugin\HelloAsso\Forms;

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

$list = Forms::list();
$tpl->assign('list', $list);

$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
