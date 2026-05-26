<?php
// plugins/termos/front/cabecalho.php

include ('../../../inc/includes.php');

// Verificar se está logado
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

// Controle de acesso básico
Session::checkRight("config", READ);

global $DB;

// Processar exclusão
if (isset($_GET['delete_id']) && isset($_GET['confirm_delete'])) {
    Session::checkRight("config", UPDATE);
    
    // Validar CSRF token
    if (!Session::validateCSRF($_GET)) {
        Session::addMessageAfterRedirect('Erro de segurança. Operação não autorizada.', true, ERROR);
        Html::redirect('cabecalho.php');
        exit;
    }
    
    $delete_id = (int)$_GET['delete_id'];
    
    if ($delete_id > 0) {
        try {
            // Soft delete - marcar como deletado
            $sql = "UPDATE glpi_termos_cabecalho SET is_deleted = 1, date_mod = NOW() WHERE id = $delete_id";
            $result = $DB->query($sql);
            
            if ($result) {
                Session::addMessageAfterRedirect("Cabeçalho excluído com sucesso!", true, INFO);
            } else {
                Session::addMessageAfterRedirect('Erro ao excluir o cabeçalho', true, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
        }
    }
    
    Html::redirect('cabecalho.php');
    exit;
}

// Processa envio do formulário (novo cabeçalho)
if (isset($_POST['add_cabecalho'])) {
    Session::checkRight("config", UPDATE);
    
    // Verificação CSRF
    if (!isset($_POST['_glpi_csrf_token']) || empty($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect('Erro de segurança. Recarregue a página e tente novamente.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Coleta e limpa dados
    $logo = trim($_POST['logo'] ?? '');
    $titulo1 = trim($_POST['titulo1'] ?? '');
    $titulo2 = trim($_POST['titulo2'] ?? '');
    $versao_serie = trim($_POST['versao_serie'] ?? '');
    $setor = trim($_POST['setor'] ?? '');
    $revisao = trim($_POST['revisao'] ?? '');
    $paginas = (int)($_POST['paginas'] ?? 0);
    $data_versao = trim($_POST['data_versao'] ?? '');
    $entities_id = intval($_SESSION['glpiactive_entity']);
    
    // Validações
    $erros = [];
    
    if (empty($titulo1)) {
        $erros[] = "Título 1 é obrigatório";
    }
    
    if (empty($titulo2)) {
        $erros[] = "Título 2 é obrigatório";
    }
    
    if (count($erros) > 0) {
        foreach ($erros as $erro) {
            Session::addMessageAfterRedirect($erro, true, ERROR);
        }
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Inserir no banco
    try {
        $logo_esc = $DB->escape($logo);
        $titulo1_esc = $DB->escape($titulo1);
        $titulo2_esc = $DB->escape($titulo2);
        $versao_serie_esc = $DB->escape($versao_serie);
        $setor_esc = $DB->escape($setor);
        $revisao_esc = $DB->escape($revisao);
        $data_versao_esc = $DB->escape($data_versao);
        
        $sql = "INSERT INTO `glpi_termos_cabecalho` 
                (`logo`, `titulo1`, `titulo2`, `versao_serie`, `setor`, 
                 `revisao`, `paginas`, `data_versao`, `entities_id`, 
                 `date_creation`, `date_mod`, `is_deleted`)
                VALUES 
                ('$logo_esc', '$titulo1_esc', '$titulo2_esc', '$versao_serie_esc', 
                 '$setor_esc', '$revisao_esc', $paginas, '$data_versao_esc', 
                 $entities_id, NOW(), NOW(), 0)";
        
        $result = $DB->query($sql);
        
        if ($result) {
            $cabecalho_id = $DB->insertId();
            Session::addMessageAfterRedirect("Cabeçalho cadastrado com sucesso! ID: $cabecalho_id", true, INFO);
        } else {
            Session::addMessageAfterRedirect('Erro ao cadastrar o cabeçalho no banco de dados', true, ERROR);
        }
        
    } catch (Exception $e) {
        Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
    }
    
    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

// Processa edição
if (isset($_POST['update_cabecalho'])) {
    Session::checkRight("config", UPDATE);
    
    // Verificação CSRF
    if (!isset($_POST['_glpi_csrf_token']) || empty($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect('Erro de segurança. Recarregue a página e tente novamente.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $logo = trim($_POST['logo'] ?? '');
    $titulo1 = trim($_POST['titulo1'] ?? '');
    $titulo2 = trim($_POST['titulo2'] ?? '');
    $versao_serie = trim($_POST['versao_serie'] ?? '');
    $setor = trim($_POST['setor'] ?? '');
    $revisao = trim($_POST['revisao'] ?? '');
    $paginas = (int)($_POST['paginas'] ?? 0);
    $data_versao = trim($_POST['data_versao'] ?? '');
    
    if ($id > 0) {
        try {
            $logo_esc = $DB->escape($logo);
            $titulo1_esc = $DB->escape($titulo1);
            $titulo2_esc = $DB->escape($titulo2);
            $versao_serie_esc = $DB->escape($versao_serie);
            $setor_esc = $DB->escape($setor);
            $revisao_esc = $DB->escape($revisao);
            $data_versao_esc = $DB->escape($data_versao);
            
            $sql = "UPDATE glpi_termos_cabecalho SET 
                    logo = '$logo_esc',
                    titulo1 = '$titulo1_esc',
                    titulo2 = '$titulo2_esc',
                    versao_serie = '$versao_serie_esc',
                    setor = '$setor_esc',
                    revisao = '$revisao_esc',
                    paginas = $paginas,
                    data_versao = '$data_versao_esc',
                    date_mod = NOW()
                    WHERE id = $id";
            
            $result = $DB->query($sql);
            
            if ($result) {
                Session::addMessageAfterRedirect("Cabeçalho atualizado com sucesso!", true, INFO);
            } else {
                Session::addMessageAfterRedirect('Erro ao atualizar o cabeçalho', true, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
        }
    }
    
    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

Html::header(__('Configurar Cabeçalho', 'termos'), $_SERVER['PHP_SELF'], 'plugins', 'termos');

echo "<div class='center'>";
echo "<div style='width: 95%; margin: 20px auto;'>";

// Botão voltar
echo "<div class='spaced'>";
echo "<a href='menu.php' class='btn btn-secondary'>← Voltar ao Menu</a>";
echo "</div>";

// Título
echo "<h1>📋 Configurar Cabeçalho dos Termos</h1>";
echo "<hr>";

// Verificar se já existe cabeçalho
$sql_check = "SELECT * FROM glpi_termos_cabecalho 
              WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
              ORDER BY date_creation DESC";

$result_check = $DB->query($sql_check);
$cabecalhos = [];
$has_cabecalho = false;

if ($result_check && $DB->numrows($result_check) > 0) {
    while ($row = $DB->fetchAssoc($result_check)) {
        $cabecalhos[] = $row;
    }
    $has_cabecalho = true;
}

// Mostrar formulário de cadastro apenas se não houver cabeçalho
if (!$has_cabecalho) {
    echo "<div class='spaced'>";
    echo "<h3>➕ Configurar Cabeçalho</h3>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff;'>";
    echo "<p style='margin: 0; color: #0056b3;'><strong>ℹ️ Informação:</strong> Apenas um cabeçalho pode ser configurado por vez. Após cadastrar, você poderá editá-lo ou excluí-lo para criar um novo.</p>";
    echo "</div>";
    
    echo "<form method='POST' action=''>";
    echo Html::hidden('add_cabecalho', ['value' => 1]);
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>";
    echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

    // Logo
    echo "<tr class='tab_bg_1'>";
    echo "<td width='20%'><label>".__('Logo', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('logo', ['value' => '', 'size' => 80]);
    echo "</td>";
    echo "</tr>";

    // Título 1
    echo "<tr class='tab_bg_1'>";
    echo "<td><label><span style='color: red;'>*</span> ".__('Título 1', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('titulo1', ['value' => '', 'size' => 80, 'required' => true]);
    echo "</td>";
    echo "</tr>";

    // Título 2
    echo "<tr class='tab_bg_1'>";
    echo "<td><label><span style='color: red;'>*</span> ".__('Título 2', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('titulo2', ['value' => '', 'size' => 80, 'required' => true]);
    echo "</td>";
    echo "</tr>";

    // Versão/Série
    echo "<tr class='tab_bg_1'>";
    echo "<td><label>".__('Versão/Série', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('versao_serie', ['value' => '', 'size' => 30]);
    echo "</td>";
    echo "</tr>";

    // Setor
    echo "<tr class='tab_bg_1'>";
    echo "<td><label>".__('Setor', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('setor', ['value' => '', 'size' => 50]);
    echo "</td>";
    echo "</tr>";

    // Revisão
    echo "<tr class='tab_bg_1'>";
    echo "<td><label>".__('Revisão', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('revisao', ['value' => '', 'size' => 20]);
    echo "</td>";
    echo "</tr>";

    // Páginas
    echo "<tr class='tab_bg_1'>";
    echo "<td><label>".__('Páginas', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('paginas', ['value' => '', 'type' => 'number', 'min' => 0]);
    echo "</td>";
    echo "</tr>";

    // Data da Versão
    echo "<tr class='tab_bg_1'>";
    echo "<td><label>".__('Data da Versão', 'termos')."</label></td>";
    echo "<td>";
    echo Html::input('data_versao', ['value' => '', 'type' => 'date']);
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<div style='text-align: center; margin-top: 20px;'>";
    echo Html::submit(_x('button','💾 Configurar Cabeçalho', 'termos'), ['name' => 'add_cabecalho', 'class' => 'btn btn-success']);
    echo "</div>";

    echo "</div>";
    echo "</form>";
    echo "</div>";
}

// Lista/Edição do cabeçalho existente
echo "<div class='spaced'>";
if ($has_cabecalho) {
    echo "<h3>📋 Cabeçalho Atual</h3>";
} else {
    echo "<h3>📋 Status do Cabeçalho</h3>";
}

try {
    if (count($cabecalhos) > 0) {
        foreach ($cabecalhos as $index => $cabecalho) {
            $edit_id = "edit_" . $cabecalho['id'];
            $is_editing = isset($_GET['edit']) && $_GET['edit'] == $cabecalho['id'];
            
            echo "<div style='background: #fff; border: 1px solid #dee2e6; border-radius: 8px; margin: 15px 0; padding: 20px;'>";
            
            if ($is_editing) {
                // Modo edição
                echo "<form method='POST' action=''>";
                echo Html::hidden('update_cabecalho', ['value' => 1]);
                echo Html::hidden('id', ['value' => $cabecalho['id']]);
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
                
                echo "<h4>✏️ Editando Cabeçalho #" . $cabecalho['id'] . "</h4>";
                echo "<table class='tab_cadre_fixe' style='width: 100%;'>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td width='20%'><label>Logo</label></td>";
                echo "<td>" . Html::input('logo', ['value' => $cabecalho['logo'], 'size' => 80]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Título 1</label></td>";
                echo "<td>" . Html::input('titulo1', ['value' => $cabecalho['titulo1'], 'size' => 80]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Título 2</label></td>";
                echo "<td>" . Html::input('titulo2', ['value' => $cabecalho['titulo2'], 'size' => 80]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Versão/Série</label></td>";
                echo "<td>" . Html::input('versao_serie', ['value' => $cabecalho['versao_serie'], 'size' => 30]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Setor</label></td>";
                echo "<td>" . Html::input('setor', ['value' => $cabecalho['setor'], 'size' => 50]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Revisão</label></td>";
                echo "<td>" . Html::input('revisao', ['value' => $cabecalho['revisao'], 'size' => 20]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Páginas</label></td>";
                echo "<td>" . Html::input('paginas', ['value' => $cabecalho['paginas'], 'type' => 'number', 'min' => 0]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td><label>Data da Versão</label></td>";
                echo "<td>" . Html::input('data_versao', ['value' => $cabecalho['data_versao'], 'type' => 'date']) . "</td>";
                echo "</tr>";
                
                echo "</table>";
                
                echo "<div style='text-align: center; margin-top: 15px;'>";
                echo Html::submit('💾 Salvar', ['name' => 'update_cabecalho', 'class' => 'btn btn-success']);
                echo " ";
                echo "<a href='cabecalho.php' class='btn btn-secondary'>❌ Cancelar</a>";
                echo "</div>";
                
                echo "</form>";
            } else {
                // Modo visualização
                echo "<h4>📋 Cabeçalho #" . $cabecalho['id'] . "</h4>";
                echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;'>";
                
                echo "<div><strong>Logo:</strong> " . Html::entities_deep($cabecalho['logo'] ?: '-') . "</div>";
                echo "<div><strong>Título 1:</strong> " . Html::entities_deep($cabecalho['titulo1']) . "</div>";
                echo "<div><strong>Título 2:</strong> " . Html::entities_deep($cabecalho['titulo2']) . "</div>";
                echo "<div><strong>Versão/Série:</strong> " . Html::entities_deep($cabecalho['versao_serie'] ?: '-') . "</div>";
                echo "<div><strong>Setor:</strong> " . Html::entities_deep($cabecalho['setor'] ?: '-') . "</div>";
                echo "<div><strong>Revisão:</strong> " . Html::entities_deep($cabecalho['revisao'] ?: '-') . "</div>";
                echo "<div><strong>Páginas:</strong> " . Html::entities_deep($cabecalho['paginas'] ?: '0') . "</div>";
                echo "<div><strong>Data da Versão:</strong> " . Html::entities_deep($cabecalho['data_versao'] ?: '-') . "</div>";
                echo "<div><strong>Criado em:</strong> " . Html::entities_deep($cabecalho['date_creation']) . "</div>";
                
                echo "</div>";
                
                // Botões de ação
                echo "<div style='text-align: center; margin-top: 15px;'>";
                echo "<a href='?edit=" . $cabecalho['id'] . "' class='btn btn-primary'>✏️ Editar</a>";
                echo " ";
                $csrf_token = Session::getNewCSRFToken();
                echo "<a href='?delete_id=" . $cabecalho['id'] . "&confirm_delete=1&_glpi_csrf_token=$csrf_token' ";
                echo "onclick='return confirm(\"Tem certeza que deseja excluir este cabeçalho?\")' ";
                echo "class='btn btn-danger'>🗑️ Excluir</a>";
                echo "</div>";
            }
            
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
        echo "<p style='margin: 0; color: #856404;'><strong>⚠️ Atenção:</strong> Nenhum cabeçalho configurado ainda. Use o formulário acima para configurar o cabeçalho dos termos.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; text-align: center;'>Erro ao carregar cabeçalhos: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "</div>";
echo "</div>";

Html::footer();
?>