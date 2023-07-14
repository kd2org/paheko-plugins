<?php

namespace Garradin\Plugin\Taima;

use Garradin\Plugin\Taima\Entities\Entry;
use Garradin\Plugin\Taima\Entities\Task;
use Garradin\Plugin\Taima\Tracking;

use Garradin\Users\DynamicFields;
use Garradin\DB;
use Garradin\Utils;
use Garradin\UserException;
use KD2\DB\EntityManager as EM;

use function Garradin\{f, qg};

use DateTime;

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

$csrf_key = 'plugin_taima_import';
$url = Utils::plugin_url(['query' => '', 'file' => 'import.php']);

$add = null;
$links = null;
$tasks = [null => '--'] + DB::getInstance()->getAssoc('SELECT id, label FROM plugin_taima_tasks;');
$json = $session->get('taima_import_json');

if (isset($_GET['cancel'])) {
	$json = null;
	$session->set('taima_import_json', $json);
	$session->save();
}

$form->runIf('load', function () use (&$session) {
	$file = $_FILES['json'] ?? null;

	if (empty($file['size']) || empty($file['tmp_name']) || empty($file['name'])) {
		throw new UserException('Erreur à l\'envoi du fichier');
	}

	$json = json_decode(@file_get_contents($file['tmp_name']), true);

	if (empty($json) || !is_array($json)) {
		throw new UserException('Fichier JSON invalide');
	}

	$required = ['Heures, en décimal', 'Catégorie', 'Bénévole', 'Date', 'Niveau', 'Projet', 'Titre', 'Description'];
	$required = array_flip($required);

	foreach ($json as $l => $row) {
		if (count(array_intersect_key($required, $row)) != count($required)) {
			throw new UserException(sprintf('Ligne %d: un des champs est manquant.', $l+1));
		}
	}

	$session->set('taima_import_json', $json);
	$session->save();
}, $csrf_key, $url);

$links = f('links');

$form->runIf($json !== null && !$links, function () use ($json, &$links, $tasks) {
	$links = [];

	foreach ($json as $l => $row) {
		if ($row['Projet'] && !isset($links[$row['Projet']])) {
			$links[$row['Projet']] = array_search(trim((string) $row['Projet']), $tasks);
		}
		elseif ($row['Niveau'] && !isset($links[$row['Niveau']])) {
			$links[$row['Niveau']] = array_search(trim((string) $row['Niveau']), $tasks);
		}
		elseif ($row['Catégorie'] && !isset($links[$row['Catégorie']])) {
			$links[$row['Catégorie']] = array_search(trim((string) $row['Catégorie']), $tasks);
		}
	}

	ksort($links);
});

$form->runIf(f('preview') && $json && count($links), function () use ($json, &$add, &$links) {
	$add = [];

	$db = DB::getInstance();
	$id_field = DynamicFields::getNameFieldsSQL();

	foreach ($json as $l => $row) {
		$e = new Entry;
		$e->setDateString($row['Date']);
		$e->duration = intval($row['Heures, en décimal'] * 60);

		if ($id = $db->firstColumn(sprintf('SELECT id FROM users WHERE %s = ?;', $id_field), $row['Bénévole'])) {
			$e->user_id = $id;
		}

		if ($row['Projet'] && isset($links[$row['Projet']])) {
			$e->task_id = (int)$links[$row['Projet']];
		}
		elseif ($row['Niveau'] && isset($links[$row['Niveau']])) {
			$e->task_id = (int)$links[$row['Niveau']];
		}
		elseif ($row['Catégorie'] && isset($links[$row['Catégorie']])) {
			$e->task_id = (int)$links[$row['Catégorie']];
		}

		$e->notes = ($row['Titre'] ?? '') . "\n" . ($row['Description'] ?? '');
		$e->notes = trim($e->notes);

		if (empty($e->notes)) {
			$e->notes = null;
		}

		$add[] = ['entry' => $e] + $row;
	}
}, $csrf_key);

$form->runIf('save', function () use ($session) {
	$entries = Utils::array_transpose($_POST['entries'] ?? []);

	foreach ($entries as $entry) {
		$e = new Entry;
		$e->importForm($entry);
		$e->setDateString($entry['date']);
		$e->save();
	}

	$session->set('taima_import_json', null);
	$session->save();
}, $csrf_key, $url . '?ok');

$tpl->assign(compact('csrf_key', 'add', 'tasks', 'links'));

$tpl->register_modifier('taima_minutes', [Tracking::class, 'formatMinutes']);
$tpl->display(__DIR__ . '/../templates/import.tpl');
