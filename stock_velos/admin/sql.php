<?php

namespace Paheko;

require_once __DIR__ . '/_inc.php';

if (qg('f') || qg('q'))
{
	try {
	    $tpl->assign('result', $velos->searchSQL(qg('f'), qg('q')));
	}
	catch (UserException $e)
	{
		$tpl->assign('error', $e->getMessage());
	}
}

$tpl->assign('fields', qg('f') ?: '*');
$tpl->assign('query', qg('q') !== null ? qg('q') : 'WHERE date_entree > datetime("now", "-1 month")');
$tpl->assign('schema', $velos->getSchemaSQL());

$tpl->display(PLUGIN_ROOT . '/templates/sql.tpl');
