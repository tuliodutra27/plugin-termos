<?php

class PluginRadiosRadio extends CommonDBTM {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return _n('Rádio', 'Rádios', $nb, 'radios');
    }

    static function canView() {
        return Session::haveRight('config', READ);
    }

    static function canCreate() {
        return Session::haveRight('config', UPDATE);
    }

    static function canUpdate() {
        return Session::haveRight('config', UPDATE);
    }

    static function canDelete() {
        return Session::haveRight('config', UPDATE);
    }

    static function getTable($classname = null) {
        return 'glpi_radios';
    }

    static function getNameField() {
        return 'serial';
    }

    static function getMenuContent() {
        $menu = [
            'title' => self::getTypeName(2),
            'page'  => '/plugins/radios/front/radio.php',
            'icon'  => 'fa-solid fa-walkie-talkie',
        ];

        $menu['options']['list'] = [
            'title' => self::getTypeName(2),
            'page'  => '/plugins/radios/front/radio.php',
            'icon'  => 'ti ti-list',
        ];

        $menu['options']['novo'] = [
            'title' => __('Adicionar', 'radios'),
            'page'  => '/plugins/radios/front/novo_radio.php',
            'icon'  => 'ti ti-plus',
        ];

        $menu['options']['historico'] = [
            'title' => __('Histórico', 'radios'),
            'page'  => '/plugins/radios/front/historico_radio.php',
            'icon'  => 'ti ti-history',
        ];

        return $menu;
    }

    function rawSearchOptions() {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => __('Informações Gerais', 'radios')];

        $tab[] = [
            'id'            => 1,
            'table'         => $this->getTable(),
            'field'         => 'serial',
            'name'          => __('Número de Série', 'radios'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
            'default'       => true,
        ];

        $tab[] = [
            'id'            => 2,
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
            'default'       => true,
        ];

        $tab[] = [
            'id'      => 3,
            'table'   => 'glpi_manufacturers',
            'field'   => 'name',
            'name'    => __('Fabricante', 'radios'),
            'datatype' => 'dropdown',
            'default' => true,
        ];

        $tab[] = [
            'id'      => 4,
            'table'   => $this->getTable(),
            'field'   => 'model',
            'name'    => __('Modelo', 'radios'),
            'datatype' => 'string',
            'default' => true,
        ];

        $tab[] = [
            'id'      => 5,
            'table'   => $this->getTable(),
            'field'   => 'otherserial',
            'name'    => __('Patrimônio', 'radios'),
            'datatype' => 'string',
            'default' => true,
        ];

        $tab[] = [
            'id'      => 6,
            'table'   => $this->getTable(),
            'field'   => 'chave_nf',
            'name'    => __('Chave NF', 'radios'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'      => 7,
            'table'   => 'glpi_states',
            'field'   => 'name',
            'name'    => __('Status', 'radios'),
            'datatype' => 'dropdown',
            'default' => true,
        ];

        $tab[] = [
            'id'      => 8,
            'table'   => 'glpi_groups',
            'field'   => 'completename',
            'name'    => __('Grupo', 'radios'),
            'datatype' => 'dropdown',
            'default' => true,
        ];

        $tab[] = [
            'id'      => 9,
            'table'   => 'glpi_users',
            'field'   => 'name',
            'name'    => __('Usuário', 'radios'),
            'datatype' => 'dropdown',
            'right'   => 'all',
            'default' => true,
        ];

        $tab[] = [
            'id'      => 10,
            'table'   => 'glpi_locations',
            'field'   => 'completename',
            'name'    => __('Localização', 'radios'),
            'datatype' => 'dropdown',
            'default' => true,
        ];

        $tab[] = [
            'id'            => 11,
            'table'         => $this->getTable(),
            'field'         => 'date_mod',
            'name'          => __('Última Atualização', 'radios'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 12,
            'table'         => $this->getTable(),
            'field'         => 'comment',
            'name'          => __('Comentários', 'radios'),
            'datatype'      => 'text',
            'massiveaction' => false,
        ];

        return $tab;
    }
}
