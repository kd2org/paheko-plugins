<?php

namespace Paheko\Plugin\Taima;

use Paheko\Entities\Accounting\Account;

use Paheko\Plugin\Taima\Entities\Task;
use Paheko\Plugin\Taima\Tracking;

use Paheko\Utils;
use KD2\DB\EntityManager as EM;

use function Paheko\{f, qg};

use DateTime;

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

$csrf_key = 'plugin_taima_config';
$url = Utils::plugin_url(['query' => '', 'file' => 'config.php']);

$form->runIf('add', function () {
	$task = new Task;
	$task->importForm();
	$task->save();
}, $csrf_key, $url);

$form->runIf('edit', function () {
	$task = EM::findOneById(Task::class, (int) qg('edit'));
	$task->importForm();
	$task->save();
}, $csrf_key, $url);

$form->runIf('delete', function () {
	$task = EM::findOneById(Task::class, (int) qg('delete'));
	$task->delete();
}, $csrf_key, $url);


$tpl->assign(compact('csrf_key'));
$tpl->assign('account_targets', Account::TYPE_VOLUNTEERING_EXPENSE);

if (qg('edit')) {
	$task = EM::findOneById(Task::class, (int) qg('edit'));
	$tpl->assign(compact('task'));
	$tpl->display(__DIR__ . '/../templates/config_edit.tpl');
}
elseif (qg('delete')) {
	$task = EM::findOneById(Task::class, (int) qg('delete'));
	$tpl->assign('task', $task);
	$tpl->display(__DIR__ . '/../templates/config_delete.tpl');
}
else {
	$tpl->assign('tasks', EM::getInstance(Task::class)->all('SELECT * FROM @TABLE ORDER BY label;'));
	$tpl->display(__DIR__ . '/../templates/config.tpl');
}
