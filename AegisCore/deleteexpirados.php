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
}

include 'header2.php';
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

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

$sql = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categoria = $row['categoriaid'];
    }
}

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

// Consultar todas as contas expiradas
$sql1 = "SELECT * FROM accounts WHERE id = '$_SESSION[iduser]'";
$result1 = $conn->query($sql1);
$contas = [];
if ($result1->num_rows > 0) {
    while ($row1 = $result1->fetch_assoc()) {
        $contas[] = $row1;
    }
}

$contas = array_unique($contas, SORT_REGULAR);

date_default_timezone_set('America/Sao_Paulo');
$data = date('Y-m-d H:i:s');
$ssh_accounts = [];

foreach ($contas as $conta) {
    $sql3 = "SELECT * FROM ssh_accounts WHERE byid = '$conta[id]' AND expira < '$data'";
    $result3 = $conn->query($sql3);
    if ($result3->num_rows > 0) {
        while ($row3 = $result3->fetch_assoc()) {
            $ssh_accounts[] = $row3;
        }
    }
}

if (empty($ssh_accounts)) {
    echo "<script>
        swal({
            title: 'Informação',
            text: 'Nenhuma conta expirada!',
            icon: 'info',
            button: 'OK'
        }).then(function() {
            window.location.href = '../home.php';
        });
    </script>";
    exit();
}

// Criar arquivo com os usuários a serem deletados
$nome = md5(uniqid(rand(), true));
$nome = substr($nome, 0, 10);
$nome = $nome . ".txt";

$file = fopen($nome, "w");
foreach ($ssh_accounts as $ssh_account) {
    $login = $ssh_account['login'];
    $uuid = $ssh_account['uuid'];
    fwrite($file, $uuid . " " . $login . PHP_EOL);
}
fclose($file);

// Buscar servidores da categoria
$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result = $conn->query($sql2);

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
        
        $loop->addTimer(0.001, function () use ($user_data, $nome, $senha_token) {
            $local_file = $nome;
            $limiter_content = file_get_contents($local_file);
            
            $ipeporta = $user_data['ip'] . ':6969';
            $headers = array('Senha: ' . $senha_token);
            
            // Enviar arquivo para o servidor
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, 'http://' . $ipeporta);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch1, CURLOPT_POST, 1);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'echo "' . $limiter_content . '" > /root/' . $nome)));
            curl_exec($ch1);
            curl_close($ch1);
            
            // Executar script de exclusão
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, 'http://' . $ipeporta);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'sudo python3 /etc/xis/delete.py ' . $nome . ' > /dev/null 2>/dev/null &')));
            curl_exec($ch2);
            curl_close($ch2);
        });
        
        $sucess_servers[] = $user_data['nome'];
        $sucess = true;
    } else {
        $servidores_com_erro[] = $user_data['ip'];
    }
}

$loop->run();

if ($sucess == true) {
    // Deletar do banco de dados
    foreach ($ssh_accounts as $ssh_account) {
        $sql4 = "DELETE FROM ssh_accounts WHERE id = '$ssh_account[id]'";
        $conn->query($sql4);
    }
    
    // Registrar log
    $datahoje = date('d-m-Y H:i:s');
    $sql_log = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Excluiu ' . count($ssh_accounts) . ' contas expiradas', '$_SESSION[iduser]')";
    $conn->query($sql_log);
    
    echo "<script>
        swal({
            title: 'Sucesso!',
            text: '" . count($ssh_accounts) . " contas expiradas foram excluídas com sucesso!',
            icon: 'success',
            button: 'OK'
        }).then(function() {
            window.location.href = '../home.php';
        });
    </script>";
} else {
    echo "<script>
        swal({
            title: 'Erro!',
            text: 'Erro ao excluir contas expiradas!',
            icon: 'error',
            button: 'OK'
        }).then(function() {
            window.location.href = '../home.php';
        });
    </script>";
}

// Limpar arquivo temporário
if (file_exists($nome)) {
    unlink($nome);
}
?>