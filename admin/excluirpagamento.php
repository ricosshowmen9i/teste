<?php
session_start();
error_reporting(0);
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $sql = "DELETE FROM pagamentos WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        echo "ok";
    } else {
        echo "erro";
    }
}
?>