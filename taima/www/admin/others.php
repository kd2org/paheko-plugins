<?php

namespace Garradin\Plugin\Taima;

use Garradin\Plugin\Taima\Tracking;
use Garradin\Plugin\Taima\Entities\Entry;
use Garradin\Membres;
use Garradin\Utils;
use Garradin\UserException;

use function Garradin\{f, qg};

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

require_once __DIR__ . '/_inc.php';

$user = qg('id_user');
$list = null;
$selected_user = null;

if ($user) {
	$user = (new Membres)->get((int)$user);

	if (!$user) {
		throw new UserException('Membre inconnu');
	}

	$list = Tracking::getList($user->id);
}
else {
	$list = Tracking::getList(null, $session->getUser()->id);
}

$list->loadFromQueryString();

$tpl->assign(compact('user', 'list', 'selected_user'));

$tpl->display(\Garradin\PLUGIN_ROOT . '/templates/others.tpl');
