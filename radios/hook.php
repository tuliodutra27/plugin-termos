<?php

function plugin_radios_install() {
    global $DB;

    // Criação da tabela principal glpi_plugin_radios_radios
    $table_radios = 'glpi_plugin_radios_radios';
    $query_radios = "CREATE TABLE IF NOT EXISTS `$table_radios` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) DEFAULT NULL,
        `manufacturers_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `model` VARCHAR(255) DEFAULT NULL,
        `serial` VARCHAR(255) DEFAULT NULL,
        `otherserial` VARCHAR(255) DEFAULT NULL,
        `chave_nf` VARCHAR(44) DEFAULT NULL,
        `comment` TEXT,
        `states_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `users_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `groups_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `locations_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `date_mod` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
        `is_template` TINYINT(1) NOT NULL DEFAULT 0,
        `template_name` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_manufacturer` (`manufacturers_id`),
        KEY `idx_state` (`states_id`),
        KEY `idx_user` (`users_id`),
        KEY `idx_group` (`groups_id`),
        KEY `idx_location` (`locations_id`),
        KEY `idx_entity` (`entities_id`),
        KEY `idx_chave_nf` (`chave_nf`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$DB->queryOrDie($query_radios, "Erro ao criar tabela glpi_plugin_radios_radios")) {
        return false;
    }

    // Criação da tabela de histórico glpi_radios_historico - VERSÃO ATUALIZADA
    $table_historico = 'glpi_radios_historico';
    $query_historico = "CREATE TABLE IF NOT EXISTS `$table_historico` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `radios_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `serial` VARCHAR(255) DEFAULT NULL,
        `model` VARCHAR(255) DEFAULT NULL,
        `manufacturers_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `patrimonio` VARCHAR(255) DEFAULT NULL,
        `states_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `groups_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `users_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `locations_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `tecnico_alterou_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `data_movimentacao` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_radios_id` (`radios_id`),
        KEY `idx_data_movimentacao` (`data_movimentacao`),
        KEY `idx_tecnico` (`tecnico_alterou_id`),
        KEY `idx_serial` (`serial`),
        KEY `idx_patrimonio` (`patrimonio`),
        CONSTRAINT `fk_radios_historico_radios` FOREIGN KEY (`radios_id`) REFERENCES `glpi_plugin_radios_radios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$DB->queryOrDie($query_historico, "Erro ao criar tabela glpi_radios_historico")) {
        return false;
    }

    // Criação da nova tabela glpi_pre_update_radios
    $table_pre_update = 'glpi_pre_update_radios';
    $query_pre_update = "CREATE TABLE IF NOT EXISTS `$table_pre_update` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) DEFAULT NULL,
        `manufacturers_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `model` VARCHAR(255) DEFAULT NULL,
        `serial` VARCHAR(255) DEFAULT NULL,
        `otherserial` VARCHAR(255) DEFAULT NULL,
        `chave_nf` VARCHAR(44) DEFAULT NULL,
        `comment` TEXT,
        `states_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `users_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `groups_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `locations_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `date_mod` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
        `is_template` TINYINT(1) NOT NULL DEFAULT 0,
        `template_name` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_pre_manufacturer` (`manufacturers_id`),
        KEY `idx_pre_state` (`states_id`),
        KEY `idx_pre_user` (`users_id`),
        KEY `idx_pre_group` (`groups_id`),
        KEY `idx_pre_location` (`locations_id`),
        KEY `idx_pre_entity` (`entities_id`),
        KEY `idx_pre_serial` (`serial`),
        KEY `idx_pre_otherserial` (`otherserial`),
        KEY `idx_pre_chave_nf` (`chave_nf`),
        KEY `idx_pre_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$DB->queryOrDie($query_pre_update, "Erro ao criar tabela glpi_pre_update_radios")) {
        return false;
    }

    return true;
}

function plugin_radios_uninstall() {
    global $DB;
    
    // Remove primeiro a tabela com foreign key (histórico)
    if (!$DB->queryOrDie("DROP TABLE IF EXISTS `glpi_radios_historico`", "Erro ao remover tabela glpi_radios_historico")) {
        return false;
    }
    
    // Remove a tabela de pré-atualização
    if (!$DB->queryOrDie("DROP TABLE IF EXISTS `glpi_pre_update_radios`", "Erro ao remover tabela glpi_pre_update_radios")) {
        return false;
    }
    
    // Por último remove a tabela principal
    if (!$DB->queryOrDie("DROP TABLE IF EXISTS `glpi_plugin_radios_radios`", "Erro ao remover tabela glpi_plugin_radios_radios")) {
        return false;
    }
    
    return true;
}

?>