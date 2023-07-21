<?php

namespace Paheko;

use Paheko\Plugin\Stock_Velos\Velos;

require_once __DIR__ . '/_inc.php';

if (qg('etiquette'))
{
    $id = $velos->getIdFromEtiquette(qg('etiquette'));
}
else
{
	$id = qg('id');
}

if (!$id)
    throw new UserException('Impossible de trouver le vélo indiqué');

$velo = Velos::get($id);

if (!$velo)
    throw new UserException('Ce vélo n\'existe pas !');

$tpl->assign('velo', $velo);

$tpl->display(PLUGIN_ROOT . '/templates/fiche.tpl');
