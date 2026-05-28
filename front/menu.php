<?php
// plugins/termos/front/menu.php

include ('../../../inc/includes.php');

// Verificar se está logado
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

// Controle de acesso básico
Session::checkRight("config", READ);

Html::header(__('Sistema de Termos de Responsabilidade', 'termos'), $_SERVER['PHP_SELF'], 'plugins', 'termos');

echo "<div class='center'>";
echo "<div style='width: 95%; margin: 20px auto;'>";

// Título principal
echo "<h1>".__('Sistema de Termos de Responsabilidade', 'termos')."</h1>";
echo "<hr>";

// Botões de ação principal
echo "<div style='margin: 40px 0; text-align: center;'>";
echo "<a href='gerar.php' class='btn btn-success' style='text-decoration: none; padding: 12px 24px; background: #28a745; color: white; border-radius: 4px; margin-right: 15px; font-size: 16px;'>📊 Gerar Termo</a>";
echo "<a href='cabecalho.php' class='btn btn-info' style='text-decoration: none; padding: 12px 24px; background: #17a2b8; color: white; border-radius: 4px; margin-right: 15px; font-size: 16px;'>📋 Configurar Cabeçalho</a>";
echo "<a href='clausulas.php' class='btn btn-warning' style='text-decoration: none; padding: 12px 24px; background: #ffc107; color: #212529; border-radius: 4px; font-size: 16px;'>📜 Gerenciar Cláusulas</a>";
echo "<a href='observacoes.php' class='btn btn-primary' style='margin-left: 15px; text-decoration: none; padding: 12px 24px; background: #007bff; color: #FFFFFF; border-radius: 4px; font-size: 16px;'>📝 Gerenciar Observações</a>";
echo "</div>";

// Mensagem informativa (opcional)
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; border-left: 4px solid #007bff;'>";
echo "<h3 style='color: #495057; margin-bottom: 10px;'>🏠 Menu Principal</h3>";
echo "<p style='color: #6c757d; margin: 0; font-size: 14px;'>Selecione uma das opções acima para acessar as funcionalidades do sistema de termos de responsabilidade.</p>";
echo "</div>";

echo "</div>";
echo "</div>";

Html::footer();
?>