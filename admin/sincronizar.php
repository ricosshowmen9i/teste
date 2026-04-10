<?php 
if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include ('Net/SSH2.php');

//se a sessão não existir, redireciona para o login
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

$sql6 = "SELECT * FROM servidores WHERE id = '$id'";
//consulta a categoria do servidor
$result = mysqli_query($conn, $sql6);
$row = mysqli_fetch_assoc($result);
$categoria = $row['subid'];

//pesquisa todos os logins da categoria
$sql = "SELECT * FROM ssh_accounts WHERE categoriaid = '$categoria'";
$result = $conn->query($sql);
$ssh_accounts = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ssh_accounts[] = $row;
    }
}

$nome = md5(uniqid(rand(), true));
$nome = substr($nome, 0, 10) . ".txt";

//salvar txt
$file = fopen($nome, "w");
foreach ($ssh_accounts as $ssh_account) {
    $login = $ssh_account['login'];
    $senha = $ssh_account['senha'];
    $validade = $ssh_account['expira'];
    $uuid = $ssh_account['uuid'];
    
    //validade para dias restantes
    $validade = date('Y-m-d H:i:s', strtotime($validade));
    $data = date('Y-m-d H:i:s');
    $diferenca = strtotime($validade) - strtotime($data);
    $dias = floor($diferenca / (60 * 60 * 24));
    if ($dias < 0) $dias = 0;
    
    $limite = $ssh_account['limite'];
    fwrite($file, $login . " " . $senha . " " . $dias . " " . $limite . " " . $uuid . "\n");
}
fclose($file);

$sql2 = "SELECT * FROM servidores WHERE id = '$id'";
$result = $conn->query($sql2);

$loop = Factory::create();
$servidores_com_erro = [];
$failed_servers = [];
$sucess = false;

while ($user_data = mysqli_fetch_assoc($result)) {
    $tentativas = 0;
    $conectado = false;

    while ($tentativas < 2 && !$conectado) {
        $ssh = new Net_SSH2($user_data['ip'], $user_data['porta']);

        if ($ssh->login($user_data['usuario'], $user_data['senha'])) {
            
            // ===== CORREÇÃO: Buscar token MD5 para este servidor =====
            $servidor_id = $user_data['id'];
            $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
            $result_token = mysqli_query($conn, $sql_token);
            
            if ($result_token && mysqli_num_rows($result_token) > 0) {
                $row_token = mysqli_fetch_assoc($result_token);
                $senhatoken = $row_token['token']; // JÁ ESTÁ EM MD5
            } else {
                $senhatoken = md5($_SESSION['token']); // fallback
            }
            // ===== FIM DA CORREÇÃO =====
            
            $loop->addTimer(0.001, function () use ($ssh, $user_data, $conn, $nome, $senhatoken) {
                $local_file = $nome;
                $limiter_content = file_get_contents($local_file);
                
                // Escapar o conteúdo para shell
                $limiter_content_escaped = addslashes($limiter_content);
                
                // Enviar arquivo
                $ssh->exec('echo "' . $limiter_content_escaped . '" > /root/' . $nome);
                
                // Executar sincronização com token
                $ssh->exec('python3 /etc/xis/sincronizar.py ' . $nome . ' ' . $senhatoken . ' > /dev/null 2>/dev/null &');
                $ssh->disconnect();
            });
            
            $conectado = true;
            $sucess = true;
        } else {
            $tentativas++;
        }
    }

    if (!$conectado) {
        $servidores_com_erro[] = $user_data['ip'];
    }
}

// Tentar novamente para servidores com erro
foreach ($servidores_com_erro as $ip) {
    $sql2 = "SELECT id, ip, porta, usuario, senha, nome FROM servidores WHERE ip = '$ip'";
    $result2 = mysqli_query($conn, $sql2);
    $user_data2 = mysqli_fetch_assoc($result2);

    $tentativas = 0;
    $conectado = false;

    while ($tentativas < 2 && !$conectado) {
        $ssh = new Net_SSH2($user_data2['ip'], $user_data2['porta']);

        if ($ssh->login($user_data2['usuario'], $user_data2['senha'])) {
            
            // ===== CORREÇÃO: Buscar token MD5 para este servidor =====
            $servidor_id = $user_data2['id'];
            $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
            $result_token = mysqli_query($conn, $sql_token);
            
            if ($result_token && mysqli_num_rows($result_token) > 0) {
                $row_token = mysqli_fetch_assoc($result_token);
                $senhatoken = $row_token['token']; // JÁ ESTÁ EM MD5
            } else {
                $senhatoken = md5($_SESSION['token']); // fallback
            }
            // ===== FIM DA CORREÇÃO =====
            
            $loop->addTimer(0.001, function () use ($ssh, $user_data2, $conn, $nome, $senhatoken) {
                $local_file = $nome;
                $limiter_content = file_get_contents($local_file);
                
                // Escapar o conteúdo para shell
                $limiter_content_escaped = addslashes($limiter_content);
                
                // Enviar arquivo
                $ssh->exec('echo "' . $limiter_content_escaped . '" > /root/' . $nome);
                
                // Executar sincronização com token
                $ssh->exec('python3 /etc/xis/sincronizar.py ' . $nome . ' ' . $senhatoken . ' > /dev/null 2>/dev/null &');
                $ssh->disconnect();
            });
            
            $conectado = true;
            $sucess = true;
        } else {
            $tentativas++;
        }
    }

    if (!$conectado) {
        $failed_servers[] = $user_data2['nome'];
    }
}

if ($sucess == true) {
    echo 'Comando enviado com sucesso';
}

$loop->run();

// Remover arquivo temporário
if (file_exists($nome)) {
    unlink($nome);
}
?>