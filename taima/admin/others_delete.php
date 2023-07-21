<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;
use Paheko\Plugin\Taima\Entities\Entry;
use Paheko\Membres;
use Paheko\Utils;
use Paheko\UserException;

use function Paheko\{f, qg};

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

require_once __DIR__ . '/_inc.php';

$csrf_key = 'remove_task';
$entry = Tracking::get((int)qg('id'));

if (!$entry) {
	throw new UserException('Tâche inconnue');
}

$form->runIf('delete', function ()  use ($entry) {
	$entry->delete();
}, $csrf_key, Utils::getSelfURI(['ok' => 1]));

$tpl->assign(compact('csrf_key'));

$tpl->display(\Paheko\PLUGIN_ROOT . '/templates/others_delete.tpl');
