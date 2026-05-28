<?php
// plugins/termos/front/historico_radio.php

include ('../../../inc/includes.php');

// Verificar se está logado
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

// Controle de acesso básico
Session::checkRight("config", READ);

global $DB;

// Verificar se é uma exportação
$export_type = $_GET['export'] ?? '';
if ($export_type === 'csv') {
    exportarDados($DB, $export_type);
    exit;
}

// Parâmetros de paginação
$start = (int)($_GET['start'] ?? 0);
$limit = 100; // 100 registros por página

// Parâmetros de filtro - serial, datas e ordenação
$filtro_serial = trim($_GET['serial'] ?? '');
$filtro_data_inicio = trim($_GET['data_inicio'] ?? '');
$filtro_data_fim = trim($_GET['data_fim'] ?? '');
$ordenacao = $_GET['ordem'] ?? 'desc'; // 'desc' para mais recente, 'asc' para mais antigo

// Validar ordenação
$ordenacao = in_array($ordenacao, ['asc', 'desc']) ? $ordenacao : 'desc';

// Construir WHERE baseado nos filtros
$where_conditions = [];

if (!empty($filtro_serial)) {
    $where_conditions[] = "h.serial LIKE '%" . $DB->escape($filtro_serial) . "%'";
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "DATE(h.data_movimentacao) >= '" . $DB->escape($filtro_data_inicio) . "'";
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "DATE(h.data_movimentacao) <= '" . $DB->escape($filtro_data_fim) . "'";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query principal com JOINs para buscar nomes descritivos
$sql = "SELECT 
    h.id,
    h.radios_id,
    h.serial,
    h.model,
    h.patrimonio,
    h.data_movimentacao,
    h.tecnico_alterou_id,
    h.manufacturers_id,
    h.states_id,
    h.groups_id,
    h.users_id,
    h.locations_id,
    m.name as fabricante_nome,
    s.name as estado_nome,
    g.name as grupo_nome,
    g.completename as grupo_nome_completo,
    u.realname as usuario_nome,
    u.firstname as usuario_sobrenome,
    l.name as localizacao_nome,
    t.realname as tecnico_nome,
    t.firstname as tecnico_sobrenome
FROM glpi_radios_historico h
LEFT JOIN glpi_manufacturers m ON h.manufacturers_id = m.id
LEFT JOIN glpi_states s ON h.states_id = s.id
LEFT JOIN glpi_groups g ON h.groups_id = g.id
LEFT JOIN glpi_users u ON h.users_id = u.id
LEFT JOIN glpi_locations l ON h.locations_id = l.id
LEFT JOIN glpi_users t ON h.tecnico_alterou_id = t.id
$where_sql
ORDER BY h.id $ordenacao
LIMIT $start, $limit";

// Query para contar total de registros
$count_sql = "SELECT COUNT(*) as total 
FROM glpi_radios_historico h
LEFT JOIN glpi_manufacturers m ON h.manufacturers_id = m.id
LEFT JOIN glpi_states s ON h.states_id = s.id
LEFT JOIN glpi_groups g ON h.groups_id = g.id
LEFT JOIN glpi_users u ON h.users_id = u.id
LEFT JOIN glpi_locations l ON h.locations_id = l.id
LEFT JOIN glpi_users t ON h.tecnico_alterou_id = t.id
$where_sql";

try {
    $result = $DB->query($sql);
    $count_result = $DB->query($count_sql);
    $total_registros = $DB->fetchAssoc($count_result)['total'];
} catch (Exception $e) {
    Session::addMessageAfterRedirect('Erro ao carregar histórico: ' . $e->getMessage(), true, ERROR);
    Html::redirect('menu.php');
    exit;
}

// Função para exportar dados
function exportarDados($DB, $tipo) {
    // Parâmetros de filtro para exportação
    $filtro_serial = trim($_GET['serial'] ?? '');
    $filtro_data_inicio = trim($_GET['data_inicio'] ?? '');
    $filtro_data_fim = trim($_GET['data_fim'] ?? '');
    $ordenacao = $_GET['ordem'] ?? 'desc';
    
    // Validar ordenação
    $ordenacao = in_array($ordenacao, ['asc', 'desc']) ? $ordenacao : 'desc';
    
    // Construir WHERE baseado nos filtros
    $where_conditions = [];
    
    if (!empty($filtro_serial)) {
        $where_conditions[] = "h.serial LIKE '%" . $DB->escape($filtro_serial) . "%'";
    }
    
    if (!empty($filtro_data_inicio)) {
        $where_conditions[] = "DATE(h.data_movimentacao) >= '" . $DB->escape($filtro_data_inicio) . "'";
    }
    
    if (!empty($filtro_data_fim)) {
        $where_conditions[] = "DATE(h.data_movimentacao) <= '" . $DB->escape($filtro_data_fim) . "'";
    }
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Query para exportação (sem LIMIT)
    $sql = "SELECT 
        h.id,
        h.serial,
        h.model,
        h.patrimonio,
        h.data_movimentacao,
        h.tecnico_alterou_id,
        m.name as fabricante_nome,
        s.name as estado_nome,
        g.name as grupo_nome,
        g.completename as grupo_nome_completo,
        u.realname as usuario_nome,
        u.firstname as usuario_sobrenome,
        l.name as localizacao_nome,
        t.realname as tecnico_nome,
        t.firstname as tecnico_sobrenome
    FROM glpi_radios_historico h
    LEFT JOIN glpi_manufacturers m ON h.manufacturers_id = m.id
    LEFT JOIN glpi_states s ON h.states_id = s.id
    LEFT JOIN glpi_groups g ON h.groups_id = g.id
    LEFT JOIN glpi_users u ON h.users_id = u.id
    LEFT JOIN glpi_locations l ON h.locations_id = l.id
    LEFT JOIN glpi_users t ON h.tecnico_alterou_id = t.id
    $where_sql
    ORDER BY h.id $ordenacao";
    
    try {
        $result = $DB->query($sql);
        $dados = [];
        
        // Cabeçalhos
        $headers = [
            'ID',
            'Serial',
            'Modelo',
            'Fabricante',
            'Patrimônio',
            'Estado',
            'Grupo',
            'Usuário',
            'Localização',
            'Usuário que Alterou',
            'Data Movimentação'
        ];
        
        $dados[] = $headers;
        
        // Dados
        while ($row = $DB->fetchAssoc($result)) {
            // Formatação dos dados
            $grupo_nome = !empty($row['grupo_nome_completo']) ? $row['grupo_nome_completo'] : ($row['grupo_nome'] ?? '-');
            $usuario_nome = trim(($row['usuario_nome'] ?? '') . ' ' . ($row['usuario_sobrenome'] ?? ''));
            if (empty($usuario_nome)) $usuario_nome = '-';
            
            $tecnico_nome = trim(($row['tecnico_nome'] ?? '') . ' ' . ($row['tecnico_sobrenome'] ?? ''));
            if (empty($tecnico_nome)) $tecnico_nome = 'Super Admin';
            
            $data_formatada = '-';
            if (!empty($row['data_movimentacao'])) {
                try {
                    $data = new DateTime($row['data_movimentacao']);
                    $data_formatada = $data->format('d/m/Y H:i:s');
                } catch (Exception $e) {
                    $data_formatada = $row['data_movimentacao'];
                }
            }
            
            $dados[] = [
                $row['id'],
                $row['serial'] ?? '-',
                $row['model'] ?? '-',
                $row['fabricante_nome'] ?? '-',
                $row['patrimonio'] ?? '-',
                $row['estado_nome'] ?? '-',
                $grupo_nome,
                $usuario_nome,
                $row['localizacao_nome'] ?? '-',
                $tecnico_nome,
                $data_formatada
            ];
        }
        
        exportarCSV($dados);
        
    } catch (Exception $e) {
        die('Erro ao exportar dados: ' . $e->getMessage());
    }
}

// Função para exportar CSV
function exportarCSV($dados) {
    $filename = 'historico_radios_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    foreach ($dados as $row) {
        fputcsv($output, $row, ';'); // Usando ponto e vírgula como separador
    }
    
    fclose($output);
}

Html::header(__('Histórico de Rádios', 'termos'), $_SERVER['PHP_SELF'], 'plugins', 'termos');

echo "<div class='center'>";
echo "<div style='width: 95%; margin: 20px auto;'>";

// Botão voltar
echo "<div class='spaced'>";
echo "<a href='menu.php' class='btn btn-secondary'>← Voltar ao Menu</a>";
echo "</div>";

// Título
echo "<h1>📊 Histórico de Movimentações de Rádios</h1>";
echo "<hr>";

// Formulário de filtros
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>";
echo "<h3 style='margin-top: 0;'>🔍 Filtros de Busca</h3>";
echo "<form method='GET' action=''>";

echo "<div style='display: flex; flex-wrap: wrap; gap: 15px; align-items: end;'>";

// Serial
echo "<div>";
echo "<label for='serial' style='display: block; margin-bottom: 5px;'>Serial:</label>";
echo "<input type='text' name='serial' id='serial' value='" . htmlspecialchars($filtro_serial) . "' style='width: 150px;'>";
echo "</div>";

// Data Início
echo "<div>";
echo "<label for='data_inicio' style='display: block; margin-bottom: 5px;'>Data Início:</label>";
echo "<input type='date' name='data_inicio' id='data_inicio' value='" . htmlspecialchars($filtro_data_inicio) . "' style='width: 150px;'>";
echo "</div>";

// Data Fim
echo "<div>";
echo "<label for='data_fim' style='display: block; margin-bottom: 5px;'>Data Fim:</label>";
echo "<input type='date' name='data_fim' id='data_fim' value='" . htmlspecialchars($filtro_data_fim) . "' style='width: 150px;'>";
echo "</div>";

// Ordenação
echo "<div>";
echo "<label for='ordem' style='display: block; margin-bottom: 5px;'>Ordenação por ID:</label>";
echo "<select name='ordem' id='ordem' style='width: 150px;'>";
echo "<option value='desc'" . ($ordenacao == 'desc' ? ' selected' : '') . ">Mais Recente</option>";
echo "<option value='asc'" . ($ordenacao == 'asc' ? ' selected' : '') . ">Mais Antigo</option>";
echo "</select>";
echo "</div>";

// Botões
echo "<div>";
echo "<button type='submit' class='btn btn-primary'>🔍 Filtrar</button>";
echo " ";
echo "<a href='historico_radio.php' class='btn btn-secondary'>🗑️ Limpar</a>";
echo "</div>";

echo "</div>";
echo "</form>";
echo "</div>";

// Informações da paginação
$total_paginas = ceil($total_registros / $limit);
$pagina_atual = floor($start / $limit) + 1;

echo "<div style='margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;'>";

echo "<div>";
echo "<strong>Total de registros:</strong> $total_registros | ";
echo "<strong>Página:</strong> $pagina_atual de $total_paginas | ";
echo "<strong>Registros por página:</strong> $limit";
if ($ordenacao == 'desc') {
    echo " | <strong>Ordenação:</strong> Mais Recente primeiro";
} else {
    echo " | <strong>Ordenação:</strong> Mais Antigo primeiro";
}
echo "</div>";

// Botão de exportação CSV
if ($total_registros > 0) {
    echo "<div>";
    // Construir URL com filtros para exportação
    $export_params = [];
    if (!empty($filtro_serial)) $export_params[] = "serial=" . urlencode($filtro_serial);
    if (!empty($filtro_data_inicio)) $export_params[] = "data_inicio=$filtro_data_inicio";
    if (!empty($filtro_data_fim)) $export_params[] = "data_fim=$filtro_data_fim";
    $export_params[] = "ordem=$ordenacao";
    
    $export_url_base = 'historico_radio.php?' . implode('&', $export_params);
    $export_separator = '&';
    
    echo "<a href='{$export_url_base}{$export_separator}export=csv' class='btn btn-success'>📊 Exportar CSV</a>";
    echo "</div>";
}

echo "</div>";

// Tabela de resultados
if ($total_registros > 0) {
    echo "<div style='overflow-x: auto;'>";
    echo "<table class='tab_cadre_fixehov'>";
    
    // Cabeçalho
    echo "<tr class='tab_bg_1'>";
    echo "<th width='50'>ID</th>";
    echo "<th width='120'>Serial</th>";
    echo "<th width='150'>Modelo</th>";
    echo "<th width='120'>Fabricante</th>";
    echo "<th width='100'>Patrimônio</th>";
    echo "<th width='100'>Estado</th>";
    echo "<th width='120'>Grupo</th>";
    echo "<th width='150'>Usuário</th>";
    echo "<th width='120'>Localização</th>";
    echo "<th width='150'>Usuário que Alterou</th>";
    echo "<th width='140'>Data Movimentação</th>";
    echo "</tr>";
    
    // Dados
    $linha = 0;
    while ($row = $DB->fetchAssoc($result)) {
        $classe_linha = ($linha % 2 == 0) ? 'tab_bg_2' : 'tab_bg_1';
        
        echo "<tr class='$classe_linha'>";
        
        // ID
        echo "<td style='text-align: center;'>" . $row['id'] . "</td>";
        
        // Serial
        echo "<td>" . Html::clean($row['serial'] ?? '-') . "</td>";
        
        // Modelo
        echo "<td>" . Html::clean($row['model'] ?? '-') . "</td>";
        
        // Fabricante
        echo "<td>" . Html::clean($row['fabricante_nome'] ?? '-') . "</td>";
        
        // Patrimônio
        echo "<td>" . Html::clean($row['patrimonio'] ?? '-') . "</td>";
        
        // Estado
        echo "<td>" . Html::clean($row['estado_nome'] ?? '-') . "</td>";
        
        // Grupo
        $grupo_nome = !empty($row['grupo_nome_completo']) ? $row['grupo_nome_completo'] : ($row['grupo_nome'] ?? '-');
        echo "<td>" . Html::clean($grupo_nome) . "</td>";
        
        // Usuário
        $usuario_nome = trim(($row['usuario_nome'] ?? '') . ' ' . ($row['usuario_sobrenome'] ?? ''));
        if (empty($usuario_nome)) $usuario_nome = '-';
        echo "<td>" . Html::clean($usuario_nome) . "</td>";
        
        // Localização
        echo "<td>" . Html::clean($row['localizacao_nome'] ?? '-') . "</td>";
        
        // Usuário que alterou (técnico)
        $tecnico_nome = trim(($row['tecnico_nome'] ?? '') . ' ' . ($row['tecnico_sobrenome'] ?? ''));
        if (empty($tecnico_nome)) $tecnico_nome = 'Super Admin';
        echo "<td>" . Html::clean($tecnico_nome) . "</td>";
        
        // Data da movimentação
        $data_formatada = '-';
        if (!empty($row['data_movimentacao'])) {
            try {
                $data = new DateTime($row['data_movimentacao']);
                $data_formatada = $data->format('d/m/Y H:i:s');
            } catch (Exception $e) {
                $data_formatada = $row['data_movimentacao'];
            }
        }
        echo "<td style='text-align: center;'>" . $data_formatada . "</td>";
        
        echo "</tr>";
        $linha++;
    }
    
    echo "</table>";
    echo "</div>";
    
    // Navegação de páginas
    if ($total_paginas > 1) {
        echo "<div style='text-align: center; margin-top: 20px;'>";
        
        // Construir URL base mantendo os filtros
        $url_params = [];
        if (!empty($filtro_serial)) $url_params[] = "serial=" . urlencode($filtro_serial);
        if (!empty($filtro_data_inicio)) $url_params[] = "data_inicio=$filtro_data_inicio";
        if (!empty($filtro_data_fim)) $url_params[] = "data_fim=$filtro_data_fim";
        $url_params[] = "ordem=$ordenacao";
        
        $url_base = 'historico_radio.php?' . implode('&', $url_params);
        $url_base .= '&';
        
        // Botão Primeira
        if ($pagina_atual > 1) {
            echo "<a href='{$url_base}start=0' class='btn btn-secondary'>« Primeira</a> ";
        }
        
        // Botão Anterior
        if ($pagina_atual > 1) {
            $start_anterior = ($pagina_atual - 2) * $limit;
            echo "<a href='{$url_base}start=$start_anterior' class='btn btn-secondary'>‹ Anterior</a> ";
        }
        
        // Páginas numeradas (mostrar 5 páginas ao redor da atual)
        $inicio_pag = max(1, $pagina_atual - 2);
        $fim_pag = min($total_paginas, $pagina_atual + 2);
        
        for ($p = $inicio_pag; $p <= $fim_pag; $p++) {
            $start_pag = ($p - 1) * $limit;
            $classe = ($p == $pagina_atual) ? 'btn btn-primary' : 'btn btn-secondary';
            echo "<a href='{$url_base}start=$start_pag' class='$classe'>$p</a> ";
        }
        
        // Botão Próxima
        if ($pagina_atual < $total_paginas) {
            $start_proximo = $pagina_atual * $limit;
            echo "<a href='{$url_base}start=$start_proximo' class='btn btn-secondary'>Próxima ›</a> ";
        }
        
        // Botão Última
        if ($pagina_atual < $total_paginas) {
            $start_ultimo = ($total_paginas - 1) * $limit;
            echo "<a href='{$url_base}start=$start_ultimo' class='btn btn-secondary'>Última »</a>";
        }
        
        echo "</div>";
    }
    
} else {
    echo "<div style='text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px;'>";
    echo "<h3>📋 Nenhum registro encontrado</h3>";
    echo "<p>Não foram encontrados registros de histórico com os filtros aplicados.</p>";
    echo "</div>";
}

echo "</div>";
echo "</div>";

Html::footer();
?>