<?php

namespace Paheko;

require_once __DIR__ . '/_inc.php';

$fields = array(
    'etiquette'      =>  'Étiquette',
    'couleur'        =>  'Couleur',
    'modele'         =>  'Marque et modèle',
    'source_details' =>  'Détails sur la source',
    'details_sortie' =>  'Détails sur la sortie',
    'raison_sortie'  =>  'Raison de sortie',
    'notes'          =>  'Notes et remarques',
    'bicycode'       =>  'Bicycode',
);

if (qg('f') && !array_key_exists(qg('f'), $fields))
{
    $_GET['f'] = '';
}

if (qg('q') && qg('f'))
{
    $tpl->assign('liste', $velos->search(qg('f'), qg('q')));
}

$tpl->assign('fields', $fields);

$tpl->assign('current_field', qg('f') ?: 'modele');
$tpl->assign('query', qg('q'));

$tpl->display(PLUGIN_ROOT . '/templates/recherche.tpl');
