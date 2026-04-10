<?php
error_reporting(0);
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (isset($_POST['login'])) {
    $login = $conn->real_escape_string($_POST['login']);
    
    // Atualiza o registro de suspensão como reativado
    $conn->query("UPDATE suspensoes_limite SET reativado = 1, data_reativacao = NOW() WHERE login = '$login' AND reativado = 0");
    
    echo 'ok';
} else {
    echo 'erro';
}
?>