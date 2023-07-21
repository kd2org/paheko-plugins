<?php

namespace Paheko;

use Paheko\Plugin\Stock_Velos\Velos;

require_once __DIR__ . '/_inc.php';

$velo = Velos::get(qg('id'));

if (!$velo)
    throw new UserException('Ce vélo n\'existe pas !');

if (!empty($velo->date_sortie))
    throw new UserException('Ce vélo ne peut être racheté.');

$tpl->assign('velo', $velo);
$tpl->assign('prix', qg('prix'));

$tpl->display(PLUGIN_ROOT . '/templates/rachat_ok.tpl');
