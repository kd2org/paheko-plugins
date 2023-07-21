<?php

namespace Paheko;

use Paheko\Plugin\Stock_Velos\Velos;

require_once __DIR__ . '/_inc.php';

if (!qg('id'))
    throw new UserException('Impossible de trouver le vélo indiqué');

$id = (int) qg('id');

$velo = Velos::get($id);

if (!$velo)
    throw new UserException('Ce vélo n\'existe pas !');

if (!empty($velo->date_sortie))
    throw new UserException('Impossible de vendre un vélo qui n\'est plus en stock !');

$csrf_key = 'vente_velo_'.$velo->id;

$form->runIf('sell', function () use ($velo) {
    $velo->sell(f('adherent'), f('prix'));

    utils::redirect(utils::plugin_url([
        'file' => 'vente_ok.php',
        'query' => 'id=' . (int)$velo->id .
            '&etat=' . rawurlencode(f('etat'))
        ]
    ));
}, $csrf_key);

$tpl->assign('prix', $velo->prix ?: qg('prix'));
$tpl->assign('etat', qg('etat') ?: 'En bon état de marche');

$tpl->assign(compact('velo', 'csrf_key'));

$tpl->display(PLUGIN_ROOT . '/templates/vente.tpl');
