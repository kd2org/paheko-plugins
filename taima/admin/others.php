<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;
use Paheko\Plugin\Taima\Entities\Entry;
use Paheko\Users\Users;
use Paheko\Utils;
use Paheko\UserException;

use function Paheko\{f, qg};

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

require_once __DIR__ . '/_inc.php';

$user = qg('id_user');
$list = null;
$selected_user = null;

if ($user) {
	$user = Users::get((int)$user);

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

$tpl->display(\Paheko\PLUGIN_ROOT . '/templates/others.tpl');
