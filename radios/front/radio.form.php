<?php

include('../../../inc/includes.php');

if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit;
}

$radio = new PluginRadiosRadio();

if (isset($_POST['add'])) {
    $radio->check(-1, CREATE, $_POST);
    $newID = $radio->add($_POST);
    if ($newID) {
        Html::redirect($_SERVER['PHP_SELF'] . '?id=' . $newID);
    }
    Html::redirect($_SERVER['PHP_SELF']);

} elseif (isset($_POST['update'])) {
    $radio->check($_POST['id'], UPDATE, $_POST);
    $radio->update($_POST);
    Html::back();

} elseif (isset($_POST['delete'])) {
    $radio->check($_POST['id'], DELETE, $_POST);
    $radio->delete($_POST);
    $radio->redirectToList();

} elseif (isset($_POST['restore'])) {
    $radio->check($_POST['id'], DELETE, $_POST);
    $radio->restore($_POST);
    Html::back();

} elseif (isset($_POST['purge'])) {
    $radio->check($_POST['id'], PURGE, $_POST);
    $radio->delete($_POST, true);
    $radio->redirectToList();
}

$ID = intval($_GET['id'] ?? -1);
$radio->check($ID, READ);

Html::header(PluginRadiosRadio::getTypeName(1), $_SERVER['PHP_SELF'], 'assets', 'PluginRadiosRadio');
$radio->display(['id' => $ID]);
Html::footer();
