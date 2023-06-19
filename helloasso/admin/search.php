<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\SearchResults;

require __DIR__ . '/_inc.php';

if (!$searched_text = qg('q')) {
	throw new UserException('Aucun texte saisi pour la recherche.');
}

$list = SearchResults::list($searched_text);

$tpl->assign('list', $list);

$tpl->display(PLUGIN_ROOT . '/templates/search.tpl');
