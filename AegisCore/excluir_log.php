<?php
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (isset($_POST['log_id'])) {
    $log_id = mysqli_real_escape_string($conn, $_POST['log_id']);
    
    // Verificar se o log pertence ao usuário logado
    $sql_check = "SELECT id FROM logs WHERE id = '$log_id' AND (revenda = '{$_SESSION['login']}' OR byid = '{$_SESSION['iduser']}')";
    $result = mysqli_query($conn, $sql_check);
    
    if (mysqli_num_rows($result) > 0) {
        $sql_delete = "DELETE FROM logs WHERE id = '$log_id'";
        if (mysqli_query($conn, $sql_delete)) {
            echo "ok";
        } else {
            echo "erro";
        }
    } else {
        echo "permissao negada";
    }
} else {
    echo "id nao informado";
}
?>