<?php 
error_reporting(0);
session_start();

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Função para buscar token do servidor
function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']);
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $login = $row['login'];
    $senha = $row['senha'];
    $categoria = $row['categoriaid'];
    $validade = $row['expira'];
    $limite = $row['limite'];
    $byid = $row['byid'];
    $uuid = $row['uuid'];
}

if ($byid != $_SESSION['iduser']) {
    echo "<script>sweetAlert('Oops...', 'Você não tem permissão para reativar este usuário!', 'error').then(function(){window.location.href='../home.php'});</script>";
    exit();
}

// Calcular dias restantes
date_default_timezone_set('America/Sao_Paulo');
$validade_ts = strtotime($validade);
$hoje_ts = strtotime(date('Y-m-d'));
$dias_restantes = floor(($validade_ts - $hoje_ts) / (60 * 60 * 24));
if ($dias_restantes < 1) {
    $dias_restantes = 2; // Garante pelo menos 2 dias
}

// Buscar servidores da categoria
$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result = mysqli_query($conn, $sql2);

set_time_limit(0);
ignore_user_abort(true);
$loop = Factory::create();
$servidores_com_erro = [];
$sucess = false;       

while ($user_data = mysqli_fetch_assoc($result)) {
    $timeout = 3;
    $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);
    
    if ($socket) {
        fclose($socket);
        
        $servidor_id = $user_data['id'];
        $senha_token = getServidorToken($conn, $servidor_id);
        
        $loop->addTimer(0.001, function () use ($user_data, $conn, $login, $senha, $dias_restantes, $limite, $senha_token, $uuid) {
            if ($uuid == "") {
                $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $login;
                $comando2 = 'sudo /etc/xis/atlascreate.sh ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite;
            } else {
                $comando1 = 'sudo /etc/xis/rem.sh ' . $uuid . ' ' . $login;
                $comando2 = 'sudo /etc/xis/add.sh ' . $uuid . ' ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite;
            }
            
            $headers = array(
                'Senha: ' . $senha_token
            );
            
            // Remove usuário antigo
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
            curl_exec($ch);
            curl_close($ch);
            
            // Recria usuário
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
            curl_exec($ch);
            curl_close($ch);
        });
        
        $sucess = true;
    } else {
        $servidores_com_erro[] = $user_data['ip'];
    }
}

if ($sucess == false) {
    echo '<script>swal("Erro!", "Erro ao conectar com o servidor!", "error").then(function() { window.location = "listarusuarios.php"; });</script>';
} elseif ($sucess == true) {
    // Limpa o status de suspenso
    $suspenso = "";
    $sql3 = "UPDATE ssh_accounts SET mainid = '$suspenso' WHERE id = '$id'";
    mysqli_query($conn, $sql3);
    echo 'reativado com sucesso';
}
$loop->run();
?>