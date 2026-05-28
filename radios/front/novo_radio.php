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

// Processa envio do formulário
if (isset($_POST['add_radio'])) {
    Session::checkRight("config", UPDATE);
    
    // Verificação CSRF
    if (!isset($_POST['_glpi_csrf_token']) || empty($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect('Erro de segurança. Recarregue a página e tente novamente.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Coleta e limpa dados
    $manufacturers_id = (int)($_POST['manufacturers_id'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $serial = trim($_POST['serial'] ?? '');
    $otherserial = trim($_POST['otherserial'] ?? '');
    $chave_nf = trim($_POST['chave_nf'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $states_id = (int)($_POST['states_id'] ?? 0);
    $users_id = (int)($_POST['users_id'] ?? 0);
    $locations_id = (int)($_POST['locations_id'] ?? 0);
    $groups_id = (int)($_POST['groups_id'] ?? 0);
    $entities_id = intval($_SESSION['glpiactive_entity']);
    
    // ID do técnico que está fazendo o cadastro
    $tecnico_alterou_id = Session::getLoginUserID();
    
    // Validações
    $erros = [];
    
    if ($manufacturers_id == 0) {
        $erros[] = "Fabricante é obrigatório";
    }
    
    if (empty($serial)) {
        $erros[] = "Número de série é obrigatório";
    } else {
        // Verificar duplicidade de série
        try {
            $check_sql = "SELECT COUNT(*) as total FROM glpi_radios WHERE serial = '" . $DB->escape($serial) . "' AND is_deleted = 0";
            $check_result = $DB->query($check_sql);
            $check_data = $DB->fetchAssoc($check_result);
            
            if ($check_data['total'] > 0) {
                $erros[] = "Número de série já existe no sistema";
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao verificar número de série";
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
        $model_esc = $DB->escape($model);
        $serial_esc = $DB->escape($serial);
        $otherserial_esc = $DB->escape($otherserial);
        $chave_nf_esc = $DB->escape($chave_nf);
        $comment_esc = $DB->escape($comment);
        
        $sql = "INSERT INTO `glpi_radios` 
                (`manufacturers_id`, `model`, `serial`, `otherserial`, `chave_nf`, 
                 `comment`, `states_id`, `users_id`, `locations_id`, `groups_id`,
                 `entities_id`, `date_creation`, `date_mod`, `is_deleted`, `is_template`)
                VALUES 
                ($manufacturers_id, '$model_esc', '$serial_esc', '$otherserial_esc', 
                 '$chave_nf_esc', '$comment_esc', $states_id, $users_id, $locations_id, $groups_id,
                 $entities_id, NOW(), NOW(), 0, 0)";
        
        $result = $DB->query($sql);
        
        if ($result) {
            $radio_id = $DB->insertId();
            
            // REGISTRAR NO HISTÓRICO - VERSÃO COMPLETA
            try {
                $hist_sql = "INSERT INTO `glpi_radios_historico` 
                            (`radios_id`, `serial`, `model`, `manufacturers_id`, 
                             `patrimonio`, `states_id`, `groups_id`, `users_id`, 
                             `locations_id`, `tecnico_alterou_id`, `data_movimentacao`, `entities_id`)
                            VALUES 
                            ($radio_id, '$serial_esc', '$model_esc', $manufacturers_id,
                             '$otherserial_esc', $states_id, $groups_id, $users_id,
                             $locations_id, $tecnico_alterou_id, NOW(), $entities_id)";
                
                $hist_result = $DB->query($hist_sql);
                
                if (!$hist_result) {
                    // Log do erro para debug
                    error_log("Erro ao inserir histórico para rádio ID: $radio_id - SQL: $hist_sql");
                    error_log("Erro MySQL: " . $DB->error());
                }
                
            } catch (Exception $e) {
                // Log do erro, mas não interrompe o cadastro principal
                error_log("Exceção ao inserir histórico: " . $e->getMessage());
            }
            
            Session::addMessageAfterRedirect("Rádio cadastrado com sucesso! ID: $radio_id", true, INFO);
        } else {
            Session::addMessageAfterRedirect('Erro ao cadastrar o rádio no banco de dados', true, ERROR);
        }
        
    } catch (Exception $e) {
        Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
    }
    
    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

Html::header(__('Novo Rádio', 'radios'), $_SERVER['PHP_SELF'], 'plugins', 'radios');

echo "<div class='center'>";
echo "<div style='width: 90%; margin: 20px auto;'>";

// Botão voltar
echo "<div class='spaced'>";
echo "<a href='menu.php' class='btn btn-secondary'>← Voltar à Lista de Rádios</a>";
echo "</div>";

// Título
echo "<h1>📻 Cadastrar Novo Rádio</h1>";
echo "<hr>";

// Formulário
echo "<div class='spaced'>";
echo "<form method='POST' action=''>";
echo Html::hidden('add_radio', ['value' => 1]);
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div style='background: #f8f9fa; padding: 30px; border-radius: 8px;'>";

// Seção 1 - Informações Básicas
echo "<h3 style='color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px;'>📋 Informações Básicas</h3>";

echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

// Fabricante
echo "<tr class='tab_bg_1'>";
echo "<td width='30%'><label><span style='color: red;'>*</span> ".__('Fabricante', 'radios')."</label></td>";
echo "<td>";
$manufacturers_options = [0 => 'Selecione o fabricante'];
try {
    $manufacturers = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_manufacturers',
        'ORDER' => 'name'
    ]);
    foreach ($manufacturers as $manufacturer) {
        $manufacturers_options[$manufacturer['id']] = $manufacturer['name'];
    }
} catch (Exception $e) {
    $manufacturers_options[0] = 'Erro ao carregar fabricantes';
}
Dropdown::showFromArray('manufacturers_id', $manufacturers_options, [
    'value' => 0,
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Modelo
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Modelo', 'radios')."</label></td>";
echo "<td>";
echo Html::input('model', [
    'value' => '', 
    'size' => 50
]);
echo "</td>";
echo "</tr>";

// Número de Série
echo "<tr class='tab_bg_1'>";
echo "<td><label><span style='color: red;'>*</span> ".__('Número de Série', 'radios')."</label></td>";
echo "<td>";
echo Html::input('serial', [
    'value' => '', 
    'size' => 50,
    'required' => true
]);
echo "</td>";
echo "</tr>";

// Patrimônio
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Patrimônio', 'radios')."</label></td>";
echo "<td>";
echo Html::input('otherserial', [
    'value' => '', 
    'size' => 50
]);
echo "</td>";
echo "</tr>";

// Chave NF
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Chave da Nota Fiscal', 'radios')."</label></td>";
echo "<td>";
echo Html::input('chave_nf', [
    'value' => '', 
    'size' => 44,
    'maxlength' => 44
]);
echo "</td>";
echo "</tr>";

echo "</table>";

// Seção 2 - Status e Localização
echo "<h3 style='color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-top: 30px;'>📍 Status e Localização</h3>";

echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

// Status
echo "<tr class='tab_bg_1'>";
echo "<td width='30%'><label>".__('Status', 'radios')."</label></td>";
echo "<td>";
$states_options = [0 => 'Nenhum'];
try {
    $states = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_states',
        'ORDER' => 'name'
    ]);
    foreach ($states as $state) {
        $states_options[$state['id']] = $state['name'];
    }
} catch (Exception $e) {
    $states_options[0] = 'Erro ao carregar status';
}
Dropdown::showFromArray('states_id', $states_options, [
    'value' => 0,
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Localização
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Localização', 'radios')."</label></td>";
echo "<td>";
$locations_options = [0 => 'Nenhuma'];
try {
    $locations = $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM' => 'glpi_locations',
        'ORDER' => 'name'
    ]);
    foreach ($locations as $location) {
        $locations_options[$location['id']] = $location['name'];
    }
} catch (Exception $e) {
    $locations_options[0] = 'Erro ao carregar localizações';
}
Dropdown::showFromArray('locations_id', $locations_options, [
    'value' => 0,
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Grupo
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Grupo', 'radios')."</label></td>";
echo "<td>";
$groups_options = [0 => 'Nenhum'];
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
        // Usar completename se disponível, senão usar name
        $group_name = !empty($group['completename']) ? $group['completename'] : $group['name'];
        $groups_options[$group['id']] = $group_name;
    }
} catch (Exception $e) {
    $groups_options[0] = 'Erro ao carregar grupos';
}
Dropdown::showFromArray('groups_id', $groups_options, [
    'value' => 0,
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Usuário
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Usuário', 'radios')."</label></td>";
echo "<td>";
$users_options = [0 => 'Nenhum'];
try {
    $users = $DB->request([
        'SELECT' => ['id', 'realname', 'firstname'],
        'FROM' => 'glpi_users',
        'WHERE' => ['is_active' => 1],
        'ORDER' => 'realname'
    ]);
    foreach ($users as $user) {
        $name = trim($user['realname'] . ' ' . $user['firstname']);
        $users_options[$user['id']] = $name;
    }
} catch (Exception $e) {
    $users_options[0] = 'Erro ao carregar usuários';
}
Dropdown::showFromArray('users_id', $users_options, [
    'value' => 0,
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Observações
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Observações', 'radios')."</label></td>";
echo "<td>";
echo Html::textarea([
    'name' => 'comment',
    'value' => '',
    'rows' => 4,
    'cols' => 50
]);
echo "</td>";
echo "</tr>";

echo "</table>";

// Botões
echo "<div style='text-align: center; margin-top: 30px;'>";
echo Html::submit(_x('button','💾 Salvar Rádio', 'radios'), ['name' => 'add_radio', 'class' => 'btn btn-success']);
echo " ";
echo "<a href='menu.php' class='btn btn-secondary'>❌ Cancelar</a>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 15px; color: #6c757d; font-size: 14px;'>";
echo "<span style='color: red;'>*</span> Campos obrigatórios";
echo "</p>";

echo "</div>";
echo "</form>";
echo "</div>";

echo "</div>";
echo "</div>";

Html::footer();
?>
