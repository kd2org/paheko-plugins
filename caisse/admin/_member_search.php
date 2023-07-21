<?php

namespace Paheko;

use Paheko\Plugin\Caisse\Tabs;


$tpl->assign('list', Tabs::searchMember($_GET['q']));

$tpl->display(PLUGIN_ROOT . '/templates/_member_search.tpl');