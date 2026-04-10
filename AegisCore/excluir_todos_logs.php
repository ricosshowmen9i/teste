<?php
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (isset($_POST['excluir_todos'])) {
    $sql_delete = "DELETE FROM logs WHERE revenda = '{$_SESSION['login']}' OR byid = '{$_SESSION['iduser']}'";
    
    if (mysqli_query($conn, $sql_delete)) {
        echo "ok";
    } else {
        echo "erro: " . mysqli_error($conn);
    }
} else {
    echo "parametro nao informado";
}
?>