<?php

namespace Garradin;

use Garradin\Plugin\Reservations\Reservations;

if ($plugin->needUpgrade()) {
	$plugin->upgrade();
}

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$r = new Reservations;

if (f('add') && $form->check('config_plugin_' . $plugin->id())) {
	$r->addCategory(f('nom'));
	utils::redirect(utils::plugin_url(['file' => 'config.php']));
}

$tpl->assign('ok', qg('saved') !== null);
$tpl->assign('categories', $r->listCategories());
$tpl->display(PLUGIN_ROOT . '/templates/admin/config.tpl');
