<script src="../app-assets/sweetalert.min.js"></script>
<?php 
use GuzzleHttp\Psr7\Query;
use LDAP\Result;
if (!isset($_SESSION)){
    error_reporting(0);
session_start();

}

//se a sessão não existir, redireciona para o login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}
include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
    
}
if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
}else{
    include_once 'suspenderrev.php';
    
}
if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "O domínio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " inválido!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}
function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}
//anti sql injection na $_GET['id']
$_GET['id'] = anti_sql($_GET['id']);

$id = $_GET['id'];
$sql22 = "UPDATE atribuidos SET byid = '1' WHERE userid = '$id'";
$result22 = $conn->query($sql22);
$sql = "UPDATE accounts SET byid = '1' WHERE id = '$id'";
$result = $conn->query($sql);
if ($result) {
    echo '<script>sweetAlert("", "Revenda puxada com sucesso!", "success").then((value) => {
        window.location.href = "listarrevendedores.php";
      });</script>';
    exit();
} else {
    echo '<script>sweetAlert("", "Erro ao puxar revenda!", "error").then((value) => {
        window.location.href = "listarrevendedores.php";
      });</script>';
    exit();
}
?>
