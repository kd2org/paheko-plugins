<?php

namespace Garradin;

use Garradin\Plugin\Ouvertures\Ouvertures;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = 'config_plugin_' . $plugin->id();

$form->runIf('save', function () use ($plugin) {
	$slots = Utils::array_transpose(f('slots'));
	$days = [];

	foreach ($slots as $i => $slot) {
		$day = $slot['frequency'] . ' ' . $slot['day'];
		$day = trim($day);

		if (!strtotime($day)) {
			throw new UserException(sprintf('Ligne %d: le sélecteur de jour %s est invalide', $i+1, $day));
		}

		$open = sprintf('%02d:%02d', $slot['open_hour'], $slot['open_minutes']);
		$close = sprintf('%02d:%02d', $slot['close_hour'], $slot['close_minutes']);

		if (!preg_match('/^(2[0-3]|[01][0-9]):([0-5][0-9])/', $open)
			|| !preg_match('/^(2[0-3]|[01][0-9]):([0-5][0-9])/', $close))
		{
			throw new UserException(sprintf('Ligne %d: format d\'heures invalide pour %s', $i + 1, $day));
		}

		$days[] = compact('day', 'open', 'close');
	}

	$closed_nb = f('closed') ? count(f('closed')['day_start']): 0;
	$closed_list = [];

	for ($i = 0; $i < $closed_nb; $i++)
	{
		$closed = [
			sprintf('%s %02d', f('closed')['month_start'][$i], f('closed')['day_start'][$i]),
			sprintf('%s %02d', f('closed')['month_end'][$i], f('closed')['day_end'][$i]),
		];

		if (!strtotime($closed[0]) || !strtotime($closed[1]))
		{
			throw new UserException('Format invalide pour jours de fermeture');
		}

		$closed_list[] = $closed;
	}

	$plugin->setConfig('open', $days);
	$plugin->setConfig('closed', $closed_list);
}, $csrf_key, utils::plugin_url(['file' => 'config.php', 'query' => 'saved']));

$tpl->register_function('html_opening_day_select', function ($params) {
	$value = $params['value'];

	if (strchr($value, ' '))
	{
		list($frequency, $day) = explode(' ', $value);
	}
	else
	{
		$frequency = '';
		$day = $value;
	}

	$out = '<select name="slots[frequency][]">';

	foreach (Ouvertures::$frequencies as $name => $label)
	{
		$out .= sprintf('<option value="%s"%s>%s</option>',
			htmlspecialchars($name),
			($name == $frequency ? ' selected="selected"' : ''),
			htmlspecialchars($label)
		);
	}

	$out .= '</select> ';

	$out .= '<select name="slots[day][]">';

	foreach (Ouvertures::$days as $name => $label)
	{
		$out .= sprintf('<option value="%s"%s>%s</option>',
			htmlspecialchars($name),
			($name == $day ? ' selected="selected"' : ''),
			htmlspecialchars($label)
		);
	}

	$out .= '</select> du mois';

	return $out;
});

$tpl->register_function('html_opening_hour_select', function ($params) {
	$hours = explode(':', $params['value']);

	$out = sprintf('<input type="number" name="slots[%s_hour][]" min="0"
		max="23" step="1" required="required" value="%02d" size="2" class="time" pattern="^\d{1,2}$" />',
		$params['name'],
		$hours[0]
	);

	$out .= ':';

	$out .= sprintf('<input type="number" name="slots[%s_minutes][]" min="0"
		max="59" step="1" required="required" value="%02d" size="2" class="time" pattern="^\d{1,2}$" />',
		$params['name'],
		$hours[1] ?? 0
	);

	return $out;
});

$tpl->register_function('html_closing_day_select', function ($params) {
	$start_end = $params['start_end'];

	list($month, $day) = explode(' ', $params['value']);

	static $months = [
		'january'   => 'janvier',
		'february'  => 'février',
		'march'     => 'mars',
		'april'     => 'avril',
		'may'       => 'mai',
		'june'      => 'juin',
		'july'      => 'juillet',
		'august'    => 'août',
		'september' => 'septembre',
		'october'   => 'octobre',
		'november'  => 'novembre',
		'december'  => 'décembre',
	];

	$out = sprintf('<input type="number" name="closed[day_%s][]" min="1" 
		max="31" step="1" required="required" value="%02d" class="time" pattern="^\d{1,2}$" /> ',
		$start_end,
		$day
	);

	$out .= sprintf('<select name="closed[month_%s][]">', $start_end);

	foreach ($months as $name => $label)
	{
		$out .= sprintf('<option value="%s"%s>%s</option>',
			htmlspecialchars($name),
			($name == $month ? ' selected="selected"' : ''),
			htmlspecialchars($label)
		);
	}

	$out .= '</select>';

	return $out;
});

$tpl->assign('plugin_config', $plugin->getConfig());
$tpl->assign('example', file_get_contents($plugin->path() . DIRECTORY_SEPARATOR . 'example.skel'));

$tpl->display(PLUGIN_ROOT . '/templates/config.tpl');
