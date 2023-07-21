<?php

namespace Paheko\Plugin;

use Paheko\Plugin\Welcome\Signaux;

// enregistrer signal pour message page d'accueil
$plugin->registerSignal('home.banner', [Signaux::class, 'banner']);
