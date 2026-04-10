<script src="../app-assets/sweetalert.min.js"></script>
<?php 
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

if ($_SESSION['login'] == 'admin') {
} else {
    echo "<script>alert('Você não tem permissão para acessar essa página!');window.location.href='../logout.php';</script>";
    exit();
}

include 'headeradmin2.php';
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

set_time_limit(0); // Limite de tempo de execução: 2h. Deixe 0 (zero) para sem limite
ignore_user_abort(true); // Continua a execução mesmo que o usuário cancele o download
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include ('Net/SSH2.php');

// Verificação de token do sistema
if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
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

//consulta todos logins 
$sql1 = "SELECT * FROM accounts WHERE id = '$_SESSION[iduser]'";
$result1 = $conn->query($sql1);
$contas = array();
if ($result1->num_rows > 0) {
    while ($row1 = $result1->fetch_assoc()) {
        $contas[] = $row1;
    }
}

$contas = array_unique($contas, SORT_REGULAR);
date_default_timezone_set('America/Sao_Paulo');
$data = date('Y-m-d H:i:s');
$ssh_accounts = array();

foreach ($contas as $conta) {
    $sql3 = "SELECT * FROM ssh_accounts WHERE byid = '$conta[id]' and expira < '$data'";
    $result3 = $conn->query($sql3);
    if ($result3->num_rows > 0) {
        while ($row3 = $result3->fetch_assoc()) {
            $ssh_accounts[] = $row3;
        }
    }
}

$nome = md5(uniqid(rand(), true));
$nome = substr($nome, 0, 10) . ".txt";

//salvar txt
$file = fopen($nome, "w");
foreach ($ssh_accounts as $ssh_account) {
    $login = $ssh_account['login'];
    $uuid = $ssh_account['uuid'];
    fwrite($file, $uuid . " " . $login . PHP_EOL);
}
fclose($file);

// Processar cada servidor
$sql2 = "SELECT * FROM servidores";
$result = $conn->query($sql2);

$loop = Factory::create();
$servidores_com_erro = [];
$failed_servers = [];
$sucess = false;

while ($user_data = mysqli_fetch_assoc($result)) {
    $conectado = false;
    $ipeporta = $user_data['ip'] . ':6969';
    $timeout = 3;
    $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

    if ($socket) {
        fclose($socket);
        
        // ===== CORREÇÃO: Buscar token MD5 para este servidor =====
        $servidor_id = $user_data['id'];
        $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
        $result_token = mysqli_query($conn, $sql_token);
        
        if ($result_token && mysqli_num_rows($result_token) > 0) {
            $row_token = mysqli_fetch_assoc($result_token);
            $senha = $row_token['token']; // JÁ ESTÁ EM MD5
        } else {
            $senha = md5($_SESSION['token']); // fallback
        }
        // ===== FIM DA CORREÇÃO =====
        
        $loop->addTimer(0.001, function () use ($user_data, $conn, $nome, $senha) {
            $local_file = $nome;
            $limiter_content = file_get_contents($local_file);
            $ipeporta = $user_data['ip'] . ':6969';
            
            $headers = array('Senha: ' . $senha);
            
            // Enviar arquivo de exclusão
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, 'http://' . $ipeporta);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch1, CURLOPT_POST, 1);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'echo "' . addslashes($limiter_content) . '" > /root/' . $nome)));
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
        
        $conectado = true;
        $sucess = true;
    } else {
        $servidores_com_erro[] = $user_data['ip'];
        $failed_servers[] = $user_data['nome'];
    }
}

if ($sucess == true) {
    // Deletar as contas do banco de dados
    foreach ($ssh_accounts as $ssh_account) {
        $sql4 = "DELETE FROM ssh_accounts WHERE id = '$ssh_account[id]'";
        $result4 = $conn->query($sql4);
    }
    
    $loop->run();
    
    // Remover arquivo temporário
    if (file_exists($nome)) {
        unlink($nome);
    }
    
    echo "<script>
        swal({
            title: 'Sucesso!',
            text: 'Contas expiradas deletadas com sucesso!',
            icon: 'success',
            button: 'OK'
        }).then(function() {
            window.location.href = 'home.php';
        });
    </script>";
} else {
    echo "<script>
        swal({
            title: 'Erro!',
            text: 'Não foi possível conectar aos servidores',
            icon: 'error',
            button: 'OK'
        }).then(function() {
            window.location.href = 'home.php';
        });
    </script>";
}
?>