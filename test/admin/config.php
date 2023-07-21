<?php

namespace Paheko;

// $session et $plugin sont déjà fournis par Paheko

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = 'config_plugin_' . $plugin->id();

$form->runIf(
    'save', // condition d'exécution : qu'un élément nommé "save" soit présent dans le POST
    function () use ($plugin) {
        // Fonction appelée si la condition est remplie
        $plugin->setConfigProperty('display_button', (bool) f('display_button'));
        $plugin->save();
    },
    $csrf_key, // Clé anti-CSRF
    utils::plugin_url() // URL de redirection en cas de succès
);

$tpl->assign(compact('csrf_key'));

$tpl->display(PLUGIN_ROOT . '/templates/config.tpl');
