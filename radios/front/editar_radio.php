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

// Verificar se foi passado um ID válido
$radio_id = (int)($_GET['id'] ?? 0);
if ($radio_id <= 0) {
    Session::addMessageAfterRedirect('ID do rádio inválido', true, ERROR);
    Html::redirect('menu.php');
    exit;
}

// Buscar dados do rádio
try {
    $sql = "SELECT * FROM glpi_radios WHERE id = $radio_id AND is_deleted = 0";
    $result = $DB->query($sql);
    
    if (!$result || $DB->numrows($result) == 0) {
        Session::addMessageAfterRedirect('Rádio não encontrado', true, ERROR);
        Html::redirect('menu.php');
        exit;
    }
    
    $radio = $DB->fetchAssoc($result);
} catch (Exception $e) {
    Session::addMessageAfterRedirect('Erro ao carregar dados do rádio: ' . $e->getMessage(), true, ERROR);
    Html::redirect('menu.php');
    exit;
}

// Inicializar variáveis com dados atuais do rádio
$form_data = $radio;
$has_errors = false;


// Função para registrar histórico de alterações
function registrarHistorico($DB, $radio_id, $dados_antigos, $dados_novos) {
    $campos_monitorados = [
        'serial' => 'Número de Série',
        'model' => 'Modelo',
        'manufacturers_id' => 'Fabricante',
        'patrimonio' => 'Patrimônio', // otherserial
        'states_id' => 'Status',
        'groups_id' => 'Grupo',
        'users_id' => 'Usuário',
        'locations_id' => 'Localização'
    ];
    
    // Mapear campos do banco para campos do histórico
    $mapeamento_campos = [
        'otherserial' => 'patrimonio'
    ];
    
    $alteracoes_registradas = 0;
    
    foreach ($campos_monitorados as $campo => $descricao) {
        $campo_banco = isset($mapeamento_campos[$campo]) ? $mapeamento_campos[$campo] : $campo;
        $campo_original = array_search($campo_banco, $mapeamento_campos) ?: $campo;
        
        $valor_antigo = $dados_antigos[$campo_original] ?? '';
        $valor_novo = $dados_novos[$campo_original] ?? '';
        
        // Converter valores nulos para strings vazias para comparação
        $valor_antigo = $valor_antigo ?? '';
        $valor_novo = $valor_novo ?? '';
        
        if ($valor_antigo != $valor_novo) {
            try {
                // Buscar nomes descritivos para IDs
                $valor_antigo_descritivo = obterValorDescritivo($DB, $campo_original, $valor_antigo);
                $valor_novo_descritivo = obterValorDescritivo($DB, $campo_original, $valor_novo);
                
                $insert_sql = "INSERT INTO glpi_radios_historico (
                    radios_id, 
                    serial, 
                    model, 
                    manufacturers_id, 
                    patrimonio, 
                    states_id, 
                    groups_id, 
                    users_id, 
                    locations_id, 
                    tecnico_alterou_id, 
                    data_movimentacao, 
                    entities_id
                ) VALUES (
                    $radio_id,
                    '" . $DB->escape($dados_novos['serial']) . "',
                    '" . $DB->escape($dados_novos['model']) . "',
                    " . (int)$dados_novos['manufacturers_id'] . ",
                    '" . $DB->escape($dados_novos['otherserial']) . "',
                    " . (int)$dados_novos['states_id'] . ",
                    " . (int)$dados_novos['groups_id'] . ",
                    " . (int)$dados_novos['users_id'] . ",
                    " . (int)$dados_novos['locations_id'] . ",
                    " . Session::getLoginUserID() . ",
                    NOW(),
                    " . intval($_SESSION['glpiactive_entity']) . "
                )";
                
                $result = $DB->query($insert_sql);
                if ($result) {
                    $alteracoes_registradas++;
                }
                
            } catch (Exception $e) {
                error_log("Erro ao registrar histórico para campo $campo: " . $e->getMessage());
            }
        }
    }
    
    return $alteracoes_registradas;
}

// Função para obter valores descritivos para IDs
function obterValorDescritivo($DB, $campo, $valor) {
    if (empty($valor) || $valor == 0) {
        return 'Não definido';
    }
    
    try {
        switch ($campo) {
            case 'manufacturers_id':
                $result = $DB->query("SELECT name FROM glpi_manufacturers WHERE id = " . (int)$valor);
                if ($result && $DB->numrows($result) > 0) {
                    $row = $DB->fetchAssoc($result);
                    return $row['name'];
                }
                break;
                
            case 'states_id':
                $result = $DB->query("SELECT name FROM glpi_states WHERE id = " . (int)$valor);
                if ($result && $DB->numrows($result) > 0) {
                    $row = $DB->fetchAssoc($result);
                    return $row['name'];
                }
                break;
                
            case 'groups_id':
                $result = $DB->query("SELECT name, completename FROM glpi_groups WHERE id = " . (int)$valor);
                if ($result && $DB->numrows($result) > 0) {
                    $row = $DB->fetchAssoc($result);
                    return !empty($row['completename']) ? $row['completename'] : $row['name'];
                }
                break;
                
            case 'users_id':
                $result = $DB->query("SELECT realname, firstname FROM glpi_users WHERE id = " . (int)$valor);
                if ($result && $DB->numrows($result) > 0) {
                    $row = $DB->fetchAssoc($result);
                    return trim($row['firstname'] . ' ' . $row['realname']);
                }
                break;
                
            case 'locations_id':
                $result = $DB->query("SELECT name FROM glpi_locations WHERE id = " . (int)$valor);
                if ($result && $DB->numrows($result) > 0) {
                    $row = $DB->fetchAssoc($result);
                    return $row['name'];
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar valor descritivo para $campo: " . $e->getMessage());
    }
    
    return $valor;
}

// Processa envio do formulário
if (isset($_POST['update_radio'])) {
    Session::checkRight("config", UPDATE);
    
    
    // Coleta e limpa dados - MANTER OS DADOS DO POST
    $form_data['manufacturers_id'] = (int)($_POST['manufacturers_id'] ?? 0);
    $form_data['model'] = trim($_POST['model'] ?? '');
    $form_data['serial'] = trim($_POST['serial'] ?? '');
    $form_data['otherserial'] = trim($_POST['otherserial'] ?? '');
    $form_data['chave_nf'] = trim($_POST['chave_nf'] ?? '');
    $form_data['comment'] = trim($_POST['comment'] ?? '');
    $form_data['states_id'] = (int)($_POST['states_id'] ?? 0);
    $form_data['users_id'] = (int)($_POST['users_id'] ?? 0);
    $form_data['locations_id'] = (int)($_POST['locations_id'] ?? 0);
    $form_data['groups_id'] = (int)($_POST['groups_id'] ?? 0); // NOVO CAMPO GRUPO
    
    
    // Validações
    if (!$has_errors) {
        $erros = [];
        
        if ($form_data['manufacturers_id'] == 0) {
            $erros[] = "Fabricante é obrigatório";
        }
        
        if (empty($form_data['serial'])) {
            $erros[] = "Número de série é obrigatório";
        } else {
            // Verificar duplicidade de série (excluindo o próprio registro)
            try {
                $check_sql = "SELECT COUNT(*) as total FROM glpi_radios WHERE serial = '" . $DB->escape($form_data['serial']) . "' AND is_deleted = 0 AND id != $radio_id";
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
            // MANTER OS DADOS E NÃO REDIRECIONAR
            $has_errors = true;
            foreach ($erros as $erro) {
                Session::addMessageAfterRedirect($erro, true, ERROR);
            }
        } else {
            // Atualizar no banco
            try {
                $model_esc = $DB->escape($form_data['model']);
                $serial_esc = $DB->escape($form_data['serial']);
                $otherserial_esc = $DB->escape($form_data['otherserial']);
                $chave_nf_esc = $DB->escape($form_data['chave_nf']);
                $comment_esc = $DB->escape($form_data['comment']);
                
                $sql = "UPDATE `glpi_radios` SET 
                        `manufacturers_id` = " . $form_data['manufacturers_id'] . ",
                        `model` = '$model_esc',
                        `serial` = '$serial_esc',
                        `otherserial` = '$otherserial_esc',
                        `chave_nf` = '$chave_nf_esc',
                        `comment` = '$comment_esc',
                        `states_id` = " . $form_data['states_id'] . ",
                        `users_id` = " . $form_data['users_id'] . ",
                        `locations_id` = " . $form_data['locations_id'] . ",
                        `groups_id` = " . $form_data['groups_id'] . ",
                        `date_mod` = NOW()
                        WHERE `id` = $radio_id";
                
                $result = $DB->query($sql);
                
                if ($result) {
                    $alteracoes = registrarHistorico($DB, $radio_id, $radio, $form_data);
                    if ($alteracoes > 0) {
                        Session::addMessageAfterRedirect("Rádio atualizado com sucesso! ($alteracoes alterações registradas)", true, INFO);
                    } else {
                        Session::addMessageAfterRedirect("Rádio atualizado com sucesso! (Nenhuma alteração detectada)", true, INFO);
                    }

                    Html::redirect('menu.php');
                    exit;
                } else {
                    Session::addMessageAfterRedirect('Erro ao atualizar o rádio no banco de dados', true, ERROR);
                    $has_errors = true;
                }
                
            } catch (Exception $e) {
                Session::addMessageAfterRedirect('Erro inesperado: ' . $e->getMessage(), true, ERROR);
                $has_errors = true;
            }
        }
    }
}

Html::header(__('Editar Rádio', 'radios'), $_SERVER['PHP_SELF'], 'plugins', 'radios');

echo "<div class='center'>";
echo "<div style='width: 90%; margin: 20px auto;'>";

// Botão voltar
echo "<div class='spaced'>";
echo "<a href='menu.php' class='btn btn-secondary'>← Voltar à Lista de Rádios</a>";
echo "</div>";

// Título
echo "<h1>✏️ Editar Rádio (ID: $radio_id)</h1>";
echo "<hr>";

// DEBUG - Mostrar dados atuais na tela durante testes
if (defined('DEBUG') && DEBUG) {
    echo "<div style='background: yellow; padding: 10px; margin: 10px 0;'>";
    echo "<strong>DEBUG - Dados do formulário:</strong><br>";
    echo "Fabricante ID: " . $form_data['manufacturers_id'] . "<br>";
    echo "Status ID: " . $form_data['states_id'] . "<br>";
    echo "Usuário ID: " . $form_data['users_id'] . "<br>";
    echo "Localização ID: " . $form_data['locations_id'] . "<br>";
    echo "Grupo ID: " . $form_data['groups_id'] . "<br>";
    echo "</div>";
}

// Formulário
echo "<div class='spaced'>";
echo "<form method='POST' action='' name='form_editar_radio'>";
echo Html::hidden('update_radio', ['value' => 1]);

// CSRF usando método mais compatível
echo "<input type='hidden' name='_glpi_csrf_token' value='".Session::getNewCSRFToken()."' />";

echo "<div style='background: #f8f9fa; padding: 30px; border-radius: 8px;'>";

// Seção 1 - Informações Básicas
echo "<h3 style='color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px;'>📋 Informações Básicas</h3>";

echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

// Fabricante - CORRIGIDO
echo "<tr class='tab_bg_1'>";
echo "<td width='30%'><label><span style='color: red;'>*</span> ".__('Fabricante', 'radios')."</label></td>";
echo "<td>";

// Carregar fabricantes e preparar array corretamente
$manufacturers_options = [];
$manufacturers_options[0] = 'Selecione o fabricante';
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
    error_log("Erro ao carregar fabricantes: " . $e->getMessage());
    $manufacturers_options[0] = 'Erro ao carregar fabricantes';
}

Dropdown::showFromArray('manufacturers_id', $manufacturers_options, [
    'value' => (int)$form_data['manufacturers_id'],
    'used' => [],
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Modelo
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Modelo', 'radios')."</label></td>";
echo "<td>";
echo Html::input('model', [
    'value' => $form_data['model'], 
    'size' => 50
]);
echo "</td>";
echo "</tr>";

// Número de Série
echo "<tr class='tab_bg_1'>";
echo "<td><label><span style='color: red;'>*</span> ".__('Número de Série', 'radios')."</label></td>";
echo "<td>";
echo Html::input('serial', [
    'value' => $form_data['serial'], 
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
    'value' => $form_data['otherserial'], 
    'size' => 50
]);
echo "</td>";
echo "</tr>";

// Chave NF
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Chave da Nota Fiscal', 'radios')."</label></td>";
echo "<td>";
echo Html::input('chave_nf', [
    'value' => $form_data['chave_nf'], 
    'size' => 44,
    'maxlength' => 44
]);
echo "</td>";
echo "</tr>";

echo "</table>";

// Seção 2 - Status e Localização
echo "<h3 style='color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-top: 30px;'>📍 Status e Localização</h3>";

echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

// Status - CORRIGIDO
echo "<tr class='tab_bg_1'>";
echo "<td width='30%'><label>".__('Status', 'radios')."</label></td>";
echo "<td>";

$states_options = [];
$states_options[0] = 'Nenhum';
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
    error_log("Erro ao carregar status: " . $e->getMessage());
    $states_options[0] = 'Erro ao carregar status';
}

Dropdown::showFromArray('states_id', $states_options, [
    'value' => (int)$form_data['states_id'],
    'used' => [],
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Localização - CORRIGIDO
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Localização', 'radios')."</label></td>";
echo "<td>";

$locations_options = [];
$locations_options[0] = 'Nenhuma';
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
    error_log("Erro ao carregar localizações: " . $e->getMessage());
    $locations_options[0] = 'Erro ao carregar localizações';
}

Dropdown::showFromArray('locations_id', $locations_options, [
    'value' => (int)$form_data['locations_id'],
    'used' => [],
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// NOVO CAMPO: Grupo - CORRIGIDO
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Grupo', 'radios')."</label></td>";
echo "<td>";

$groups_options = [];
$groups_options[0] = 'Nenhum';
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
    error_log("Erro ao carregar grupos: " . $e->getMessage());
    $groups_options[0] = 'Erro ao carregar grupos';
}

Dropdown::showFromArray('groups_id', $groups_options, [
    'value' => (int)$form_data['groups_id'],
    'used' => [],
    'display_emptychoice' => false
]);
echo "</td>";
echo "</tr>";

// Usuário - CORRIGIDO
echo "<tr class='tab_bg_1'>";
echo "<td><label>".__('Usuário', 'radios')."</label></td>";
echo "<td>";

$users_options = [];
$users_options[0] = 'Nenhum';
try {
    $users = $DB->request([
        'SELECT' => ['id', 'realname', 'firstname'],
        'FROM' => 'glpi_users',
        'WHERE' => ['is_active' => 1],
        'ORDER' => 'realname'
    ]);
    foreach ($users as $user) {
        $name = trim($user['firstname'] . ' ' . $user['realname']);
        if (empty($name)) {
            $name = "Usuário ID " . $user['id'];
        }
        $users_options[$user['id']] = $name;
    }
} catch (Exception $e) {
    error_log("Erro ao carregar usuários: " . $e->getMessage());
    $users_options[0] = 'Erro ao carregar usuários';
}

Dropdown::showFromArray('users_id', $users_options, [
    'value' => (int)$form_data['users_id'],
    'used' => [],
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
    'value' => $form_data['comment'],
    'rows' => 4,
    'cols' => 50
]);
echo "</td>";
echo "</tr>";

echo "</table>";

// Botões
echo "<div style='text-align: center; margin-top: 30px;'>";
echo Html::submit(_x('button','💾 Atualizar Rádio', 'radios'), ['name' => 'update_radio', 'class' => 'btn btn-success']);
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
