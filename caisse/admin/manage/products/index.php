<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Products;

require __DIR__ . '/../_inc.php';

$tpl->assign('list', Products::listByCategory(false));

$tpl->display(PLUGIN_ROOT . '/templates/manage/products/index.tpl');
