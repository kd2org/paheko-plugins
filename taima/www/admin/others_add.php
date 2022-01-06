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

$csrf_key = 'add_task';
$selected_user = null;
$user = qg('id_user');

if ($user) {
	$user = (new Membres)->get((int)$user);

	if (!$user) {
		throw new UserException('Membre inconnu');
	}

	$selected_user = [$user->id => $user->identite];
}

$form->runIf('save', function () {
	$entry = new Entry;
	$entry->setDateString(f('day'));
	$entry->user_id = @key(f('user'));
	$entry->importForm();
	$entry->setDuration(f('duration'));
	$entry->save();
}, $csrf_key, Utils::getSelfURI(['ok' => 1]));

$tasks = ['' => '--'] + Tracking::listTasks();
$now = new \DateTime;

$tpl->assign(compact('tasks', 'csrf_key', 'now', 'selected_user'));

$tpl->display(\Garradin\PLUGIN_ROOT . '/templates/others_add.tpl');
