<?php

namespace Garradin\Plugin\Test;

use Garradin\Plugins;
use Garradin\Users\Session;
use Garradin\UserTemplate\CommonFunctions;

class Test
{
	static public function homeButton(array $params, array &$buttons): void
	{
		$plugin = Plugins::get('test');

		// Désactiver l'affichage du bouton
		if (empty($plugin->config->display_button)) {
			return;
		}

		// On ajoute notre bouton sur la page d'accueil
		$buttons['test'] = CommonFunctions::linkbutton([
			'label' => 'Test !',
			'icon' => Plugins::getPrivateURL('test', 'icon.svg'),
			'href' => Plugins::getPrivateURL('test'),
		]);
	}
}