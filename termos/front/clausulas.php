<?php
// plugins/termos/front/clausulas.php

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
        Html::redirect('clausulas.php');
        exit;
    }
    
    $delete_id = (int)$_GET['delete_id'];
    
    if ($delete_id > 0) {
        try {
            // Soft delete - marcar como deletado
            $sql = "UPDATE glpi_plugin_termo_clausulas SET is_deleted = 1, date_mod = NOW() WHERE id = $delete_id";
            $result = $DB->query($sql);
            
            if ($result) {
                Session::addMessageAfterRedirect("Cláusula excluída com sucesso!", true, INFO);
            } else {
                Session::addMessageAfterRedirect('Erro ao excluir a cláusula', true, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
        }
    }
    
    Html::redirect('clausulas.php');
    exit;
}

// Processa envio do formulário (nova cláusula)
if (isset($_POST['add_clausula'])) {
    Session::checkRight("config", UPDATE);
    
    // Verificação CSRF
    if (!isset($_POST['_glpi_csrf_token']) || empty($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect('Erro de segurança. Recarregue a página e tente novamente.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Coleta e limpa dados
    $indice = trim($_POST['indice'] ?? '');
    $texto = trim($_POST['texto'] ?? '');
    $entities_id = intval($_SESSION['glpiactive_entity']);
    
    // Validações
    $erros = [];
    
    if (empty($indice)) {
        $erros[] = "Índice é obrigatório";
    }
    
    if (empty($texto)) {
        $erros[] = "Texto da cláusula é obrigatório";
    }
    
    // Verificar se já existe cláusula com o mesmo índice
    if (!empty($indice)) {
        $indice_check = $DB->escape($indice);
        $sql_check = "SELECT COUNT(*) as total FROM glpi_plugin_termo_clausulas 
                      WHERE indice = '$indice_check' 
                      AND is_deleted = 0 
                      AND entities_id = $entities_id";
        $result_check = $DB->query($sql_check);
        if ($result_check && $DB->result($result_check, 0, 'total') > 0) {
            $erros[] = "Já existe uma cláusula com este índice";
        }
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
        $indice_esc = $DB->escape($indice);
        $texto_esc = $DB->escape($texto);
        
        $sql = "INSERT INTO `glpi_plugin_termo_clausulas` 
                (`indice`, `texto`, `entities_id`, `date_creation`, `date_mod`, `is_deleted`)
                VALUES 
                ('$indice_esc', '$texto_esc', $entities_id, NOW(), NOW(), 0)";
        
        $result = $DB->query($sql);
        
        if ($result) {
            $clausula_id = $DB->insertId();
            Session::addMessageAfterRedirect("Cláusula cadastrada com sucesso! ID: $clausula_id", true, INFO);
        } else {
            Session::addMessageAfterRedirect('Erro ao cadastrar a cláusula no banco de dados', true, ERROR);
        }
        
    } catch (Exception $e) {
        Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
    }
    
    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

// Processa edição
if (isset($_POST['update_clausula'])) {
    Session::checkRight("config", UPDATE);
    
    // Verificação CSRF
    if (!isset($_POST['_glpi_csrf_token']) || empty($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect('Erro de segurança. Recarregue a página e tente novamente.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $indice = trim($_POST['indice'] ?? '');
    $texto = trim($_POST['texto'] ?? '');
    $entities_id = intval($_SESSION['glpiactive_entity']);
    
    // Validações
    $erros = [];
    
    if (empty($indice)) {
        $erros[] = "Índice é obrigatório";
    }
    
    if (empty($texto)) {
        $erros[] = "Texto da cláusula é obrigatório";
    }
    
    // Verificar se já existe outra cláusula com o mesmo índice
    if (!empty($indice) && $id > 0) {
        $indice_check = $DB->escape($indice);
        $sql_check = "SELECT COUNT(*) as total FROM glpi_plugin_termo_clausulas 
                      WHERE indice = '$indice_check' 
                      AND is_deleted = 0 
                      AND entities_id = $entities_id
                      AND id != $id";
        $result_check = $DB->query($sql_check);
        if ($result_check && $DB->result($result_check, 0, 'total') > 0) {
            $erros[] = "Já existe outra cláusula com este índice";
        }
    }
    
    if (count($erros) > 0) {
        foreach ($erros as $erro) {
            Session::addMessageAfterRedirect($erro, true, ERROR);
        }
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    if ($id > 0) {
        try {
            $indice_esc = $DB->escape($indice);
            $texto_esc = $DB->escape($texto);
            
            $sql = "UPDATE glpi_plugin_termo_clausulas SET 
                    indice = '$indice_esc',
                    texto = '$texto_esc',
                    date_mod = NOW()
                    WHERE id = $id";
            
            $result = $DB->query($sql);
            
            if ($result) {
                Session::addMessageAfterRedirect("Cláusula atualizada com sucesso!", true, INFO);
            } else {
                Session::addMessageAfterRedirect('Erro ao atualizar a cláusula', true, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
        }
    }
    
    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

Html::header(__('Gerenciar Cláusulas', 'termos'), $_SERVER['PHP_SELF'], 'plugins', 'termos');

echo "<div class='center'>";
echo "<div style='width: 95%; margin: 20px auto;'>";

// Botão voltar
echo "<div class='spaced'>";
echo "<a href='menu.php' class='btn btn-secondary'>← Voltar ao Menu</a>";
echo "</div>";

// Título
echo "<h1>📝 Gerenciar Cláusulas dos Termos</h1>";
echo "<hr>";

// Formulário de cadastro de nova cláusula
echo "<div class='spaced'>";
echo "<h3>➕ Cadastrar Nova Cláusula</h3>";

echo "<form method='POST' action=''>";
echo Html::hidden('add_clausula', ['value' => 1]);
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>";
echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

// Índice
echo "<tr class='tab_bg_1'>";
echo "<td width='15%'><label><span style='color: red;'>*</span> ".__('Índice', 'termos')."</label></td>";
echo "<td>";
echo Html::input('indice', ['value' => '', 'size' => 30, 'required' => true, 'placeholder' => 'Ex: 1, 1.1, A, I, etc.']);
echo "<br><small style='color: #666;'>Identificação única da cláusula (Ex: 1, 1.1, A, I, etc.)</small>";
echo "</td>";
echo "</tr>";

// Texto
echo "<tr class='tab_bg_1'>";
echo "<td style='vertical-align: top;'><label><span style='color: red;'>*</span> ".__('Texto da Cláusula', 'termos')."</label></td>";
echo "<td>";
echo "<textarea name='texto' rows='8' cols='100' required style='width: 100%; resize: vertical;' placeholder='Digite aqui o texto completo da cláusula...'></textarea>";
echo "<br><small style='color: #666;'>Conteúdo completo da cláusula</small>";
echo "</td>";
echo "</tr>";

echo "</table>";

echo "<div style='text-align: center; margin-top: 20px;'>";
echo Html::submit(_x('button','💾 Cadastrar Cláusula', 'termos'), ['name' => 'add_clausula', 'class' => 'btn btn-success']);
echo "</div>";

echo "</div>";
echo "</form>";
echo "</div>";

// Lista de cláusulas existentes
echo "<div class='spaced'>";
echo "<h3>📋 Cláusulas Cadastradas</h3>";

try {
    $sql_list = "SELECT * FROM glpi_plugin_termo_clausulas 
                 WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                 ORDER BY indice ASC, date_creation ASC";
    
    $result_list = $DB->query($sql_list);
    
    if ($result_list && $DB->numrows($result_list) > 0) {
        
        while ($clausula = $DB->fetchAssoc($result_list)) {
            $is_editing = isset($_GET['edit']) && $_GET['edit'] == $clausula['id'];
            
            echo "<div style='background: #fff; border: 1px solid #dee2e6; border-radius: 8px; margin: 15px 0; padding: 20px;'>";
            
            if ($is_editing) {
                // Modo edição
                echo "<form method='POST' action=''>";
                echo Html::hidden('update_clausula', ['value' => 1]);
                echo Html::hidden('id', ['value' => $clausula['id']]);
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
                
                echo "<h4>✏️ Editando Cláusula #" . $clausula['id'] . "</h4>";
                echo "<table class='tab_cadre_fixe' style='width: 100%;'>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td width='15%'><label>Índice</label></td>";
                echo "<td>" . Html::input('indice', ['value' => $clausula['indice'], 'size' => 30, 'required' => true]) . "</td>";
                echo "</tr>";
                
                echo "<tr class='tab_bg_1'>";
                echo "<td style='vertical-align: top;'><label>Texto da Cláusula</label></td>";
                echo "<td><textarea name='texto' rows='8' cols='100' required style='width: 100%; resize: vertical;'>" . Html::entities_deep($clausula['texto']) . "</textarea></td>";
                echo "</tr>";
                
                echo "</table>";
                
                echo "<div style='text-align: center; margin-top: 15px;'>";
                echo Html::submit('💾 Salvar', ['name' => 'update_clausula', 'class' => 'btn btn-success']);
                echo " ";
                echo "<a href='clausulas.php' class='btn btn-secondary'>❌ Cancelar</a>";
                echo "</div>";
                
                echo "</form>";
            } else {
                // Modo visualização
                echo "<div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;'>";
                echo "<h4>📑 Cláusula " . Html::entities_deep($clausula['indice']) . " <small style='color: #666;'>(ID: #" . $clausula['id'] . ")</small></h4>";
                
                // Botões de ação
                echo "<div>";
                echo "<a href='?edit=" . $clausula['id'] . "' class='btn btn-primary btn-sm'>✏️ Editar</a>";
                echo " ";
                $csrf_token = Session::getNewCSRFToken();
                echo "<a href='?delete_id=" . $clausula['id'] . "&confirm_delete=1&_glpi_csrf_token=$csrf_token' ";
                echo "onclick='return confirm(\"Tem certeza que deseja excluir esta cláusula?\")' ";
                echo "class='btn btn-danger btn-sm'>🗑️ Excluir</a>";
                echo "</div>";
                echo "</div>";
                
                // Conteúdo da cláusula
                echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;'>";
                echo "<div style='white-space: pre-wrap; line-height: 1.6;'>" . Html::entities_deep($clausula['texto']) . "</div>";
                echo "</div>";
                
                // Informações adicionais
                echo "<div style='font-size: 0.9em; color: #666; border-top: 1px solid #eee; padding-top: 10px;'>";
                echo "<strong>Criado em:</strong> " . Html::entities_deep($clausula['date_creation']);
                if ($clausula['date_mod'] !== $clausula['date_creation']) {
                    echo " | <strong>Última modificação:</strong> " . Html::entities_deep($clausula['date_mod']);
                }
                echo "</div>";
            }
            
            echo "</div>";
        }
        
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
        echo "<p style='margin: 0; color: #856404;'><strong>⚠️ Atenção:</strong> Nenhuma cláusula cadastrada ainda. Use o formulário acima para cadastrar as cláusulas dos termos.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;'>";
    echo "<p style='margin: 0; color: #721c24;'><strong>❌ Erro:</strong> Erro ao carregar cláusulas: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</div>";

echo "</div>";
echo "</div>";

Html::footer();
?>