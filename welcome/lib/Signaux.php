<?php

namespace Paheko\Plugin\Welcome;

use Paheko\Template;

class Signaux
{
	static public function banner(array $params, &$return)
	{
		$return = Template::getInstance()->fetch($params['plugin_root'] . '/templates/banner.tpl');
	}
}
