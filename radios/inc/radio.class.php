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
        return 'glpi_plugin_radios_radios';
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
            'page'  => '/plugins/radios/front/radio.form.php',
            'icon'  => 'ti ti-plus',
        ];

        $menu['options']['historico'] = [
            'title' => __('Histórico', 'radios'),
            'page'  => '/plugins/radios/front/historico_radio.php',
            'icon'  => 'ti ti-history',
        ];

        return $menu;
    }

    function prepareInputForAdd($input) {
        if (empty($input['serial'])) {
            Session::addMessageAfterRedirect(__('Número de série é obrigatório.', 'radios'), false, ERROR);
            return false;
        }
        return $input;
    }

    function prepareInputForUpdate($input) {
        if (isset($input['serial']) && empty($input['serial'])) {
            Session::addMessageAfterRedirect(__('Número de série é obrigatório.', 'radios'), false, ERROR);
            return false;
        }
        return $input;
    }

    function post_addItem() {
        $this->insertHistoricoEntry();
    }

    function post_updateItem($history = true) {
        $this->insertHistoricoEntry();
    }

    private function insertHistoricoEntry() {
        global $DB;
        $DB->query(
            "INSERT INTO `glpi_radios_historico`
                (`radios_id`, `serial`, `model`, `manufacturers_id`, `patrimonio`,
                 `states_id`, `groups_id`, `users_id`, `locations_id`,
                 `tecnico_alterou_id`, `data_movimentacao`, `entities_id`)
             VALUES (
                 "  . intval($this->fields['id'])                              . ",
                 '" . $DB->escape($this->fields['serial']        ?? '') . "',
                 '" . $DB->escape($this->fields['model']         ?? '') . "',
                 "  . intval($this->fields['manufacturers_id']   ?? 0)         . ",
                 '" . $DB->escape($this->fields['otherserial']   ?? '') . "',
                 "  . intval($this->fields['states_id']          ?? 0)         . ",
                 "  . intval($this->fields['groups_id']          ?? 0)         . ",
                 "  . intval($this->fields['users_id']           ?? 0)         . ",
                 "  . intval($this->fields['locations_id']       ?? 0)         . ",
                 "  . Session::getLoginUserID()                                . ",
                 NOW(),
                 "  . intval($this->fields['entities_id']        ?? 0)         . "
             )"
        );
    }

    function showForm($ID, $options = []) {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // --- Row 1: Número de Série + Status ---
        echo "<tr class='tab_bg_1'>";
        echo "<td><span class='required'>*</span>&nbsp;" . __('Número de Série', 'radios') . "</td>";
        echo "<td>" . Html::input('serial', ['value' => $this->fields['serial'] ?? '']) . "</td>";
        echo "<td>" . __('Status', 'radios') . "</td>";
        echo "<td>";
        State::dropdown(['value' => $this->fields['states_id'] ?? 0, 'name' => 'states_id']);
        echo "</td>";
        echo "</tr>";

        // --- Row 2: Fabricante + Localização ---
        echo "<tr class='tab_bg_1'>";
        echo "<td><span class='required'>*</span>&nbsp;" . __('Fabricante', 'radios') . "</td>";
        echo "<td>";
        Manufacturer::dropdown(['value' => $this->fields['manufacturers_id'] ?? 0, 'name' => 'manufacturers_id']);
        echo "</td>";
        echo "<td>" . __('Localização', 'radios') . "</td>";
        echo "<td>";
        Location::dropdown(['value' => $this->fields['locations_id'] ?? 0, 'name' => 'locations_id']);
        echo "</td>";
        echo "</tr>";

        // --- Row 3: Modelo + Grupo ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Modelo', 'radios') . "</td>";
        echo "<td>" . Html::input('model', ['value' => $this->fields['model'] ?? '']) . "</td>";
        echo "<td>" . __('Grupo', 'radios') . "</td>";
        echo "<td>";
        Group::dropdown([
            'value'  => $this->fields['groups_id'] ?? 0,
            'name'   => 'groups_id',
            'entity' => $this->fields['entities_id'] ?? 0,
        ]);
        echo "</td>";
        echo "</tr>";

        // --- Row 4: Patrimônio + Usuário ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Patrimônio', 'radios') . "</td>";
        echo "<td>" . Html::input('otherserial', ['value' => $this->fields['otherserial'] ?? '']) . "</td>";
        echo "<td>" . __('Usuário', 'radios') . "</td>";
        echo "<td>";
        User::dropdown([
            'value'  => $this->fields['users_id'] ?? 0,
            'name'   => 'users_id',
            'right'  => 'all',
            'entity' => $this->fields['entities_id'] ?? 0,
        ]);
        echo "</td>";
        echo "</tr>";

        // --- Row 5: Chave da Nota Fiscal ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Chave da Nota Fiscal', 'radios') . "</td>";
        echo "<td colspan='3'>";
        echo Html::input('chave_nf', ['value' => $this->fields['chave_nf'] ?? '', 'maxlength' => 44, 'size' => 50]);
        echo "</td>";
        echo "</tr>";

        // --- Row 6: Comentários ---
        echo "<tr class='tab_bg_1'>";
        echo "<td class='top'>" . __('Comentários', 'radios') . "</td>";
        echo "<td colspan='3'>";
        echo Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'rows' => 5, 'display' => false]);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
        return true;
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
