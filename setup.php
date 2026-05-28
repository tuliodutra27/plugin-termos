<?php

define('PLUGIN_TERMOS_VERSION', '1.0.0');

function plugin_version_termos() {
    return [
        'name'           => 'termos',
        'version'        => PLUGIN_TERMOS_VERSION,
        'author'         => 'Diego, Luciano, Rafael e Tulio',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0'
            ]
        ]
    ];
}

function plugin_termos_check_prerequisites() {
    return true;
}

function plugin_termos_check_config() {
    return true;
}

function plugin_init_termos() {
    global $PLUGIN_HOOKS;
    
    $PLUGIN_HOOKS['csrf_compliant']['termos'] = true;
    
    // Adiciona item ao menu Ativos
    $PLUGIN_HOOKS['menu_toadd']['termos'] = ['assets' => 'PluginTermosMenu'];
    
    // Define as classes do plugin
    Plugin::registerClass('PluginTermosMenu');
}