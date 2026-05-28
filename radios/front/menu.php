<?php

include ('../../../inc/includes.php');

// Verificar se está logado
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

// Controle de acesso básico
Session::checkRight("config", READ);

global $DB;

function buildRadioWhereConditions($DB) {
    $conditions = ["r.is_deleted = 0"];
    if (!empty($_GET['serial'])) {
        $conditions[] = "r.serial LIKE '%" . $DB->escape($_GET['serial']) . "%'";
    }
    if (!empty($_GET['manufacturer'])) {
        $conditions[] = "r.manufacturers_id = " . (int)$_GET['manufacturer'];
    }
    if (!empty($_GET['model'])) {
        $conditions[] = "r.model LIKE '%" . $DB->escape($_GET['model']) . "%'";
    }
    if (!empty($_GET['state'])) {
        $conditions[] = "r.states_id = " . (int)$_GET['state'];
    }
    if (!empty($_GET['group'])) {
        $conditions[] = "r.groups_id = " . (int)$_GET['group'];
    }
    if (!empty($_GET['user'])) {
        $conditions[] = "r.users_id = " . (int)$_GET['user'];
    }
    if (!empty($_GET['location'])) {
        $conditions[] = "r.locations_id = " . (int)$_GET['location'];
    }
    return $conditions;
}

// Processar exportação CSV
if (isset($_GET['export_csv'])) {
    Session::checkRight("config", READ);
    
    // Validar CSRF token
    if (!Session::validateCSRF($_GET)) {
        Session::addMessageAfterRedirect('Erro de segurança. Operação não autorizada.', true, ERROR);
        Html::redirect('menu.php');
        exit;
    }
    
    try {
        $where_conditions = buildRadioWhereConditions($DB);
        $where_clause = implode(' AND ', $where_conditions);
        
        // Ordenação para CSV
        $order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        
        // Query SQL para exportação (sem limite)
        $sql = "SELECT r.*, 
                       m.name AS manufacturer_name,
                       s.name AS state_name,
                       g.name AS group_name,
                       g.completename AS group_completename,
                       u.realname AS user_realname,
                       u.firstname AS user_firstname,
                       l.name AS location_name
                FROM glpi_radios r
                LEFT JOIN glpi_manufacturers m ON r.manufacturers_id = m.id
                LEFT JOIN glpi_states s ON r.states_id = s.id
                LEFT JOIN glpi_groups g ON r.groups_id = g.id
                LEFT JOIN glpi_users u ON r.users_id = u.id
                LEFT JOIN glpi_locations l ON r.locations_id = l.id
                WHERE $where_clause
                ORDER BY r.id $order";
        
        $result = $DB->query($sql);
        
        // Preparar arquivo CSV
        $filename = 'radios_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Headers para download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Abrir output
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8 (para Excel reconhecer acentos)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers do CSV em português
        $csv_headers = [
            'ID',
            'Fabricante',
            'Modelo',
            'Número de Série',
            'Patrimônio',
            'Chave NF',
            'Status',
            'Grupo',
            'Usuário',
            'Localização',
            'Comentário',
            'Data Criação',
            'Data Modificação'
        ];
        
        fputcsv($output, $csv_headers, ';');
        
        // Dados do CSV
        if ($result && $DB->numrows($result) > 0) {
            while ($row = $DB->fetchAssoc($result)) {
                // Preparar nome do grupo
                $group_display = '';
                if (!empty($row['group_completename'])) {
                    $group_display = $row['group_completename'];
                } elseif (!empty($row['group_name'])) {
                    $group_display = $row['group_name'];
                }
                
                // Preparar nome do usuário
                $user_display = trim(($row['user_firstname'] ?: '') . ' ' . ($row['user_realname'] ?: ''));
                
                $csv_row = [
                    $row['id'],
                    $row['manufacturer_name'] ?: '',
                    $row['model'] ?: '',
                    $row['serial'] ?: '',
                    $row['otherserial'] ?: '',
                    $row['chave_nf'] ?: '',
                    $row['state_name'] ?: '',
                    $group_display,
                    $user_display,
                    $row['location_name'] ?: '',
                    $row['comment'] ?: '',
                    $row['date_creation'] ?: '',
                    $row['date_mod'] ?: ''
                ];
                
                fputcsv($output, $csv_row, ';');
            }
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        Session::addMessageAfterRedirect('Erro ao exportar CSV: ' . $e->getMessage(), true, ERROR);
        Html::redirect('menu.php');
        exit;
    }
}


// Processar exclusão de rádio
if (isset($_POST['delete_id']) && isset($_POST['confirm_delete'])) {
    Session::checkRight("config", UPDATE);


    $delete_id = (int)$_POST['delete_id'];
    
    if ($delete_id > 0) {
        try {
            // Soft delete - marcar como deletado
            $sql = "UPDATE glpi_radios SET is_deleted = 1, date_mod = NOW() WHERE id = $delete_id";
            $result = $DB->query($sql);
            
            if ($result) {
                Session::addMessageAfterRedirect("Rádio excluído com sucesso!", true, INFO);
            } else {
                Session::addMessageAfterRedirect('Erro ao excluir o rádio', true, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
        }
    }
    
    Html::redirect('menu.php');
    exit;
}

// Processar edição em massa
if (isset($_POST['bulk_edit'])) {
    Session::checkRight("config", UPDATE);

    $ids = array_values(array_filter(array_map('intval', (array)($_POST['selected_ids'] ?? []))));

    if (empty($ids)) {
        Session::addMessageAfterRedirect('Nenhum rádio selecionado.', true, WARNING);
        Html::redirect('menu.php');
        exit;
    }

    $updates = [];
    if (!empty($_POST['bulk_states_id']))    $updates['states_id']    = intval($_POST['bulk_states_id']);
    if (!empty($_POST['bulk_groups_id']))    $updates['groups_id']    = intval($_POST['bulk_groups_id']);
    if (!empty($_POST['bulk_users_id']))     $updates['users_id']     = intval($_POST['bulk_users_id']);
    if (!empty($_POST['bulk_locations_id'])) $updates['locations_id'] = intval($_POST['bulk_locations_id']);

    if (empty($updates)) {
        Session::addMessageAfterRedirect('Selecione ao menos um campo para atualizar.', true, WARNING);
        Html::redirect('menu.php');
        exit;
    }

    $set_parts = [];
    foreach ($updates as $field => $value) {
        $set_parts[] = "`$field` = $value";
    }
    $set_parts[] = '`date_mod` = NOW()';
    $set_sql = implode(', ', $set_parts);

    $updated = 0;
    foreach ($ids as $radio_id) {
        $before = $DB->fetchAssoc($DB->query("SELECT * FROM glpi_radios WHERE id = $radio_id AND is_deleted = 0"));
        if (!$before) continue;

        if ($DB->query("UPDATE `glpi_radios` SET $set_sql WHERE `id` = $radio_id")) {
            $after = array_merge($before, $updates);
            $DB->query("INSERT INTO `glpi_radios_historico`
                (`radios_id`, `serial`, `model`, `manufacturers_id`, `patrimonio`,
                 `states_id`, `groups_id`, `users_id`, `locations_id`,
                 `tecnico_alterou_id`, `data_movimentacao`, `entities_id`)
                VALUES (
                    $radio_id,
                    '" . $DB->escape($after['serial']) . "',
                    '" . $DB->escape($after['model']) . "',
                    " . intval($after['manufacturers_id']) . ",
                    '" . $DB->escape($after['otherserial']) . "',
                    " . intval($after['states_id']) . ",
                    " . intval($after['groups_id']) . ",
                    " . intval($after['users_id']) . ",
                    " . intval($after['locations_id']) . ",
                    " . Session::getLoginUserID() . ",
                    NOW(),
                    " . intval($_SESSION['glpiactive_entity']) . "
                )");
            $updated++;
        }
    }

    Session::addMessageAfterRedirect("$updated rádio(s) atualizado(s) com sucesso!", true, INFO);
    Html::redirect('menu.php');
    exit;
}

Html::header(__('Sistema de Controle de Rádios', 'radios'), $_SERVER['PHP_SELF'], 'plugins', 'radios');

echo "<div class='center'>";
echo "<div style='width: 95%; margin: 20px auto;'>";

// Título principal
echo "<h1>".__('Sistema de Controle de Rádios', 'radios')."</h1>";
echo "<hr>";

// Botões de ação principal
echo "<div style='margin: 20px 0; text-align: center;'>";
echo "<a href='novo_radio.php' class='btn btn-success' style='text-decoration: none; padding: 10px 20px; background: #28a745; color: white; border-radius: 4px; margin-right: 10px;'>📻 Novo Rádio</a>";
echo "<a href='historico_radio.php' class='btn btn-info' style='text-decoration: none; padding: 10px 20px; background: #17a2b8; color: white; border-radius: 4px;'>📋 Histórico de Movimentações</a>";
echo "</div>";

// Formulário de filtros
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>🔍 Filtros de Pesquisa</h3>";
echo "<form method='GET' style='display: flex; flex-wrap: wrap; gap: 15px; align-items: end;'>";

// Filtro por número de série
echo "<div style='min-width: 150px;'>";
echo "<label for='serial'><strong>Número de Série:</strong></label>";
echo "<input type='text' name='serial' id='serial' value='".htmlspecialchars($_GET['serial'] ?? '')."' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "</div>";

// Filtro por fabricante/marca
echo "<div style='min-width: 150px;'>";
echo "<label for='manufacturer'><strong>Fabricante:</strong></label>";
echo "<select name='manufacturer' id='manufacturer' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "<option value=''>Todos</option>";

try {
    $manufacturers = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_manufacturers',
        'ORDER' => 'name'
    ]);
    foreach ($manufacturers as $manufacturer) {
        $selected = (isset($_GET['manufacturer']) && $_GET['manufacturer'] == $manufacturer['id']) ? 'selected' : '';
        echo "<option value='".intval($manufacturer['id'])."' $selected>".htmlspecialchars($manufacturer['name'])."</option>";
    }
} catch (Exception $e) {
    echo "<option value=''>Erro ao carregar</option>";
}
echo "</select>";
echo "</div>";

// Filtro por modelo
echo "<div style='min-width: 150px;'>";
echo "<label for='model'><strong>Modelo:</strong></label>";
echo "<input type='text' name='model' id='model' value='".htmlspecialchars($_GET['model'] ?? '')."' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "</div>";

// Filtro por status
echo "<div style='min-width: 150px;'>";
echo "<label for='state'><strong>Status:</strong></label>";
echo "<select name='state' id='state' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "<option value=''>Todos</option>";

try {
    $states = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_states',
        'ORDER' => 'name'
    ]);
    foreach ($states as $state) {
        $selected = (isset($_GET['state']) && $_GET['state'] == $state['id']) ? 'selected' : '';
        echo "<option value='".intval($state['id'])."' $selected>".htmlspecialchars($state['name'])."</option>";
    }
} catch (Exception $e) {
    echo "<option value=''>Erro ao carregar</option>";
}
echo "</select>";
echo "</div>";

// NOVO FILTRO: Grupo
echo "<div style='min-width: 150px;'>";
echo "<label for='group'><strong>Grupo:</strong></label>";
echo "<select name='group' id='group' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "<option value=''>Todos</option>";

try {
    // Buscar grupos da entidade atual ou entidade raiz (0)
    $entity_condition = [
        'OR' => [
            'entities_id' => $_SESSION['glpiactive_entity'],
            'entities_id' => 0,
            'is_recursive' => 1
        ]
    ];
    
    $groups = $DB->request([
        'SELECT' => ['id', 'name', 'completename'],
        'FROM' => 'glpi_groups',
        'WHERE' => $entity_condition,
        'ORDER' => 'completename'
    ]);
    
    foreach ($groups as $group) {
        $selected = (isset($_GET['group']) && $_GET['group'] == $group['id']) ? 'selected' : '';
        $group_name = !empty($group['completename']) ? $group['completename'] : $group['name'];
        echo "<option value='".intval($group['id'])."' $selected>".htmlspecialchars($group_name)."</option>";
    }
} catch (Exception $e) {
    echo "<option value=''>Erro ao carregar</option>";
}
echo "</select>";
echo "</div>";

// Filtro por usuário
echo "<div style='min-width: 150px;'>";
echo "<label for='user'><strong>Usuário:</strong></label>";
echo "<select name='user' id='user' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "<option value=''>Todos</option>";

try {
    $users = $DB->request([
        'SELECT' => ['id', 'realname', 'firstname'],
        'FROM' => 'glpi_users',
        'WHERE' => ['is_active' => 1],
        'ORDER' => 'realname'
    ]);
    foreach ($users as $user) {
        $selected = (isset($_GET['user']) && $_GET['user'] == $user['id']) ? 'selected' : '';
        $name = trim($user['firstname'] . ' ' . $user['realname']);
        echo "<option value='".intval($user['id'])."' $selected>".htmlspecialchars($name)."</option>";
    }
} catch (Exception $e) {
    echo "<option value=''>Erro ao carregar</option>";
}
echo "</select>";
echo "</div>";

// Filtro por localização
echo "<div style='min-width: 150px;'>";
echo "<label for='location'><strong>Localização:</strong></label>";
echo "<select name='location' id='location' style='width: 100%; padding: 5px; margin-top: 5px;'>";
echo "<option value=''>Todas</option>";

try {
    $locations = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_locations',
        'ORDER' => 'name'
    ]);
    foreach ($locations as $location) {
        $selected = (isset($_GET['location']) && $_GET['location'] == $location['id']) ? 'selected' : '';
        echo "<option value='".intval($location['id'])."' $selected>".htmlspecialchars($location['name'])."</option>";
    }
} catch (Exception $e) {
    echo "<option value=''>Erro ao carregar</option>";
}
echo "</select>";
echo "</div>";

// NOVO: Filtro de ordenação
echo "<div style='min-width: 150px;'>";
echo "<label for='order'><strong>Ordenação por ID:</strong></label>";
echo "<select name='order' id='order' style='width: 100%; padding: 5px; margin-top: 5px;'>";
$current_order = $_GET['order'] ?? 'desc';
echo "<option value='desc'" . ($current_order === 'desc' ? ' selected' : '') . ">Mais novo → Mais antigo</option>";
echo "<option value='asc'" . ($current_order === 'asc' ? ' selected' : '') . ">Mais antigo → Mais novo</option>";
echo "</select>";
echo "</div>";

// Botões do filtro
echo "<div style='display: flex; gap: 10px;'>";
echo "<input type='submit' value='🔍 Filtrar' style='padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;'>";
echo "<a href='menu.php' style='text-decoration: none; padding: 8px 15px; background: #6c757d; color: white; border-radius: 4px;'>🔄 Limpar</a>";
echo "</div>";

echo "</form>";
echo "</div>";

// Botões de ação adicionais - NOVO: Botão de exportar CSV
echo "<div style='margin: 20px 0; text-align: center;'>";
$csrf_token = Session::getNewCSRFToken();

// Manter parâmetros atuais para exportação
$export_params = [];
foreach (['serial', 'manufacturer', 'model', 'state', 'group', 'user', 'location', 'order'] as $param) {
    if (!empty($_GET[$param])) {
        $export_params[] = $param . '=' . urlencode($_GET[$param]);
    }
}
$export_params[] = '_glpi_csrf_token=' . $csrf_token;
$export_params[] = 'export_csv=1';
$export_string = implode('&', $export_params);

echo "<a href='?$export_string' style='text-decoration: none; padding: 10px 20px; background: #28a745; color: white; border-radius: 4px; margin-right: 10px;'>📊 Exportar CSV</a>";
echo "</div>";

// Lista de rádios
echo "<div class='spaced'>";
echo "<h3>📻 Lista de Rádios</h3>";

try {
    // Configuração da paginação
    $per_page = 100;
    $page = (int)($_GET['page'] ?? 1);
    $offset = ($page - 1) * $per_page;
    
    $where_conditions = buildRadioWhereConditions($DB);
    $where_clause = implode(' AND ', $where_conditions);
    
    // NOVA FUNCIONALIDADE: Ordenação
    $order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    // Query para contar total de registros
    $count_sql = "SELECT COUNT(*) as total 
                  FROM glpi_radios r 
                  LEFT JOIN glpi_manufacturers m ON r.manufacturers_id = m.id
                  LEFT JOIN glpi_states s ON r.states_id = s.id
                  LEFT JOIN glpi_groups g ON r.groups_id = g.id
                  LEFT JOIN glpi_users u ON r.users_id = u.id
                  LEFT JOIN glpi_locations l ON r.locations_id = l.id
                  WHERE $where_clause";
    
    $count_result = $DB->query($count_sql);
    $count_data = $DB->fetchAssoc($count_result);
    $total_records = $count_data['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Query SQL com JOINs, paginação e NOVA ordenação
    $sql = "SELECT r.*, 
                   m.name AS manufacturer_name,
                   s.name AS state_name,
                   g.name AS group_name,
                   g.completename AS group_completename,
                   u.realname AS user_realname,
                   u.firstname AS user_firstname,
                   l.name AS location_name
            FROM glpi_radios r
            LEFT JOIN glpi_manufacturers m ON r.manufacturers_id = m.id
            LEFT JOIN glpi_states s ON r.states_id = s.id
            LEFT JOIN glpi_groups g ON r.groups_id = g.id
            LEFT JOIN glpi_users u ON r.users_id = u.id
            LEFT JOIN glpi_locations l ON r.locations_id = l.id
            WHERE $where_clause
            ORDER BY r.id $order
            LIMIT $per_page OFFSET $offset";
    
    $result = $DB->query($sql);
    $radios = [];

    
    if ($result && $DB->numrows($result) > 0) {
        // Colocar resultados em array
        while ($row = $DB->fetchAssoc($result)) {
            $radios[] = $row;
        }
    }

    if (count($radios) > 0) {
        $bulk_csrf_token  = Session::getNewCSRFToken();
        $delete_csrf_token = Session::getNewCSRFToken();

        echo "<form id='bulk-form' method='POST' action=''>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='".htmlspecialchars($bulk_csrf_token)."'>";

        echo "<table class='tab_cadre_fixe' style='width: 100%;'>";
        echo "<tr class='tab_bg_2'>";
        echo "<th style='width:40px;'><input type='checkbox' id='select-all' title='Selecionar todos'></th>";
        echo "<th>ID</th>";
        echo "<th>Fabricante</th>";
        echo "<th>Modelo</th>";
        echo "<th>Série</th>";
        echo "<th>Patrimônio</th>";
        echo "<th>Status</th>";
        echo "<th>Grupo</th>";
        echo "<th>Usuário</th>";
        echo "<th>Localização</th>";
        echo "<th>Ações</th>";
        echo "</tr>";

        $i = 0;
        foreach ($radios as $radio) {
            $class = ($i % 2 == 0) ? 'tab_bg_1' : 'tab_bg_3';
            echo "<tr class='$class'>";
            echo "<td style='text-align:center;'><input type='checkbox' name='selected_ids[]' value='".intval($radio['id'])."' class='radio-cb'></td>";
            echo "<td>".Html::entities_deep($radio['id'])."</td>";
            echo "<td>".Html::entities_deep($radio['manufacturer_name'] ?: '-')."</td>";
            echo "<td>".Html::entities_deep($radio['model'] ?: '-')."</td>";
            echo "<td>".Html::entities_deep($radio['serial'] ?: '-')."</td>";
            echo "<td>".Html::entities_deep($radio['otherserial'] ?: '-')."</td>";
            echo "<td>".Html::entities_deep($radio['state_name'] ?: '-')."</td>";
            
            // Coluna Grupo - usar completename se disponível, senão usar name
            $group_display = '';
            if (!empty($radio['group_completename'])) {
                $group_display = $radio['group_completename'];
            } elseif (!empty($radio['group_name'])) {
                $group_display = $radio['group_name'];
            } else {
                $group_display = '-';
            }
            echo "<td>".Html::entities_deep($group_display)."</td>";
            
            echo "<td>".Html::entities_deep(trim(($radio['user_firstname'] ?: '') . ' ' . ($radio['user_realname'] ?: '')))."</td>";
            echo "<td>".Html::entities_deep($radio['location_name'] ?: '-')."</td>";
            
            echo "<td style='text-align: center; white-space:nowrap;'>";
            echo "<a href='editar_radio.php?id=".intval($radio['id'])."' style='color:#007bff;text-decoration:none;margin-right:5px;'>✏️ Editar</a>";
            echo " | ";
            echo "<a href='#' onclick='deleteRadio(".intval($radio['id']).")' style='color:#dc3545;text-decoration:none;'>🗑️ Excluir</a>";
            echo "</td>";
            
            echo "</tr>";
            $i++;
        }
        echo "</table>";

        // Painel de edição em massa
        echo "<div id='bulk-panel' style='background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:20px;margin-top:15px;'>";
        echo "<h4 style='margin-top:0;'>✏️ Edição em Massa — <span id='bulk-count'>0</span> rádio(s) selecionado(s)</h4>";
        echo "<div style='display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end;'>";

        // Status
        $states_bulk = ['' => '— sem alteração —'];
        $sr = $DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_states', 'ORDER' => 'name']);
        foreach ($sr as $s) $states_bulk[$s['id']] = $s['name'];
        echo "<div><label><strong>Status:</strong></label><br>";
        echo "<select name='bulk_states_id' style='padding:5px;'>";
        foreach ($states_bulk as $v => $l) echo "<option value='".htmlspecialchars($v)."'>".htmlspecialchars($l)."</option>";
        echo "</select></div>";

        // Grupo
        $groups_bulk = ['' => '— sem alteração —'];
        $entity_cond = ['OR' => ['entities_id' => intval($_SESSION['glpiactive_entity']), 'is_recursive' => 1]];
        $gr = $DB->request(['SELECT' => ['id','completename','name'], 'FROM' => 'glpi_groups', 'WHERE' => $entity_cond, 'ORDER' => 'completename']);
        foreach ($gr as $g) $groups_bulk[$g['id']] = $g['completename'] ?: $g['name'];
        echo "<div><label><strong>Grupo:</strong></label><br>";
        echo "<select name='bulk_groups_id' style='padding:5px;'>";
        foreach ($groups_bulk as $v => $l) echo "<option value='".htmlspecialchars($v)."'>".htmlspecialchars($l)."</option>";
        echo "</select></div>";

        // Usuário
        $users_bulk = ['' => '— sem alteração —'];
        $ur = $DB->request(['SELECT' => ['id','firstname','realname'], 'FROM' => 'glpi_users', 'WHERE' => ['is_active' => 1], 'ORDER' => 'firstname']);
        foreach ($ur as $u) $users_bulk[$u['id']] = trim($u['firstname'] . ' ' . $u['realname']);
        echo "<div><label><strong>Usuário:</strong></label><br>";
        echo "<select name='bulk_users_id' style='padding:5px;'>";
        foreach ($users_bulk as $v => $l) echo "<option value='".htmlspecialchars($v)."'>".htmlspecialchars($l)."</option>";
        echo "</select></div>";

        // Localização
        $locations_bulk = ['' => '— sem alteração —'];
        $lr = $DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_locations', 'ORDER' => 'name']);
        foreach ($lr as $l) $locations_bulk[$l['id']] = $l['name'];
        echo "<div><label><strong>Localização:</strong></label><br>";
        echo "<select name='bulk_locations_id' style='padding:5px;'>";
        foreach ($locations_bulk as $v => $lbl) echo "<option value='".htmlspecialchars($v)."'>".htmlspecialchars($lbl)."</option>";
        echo "</select></div>";

        echo "<div>";
        echo "<button type='submit' name='bulk_edit' value='1' id='bulk-submit' disabled
              onclick=\"return confirm('Aplicar alterações a ' + document.getElementById('bulk-count').textContent + ' rádio(s) selecionado(s)?')\"
              style='padding:8px 20px;background:#ffc107;border:none;border-radius:4px;cursor:pointer;font-weight:bold;'>✔ Aplicar</button>";
        echo "</div>";
        echo "</div>";
        echo "<small style='color:#6c757d;'>Apenas campos preenchidos serão alterados. Campos em branco são ignorados.</small>";
        echo "</div>";

        echo "</form>"; // fecha o bulk-form

        // Form oculto para exclusão individual
        echo "<form id='delete-form' method='POST' action=''>";
        echo "<input type='hidden' name='delete_id' id='delete-form-id' value=''>";
        echo "<input type='hidden' name='confirm_delete' value='1'>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='".htmlspecialchars($delete_csrf_token)."'>";
        echo "</form>";

        echo "<script>
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.radio-cb').forEach(cb => cb.checked = this.checked);
            updateBulkCount();
        });
        document.querySelectorAll('.radio-cb').forEach(cb => cb.addEventListener('change', updateBulkCount));
        function updateBulkCount() {
            const n = document.querySelectorAll('.radio-cb:checked').length;
            document.getElementById('bulk-count').textContent = n;
            document.getElementById('bulk-submit').disabled = n === 0;
            document.getElementById('select-all').indeterminate =
                n > 0 && n < document.querySelectorAll('.radio-cb').length;
        }
        function deleteRadio(id) {
            if (!confirm('Tem certeza que deseja excluir este rádio?')) return;
            document.getElementById('delete-form-id').value = id;
            document.getElementById('delete-form').submit();
        }
        </script>";

        // Navegação de paginação (mostrar apenas se houver mais de uma página)
        if ($total_pages > 1) {
            echo "<div style='text-align: center; margin: 20px 0;'>";
            
            // Manter parâmetros de filtro na paginação - INCLUINDO NOVA ORDENAÇÃO
            $filter_params = [];
            foreach (['serial', 'manufacturer', 'model', 'state', 'group', 'user', 'location', 'order'] as $param) {
                if (!empty($_GET[$param])) {
                    $filter_params[] = $param . '=' . urlencode($_GET[$param]);
                }
            }
            $filter_string = !empty($filter_params) ? '&' . implode('&', $filter_params) : '';
            
            echo "<div style='display: inline-flex; align-items: center; gap: 10px;'>";
            
            // Botão Primeira página
            if ($page > 1) {
                echo "<a href='?page=1$filter_string' style='padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>« Primeira</a>";
            }
            
            // Botão Anterior
            if ($page > 1) {
                $prev_page = $page - 1;
                echo "<a href='?page=$prev_page$filter_string' style='padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>‹ Anterior</a>";
            }
            
            // Páginas numeradas (mostrar até 5 páginas ao redor da atual)
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo "<span>...</span>";
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo "<span style='padding: 8px 12px; background: #28a745; color: white; border-radius: 4px; font-weight: bold;'>$i</span>";
                } else {
                    echo "<a href='?page=$i$filter_string' style='padding: 8px 12px; background: #f8f9fa; color: #007bff; text-decoration: none; border: 1px solid #dee2e6; border-radius: 4px;'>$i</a>";
                }
            }
            
            if ($end_page < $total_pages) {
                echo "<span>...</span>";
            }
            
            // Botão Próximo
            if ($page < $total_pages) {
                $next_page = $page + 1;
                echo "<a href='?page=$next_page$filter_string' style='padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>Próximo ›</a>";
            }
            
            // Botão Última página
            if ($page < $total_pages) {
                echo "<a href='?page=$total_pages$filter_string' style='padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Última »</a>";
            }
            
            echo "</div>";
            echo "</div>";
        }
        
        // Resumo com informações de paginação
        echo "<p style='margin-top: 20px; text-align: center;'>";
        if ($total_records <= $per_page) {
            echo "<strong>Total de rádios encontrados: $total_records</strong>";
        } else {
            $start_record = $offset + 1;
            $end_record = min($offset + $per_page, $total_records);
            echo "<strong>Mostrando $start_record a $end_record de $total_records rádios encontrados";
            if ($total_pages > 1) {
                echo " (Página $page de $total_pages)";
            }
            echo "</strong>";
        }
        
        // NOVA INFORMAÇÃO: Mostrar ordenação atual
        $order_text = ($_GET['order'] ?? 'desc') === 'asc' ? 'mais antigo para o mais novo' : 'mais novo para o mais antigo';
        echo "<br><em>Ordenação: $order_text</em>";
        echo "</p>";
        
    } else {
        // Debug: verificar se a tabela existe e tem dados
        try {
            $check_table = $DB->query("SHOW TABLES LIKE 'glpi_radios'");
            if ($DB->numrows($check_table) == 0) {
                echo "<p style='text-align: center; color: red;'>❌ Tabela 'glpi_radios' não existe. Verifique se o hook de instalação foi executado.</p>";
            } else {
                $count_query = $DB->query("SELECT COUNT(*) as total FROM glpi_radios WHERE is_deleted = 0");
                $count_result = $DB->fetchAssoc($count_query);
                $total_radios = $count_result['total'];
                
                if ($total_radios == 0) {
                    echo "<p style='text-align: center; color: #6c757d; font-style: italic;'>📻 Nenhum rádio cadastrado ainda. <a href='novo_radio.php'>Clique aqui para cadastrar o primeiro rádio</a>.</p>";
                } else {
                    echo "<p style='text-align: center; color: #6c757d; font-style: italic;'>Nenhum rádio encontrado com os filtros aplicados. (Total no sistema: $total_radios)</p>";
                }
            }
        } catch (Exception $debug_e) {
            echo "<p style='text-align: center; color: red;'>Erro ao verificar dados: " . $debug_e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; text-align: center;'>Erro ao carregar a lista de rádios: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "</div>";
echo "</div>";

Html::footer();
?>
