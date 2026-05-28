<?php

include('../../../inc/includes.php');

Session::checkRight("config", READ);

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    Html::redirect('editar_radio.php?id=' . $id);
} else {
    Html::redirect('novo_radio.php');
}
