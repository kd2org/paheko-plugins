<?php

namespace Garradin;

use Garradin\Users\Session;

use Garradin\Plugin\Reservations\Reservations;

$plugin->upgradeIfRequired();

$session = Session::getInstance();
$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = 'config_bookings';

$r = new Reservations;

$form->runIf('add', function () use ($r) {
	$r->addCategory(f('nom'));
}, $csrf_key, utils::plugin_url(['file' => 'config.php']));

$tpl->assign('ok', qg('saved') !== null);
$tpl->assign('categories', $r->listCategories());
$tpl->assign(compact('csrf_key'));
$tpl->display(PLUGIN_ROOT . '/templates/admin/config.tpl');
