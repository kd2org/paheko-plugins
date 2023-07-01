<?php

namespace Garradin;

use Garradin\Users\Session;
use Garradin\Plugin\Reservations\Reservations;

$session = Session::getInstance();
$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

if (null === qg('id')) {
	throw new UserException('Numéro de catégorie manquant');
}

$r = new Reservations;

$cat = $r->getCategory(qg('id'));

$csrf_key = 'config_bookings_'. $cat->id;

$form->runIf('delete', function () use ($r, $cat) {
	$r->deleteCategory($cat->id);
}, $csrf_key, utils::plugin_url(['file' => 'config.php']));

$form->runIf('save', function () use ($r, $cat) {
	if (f('has_field')) {
		$champ = f('field');
	}
	else {
		$champ = null;
	}

	$r->updateCategory($cat->id, f('nom'), f('description'), $champ);
}, $csrf_key, utils::plugin_url(['file' => 'config.php', 'query' => 'ok']));

$has_field = $cat->champ ? true : false;

$tpl->assign('ok', qg('saved') !== null);
$tpl->assign(compact('csrf_key', 'cat', 'has_field'));
$tpl->display(PLUGIN_ROOT . '/templates/admin/config_cat.tpl');
