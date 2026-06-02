<?php
// plugins/termos/front/gerar.php


try {
    include('../../../inc/includes.php');

    // Verificação de login mais robusta
    if (!Session::getLoginUserID()) {
        echo "<div style='text-align: center; margin-top: 50px; font-family: Arial, sans-serif;'>";
        echo "<h2 style='color: #dc3545;'>⚠️ Acesso Restrito</h2>";
        echo "<p>Você precisa estar logado no GLPI para acessar esta página.</p>";
        echo "<p><a href='../../../index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fazer Login no GLPI</a></p>";
        echo "</div>";
        exit;
    }

    // Controle de acesso básico
    Session::checkRight("config", READ);

    global $DB;

    // Processar geração do PDF
    if (isset($_GET['gerar_pdf'])) {
        Session::checkRight("config", READ);

        $user_id = intval($_GET['user_id'] ?? 0);
        
        if (empty($user_id)) {
            Session::addMessageAfterRedirect('Seleção de usuário é obrigatória para gerar o PDF.', true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
        
        try {
            // DEBUG: Log início do processo

            // BUSCAR CABEÇALHO PRIMEIRO PARA OBTER A VERSÃO/SÉRIE
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

            // OBTER VERSÃO/SÉRIE DO CABEÇALHO
            $versao_serie = $cabecalho['versao_serie'] ?? '';
            if (empty($versao_serie)) {
                Session::addMessageAfterRedirect('Versão/Série não configurada no cabeçalho. Configure o cabeçalho primeiro.', true, ERROR);
                Html::redirect($_SERVER['REQUEST_URI']);
                exit;
            }

            
            // Buscar dados do usuário selecionado - INCLUINDO COMMENT
            $sql_user = "SELECT realname, firstname, comment FROM glpi_users WHERE id = " . intval($user_id) . " AND is_deleted = 0";
            $result_user = $DB->query($sql_user);
            
            if (!$result_user || $DB->numrows($result_user) == 0) {
                Session::addMessageAfterRedirect('Usuário selecionado não encontrado.', true, ERROR);
                Html::redirect($_SERVER['REQUEST_URI']);
                exit;
            }
            
            $user_data = $DB->fetchAssoc($result_user);
            $nome_completo = trim($user_data['firstname'] . ' ' . $user_data['realname']);
            
            // Extrair CPF e Cargo do campo comment
            $comment = $user_data['comment'] ?? '';
            $cpf = '';
            $cargo = '';
            
            // Extrair CPF usando regex
            if (preg_match('/\[cpf\](.*?)\[\/cpf\]/i', $comment, $matches)) {
                $cpf = trim($matches[1]);
            }
            
            // Extrair Cargo usando regex
            if (preg_match('/\[cargo\](.*?)\[\/cargo\]/i', $comment, $matches)) {
                $cargo = trim($matches[1]);
            }
            
            
            // BUSCAR COMPUTADORES
            
            $sql_computers = "SELECT 
                c.id,
                c.name,
                c.serial,
                c.otherserial,
                c.comment,
                ct.name as tipo_equipamento,
                m.name as fabricante,
                cm.name as modelo
            FROM glpi_computers c
            LEFT JOIN glpi_computertypes ct ON c.computertypes_id = ct.id
            LEFT JOIN glpi_manufacturers m ON c.manufacturers_id = m.id
            LEFT JOIN glpi_computermodels cm ON c.computermodels_id = cm.id
            WHERE c.users_id = " . intval($user_id) . " 
            AND c.is_deleted = 0 
            AND c.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY c.name";
            
            
            $result_computers = $DB->query($sql_computers);
            $computers = [];
            
            if ($result_computers && $DB->numrows($result_computers) > 0) {
                while ($computer = $DB->fetchAssoc($result_computers)) {
                    // Extrair acessórios e valor do comentário do computador
                    $computer_comment = $computer['comment'] ?? '';
                    $acessorios = '';
                    $valor = '';
                    
                    // Extrair acessórios usando regex
                    if (preg_match('/\[acessorios\](.*?)\[\/acessorios\]/i', $computer_comment, $matches)) {
                        $acessorios = trim($matches[1]);
                    }
                    
                    // Extrair valor usando regex
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $computer_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }
                    
                    // Adicionar acessórios e valor ao array do computador
                    $computer['acessorios'] = $acessorios;
                    $computer['valor'] = $valor;
                    $computer['tipo'] = 'Computador';
                    
                    $computers[] = $computer;
                    
                }
            } else {
            }
            
            // BUSCAR MONITORES
            
            $sql_monitors = "SELECT 
                m.id,
                m.name,
                m.serial,
                m.otherserial,
                m.comment,
                mt.name as tipo_equipamento,
                mf.name as fabricante,
                mm.name as modelo,
                m.size
            FROM glpi_monitors m
            LEFT JOIN glpi_monitortypes mt ON m.monitortypes_id = mt.id
            LEFT JOIN glpi_manufacturers mf ON m.manufacturers_id = mf.id
            LEFT JOIN glpi_monitormodels mm ON m.monitormodels_id = mm.id
            WHERE m.users_id = " . intval($user_id) . " 
            AND m.is_deleted = 0 
            AND m.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY m.name";
            
            
            $result_monitors = $DB->query($sql_monitors);
            $monitors = [];
            
            if ($result_monitors && $DB->numrows($result_monitors) > 0) {
                while ($monitor = $DB->fetchAssoc($result_monitors)) {
                    // Extrair acessórios e valor do comentário do monitor
                    $monitor_comment = $monitor['comment'] ?? '';
                    $acessorios = '';
                    $valor = '';
                    
                    // Extrair acessórios usando regex
                    if (preg_match('/\[acessorios\](.*?)\[\/acessorios\]/i', $monitor_comment, $matches)) {
                        $acessorios = trim($matches[1]);
                    }
                    
                    // Extrair valor usando regex
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $monitor_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }
                    
                    // Adicionar acessórios e valor ao array do monitor
                    $monitor['acessorios'] = $acessorios;
                    $monitor['valor'] = $valor;
                    $monitor['tipo'] = 'Monitor';
                    
                    $monitors[] = $monitor;
                    
                }
            } else {
            }
            
            // BUSCAR TELEFONES/CELULARES
            
            $sql_phones = "SELECT 
                p.id,
                p.name,
                p.serial,
                p.otherserial,
                p.comment,
                pt.name as tipo_equipamento,
                mf.name as fabricante,
                pm.name as modelo,
                p.number_line,
                p.brand
            FROM glpi_phones p
            LEFT JOIN glpi_phonetypes pt ON p.phonetypes_id = pt.id
            LEFT JOIN glpi_manufacturers mf ON p.manufacturers_id = mf.id
            LEFT JOIN glpi_phonemodels pm ON p.phonemodels_id = pm.id
            WHERE p.users_id = " . intval($user_id) . " 
            AND p.is_deleted = 0 
            AND p.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY p.name";
            
            
            $result_phones = $DB->query($sql_phones);
            $phones = [];
            
            if ($result_phones && $DB->numrows($result_phones) > 0) {
                while ($phone = $DB->fetchAssoc($result_phones)) {
                    // Extrair acessórios e valor do comentário do telefone
                    $phone_comment = $phone['comment'] ?? '';
                    $acessorios = '';
                    $valor = '';
                    
                    // Extrair acessórios usando regex
                    if (preg_match('/\[acessorios\](.*?)\[\/acessorios\]/i', $phone_comment, $matches)) {
                        $acessorios = trim($matches[1]);
                    }
                    
                    // Extrair valor usando regex
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $phone_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }
                    
                    // Adicionar acessórios e valor ao array do telefone
                    $phone['acessorios'] = $acessorios;
                    $phone['valor'] = $valor;
                    $phone['tipo'] = 'Telefone';
                    
                    $phones[] = $phone;
                    
                }
            } else {
            }
            
            // BUSCAR LINHAS TELEFÔNICAS
            
            $sql_lines = "SELECT 
                l.id,
                l.name,
                l.comment,
                l.caller_num,
                l.caller_name,
                lt.name as linetype
            FROM glpi_lines l
            LEFT JOIN glpi_linetypes lt ON l.linetypes_id = lt.id
            WHERE l.users_id = " . intval($user_id) . " 
            AND l.is_deleted = 0 
            AND l.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY l.name";
            
            
            $result_lines = $DB->query($sql_lines);
            $lines = [];
            
            if ($result_lines && $DB->numrows($result_lines) > 0) {
                while ($line = $DB->fetchAssoc($result_lines)) {
                    // Extrair informações do comentário da linha
                    $line_comment = $line['comment'] ?? '';
                    $prefixo = '';
                    $operadora = '';
                    $tipo_linha = '';
                    $numero_chamado = '';
                    $nome_linha = '';
                    $serial_equip = '';
                    $valor = '';
                    
                    // Extrair prefixo usando regex
                    if (preg_match('/\[prefixo\](.*?)\[\/prefixo\]/i', $line_comment, $matches)) {
                        $prefixo = trim($matches[1]);
                    }
                    
                    // Extrair operadora usando regex
                    if (preg_match('/\[operadora\](.*?)\[\/operadora\]/i', $line_comment, $matches)) {
                        $operadora = trim($matches[1]);
                    }
                    
                    // Extrair tipo usando regex
                    if (preg_match('/\[tipo\](.*?)\[\/tipo\]/i', $line_comment, $matches)) {
                        $tipo_linha = trim($matches[1]);
                    }
                    
                    // Extrair número de chamado usando regex
                    if (preg_match('/\[numero_chamado\](.*?)\[\/numero_chamado\]/i', $line_comment, $matches)) {
                        $numero_chamado = trim($matches[1]);
                    }
                    
                    // Extrair nome usando regex
                    if (preg_match('/\[nome\](.*?)\[\/nome\]/i', $line_comment, $matches)) {
                        $nome_linha = trim($matches[1]);
                    }
                    
                    // Extrair serial do equipamento usando regex
                    if (preg_match('/\[serial_equip\](.*?)\[\/serial_equip\]/i', $line_comment, $matches)) {
                        $serial_equip = trim($matches[1]);
                    }
                    
                    // Extrair valor usando regex
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $line_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }
                    
                    // Adicionar informações extraídas ao array da linha
                    $line['prefixo'] = $prefixo;
                    $line['operadora'] = $operadora;
                    $line['tipo_linha'] = $tipo_linha;
                    $line['numero_chamado'] = $numero_chamado;
                    $line['nome_linha'] = $nome_linha;
                    $line['serial_equip'] = $serial_equip;
                    $line['valor'] = $valor;
                    $line['tipo'] = 'Linha Telefônica';
                    
                    $lines[] = $line;
                    
                }
            } else {
            }
            
            // BUSCAR RÁDIOS
            
            $sql_radios = "SELECT
                r.id,
                r.serial,
                r.otherserial,
                r.comment,
                r.chave_nf,
                m.name as fabricante,
                r.model
            FROM glpi_plugin_radios_radios r
            LEFT JOIN glpi_manufacturers m ON r.manufacturers_id = m.id
            WHERE r.users_id = " . intval($user_id) . "
            AND r.is_deleted = 0
            AND r.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY r.serial";
            
            
            $result_radios = $DB->query($sql_radios);
            $radios = [];
            
            if ($result_radios && $DB->numrows($result_radios) > 0) {
                while ($radio = $DB->fetchAssoc($result_radios)) {
                    // Extrair acessórios e valor do comentário do rádio
                    $radio_comment = $radio['comment'] ?? '';
                    $acessorios = '';
                    $valor = '';
                    
                    // Extrair acessórios usando regex
                    if (preg_match('/\[acessorios\](.*?)\[\/acessorios\]/i', $radio_comment, $matches)) {
                        $acessorios = trim($matches[1]);
                    }
                    
                    // Extrair valor usando regex
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $radio_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }
                    
                    // Adicionar acessórios e valor ao array do rádio
                    $radio['acessorios'] = $acessorios;
                    $radio['valor'] = $valor;
                    $radio['tipo'] = 'Rádio';
                    
                    $radios[] = $radio;
                    
                }
            } else {
            }
            
            // BUSCAR IMPRESSORAS
            $sql_printers = "SELECT
                p.id,
                p.name,
                p.serial,
                p.otherserial,
                p.comment,
                pt.name as tipo_equipamento,
                mf.name as fabricante,
                pm.name as modelo
            FROM glpi_printers p
            LEFT JOIN glpi_printertypes pt ON p.printertypes_id = pt.id
            LEFT JOIN glpi_manufacturers mf ON p.manufacturers_id = mf.id
            LEFT JOIN glpi_printermodels pm ON p.printermodels_id = pm.id
            WHERE p.users_id = " . intval($user_id) . "
            AND p.is_deleted = 0
            AND p.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY p.name";

            $result_printers = $DB->query($sql_printers);
            $printers = [];

            if ($result_printers && $DB->numrows($result_printers) > 0) {
                while ($printer = $DB->fetchAssoc($result_printers)) {
                    $printer_comment = $printer['comment'] ?? '';
                    $acessorios = '';
                    $valor = '';

                    if (preg_match('/\[acessorios\](.*?)\[\/acessorios\]/i', $printer_comment, $matches)) {
                        $acessorios = trim($matches[1]);
                    }
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $printer_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }

                    $printer['acessorios'] = $acessorios;
                    $printer['valor'] = $valor;
                    $printer['tipo'] = 'Impressora';

                    $printers[] = $printer;
                }
            }

            // BUSCAR PERIFÉRICOS
            $sql_peripherals = "SELECT
                p.id,
                p.name,
                p.serial,
                p.otherserial,
                p.comment,
                pt.name as tipo_equipamento,
                mf.name as fabricante,
                pm.name as modelo
            FROM glpi_peripherals p
            LEFT JOIN glpi_peripheraltypes pt ON p.peripheraltypes_id = pt.id
            LEFT JOIN glpi_manufacturers mf ON p.manufacturers_id = mf.id
            LEFT JOIN glpi_peripheralmodels pm ON p.peripheralmodels_id = pm.id
            WHERE p.users_id = " . intval($user_id) . "
            AND p.is_deleted = 0
            AND p.entities_id = " . intval($_SESSION['glpiactive_entity']) . "
            ORDER BY p.name";

            $result_peripherals = $DB->query($sql_peripherals);
            $peripherals = [];

            if ($result_peripherals && $DB->numrows($result_peripherals) > 0) {
                while ($peripheral = $DB->fetchAssoc($result_peripherals)) {
                    $peripheral_comment = $peripheral['comment'] ?? '';
                    $acessorios = '';
                    $valor = '';

                    if (preg_match('/\[acessorios\](.*?)\[\/acessorios\]/i', $peripheral_comment, $matches)) {
                        $acessorios = trim($matches[1]);
                    }
                    if (preg_match('/\[valor\](.*?)\[\/valor\]/i', $peripheral_comment, $matches)) {
                        $valor = trim($matches[1]);
                    }

                    $peripheral['acessorios'] = $acessorios;
                    $peripheral['valor'] = $valor;
                    $peripheral['tipo'] = 'Periférico';

                    $peripherals[] = $peripheral;
                }
            }

            // Combinar todos os equipamentos em um único array
            $all_equipment = array_merge($computers, $monitors, $phones, $lines, $radios, $printers, $peripherals);
            
            
            // BUSCAR OBSERVAÇÕES DA TABELA
            
            $sql_observacoes = "SELECT * FROM glpi_plugin_termo_observacoes 
                                WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                                ORDER BY indice ASC";
            
            
            $result_observacoes = $DB->query($sql_observacoes);
            
            $observacoes = [];
            if ($result_observacoes && $DB->numrows($result_observacoes) > 0) {
                while ($obs = $DB->fetchAssoc($result_observacoes)) {
                    $observacoes[$obs['indice']] = $obs['texto'];
                }
            }
            
            // BUSCAR CLÁUSULAS DA TABELA
            
            $sql_clausulas = "SELECT * FROM glpi_plugin_termo_clausulas 
                              WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                              ORDER BY indice ASC";
            
            
            $result_clausulas = $DB->query($sql_clausulas);
            
            $clausulas_html = '';
            $clausulas_count = 0;
            
            if ($result_clausulas && $DB->numrows($result_clausulas) > 0) {
                
                while ($clausula = $DB->fetchAssoc($result_clausulas)) {
                    $clausulas_count++;
                    $clausulas_html .= '<li>' . htmlspecialchars($clausula['texto']) . '</li>' . "\n                ";
                    
                }
            } else {
                
                // Cláusulas padrão caso não haja nenhuma cadastrada
                $clausulas_html = '
                    <li>Zelar pela guarda e conservação dos bens recebidos;</li>
                    <li>Utilizá-los exclusivamente para fins de trabalho;</li>';
                
                $clausulas_html .= '
                    <li>Responsabilizar-me por eventuais danos causados por mau uso.</li>';
            }
            
            
            // Verificar se TCPDF está disponível
            $tcpdf_paths = [
                GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php',
                GLPI_ROOT . '/lib/tcpdf/tcpdf.php',
                GLPI_ROOT . '/lib/TCPDF/tcpdf.php',
                GLPI_ROOT . '/plugins/termos/lib/tcpdf/tcpdf.php'
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
            
            
            // Incluir TCPDF com tratamento de erro
            if (!class_exists('TCPDF')) {
                require_once($tcpdf_path);
            }
            
            // Verificar se a classe foi carregada
            if (!class_exists('TCPDF')) {
                Session::addMessageAfterRedirect('Erro ao carregar biblioteca PDF.', true, ERROR);
                Html::redirect($_SERVER['REQUEST_URI']);
                exit;
            }
            
            // Classe customizada para rodapé personalizado
            class CustomTCPDF extends TCPDF {
                private $cabecalhoData = null;
                private $versaoSerie = '';
                
                // Método para definir os dados do cabeçalho
                public function setCabecalhoData($cabecalho, $versao_serie) {
                    $this->cabecalhoData = $cabecalho;
                    $this->versaoSerie = $versao_serie;
                }
                
                // Método para criar o rodapé personalizado
                public function Footer() {
                    // Posicionar o rodapé a 15mm do final da página
                    $this->SetY(-15);
                    
                    // Definir fonte para o rodapé
                    $this->SetFont('arial', '', 7);
                    
                    // Linha separadora
                    $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
                    
                    // Avançar um pouco após a linha
                    $this->SetY($this->GetY() + 3);
                    
                    // Preparar dados do rodapé
                    $esquerda_texto = '';
                    $centro_texto = '';
                    $direita_texto = '';
                    
                    // ESQUERDA: versao/serie + revisão
                    if ($this->cabecalhoData) {
                        $revisao = isset($this->cabecalhoData['revisao']) ? $this->cabecalhoData['revisao'] : '01';
                        $esquerda_texto = htmlspecialchars($this->versaoSerie) . ' - Rev. ' . htmlspecialchars($revisao);
                    } else {
                        $esquerda_texto = htmlspecialchars($this->versaoSerie) . ' - Rev. 01';
                    }
                    
                    // CENTRO: Data da versão do cabeçalho
                    if ($this->cabecalhoData && !empty($this->cabecalhoData['data_versao'])) {
                        $centro_texto = date('d/m/Y', strtotime($this->cabecalhoData['data_versao']));
                    } else {
                        $centro_texto = date('d/m/Y');
                    }
                    
                    // DIREITA: Paginação
                    $direita_texto = $this->getAliasNumPage() . '/' . $this->getAliasNbPages();
                    
                    // Criar tabela para o rodapé com 3 colunas
                    $html = '
                    <table style="width: 100%; font-size: 9px; margin: 0; padding: 0;">
                        <tr>
                            <td style="text-align: left; width: 33%; padding: 2px;">
                                <strong>' . $esquerda_texto . '</strong>
                            </td>
                            <td style="text-align: center; width: 34%; padding: 2px;">
                                <strong>' . $centro_texto . '</strong>
                            </td>
                            <td style="text-align: right; width: 33%; padding: 2px;">
                                <strong>' . $direita_texto . '</strong>
                            </td>
                        </tr>
                    </table>';
                    
                    $this->writeHTML($html, true, false, true, false, '');
                }
            }
            
            // Criar instância do TCPDF customizado
            try {
                
                // Verificar se as constantes estão definidas
                if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
                if (!defined('PDF_UNIT')) define('PDF_UNIT', 'mm');
                if (!defined('PDF_PAGE_FORMAT')) define('PDF_PAGE_FORMAT', 'A4');
                if (!defined('PDF_FONT_MONOSPACED')) define('PDF_FONT_MONOSPACED', 'courier');
                if (!defined('PDF_IMAGE_SCALE_RATIO')) define('PDF_IMAGE_SCALE_RATIO', 1.25);
                
                $pdf = new CustomTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

                // IMPORTANTE: Configurar os dados do cabeçalho APÓS criar a instância
                $pdf->setCabecalhoData($cabecalho, $versao_serie);
                
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
                $pdf->SetAutoPageBreak(TRUE, 20); // 20mm para dar espaço ao rodapé
                $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
                
                // Configurar rodapé customizado - ativar
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(true);
                
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
            
            // Tratamento da imagem do logo
            $logo_html = '<div style="height:60px; display:flex; align-items:center; justify-content:center; font-size:10px;">LOGO</div>';
            if (!empty($cabecalho['logo'])) {
                $logo_path = $cabecalho['logo'];
                
                // Verificar se é URL válida ou arquivo existe
                if (filter_var($logo_path, FILTER_VALIDATE_URL)) {
                    $logo_html = '<img src="' . htmlspecialchars($logo_path) . '" style="height:60px;">';
                } elseif (file_exists($logo_path)) {
                    $logo_html = '<img src="' . htmlspecialchars($logo_path) . '" style="height:60px;">';
                } else {
                    // Tentar caminho absoluto
                    $absolute_path = GLPI_ROOT . '/' . ltrim($logo_path, '/');
                    if (file_exists($absolute_path)) {
                        $logo_html = '<img src="' . htmlspecialchars($absolute_path) . '" style="height:60px;">';
                    }
                }
            }
            
            // HTML do cabeçalho
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
            width: 85%;
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
                ' . $logo_html . '
            </div>
            </td>
            <td class="title-cell">
                <div>
                    <tr>
                        <p class="main-title">' . htmlspecialchars($cabecalho['titulo1'] ?? 'TÍTULO 1') . '</p>
                        <p class="sub-title">' . htmlspecialchars($cabecalho['titulo2'] ?? 'TÍTULO 2') . '</p>
                    </tr>           
                </div>
            </td>
        </tr>
    </table>';
            
            // Adicionar conteúdo do termo (corpo do documento) - AGORA COM CPF E CARGO EXTRAÍDOS
            $html .= '
            <div style="text-align: justify; font-size: 12px; line-height: 1.2; margin: 20px 0;">';
            
            // Definir valores com fallback para campos em branco
            $cpf_display = !empty($cpf) ? htmlspecialchars($cpf) : '_';
            $cargo_display = !empty($cargo) ? htmlspecialchars($cargo) : '_';
            
            $html .= 
            '<p style="text-transform: uppercase;">EU, <strong>' . htmlspecialchars($nome_completo) . '</strong> PORTADOR DO CPF Nº <strong>' . $cpf_display . '</strong>
                EXERCENDO A ATIVIDADE DE <strong>' . $cargo_display . '</strong> NA ALISEO EMPREENDIMENTOS E PARTICIPAÇÕES S.A.
                DECLARO PARA OS DEVIDOS FINS, TER RECEBIDO NESTA DATA, DA ALISEO EMPREENDIMENTOS E PARTICIPAÇOES S.A., 
            </p>
                
                <p>' . htmlspecialchars($observacoes[1] ?? '') . '</p>
                
                <ol style="margin-left: 20px;">
                    ' . $clausulas_html . '
                </ol>

                <p>' . htmlspecialchars($observacoes[2] ?? '') . '</p>
                <p>' . htmlspecialchars($observacoes[3] ?? '') . '</p>
                
                <p><strong>EQUIPAMENTO/MATERIAL RECEBIDO:</strong></p>';
                
            // SEÇÃO ATUALIZADA COM AS MODIFICAÇÕES SOLICITADAS: Tabela com equipamentos do usuário
            if (!empty($all_equipment)) {
                $html .= '
                <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                    <tr style="background-color: #f0f0f0; text-align: center;">
                        <td style="width: 85%; border: 1px solid #000; padding: 4px; font-weight: bold; height: 15px;">Descrição do Item</td>
                        <td style="width: 15%; border: 1px solid #000; padding: 4px; font-weight: bold; height: 15px;">Valor (R$)</td>
                    </tr>';
                
                foreach ($all_equipment as $equipment) {
                    // Concatenar informações baseado no tipo de equipamento - COM AS MODIFICAÇÕES SOLICITADAS
                    $descricao_partes = [];
                    
                    if ($equipment['tipo'] === 'Computador') {
                        // MODIFICAÇÃO: Remover a palavra "Computador" e começar direto com tipo_equipamento (Desktop/Notebook)
                        if (!empty($equipment['tipo_equipamento'])) {
                            $descricao_partes[] = $equipment['tipo_equipamento'];
                        }
                        if (!empty($equipment['fabricante'])) {
                            $descricao_partes[] = $equipment['fabricante'];
                        }
                        if (!empty($equipment['modelo'])) {
                            $descricao_partes[] = $equipment['modelo'];
                        }
                        if (!empty($equipment['serial'])) {
                            $descricao_partes[] = $equipment['serial'];
                        }
                        // Adicionar acessórios após o serial
                        if (!empty($equipment['acessorios'])) {
                            $descricao_partes[] = $equipment['acessorios'];
                        }
                        
                    } elseif ($equipment['tipo'] === 'Telefone') {
                        // MODIFICAÇÃO: Remover a palavra "Telefone" e começar direto com tipo_equipamento (Celular/Tablet)
                        if (!empty($equipment['tipo_equipamento'])) {
                            $descricao_partes[] = $equipment['tipo_equipamento'];
                        }
                        if (!empty($equipment['fabricante'])) {
                            $descricao_partes[] = $equipment['fabricante'];
                        } elseif (!empty($equipment['brand'])) {
                            $descricao_partes[] = $equipment['brand'];
                        }
                        if (!empty($equipment['modelo'])) {
                            $descricao_partes[] = $equipment['modelo'];
                        }
                        // Adicionar número da linha para telefones
                        if (!empty($equipment['number_line'])) {
                            $descricao_partes[] = 'Tel: ' . $equipment['number_line'];
                        }
                        if (!empty($equipment['serial'])) {
                            $descricao_partes[] = $equipment['serial'];
                        }
                        // Adicionar acessórios após o serial
                        if (!empty($equipment['acessorios'])) {
                            $descricao_partes[] = $equipment['acessorios'];
                        }
                        
                    } elseif ($equipment['tipo'] === 'Linha Telefônica') {
                        // MODIFICAÇÃO: Para chips/linhas, só o conteúdo das tags, sem prefixos como "Prefixo:", "Tipo:"
                        if (!empty($equipment['prefixo'])) {
                            $descricao_partes[] = $equipment['prefixo'];
                        }
                        if (!empty($equipment['operadora'])) {
                            $descricao_partes[] = $equipment['operadora'];
                        }
                        if (!empty($equipment['tipo_linha'])) {
                            $descricao_partes[] = $equipment['tipo_linha'];
                        } elseif (!empty($equipment['linetype'])) {
                            $descricao_partes[] = $equipment['linetype'];
                        }
                        if (!empty($equipment['numero_chamado'])) {
                            $descricao_partes[] = $equipment['numero_chamado'];
                        } elseif (!empty($equipment['caller_num'])) {
                            $descricao_partes[] = $equipment['caller_num'];
                        }
                        if (!empty($equipment['nome_linha'])) {
                            $descricao_partes[] = $equipment['nome_linha'];
                        } elseif (!empty($equipment['caller_name'])) {
                            $descricao_partes[] = $equipment['caller_name'];
                        }
                        if (!empty($equipment['serial_equip'])) {
                            $descricao_partes[] = $equipment['serial_equip'];
                        }
                        
                    } elseif ($equipment['tipo'] === 'Rádio') {
                        // Para rádios, usar os campos específicos da tabela glpi_radios
                        $descricao_partes[] = 'Rádio';
                        
                        if (!empty($equipment['fabricante'])) {
                            $descricao_partes[] = $equipment['fabricante'];
                        }
                        
                        if (!empty($equipment['model'])) {
                            $descricao_partes[] = $equipment['model'];
                        }
                        
                        if (!empty($equipment['serial'])) {
                            $descricao_partes[] = $equipment['serial'];
                        }
                        
                        // Adicionar acessórios após o serial
                        if (!empty($equipment['acessorios'])) {
                            $descricao_partes[] = $equipment['acessorios'];
                        }
                        
                    } elseif ($equipment['tipo'] === 'Impressora') {
                        if (!empty($equipment['tipo_equipamento'])) {
                            $descricao_partes[] = $equipment['tipo_equipamento'];
                        } else {
                            $descricao_partes[] = 'Impressora';
                        }
                        if (!empty($equipment['fabricante'])) {
                            $descricao_partes[] = $equipment['fabricante'];
                        }
                        if (!empty($equipment['modelo'])) {
                            $descricao_partes[] = $equipment['modelo'];
                        }
                        if (!empty($equipment['serial'])) {
                            $descricao_partes[] = $equipment['serial'];
                        }
                        if (!empty($equipment['acessorios'])) {
                            $descricao_partes[] = $equipment['acessorios'];
                        }

                    } elseif ($equipment['tipo'] === 'Periférico') {
                        if (!empty($equipment['tipo_equipamento'])) {
                            $descricao_partes[] = $equipment['tipo_equipamento'];
                        } else {
                            $descricao_partes[] = 'Periférico';
                        }
                        if (!empty($equipment['fabricante'])) {
                            $descricao_partes[] = $equipment['fabricante'];
                        }
                        if (!empty($equipment['modelo'])) {
                            $descricao_partes[] = $equipment['modelo'];
                        }
                        if (!empty($equipment['serial'])) {
                            $descricao_partes[] = $equipment['serial'];
                        }
                        if (!empty($equipment['acessorios'])) {
                            $descricao_partes[] = $equipment['acessorios'];
                        }

                    } else {
                        // Para outros equipamentos (monitor, etc) - mantém o padrão original
                        if (!empty($equipment['tipo'])) {
                            $descricao_partes[] = $equipment['tipo'];
                        }
                        
                        if (!empty($equipment['tipo_equipamento'])) {
                            $descricao_partes[] = $equipment['tipo_equipamento'];
                        }
                        if (!empty($equipment['fabricante'])) {
                            $descricao_partes[] = $equipment['fabricante'];
                        }
                        if (!empty($equipment['modelo'])) {
                            $descricao_partes[] = $equipment['modelo'];
                        }
                        
                        // Adicionar tamanho para monitores
                        if ($equipment['tipo'] === 'Monitor' && !empty($equipment['size']) && $equipment['size'] > 0) {
                            $descricao_partes[] = $equipment['size'] . '"';
                        }
                        
                        if (!empty($equipment['serial'])) {
                            $descricao_partes[] = $equipment['serial'];
                        }
                        
                        // Adicionar acessórios após o serial
                        if (!empty($equipment['acessorios'])) {
                            $descricao_partes[] = $equipment['acessorios'];
                        }
                    }
                    
                    $descricao = !empty($descricao_partes) ? implode(' - ', $descricao_partes) : $equipment['tipo'] ?? 'Equipamento';
                    
                    // Usar valor do comentário ou padrão
                    $valor_display = !empty($equipment['valor']) ? htmlspecialchars($equipment['valor']) : 'R$ 10.000,00';
                    
                    $html .= '
                    <tr style="vertical-align: middle;">
                        <td style="border: 1px solid #000; height: 10px; vertical-align: middle;">' . htmlspecialchars($descricao) . '</td>
                        <td style="border: 1px solid #000; height: 10px; text-align: center; vertical-align: middle;">' . $valor_display . '</td>
                    </tr>';
                }
                
                $html .= '</table>';
            } else {
                // Tabela original com campos em branco se não houver equipamentos
                $html .= '
                <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                    <tr style="background-color: #f0f0f0;">
                        <td style="border: 1px solid #000; padding: 20px; font-weight: bold; height: 20px;">Descrição</td>
                        <td style="border: 1px solid #000; padding: 4px; font-weight: bold; height: 20px;">Valor</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 3px; height: 25px; vertical-align: middle;">&nbsp;</td>
                        <td style="border: 1px solid #000; padding: 3px; height: 25px; vertical-align: middle;">' . htmlspecialchars($versao_serie) . '</td>
                    </tr>
                </table>';
            }
                
            $html .= '
            </div>
            
            <div style="margin-top: 40px;">
                <table style="width: 100%; margin-top: 50px;">
                    <tr>
                        <td style="text-align: center; width: 50%; padding: 10px;">
                            <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 5px;">
                                <strong>Data e Assinatura (Recebimento)</strong><br>
                            </div>
                        </td>
                        <td style="text-align: center; width: 50%; padding: 10px;">
                            <div style="border-top: 1px solid #000; margin-top: 60px; padding-top: 5px;">
                                <strong>Data e Assinatura (Devolução)</strong><br>
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
            $filename = 'termo_responsabilidade_' . preg_replace('/[^a-zA-Z0-9]/', '', $versao_serie) . '_' . date('Y-m-d_H-i-s') . '.pdf';
            
            // Limpar buffer de saída antes de enviar PDF
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Tentar gerar o PDF
            try {
                
                // Headers para download
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                echo $pdf->Output($filename, 'S'); // 'S' = String
                
                exit;
                
            } catch (Exception $output_e) {
                Session::addMessageAfterRedirect('Erro ao gerar download do PDF: ' . $output_e->getMessage(), true, ERROR);
                Html::redirect($_SERVER['REQUEST_URI']);
                exit;
            }
            
        } catch (Exception $e) {
            Session::addMessageAfterRedirect('Erro ao gerar PDF: ' . $e->getMessage(), true, ERROR);
            Html::redirect($_SERVER['REQUEST_URI']);
            exit;
        }
    }

    Html::header('Gerar Termo de Responsabilidade', $_SERVER['PHP_SELF'], 'plugins', 'termos');

    echo "<div class='center'>";
    echo "<div style='width: 90%; margin: 20px auto;'>";

    // Botão voltar
    echo "<div class='spaced'>";
    echo "<a href='menu.php' class='btn btn-secondary'>← Voltar ao Menu</a>";
    echo "</div>";

    // Título
    echo "<h1>Gerar Termo de Responsabilidade</h1>";
    echo "<hr>";

    // Verificar se há cabeçalho configurado
    try {
        // Verificar se a tabela existe primeiro
        $table_check = $DB->query("SHOW TABLES LIKE 'glpi_termos_cabecalho'");
        if (!$table_check || $DB->numrows($table_check) == 0) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
            echo "<h4 style='margin: 0 0 10px 0;'>Tabela Não Encontrada</h4>";
            echo "<p style='margin: 0;'>A tabela de cabeçalhos não foi encontrada. Verifique se o plugin foi instalado corretamente.</p>";
            echo "</div>";
        } else {
            $sql_check = "SELECT * FROM glpi_termos_cabecalho 
                          WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                          ORDER BY date_creation DESC LIMIT 1";
            
            $result_check = $DB->query($sql_check);
            $has_cabecalho = ($result_check && $DB->numrows($result_check) > 0);
            
            if (!$has_cabecalho) {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
                echo "<h4 style='margin: 0 0 10px 0;'>Cabeçalho Não Configurado</h4>";
                echo "<p style='margin: 0;'>Antes de gerar um termo, você precisa configurar o cabeçalho. <a href='cabecalho.php' style='color: #721c24; font-weight: bold;'>Clique aqui para configurar</a>.</p>";
                echo "</div>";
            } else {
                // Mostrar cabeçalho atual
                $cabecalho = $DB->fetchAssoc($result_check);
                
                echo "<div style='background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; border-left: 4px solid #17a2b8; margin: 20px 0;'>";
                echo "<h4 style='margin: 0 0 15px 0;'>Cabeçalho Configurado</h4>";
                echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; font-size: 14px;'>";
                echo "<div><strong>Título 1:</strong> " . htmlspecialchars($cabecalho['titulo1'] ?? '') . "</div>";
                echo "<div><strong>Título 2:</strong> " . htmlspecialchars($cabecalho['titulo2'] ?? '') . "</div>";
                // MODIFICAÇÃO: Agora mostra a versão/série do cabeçalho
                if (!empty($cabecalho['versao_serie'])) {
                    echo "<div><strong>Versão/Série:</strong> " . htmlspecialchars($cabecalho['versao_serie']) . "</div>";
                }
                if (!empty($cabecalho['setor'])) {
                    echo "<div><strong>Setor:</strong> " . htmlspecialchars($cabecalho['setor']) . "</div>";
                }
                if (!empty($cabecalho['revisao'])) {
                    echo "<div><strong>Revisão:</strong> " . htmlspecialchars($cabecalho['revisao']) . "</div>";
                }
                echo "</div>";
                echo "</div>";
                
                // NOVA SEÇÃO: Mostrar observações cadastradas
                echo "<div style='background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
                echo "<h4 style='margin: 0 0 15px 0;'>Observações Cadastradas</h4>";
                
                $sql_observacoes_preview = "SELECT * FROM glpi_plugin_termo_observacoes 
                                            WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                                            ORDER BY indice ASC";
                
                $result_observacoes_preview = $DB->query($sql_observacoes_preview);
                
                if ($result_observacoes_preview && $DB->numrows($result_observacoes_preview) > 0) {
                    echo "<div style='font-size: 14px;'>";
                    echo "<p style='margin: 0 0 10px 0;'><strong>Total de observações:</strong> " . $DB->numrows($result_observacoes_preview) . "</p>";
                    echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
                    
                    while ($obs_preview = $DB->fetchAssoc($result_observacoes_preview)) {
                        $texto_resumido = strlen($obs_preview['texto']) > 80 ? 
                                          substr($obs_preview['texto'], 0, 80) . '...' : 
                                          $obs_preview['texto'];
                        $posicao_desc = '';
                        switch($obs_preview['indice']) {
                            case 1:
                                $posicao_desc = ' (antes das cláusulas)';
                                break;
                            case 2:
                            case 3:
                                $posicao_desc = ' (após cláusula 4)';
                                break;
                            default:
                                $posicao_desc = '';
                        }
                        echo "<li style='margin: 5px 0;'><strong>Índice " . $obs_preview['indice'] . "</strong>" . $posicao_desc . ": " . htmlspecialchars($texto_resumido) . "</li>";
                    }
                    echo "</ul>";
                    echo "</div>";
                } else {
                    echo "<p style='margin: 0; font-style: italic;'>Nenhuma observação cadastrada.</p>";
                }
                echo "</div>";
                
                // NOVA SEÇÃO: Mostrar cláusulas cadastradas
                echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;'>";
                echo "<h4 style='margin: 0 0 15px 0;'>Cláusulas Cadastradas</h4>";
                
                $sql_clausulas_preview = "SELECT * FROM glpi_plugin_termo_clausulas 
                                          WHERE is_deleted = 0 AND entities_id = " . intval($_SESSION['glpiactive_entity']) . "
                                          ORDER BY indice ASC";
                
                $result_clausulas_preview = $DB->query($sql_clausulas_preview);
                
                if ($result_clausulas_preview && $DB->numrows($result_clausulas_preview) > 0) {
                    echo "<div style='font-size: 14px;'>";
                    echo "<p style='margin: 0 0 10px 0;'><strong>Total de cláusulas:</strong> " . $DB->numrows($result_clausulas_preview) . "</p>";
                    echo "<ol style='margin: 10px 0; padding-left: 20px;'>";
                    
                    $count = 0;
                    while ($clausula_preview = $DB->fetchAssoc($result_clausulas_preview)) {
                        $count++;
                        $texto_resumido = strlen($clausula_preview['texto']) > 80 ? 
                                          substr($clausula_preview['texto'], 0, 80) . '...' : 
                                          $clausula_preview['texto'];
                        echo "<li style='margin: 5px 0;'>" . htmlspecialchars($texto_resumido) . "</li>";
                        
                        // Limitar exibição a 5 cláusulas para não sobrecarregar a interface
                        if ($count >= 5) {
                            $restantes = $DB->numrows($result_clausulas_preview) - 5;
                            if ($restantes > 0) {
                                echo "<li style='margin: 5px 0; font-style: italic; color: #666;'>... e mais {$restantes} cláusula(s)</li>";
                            }
                            break;
                        }
                    }
                    echo "</ol>";
                    echo "</div>";
                } else {
                    echo "<p style='margin: 0; font-style: italic;'>Nenhuma cláusula cadastrada. Serão usadas as cláusulas padrão do sistema.</p>";
                }
                echo "</div>";
                
                // Formulário para gerar PDF - MODIFICADO SEM CAMPO VERSÃO/SÉRIE
                echo "<div class='spaced'>";
                echo "<h3>Gerar PDF do Termo</h3>";
                echo "<form method='GET' action=''>";
                echo Html::hidden('gerar_pdf', ['value' => 1]);

                echo "<div style='background: #f8f9fa; padding: 30px; border-radius: 8px;'>";
                echo "<table class='tab_cadre_fixe' style='width: 100%;'>";

                // Seleção de usuário
                echo "<tr class='tab_bg_1'>";
                echo "<td width='30%'><label><span style='color: red;'>*</span> Selecionar Usuário:</label></td>";
                echo "<td>";
                
                // Buscar todos os usuários ativos COM COMMENT
                $sql_users = "SELECT id, realname, firstname, comment FROM glpi_users 
                              WHERE is_deleted = 0 AND is_active = 1 
                              ORDER BY realname, firstname";
                $result_users = $DB->query($sql_users);
                
                echo "<select name='user_id' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
                echo "<option value=''>-- Selecione um usuário --</option>";
                
                if ($result_users && $DB->numrows($result_users) > 0) {
                    while ($user = $DB->fetchAssoc($result_users)) {
                        $nome_completo = trim($user['firstname'] . ' ' . $user['realname']);
                        
                        // Extrair informações adicionais para exibição no dropdown
                        $comment = $user['comment'] ?? '';
                        $cpf_info = '';
                        $cargo_info = '';
                        
                        // Extrair CPF para exibição
                        if (preg_match('/\[cpf\](.*?)\[\/cpf\]/i', $comment, $matches)) {
                            $cpf_info = ' - CPF: ' . trim($matches[1]);
                        }
                        
                        // Extrair Cargo para exibição
                        if (preg_match('/\[cargo\](.*?)\[\/cargo\]/i', $comment, $matches)) {
                            $cargo_info = ' - ' . trim($matches[1]);
                        }
                        
                        // Buscar quantos computadores, monitores, telefones, linhas e rádios o usuário tem
                        $sql_count_computers = "SELECT COUNT(*) as total FROM glpi_computers 
                                               WHERE users_id = " . intval($user['id']) . " 
                                               AND is_deleted = 0 
                                               AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        
                        $result_count_computers = $DB->query($sql_count_computers);
                        $computer_count = 0;
                        
                        if ($result_count_computers && $DB->numrows($result_count_computers) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_computers);
                            $computer_count = $count_data['total'];
                        }
                        
                        $sql_count_monitors = "SELECT COUNT(*) as total FROM glpi_monitors 
                                              WHERE users_id = " . intval($user['id']) . " 
                                              AND is_deleted = 0 
                                              AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        
                        $result_count_monitors = $DB->query($sql_count_monitors);
                        $monitor_count = 0;
                        
                        if ($result_count_monitors && $DB->numrows($result_count_monitors) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_monitors);
                            $monitor_count = $count_data['total'];
                        }
                        
                        $sql_count_phones = "SELECT COUNT(*) as total FROM glpi_phones 
                                            WHERE users_id = " . intval($user['id']) . " 
                                            AND is_deleted = 0 
                                            AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        
                        $result_count_phones = $DB->query($sql_count_phones);
                        $phone_count = 0;
                        
                        if ($result_count_phones && $DB->numrows($result_count_phones) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_phones);
                            $phone_count = $count_data['total'];
                        }
                        
                        $sql_count_lines = "SELECT COUNT(*) as total FROM glpi_lines 
                                           WHERE users_id = " . intval($user['id']) . " 
                                           AND is_deleted = 0 
                                           AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        
                        $result_count_lines = $DB->query($sql_count_lines);
                        $line_count = 0;
                        
                        if ($result_count_lines && $DB->numrows($result_count_lines) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_lines);
                            $line_count = $count_data['total'];
                        }
                        
                        // NOVO: Buscar quantos rádios o usuário tem
                        $sql_count_radios = "SELECT COUNT(*) as total FROM glpi_plugin_radios_radios
                                            WHERE users_id = " . intval($user['id']) . "
                                            AND is_deleted = 0
                                            AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        
                        $result_count_radios = $DB->query($sql_count_radios);
                        $radio_count = 0;
                        
                        if ($result_count_radios && $DB->numrows($result_count_radios) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_radios);
                            $radio_count = $count_data['total'];
                        }
                        
                        $sql_count_printers = "SELECT COUNT(*) as total FROM glpi_printers
                                              WHERE users_id = " . intval($user['id']) . "
                                              AND is_deleted = 0
                                              AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        $result_count_printers = $DB->query($sql_count_printers);
                        $printer_count = 0;
                        if ($result_count_printers && $DB->numrows($result_count_printers) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_printers);
                            $printer_count = $count_data['total'];
                        }

                        $sql_count_peripherals = "SELECT COUNT(*) as total FROM glpi_peripherals
                                                 WHERE users_id = " . intval($user['id']) . "
                                                 AND is_deleted = 0
                                                 AND entities_id = " . intval($_SESSION['glpiactive_entity']);
                        $result_count_peripherals = $DB->query($sql_count_peripherals);
                        $peripheral_count = 0;
                        if ($result_count_peripherals && $DB->numrows($result_count_peripherals) > 0) {
                            $count_data = $DB->fetchAssoc($result_count_peripherals);
                            $peripheral_count = $count_data['total'];
                        }

                        $total_equipment = $computer_count + $monitor_count + $phone_count + $line_count + $radio_count + $printer_count + $peripheral_count;
                        $equipment_info = $total_equipment > 0 ? " ({$computer_count} comp., {$monitor_count} mon., {$phone_count} tel., {$line_count} linhas, {$radio_count} rádios, {$printer_count} impr., {$peripheral_count} perif.)" : " (sem equipamentos)";
                        
                        echo "<option value='" . $user['id'] . "'>" . htmlspecialchars($nome_completo . $cargo_info . $cpf_info . $equipment_info) . "</option>";
                    }
                }
                
                echo "</select>";
                echo "<br><small style='color: #6c757d;'>Selecione o usuário que receberá o equipamento/material (CPF, Cargo, Computadores, Monitores, Telefones, Linhas, Rádios, Impressoras e Periféricos serão incluídos automaticamente)</small>";
                echo "</td>";
                echo "</tr>";

                echo "</table>";

                echo "<div style='text-align: center; margin-top: 30px;'>";
                echo Html::submit('Gerar PDF', ['name' => 'gerar_pdf', 'class' => 'btn btn-success', 'style' => 'font-size: 16px; padding: 12px 30px;']);
                echo "</div>";

                echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
                echo "<h4 style='margin: 0 0 10px 0; color: #495057;'>Informações sobre o PDF:</h4>";
                echo "<ul style='margin: 0; padding-left: 20px; color: #6c757d;'>";
                echo "<li>O PDF será gerado em formato A4</li>";
                echo "<li>A versão/série será obtida automaticamente do cabeçalho configurado</li>";
                echo "</ul>";
                echo "</div>";

                echo "</div>";
                echo "</form>";
                echo "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red; text-align: center;'>Erro ao verificar configurações: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "</div>";
    echo "</div>";

    Html::footer();

} catch (Exception $main_e) {
    echo "<div style='text-align: center; margin-top: 50px; font-family: Arial, sans-serif;'>";
    echo "<h2 style='color: #dc3545;'>❌ Erro Fatal</h2>";
    echo "<p><strong>Erro:</strong> " . htmlspecialchars($main_e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $main_e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $main_e->getLine() . "</p>";
    echo "<p><a href='../../../index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Voltar ao GLPI</a></p>";
    echo "</div>";
}
?>
