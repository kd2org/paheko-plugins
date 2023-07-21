<?php
namespace Paheko;

use Paheko\Plugin\HelloAsso\Entities\PahekoSearchOverride as SE;
use Paheko\Plugin\HelloAsso\PahekoSearchOverride as Search;

require __DIR__ . '/_inc.php';

$query = trim((string) (qg('q') ?? f('q')));

$list = null;

// Simple search
if ($query !== '') {
	$list = Search::quick(SE::TARGET_FEE, $query);
}

$tpl->assign(compact('query', 'list'));

$tpl->display(PLUGIN_ROOT . '/templates/_fee_selector.tpl');
