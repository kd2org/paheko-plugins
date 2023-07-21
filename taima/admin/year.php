<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;

require_once __DIR__ . '/_inc.php';

$user_id = $session->getUser()->id;
$weeks = Tracking::listUserWeeks($user_id);

$tpl->assign(compact('weeks'));

$tpl->display(\Paheko\PLUGIN_ROOT . '/templates/year.tpl');
