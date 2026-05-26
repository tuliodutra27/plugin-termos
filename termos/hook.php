<?php

function plugin_termos_install() {
    global $DB;

    // Criação da tabela termos_cabecalho (seguindo padrão GLPI)
    $table_termos = 'glpi_termos_cabecalho';
    $query_termos = "CREATE TABLE IF NOT EXISTS `$table_termos` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Caminho do arquivo de logo',
        `titulo1` VARCHAR(255) NOT NULL COMMENT 'Título principal do termo',
        `titulo2` VARCHAR(255) DEFAULT NULL COMMENT 'Título secundário do termo',
        `versao_serie` VARCHAR(100) DEFAULT NULL COMMENT 'Versão/Série do documento',
        `setor` VARCHAR(150) DEFAULT NULL COMMENT 'Setor responsável',
        `revisao` VARCHAR(50) DEFAULT NULL COMMENT 'Número da revisão',
        `paginas` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Número total de páginas',
        `data_versao` DATE DEFAULT NULL COMMENT 'Data da versão do documento',
        `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
        `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `date_mod` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_termos_entity` (`entities_id`),
        KEY `idx_termos_deleted` (`is_deleted`),
        KEY `idx_termos_setor` (`setor`),
        KEY `idx_termos_versao` (`versao_serie`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$DB->queryOrDie($query_termos, "Erro ao criar tabela $table_termos")) {
        return false;
    }

    // Criação da tabela plugin_termo_clausulas
    $table_clausulas = 'glpi_plugin_termo_clausulas';
    $query_clausulas = "CREATE TABLE IF NOT EXISTS `$table_clausulas` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `indice` VARCHAR(50) NOT NULL COMMENT 'Índice da cláusula',
        `texto` LONGTEXT NOT NULL COMMENT 'Texto da cláusula',
        `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
        `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `date_mod` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_clausulas_entity` (`entities_id`),
        KEY `idx_clausulas_deleted` (`is_deleted`),
        KEY `idx_clausulas_indice` (`indice`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$DB->queryOrDie($query_clausulas, "Erro ao criar tabela $table_clausulas")) {
        return false;
    }

    // Criação da tabela plugin_termo_observacoes (NOVA TABELA)
    $table_observacoes = 'glpi_plugin_termo_observacoes';
    $query_observacoes = "CREATE TABLE IF NOT EXISTS `$table_observacoes` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `indice` VARCHAR(50) NOT NULL COMMENT 'Índice da observação',
        `texto` LONGTEXT NOT NULL COMMENT 'Texto da observação',
        `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
        `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `date_mod` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_observacoes_entity` (`entities_id`),
        KEY `idx_observacoes_deleted` (`is_deleted`),
        KEY `idx_observacoes_indice` (`indice`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$DB->queryOrDie($query_observacoes, "Erro ao criar tabela $table_observacoes")) {
        return false;
    }

    // Log de sucesso
    error_log("Plugin Termos: Tabelas criadas com sucesso - $table_termos, $table_clausulas e $table_observacoes");
    
    return true;
}

function plugin_termos_uninstall() {
    global $DB;
    
    // Remove a tabela termos_cabecalho
    if (!$DB->queryOrDie("DROP TABLE IF EXISTS `glpi_termos_cabecalho`", "Erro ao remover tabela glpi_termos_cabecalho")) {
        return false;
    }
    
    // Remove a tabela plugin_termo_clausulas
    if (!$DB->queryOrDie("DROP TABLE IF EXISTS `glpi_plugin_termo_clausulas`", "Erro ao remover tabela glpi_plugin_termo_clausulas")) {
        return false;
    }
    
    // Remove a tabela plugin_termo_observacoes (NOVA TABELA)
    if (!$DB->queryOrDie("DROP TABLE IF EXISTS `glpi_plugin_termo_observacoes`", "Erro ao remover tabela glpi_plugin_termo_observacoes")) {
        return false;
    }
    
    error_log("Plugin Termos: Tabelas removidas com sucesso - glpi_termos_cabecalho, glpi_plugin_termo_clausulas e glpi_plugin_termo_observacoes");
    
    return true;
}

/**
 * Função para verificar se as tabelas existem (útil para debugging)
 */
function plugin_termos_check_tables() {
    global $DB;
    
    $tables = ['glpi_termos_cabecalho', 'glpi_plugin_termo_clausulas', 'glpi_plugin_termo_observacoes'];
    $results = [];
    
    foreach ($tables as $table) {
        $result = $DB->query("SHOW TABLES LIKE '$table'");
        $exists = $DB->numrows($result) > 0;
        
        $results[] = [
            'table' => $table,
            'exists' => $exists,
            'status' => $exists ? 'Tabela existe' : 'Tabela não encontrada'
        ];
    }
    
    return $results;
}