<?php

namespace Paheko;

$plugin->registerSignal('files.move', 'Paheko\Plugin\Git_Documents\GitDocuments::sync');
$plugin->registerSignal('files.delete', 'Paheko\Plugin\Git_Documents\GitDocuments::sync');
$plugin->registerSignal('files.store', 'Paheko\Plugin\Git_Documents\GitDocuments::sync');
$plugin->registerSignal('files.mkdir', 'Paheko\Plugin\Git_Documents\GitDocuments::sync');
