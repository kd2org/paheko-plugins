<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\HelloAsso;

$session->requireAccess($session::SECTION_ACCOUNTING, $session::ACCESS_READ);

$ha = HelloAsso::getInstance();

if (!$ha->isConfigured()) {
	Utils::redirect(PLUGIN_ADMIN_URL . 'config.php');
}
