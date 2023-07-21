<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\HelloAsso;

require_once __DIR__ . '/../../../../include/init.php';

HelloAsso::handleCallback();
