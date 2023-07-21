<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;
use Paheko\Utils;

use DateTime;

use function Paheko\{f, qg};


$user_id = $session->getUser()->id;

function taima_url($day = null)
{
	return Utils::plugin_url($day ? ['query' => 'day=' . (Utils::get_datetime($day))->format('Y-m-d')] : []);
}

$day = $today = new DateTime;
$day->setTime(0, 0, 0);

if (qg('day')) {
	$day = DateTime::createFromFormat('!Y-m-d', qg('day'));
}

$tpl->register_modifier('taima_date', function ($date, string $format) {
	$date = Utils::get_datetime($date);
	return \IntlDateFormatter::formatObject($date, $format, 'fr_FR');
});

$tpl->register_modifier('taima_minutes', [Tracking::class, 'formatMinutes']);
$tpl->register_modifier('taima_url', __NAMESPACE__ . '\taima_url');
$tpl->assign('plugin_css', ['style.css']);
