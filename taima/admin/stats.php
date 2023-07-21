<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;

use function Paheko\{f, qg};

require_once __DIR__ . '/_inc.php';

$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);

$per_user = qg('per_user') !== null;
$grouping = qg('g') ?? 'week';
$per_week = Tracking::listPerInterval($grouping, $per_user);

$tpl->assign(compact('per_week', 'per_user', 'grouping'));

$tpl->display(__DIR__ . '/../templates/stats.tpl');
