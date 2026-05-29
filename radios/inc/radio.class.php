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
            'page'  => self::getSearchURL(false),
            'icon'  => 'fa-solid fa-walkie-talkie',
            'links' => [
                'search' => self::getSearchURL(false),
                'add'    => self::getFormURL(false),
            ],
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

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item instanceof PluginRadiosRadio && $item->getID() > 0) {
            $count = countElementsInTable('glpi_radios_historico', ['radios_id' => $item->getID()]);
            return self::createTabEntry(__('Histórico', 'radios'), $count);
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item instanceof PluginRadiosRadio) {
            $item->showHistoricoTab();
        }
        return true;
    }

    function showHistoricoTab() {
        global $DB;
        $radio_id = intval($this->fields['id']);
        $start    = intval($_REQUEST['start'] ?? 0);
        $limit    = intval($_SESSION['glpilist_limit'] ?? 20);

        $total = countElementsInTable('glpi_radios_historico', ['radios_id' => $radio_id]);

        echo "<div class='spaced'>";

        // Export button
        $serial = $DB->escape($this->fields['serial'] ?? '');
        echo "<div class='d-flex justify-content-end mb-2'>";
        echo "<a class='btn btn-outline-secondary btn-sm' href='/plugins/radios/front/historico_radio.php?export=csv&serial=" . htmlspecialchars($serial) . "'>";
        echo "<i class='ti ti-download'></i>&nbsp;" . __('Exportar', 'radios') . "</a>";
        echo "</div>";

        Html::printAjaxPager(__('Histórico', 'radios'), $start, $total);

        $result = $DB->query(
            "SELECT h.id,
                    h.data_movimentacao,
                    CONCAT(IFNULL(t.firstname,''), ' ', IFNULL(t.realname,'')) AS tecnico_nome,
                    s.name  AS state_name,
                    g.completename AS group_name,
                    CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,'')) AS usuario_nome,
                    l.name  AS location_name
             FROM glpi_radios_historico h
             LEFT JOIN glpi_users t ON h.tecnico_alterou_id = t.id
             LEFT JOIN glpi_states s ON h.states_id = s.id
             LEFT JOIN glpi_groups g ON h.groups_id = g.id
             LEFT JOIN glpi_users u ON h.users_id = u.id
             LEFT JOIN glpi_locations l ON h.locations_id = l.id
             WHERE h.radios_id = $radio_id
             ORDER BY h.data_movimentacao DESC
             LIMIT " . intval($start) . ", " . intval($limit)
        );

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_2'>";
        echo "<th>" . __('ID')                    . "</th>";
        echo "<th>" . __('Data', 'radios')         . "</th>";
        echo "<th>" . __('Técnico', 'radios')      . "</th>";
        echo "<th>" . __('Status', 'radios')       . "</th>";
        echo "<th>" . __('Grupo', 'radios')        . "</th>";
        echo "<th>" . __('Usuário', 'radios')      . "</th>";
        echo "<th>" . __('Localização', 'radios')  . "</th>";
        echo "</tr>";

        if ($result && $DB->numrows($result) > 0) {
            $i = 0;
            while ($row = $DB->fetchAssoc($result)) {
                $class = ($i % 2 === 0) ? 'tab_bg_1' : 'tab_bg_3';
                echo "<tr class='$class'>";
                echo "<td>" . intval($row['id']) . "</td>";
                echo "<td>" . Html::convDateTime($row['data_movimentacao']) . "</td>";
                echo "<td>" . Html::entities_deep(trim($row['tecnico_nome'])) . "</td>";
                echo "<td>" . Html::entities_deep($row['state_name']  ?? '-') . "</td>";
                echo "<td>" . Html::entities_deep($row['group_name']  ?? '-') . "</td>";
                echo "<td>" . Html::entities_deep(trim($row['usuario_nome'])) . "</td>";
                echo "<td>" . Html::entities_deep($row['location_name'] ?? '-') . "</td>";
                echo "</tr>";
                $i++;
            }
        } else {
            echo "<tr class='tab_bg_1'><td colspan='7' class='center'>";
            echo __('Nenhum histórico encontrado.', 'radios');
            echo "</td></tr>";
        }

        echo "</table>";

        Html::printAjaxPager(__('Histórico', 'radios'), $start, $total);
        echo "</div>";
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

        // col-4/col-8 in each col-sm-6 = 16.7% label / 33.3% input per column
        // col-2/col-10 in col-12 = same 16.7% label position across full width
        echo "<div class='row'>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'><span class='required'>*</span>&nbsp;" . __('Número de Série', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        echo Html::input('serial', ['value' => $this->fields['serial'] ?? '']);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'>" . __('Status', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        State::dropdown(['value' => $this->fields['states_id'] ?? 0, 'name' => 'states_id']);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'><span class='required'>*</span>&nbsp;" . __('Fabricante', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        Manufacturer::dropdown(['value' => $this->fields['manufacturers_id'] ?? 0, 'name' => 'manufacturers_id']);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'>" . __('Localização', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        Location::dropdown(['value' => $this->fields['locations_id'] ?? 0, 'name' => 'locations_id']);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'>" . __('Modelo', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        echo Html::input('model', ['value' => $this->fields['model'] ?? '']);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'>" . __('Grupo', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        Group::dropdown([
            'value'  => $this->fields['groups_id'] ?? 0,
            'name'   => 'groups_id',
            'entity' => $this->fields['entities_id'] ?? 0,
        ]);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'>" . __('Patrimônio', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        echo Html::input('otherserial', ['value' => $this->fields['otherserial'] ?? '']);
        echo "</div></div>";

        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-4 text-end'>" . __('Usuário', 'radios') . "</label>";
        echo "<div class='col-8 field-container'>";
        User::dropdown([
            'value'  => $this->fields['users_id'] ?? 0,
            'name'   => 'users_id',
            'right'  => 'all',
            'entity' => $this->fields['entities_id'] ?? 0,
        ]);
        echo "</div></div>";

        echo "<div class='form-field row col-12 mb-2'>";
        echo "<label class='col-form-label col-2 text-end'>" . __('Chave da Nota Fiscal', 'radios') . "</label>";
        echo "<div class='col-10 field-container'>";
        echo Html::input('chave_nf', ['value' => $this->fields['chave_nf'] ?? '', 'maxlength' => 44]);
        echo "</div></div>";

        echo "<div class='form-field row col-12 mb-2'>";
        echo "<label class='col-form-label col-2 text-end'>" . __('Comentários', 'radios') . "</label>";
        echo "<div class='col-10 field-container'>";
        echo Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'rows' => 5, 'display' => false]);
        echo "</div></div>";

        echo "</div>"; // close wrapper row

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
