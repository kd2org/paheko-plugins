<?php

namespace Garradin;

use Garradin\Plugin\Reservations\Reservations;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$r = new Reservations;

if (null === qg('id')) {
	throw new UserException('Numéro de catégorie manquant');
}

$cat = $r->getCategory(qg('id'));

if (!$cat) {
	throw new UserException('Catégorie inconnue');
}

$tpl->assign('ok', qg('saved') !== null);

if (f('save'))
{
	$form->check('config_plugin_' . $plugin->id(), [
		'slot'   => 'array',
	]);

	if (!$form->hasErrors())
	{
		$i = 0;
		$ids = [];
		$slots = f('slot') ?: [];

		foreach ($slots as $id => $props) {
			$i++;
			$props = (object)$props;
			$props->repetition = !empty($props->repetition);

			if (!isset($props->repetition, $props->jour, $props->heure, $props->maximum)) {
				$form->addError('Erreur à la ligne ' . $i);
				continue;
			}

			try {
				if ('_' === substr($id, 0, 1)) {
					$ids[] = $r->createSlot($cat->id, $props->jour, $props->heure, $props->repetition, (int)$props->maximum);
				}
				else {
					$r->updateSlot((int)$id, $props->jour, $props->heure, $props->repetition, (int)$props->maximum);
					$ids[] = (int)$id;
				}
			}
			catch (UserException $e) {
				$form->addError(sprintf('Ligne %d: %s', $i, $e->getMessage()));
			}
		}

		$r->deleteMissingSlots($cat->id, $ids);

		if (!$form->hasErrors()) {
			utils::redirect(utils::plugin_url(['file' => 'config_slots.php', 'query' => sprintf('id=%d&saved', $cat->id)]));
		}
	}
}

$slots = $r->listSlots($cat->id);

if (!count($slots)) {
	$slots = [];
	$slots[] = (object)['id' => '_1', 'jour' => '', 'heure' => '', 'maximum' => '', 'repetition' => 0];
}

$tpl->assign('slots', $slots);
$tpl->assign('cat', $cat);

$tpl->display(PLUGIN_ROOT . '/templates/admin/config_slots.tpl');
