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
                $user_display = trim(($row['user_realname'] ?: '') . ' ' . ($row['user_firstname'] ?: ''));
                
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

    if (!isset($_POST['_glpi_csrf_token']) || !Session::validateCSRF($_POST)) {
        Session::addMessageAfterRedirect('Erro de segurança. Operação não autorizada.', true, ERROR);
        Html::redirect('menu.php');
        exit;
    }

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
        $name = trim($user['realname'] . ' ' . $user['firstname']);
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
        $delete_csrf_token = Session::getNewCSRFToken();
        echo "<table class='tab_cadre_fixe' style='width: 100%;'>";
        echo "<tr class='tab_bg_2'>";
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
            
            echo "<td>".Html::entities_deep(trim(($radio['user_realname'] ?: '') . ' ' . ($radio['user_firstname'] ?: '')))."</td>";
            echo "<td>".Html::entities_deep($radio['location_name'] ?: '-')."</td>";
            
            echo "<td style='text-align: center;'>";
            echo "<a href='editar_radio.php?id=".intval($radio['id'])."' title='Editar' style='margin-right: 5px; color: #007bff; text-decoration: none;'>✏️ Editar</a>";
            echo " | ";
            echo "<form method='POST' action='' style='display:inline;'>";
            echo "<input type='hidden' name='delete_id' value='".intval($radio['id'])."'>";
            echo "<input type='hidden' name='confirm_delete' value='1'>";
            echo "<input type='hidden' name='_glpi_csrf_token' value='".htmlspecialchars($delete_csrf_token)."'>";
            echo "<button type='submit' onclick='return confirm(\"Tem certeza que deseja excluir este rádio?\")' style='background:none;border:none;color:#dc3545;cursor:pointer;font-size:inherit;padding:0;'>🗑️ Excluir</button>";
            echo "</form>";
            echo "</td>";
            
            echo "</tr>";
            $i++;
        }
        echo "</table>";
        
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
