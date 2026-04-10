<?php
error_reporting(0);
session_start();

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

// Verificar login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit();
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
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

// Anti SQL injection
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($id)) {
    echo 'erro no servidor';
    exit();
}

$sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo 'erro no servidor';
    exit();
}

$row = mysqli_fetch_assoc($result);
$login = $row['login'];
$categoria = $row['categoriaid'];
$uuid = $row['uuid'];

// Buscar servidores da categoria
$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result = $conn->query($sql2);

// Registrar log
date_default_timezone_set('America/Sao_Paulo');
$datahoje = date('d-m-Y H:i:s');
$sql10 = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Excluiu o usuario $login', '$_SESSION[iduser]')";
mysqli_query($conn, $sql10);

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

$loop = Factory::create();
$servidores_com_erro = [];
$sucess = false;
$sucess_servers = [];

while ($user_data = mysqli_fetch_assoc($result)) {
    $timeout = 3;
    $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);
    
    if ($socket) {
        fclose($socket);
        
        $servidor_id = $user_data['id'];
        $senha_token = getServidorToken($conn, $servidor_id);
        
        $loop->addTimer(0.001, function () use ($user_data, $login, $senha_token, $uuid) {
            if ($uuid == '' || $uuid == 'Não Gerado') {
                $comando = 'sudo /etc/xis/atlasremove.sh ' . $login;
            } else {
                $comando = 'sudo /etc/xis/rem.sh ' . $uuid . ' ' . $login;
            }
            
            $headers = array('Senha: ' . $senha_token);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando");
            $output = curl_exec($ch);
            curl_close($ch);
        });
        
        $sucess_servers[] = $user_data['nome'];
        $sucess = true;
    } else {
        $servidores_com_erro[] = $user_data['ip'];
    }
}

$loop->run();

if ($sucess == true) {
    $sql3 = "DELETE FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql3);
    
    if ($result) {
        echo 'excluido';
    } else {
        echo 'erro no servidor';
    }
} else {
    echo 'erro no servidor';
}
?>