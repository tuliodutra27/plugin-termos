<?php

define('PLUGIN_RADIOS_VERSION', '1.0.0');

function plugin_version_radios() {
    return [
        'name'           => 'Radios',
        'version'        => PLUGIN_RADIOS_VERSION,
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

function plugin_radios_check_prerequisites() {
    return true;
}

function plugin_radios_check_config() {
    return true;
}

function plugin_init_radios() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['radios'] = true;

    // Adiciona item ao menu Ativos
    $PLUGIN_HOOKS['menu_toadd']['radios'] = ['assets' => 'PluginRadiosRadio'];

    // Define as classes do plugin
    Plugin::registerClass('PluginRadiosMenu');
    Plugin::registerClass('PluginRadiosRadio');
}