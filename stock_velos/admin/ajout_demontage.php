<?php

namespace Garradin;

require_once __DIR__ . '/_inc.php';

$form->runIf('save', function () use ($velos) {
	$velos->addVelosDemontes(f('nb'), f('source'), f('source_details'));
}, 'ajout_velos', utils::plugin_url());

$tpl->assign('sources', $velos->listSources());

$tpl->display(PLUGIN_ROOT . '/templates/ajout_demontage.tpl');
