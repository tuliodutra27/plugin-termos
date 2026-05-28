<?php
// plugins/termos/front/gerar.php

include ('../../../inc/includes.php');

// Verificar se está logado
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

// Controle de acesso básico
Session::checkRight("config", READ);

global $DB;

// Processar geração do PDF
if (isset($_POST['gerar_pdf'])) {
    Session::checkRight("config", READ);
    
    // Verificação CSRF
    if (!isset($_POST['_glpi_csrf_token']) || empty($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect('Erro de segurança. Recarregue a página e tente novamente.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    $versao_serie = trim($_POST['versao_serie'] ?? '');
    
    if (empty($versao_serie)) {
        Session::addMessageAfterRedirect('Número de série é obrigatório para gerar o PDF.', true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
    
    try {
        // DEBUG: Log início do processo
        
        // Buscar cabeçalho configurado
        $sql_cabecalho = "SELECT * FROM glpi_termos_cabecalho 
                          WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                          ORDER BY date_creation DESC LIMIT 1";
        
        
        $result_cabecalho = $DB->query($sql_cabecalho);
        
        if (!$result_cabecalho) {
            Session::addMessageAfterRedirect('Erro na consulta do cabeçalho: ' . $DB->error(), true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        if ($DB->numrows($result_cabecalho) == 0) {
            Session::addMessageAfterRedirect('Nenhum cabeçalho configurado. Configure o cabeçalho primeiro.', true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        $cabecalho = $DB->fetchAssoc($result_cabecalho);
        
        // Verificar se TCPDF está disponível
        $tcpdf_paths = [
            GLPI_ROOT . '/lib/tcpdf/tcpdf.php',
            GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php',
            GLPI_ROOT . '/lib/TCPDF/tcpdf.php'
        ];
        
        $tcpdf_found = false;
        $tcpdf_path = '';
        
        foreach ($tcpdf_paths as $path) {
            if (file_exists($path)) {
                $tcpdf_found = true;
                $tcpdf_path = $path;
                break;
            }
        }
        
        if (!$tcpdf_found) {
            foreach ($tcpdf_paths as $path) {
            }
            
            Session::addMessageAfterRedirect('Biblioteca PDF não encontrada no sistema. Verifique se o TCPDF está instalado.', true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        
        require_once($tcpdf_path);
        
        // Criar instância do TCPDF
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        } catch (Exception $tcpdf_e) {
            Session::addMessageAfterRedirect('Erro ao inicializar PDF: ' . $tcpdf_e->getMessage(), true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        // Configurações do documento
        try {
            $pdf->SetCreator('GLPI - Plugin Termos');
            $pdf->SetAuthor('Sistema GLPI');
            $pdf->SetTitle('Termo de Responsabilidade - ' . $versao_serie);
            $pdf->SetSubject('Termo de Responsabilidade');
            
            // Configurações da página
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            $pdf->SetAutoPageBreak(TRUE, 25);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Remover header/footer padrão
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
        } catch (Exception $config_e) {
            Session::addMessageAfterRedirect('Erro ao configurar PDF: ' . $config_e->getMessage(), true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        // Adicionar página
        try {
            $pdf->AddPage();
        } catch (Exception $page_e) {
            Session::addMessageAfterRedirect('Erro ao criar página PDF: ' . $page_e->getMessage(), true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        // HTML do cabeçalho baseado na imagem
        $html = '
<style>
    .header-table {
        width: 100%;
        border: 1px solid #000;
        border-collapse: collapse;
    }
    .header-table td {
        border: 1px solid #000;
        vertical-align: middle;
    }
    .logo-cell {
        width: 15%;
        text-align: center;
        vertical-align: middle;
        height:60px; 
        padding:0;
    }
    .title-cell {
        width: 55%;
        text-align: center;
        vertical-align: middle;
        padding: 0px;
    }
    .info-cell {
        width: 30%;
    }
    .info-table {
        width: 100%;
        border-collapse: collapse;
    }
    .info-table td {
        border: 1px solid #000;
        padding: 2px 4px;
        font-size: 10px;
    }
    .info-label {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
        width: 50%;
    }
    .info-value {
        text-align: center;
        width: 50%;
    }
    .main-title {
        font-weight: bold;
        font-size: 14px;
        margin: 0;
        padding: 0;
        line-height: 0.5;
    }
    .sub-title {
        font-size: 10px;
        margin: 1px 0 0 0;
        padding: 0;
        line-height: 0.2;
    }
</style>
        
<table class="header-table">
    <tr>
        <td class="logo-cell">
         <div style="display:flex; align-items:center; justify-content:center; height:100%; width:100%;">
            ' . (!empty($cabecalho['logo']) 
                ? '<img src="' . htmlspecialchars($cabecalho['logo']) . '"  style="height:60px;">' 
                : '<div style="height:60px; display:flex; align-items:center; justify-content:center; font-size:10px;">LOGO</div>') . '
        </div>
        </td>
        <td class="title-cell">
            <div>
                <tr>
                    <p class="main-title">' . htmlspecialchars($cabecalho['titulo1']) . '</p>
                    <p class="sub-title">' . htmlspecialchars($cabecalho['titulo2']) . '</p>
                </tr>           
            </div>
        </td>
        <td class="info-cell">
            <table class="info-table">
                <tr>
                    <td class="info-label">' . htmlspecialchars($versao_serie) . '</td>
                    <td class="info-label">TI</td>
                </tr>
                <tr>
                    <td class="info-label">Revisão</td>
                    <td class="info-value">' . htmlspecialchars($cabecalho['revisao'] ?: '01') . '</td>
                </tr>
                <tr>
                    <td class="info-label">Página</td>
                    <td class="info-value">' . htmlspecialchars($cabecalho['paginas'] ?: '1') . '/1</td>
                </tr>
                <tr>
                    <td class="info-label">Data</td>
                    <td class="info-value">' . (!empty($cabecalho['data_versao']) ? date('d/m/Y', strtotime($cabecalho['data_versao'])) : date('d/m/Y')) . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>';
        
        // Adicionar conteúdo do termo (corpo do documento)
        $html .= '
        <br><br>
        <div style="text-align: center; font-weight: bold; font-size: 16px; margin: 20px 0;">
            TERMO DE RESPONSABILIDADE
        </div>
        
        <div style="text-align: justify; font-size: 12px; line-height: 1.5; margin: 20px 0;">
            <p>Eu, <strong>_________________________________</strong>, portador do CPF nº <strong>_______________</strong>, 
            ocupante do cargo de <strong>_________________________________</strong>, 
            lotado no setor <strong>' . htmlspecialchars($cabecalho['setor'] ?: '_________________________________') . '</strong>, 
            declaro ter recebido em perfeitas condições de uso e funcionamento os equipamentos/materiais 
            relacionados abaixo, comprometendo-me a:</p>
            
            <ul style="margin-left: 20px;">
                <li>Zelar pela guarda e conservação dos bens recebidos;</li>
                <li>Utilizá-los exclusivamente para fins de trabalho;</li>
                <li>Comunicar imediatamente qualquer defeito, perda, furto ou roubo;</li>
                <li>Devolver os bens quando solicitado ou ao desligar-me da empresa;</li>
                <li>Responsabilizar-me por eventuais danos causados por mau uso.</li>
            </ul>
            
            <p><strong>EQUIPAMENTO/MATERIAL RECEBIDO:</strong></p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                <tr style="background-color: #f0f0f0;">
                    <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">Descrição</td>
                    <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">Série/Patrimônio</td>
                    <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">Estado</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">' . htmlspecialchars($versao_serie) . '</td>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #000; padding: 15px; height: 30px;">&nbsp;</td>
                </tr>
            </table>
            
            <p><strong>Observações:</strong></p>
            <div style="border: 1px solid #000; min-height: 60px; padding: 10px; margin: 10px 0;">
                &nbsp;
            </div>
        </div>
        
        <div style="margin-top: 40px;">
            <table style="width: 100%; margin-top: 50px;">
                <tr>
                    <td style="text-align: center; width: 50%; padding: 10px;">
                        <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 5px;">
                            <strong>Responsável pelo Recebimento</strong><br>
                            Data: ___/___/______
                        </div>
                    </td>
                    <td style="text-align: center; width: 50%; padding: 10px;">
                        <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 5px;">
                            <strong>Responsável pela Entrega</strong><br>
                            Data: ___/___/______
                        </div>
                    </td>
                </tr>
            </table>
        </div>';
        
        // Escrever HTML no PDF
        try {
            
            $pdf->writeHTML($html, true, false, true, false, '');
        } catch (Exception $html_e) {
            Session::addMessageAfterRedirect('Erro ao processar conteúdo do PDF: ' . $html_e->getMessage(), true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        // Gerar nome do arquivo
        $filename = 'termo_responsabilidade_' . preg_replace('/[^a-zA-Z0-9]/', '_', $versao_serie) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Tentar diferentes métodos de saída
        try {
            
            // Método 1: Tentar output direto
            ob_clean(); // Limpar qualquer output anterior
            
            // Headers para download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            echo $pdf->Output('', 'S'); // 'S' = string
            
            exit;
            
        } catch (Exception $output_e) {
            
            // Método 2: Salvar arquivo temporário primeiro
            try {
                $temp_file = tempnam(sys_get_temp_dir(), 'termo_pdf_');
                $pdf_content = $pdf->Output('', 'S');
                file_put_contents($temp_file, $pdf_content);
                
                if (file_exists($temp_file) && filesize($temp_file) > 0) {
                    
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($temp_file));
                    readfile($temp_file);
                    unlink($temp_file); // Remover arquivo temporário
                    
                    exit;
                } else {
                }
                
            } catch (Exception $temp_e) {
            }
            
            // Se chegou até aqui, algo deu errado
            Session::addMessageAfterRedirect('Erro ao gerar download do PDF. Verifique os logs do sistema.', true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
    } catch (Exception $e) {
        Session::addMessageAfterRedirect('Erro ao gerar PDF: ' . $e->getMessage(), true, ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
    }
}

Html::header(__('Gerar Termo de Responsabilidade', 'termos'), $_SERVER['PHP_SELF'], 'plugins', 'termos');

echo "<div class='center'>";
echo "<div style='width: 90%; margin: 20px auto;'>";

// Botão voltar
echo "<div class='spaced'>";
echo "<a href='menu.php' class='btn btn-secondary'>← Voltar ao Menu</a>";
echo "</div>";

// Título
echo "<h1>📊 Gerar Termo de Responsabilidade</h1>";
echo "<hr>";

// Verificar se há cabeçalho configurado
try {
    $sql_check = "SELECT * FROM glpi_termos_cabecalho 
                  WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                  ORDER BY date_creation DESC LIMIT 1";
    
    $result_check = $DB->query($sql_check);
    $has_cabecalho = ($result_check && $DB->numrows($result_check) > 0);
    
    if (!$has_cabecalho) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
        echo "<h4 style='margin: 0 0 10px 0;'>⚠️ Cabeçalho Não Configurado</h4>";
        echo "<p style='margin: 0;'>Antes de gerar um termo, você precisa configurar o cabeçalho. <a href='cabecalho.php' style='color: #721c24; font-weight: bold;'>Clique aqui para configurar</a>.</p>";
        echo "</div>";
    } else {
        // Mostrar cabeçalho atual
        $cabecalho = $DB->fetchAssoc($result_check);
        
        echo "<div style='background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; border-left: 4px solid #17a2b8; margin: 20px 0;'>";
        echo "<h4 style='margin: 0 0 15px 0;'>📋 Cabeçalho Configurado</h4>";
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; font-size: 14px;'>";
        echo "<div><strong>Título 1:</strong> " . htmlspecialchars($cabecalho['titulo1']) . "</div>";
        echo "<div><strong>Título 2:</strong> " . htmlspecialchars($cabecalho['titulo2']) . "</div>";
        if (!empty($cabecalho['setor'])) {
            echo "<div><strong>Setor:</strong> " . htmlspecialchars($cabecalho['setor']) . "</div>";
        }
        if (!empty($cabecalho['revisao'])) {
            echo "<div><strong>Revisão:</strong> " . htmlspecialchars($cabecalho['revisao']) . "</div>";
        }
        echo "</div>";
        echo "</div>";
        
        // Formulário para gerar PDF
        echo "<div class='spaced'>";
        echo "<h3>📄 Gerar PDF do Termo</h3>";
        echo "<form method='POST' action=''>";
        echo Html::hidden('gerar_pdf', ['value' => 1]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo "<div style='background: #f8f9fa; padding: 30px; border-radius: 8px;'>";
        echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

        // Série do documento
        echo "<tr class='tab_bg_1'>";
        echo "<td width='30%'><label><span style='color: red;'>*</span> Série/Código do Documento:</label></td>";
        echo "<td>";
        echo Html::input('versao_serie', [
            'value' => '', 
            'size' => 50,
            'required' => true,
            'placeholder' => 'Ex: ALS.FO.TI.001'
        ]);
        echo "<br><small style='color: #6c757d;'>Este código aparecerá no cabeçalho do PDF gerado</small>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";

        echo "<div style='text-align: center; margin-top: 30px;'>";
        echo Html::submit('📊 Gerar PDF', ['name' => 'gerar_pdf', 'class' => 'btn btn-success', 'style' => 'font-size: 16px; padding: 12px 30px;']);
        echo "</div>";

        echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
        echo "<h4 style='margin: 0 0 10px 0; color: #495057;'>ℹ️ Informações sobre o PDF:</h4>";
        echo "<ul style='margin: 0; padding-left: 20px; color: #6c757d;'>";
        echo "<li>O PDF será gerado em formato A4</li>";
        echo "<li>Contém o cabeçalho configurado no sistema</li>";
        echo "<li>Inclui campos para preenchimento manual</li>";
        echo "<li>Pronto para impressão e assinatura</li>";
        echo "</ul>";
        echo "</div>";

        echo "</div>";
        echo "</form>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; text-align: center;'>Erro ao verificar configurações: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</div>";

Html::footer();
?>