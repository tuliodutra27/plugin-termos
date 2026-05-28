<?php

include('../../../inc/includes.php');

if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

Session::checkRight("config", READ);

Html::header(PluginRadiosRadio::getTypeName(2), $_SERVER['PHP_SELF'], 'assets', 'PluginRadiosRadio');

Search::show('PluginRadiosRadio');

Html::footer();
