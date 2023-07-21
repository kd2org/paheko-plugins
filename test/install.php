<?php

use Paheko\Plugin\Test\Test;

$plugin->registerSignal('home.button', [Test::class, 'homeButton']);
