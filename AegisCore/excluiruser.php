<?php
error_reporting(0);
session_start();

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

// Verificar login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    echo 'erro no servidor';
    exit();
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    echo 'erro no servidor';
    exit();
}

if (!file_exists('../admin/suspenderrev.php')) {
    echo 'erro no servidor';
    exit();
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo 'erro no servidor';
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

// Pegar ID do usuário
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($id)) {
    echo 'erro no servidor';
    exit();
}

// Buscar dados do usuário
$sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo 'erro no servidor';
    exit();
}

$row = mysqli_fetch_assoc($result);
$login = $row['login'];
$categoria = $row['categoriaid'];
$byid = $row['byid'];
$uuid = $row['uuid'];

// Verificar permissão
if ($byid != $_SESSION['iduser']) {
    echo 'erro no servidor';
    exit();
}

// Buscar servidores da categoria
$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result = mysqli_query($conn, $sql2);

$sucess = false;
$sucess_servers = [];

while ($user_data = mysqli_fetch_assoc($result)) {
    $timeout = 3;
    $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);
    
    if ($socket) {
        fclose($socket);
        
        // Buscar token do servidor
        $servidor_id = $user_data['id'];
        $senha_token = getServidorToken($conn, $servidor_id);
        
        // Comando para excluir
        if ($uuid != '' && $uuid != 'Não Gerado') {
            $comando = 'sudo /etc/xis/rem.sh ' . $uuid . ' ' . $login;
        } else {
            $comando = 'sudo /etc/xis/atlasremove.sh ' . $login;
        }
        
        // Executar comando via curl
        $headers = array('Senha: ' . $senha_token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando");
        $output = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $sucess = true;
            $sucess_servers[] = $user_data['nome'];
        }
    }
}

// Se conseguiu excluir em pelo menos um servidor, remove do banco
if ($sucess == true) {
    $sql3 = "DELETE FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql3);
    
    if ($result) {
        // Registrar log
        $datahoje = date('d-m-Y H:i:s');
        $sql_log = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Excluiu o usuário $login', '$_SESSION[iduser]')";
        mysqli_query($conn, $sql_log);
        
        echo 'excluido';
    } else {
        echo 'erro no servidor';
    }
} else {
    echo 'erro no servidor';
}
?>