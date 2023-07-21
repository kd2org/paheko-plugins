<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Products;

require __DIR__ . '/../_inc.php';

$product = Products::get((int) qg('id'));
$events_only = qg('events_only') !== null;
$history = $product->history($events_only);

$tpl->assign(compact('product', 'history', 'events_only'));

$tpl->display(PLUGIN_ROOT . '/templates/manage/products/history.tpl');
