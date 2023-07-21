<?php

namespace Paheko;

use Paheko\Plugin\Stock_Velos\Velos;

require_once __DIR__ . '/_inc.php';

$id = qg('id');

$velo = Velos::get($id);

if (!$velo)
    throw new UserException('Ce vélo n\'existe pas !');

if (empty($velo->date_sortie) || ($velo->raison_sortie != 'Vendu' && $velo->raison_sortie != 'Vendu en bourse'))
    throw new UserException('Impossible de racheter un vélo qui n\'est pas vendu !');

if ($velo->get_buyback()) {
    throw new UserException('Le vélo a déjà été racheté !');
}

$csrf_key = 'rachat_velo_'.$velo->id;

$form->runIf('buy', function () use ($velo) {
    $new = $velo->buyback((int) f('etiquette'), f('etat'), (float) f('prix'));

    utils::redirect(utils::plugin_url([
        'file' => 'rachat_ok.php',
        'query' => 'id=' . $new->id .
            '&prix=' . rawurlencode(floatval(f('prix')))
        ]
    ));
}, $csrf_key);

$tpl->assign('velo', $velo);
$tpl->assign('prix', round($velo->prix / 3));
$tpl->assign('libre', $velos->getEtiquetteLibre());

$tpl->display(PLUGIN_ROOT . '/templates/rachat.tpl');
