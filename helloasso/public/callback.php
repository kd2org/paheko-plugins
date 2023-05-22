<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\HelloAsso;

require_once __DIR__ . '/../../../../include/init.php';

HelloAsso::handleCallback();
