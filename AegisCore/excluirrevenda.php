<script src="../app-assets/sweetalert.min.js"></script>
<?php 
if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit();
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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// CORREÇÃO: Busca token específico do servidor
function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']); // fallback
}

// CORREÇÃO: Função recursiva em vez de foreach repetido 13 vezes
function buscarTodosSubordinados($conn, $id_pai, &$todos_ids = []) {
    if (!in_array($id_pai, $todos_ids)) {
        $todos_ids[] = $id_pai;
    }
    $sql    = "SELECT id FROM accounts WHERE byid = '$id_pai'";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        buscarTodosSubordinados($conn, $row['id'], $todos_ids);
    }
    return $todos_ids;
}

$_GET['id'] = anti_sql($_GET['id']);

if (empty($_GET['id'])) {
    echo "<script>sweetAlert('Erro!', 'ID não fornecido!', 'error').then(function(){window.location.href='listarrevendedores.php'});</script>";
    exit();
}

$id = $_GET['id'];

$sql = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $categoria = $row['categoriaid'];
    $byid = $row['byid'];
} else {
    echo "<script>sweetAlert('Erro!', 'Revendedor não encontrado!', 'error').then(function(){window.location.href='listarrevendedores.php'});</script>";
    exit();
}

// Verifica permissão
if ($byid != $_SESSION['iduser'] && $_SESSION['login'] != 'admin') {
    echo "<script>sweetAlert('Oops...', 'Você não tem permissão para excluir este revendedor!', 'error').then(function(){window.location.href='../home.php'});</script>";
    exit();
}

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include ('Net/SSH2.php');

// CORREÇÃO: Busca recursiva de todos os subordinados
$todos_ids = [];
buscarTodosSubordinados($conn, $id, $todos_ids);
$todos_ids = array_unique($todos_ids);

// Busca todas as contas SSH
$ssh_accounts = [];
if (!empty($todos_ids)) {
    $ids_str = implode(",", $todos_ids);
    $sql_ssh = "SELECT * FROM ssh_accounts WHERE byid IN ($ids_str)";
    $result_ssh = $conn->query($sql_ssh);
    while ($row_ssh = $result_ssh->fetch_assoc()) {
        $ssh_accounts[] = $row_ssh;
    }
}

// Cria arquivo TXT com logins para exclusão
$nome_arquivo     = substr(md5(uniqid(rand(), true)), 0, 10) . ".txt";
$caminho_completo = __DIR__ . '/' . $nome_arquivo;

if (!empty($ssh_accounts)) {
    $file = fopen($caminho_completo, "w");
    foreach ($ssh_accounts as $ssh_account) {
        $login = $ssh_account['login'];
        $uuid = $ssh_account['uuid'] ?? '';
        if (!empty($uuid) && $uuid != 'Não Gerado') {
            fwrite($file, "$uuid $login\n");
        } else {
            fwrite($file, "$login\n");
        }
    }
    fclose($file);
}

// Envia para servidores
$sql_servidores = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result_servidores = $conn->query($sql_servidores);

$servidores_ok = [];
$servidores_erro = [];

if ($result_servidores && $result_servidores->num_rows > 0 && !empty($ssh_accounts)) {
    $loop = Factory::create();

    while ($servidor = mysqli_fetch_assoc($result_servidores)) {
        $ip          = $servidor['ip'];
        $servidor_id = $servidor['id'];
        // CORREÇÃO: Usa token específico do servidor
        $senha_token = getServidorToken($conn, $servidor_id);

        $socket = @fsockopen($ip, 6969, $errno, $errstr, 5);

        if ($socket) {
            fclose($socket);

            $loop->addTimer(0.1, function () use ($ip, $caminho_completo, $nome_arquivo, $senha_token, &$servidores_ok, &$servidores_erro) {
                if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) {
                    $servidores_ok[] = $ip;
                    return;
                }

                $limiter_content = file_get_contents($caminho_completo);
                $headers = [
                    'Senha: ' . $senha_token,
                    'User-Agent: Atlas-Excluir-Revenda/1.0'
                ];

                // Envia arquivo para /root/
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, 'http://' . $ip . ':6969');
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch1, CURLOPT_POST, 1);
                curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
                    'comando' => 'echo "' . addslashes($limiter_content) . '" > /root/' . $nome_arquivo
                ]));
                curl_setopt($ch1, CURLOPT_TIMEOUT, 30);
                $output1   = curl_exec($ch1);
                $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
                curl_close($ch1);

                if ($httpCode1 == 200) {
                    // CORREÇÃO: delete.py agora busca em /root/ automaticamente
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, 'http://' . $ip . ':6969');
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
                        'comando' => 'cd /etc/xis && sudo python3 /etc/xis/delete.py ' . $nome_arquivo
                    ]));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 60);
                    curl_exec($ch2);
                    curl_close($ch2);

                    $servidores_ok[] = $ip;
                } else {
                    $servidores_erro[] = $ip;
                }
            });

        } else {
            $servidores_erro[] = $ip;
        }
    }

    // CORREÇÃO CRÍTICA: $loop->run() ANTES de deletar do banco
    $loop->run();
}

// Limpa arquivo temporário
if (file_exists($caminho_completo)) {
    unlink($caminho_completo);
}

// AGORA deleta do banco de dados
date_default_timezone_set('America/Sao_Paulo');
$datahoje = date('d-m-Y H:i:s');
$log_texto = "Excluiu revendedor ID $id e " . (count($todos_ids) - 1) . " subordinados. SSH: " . count($ssh_accounts);
$conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$_SESSION['login']}', '$datahoje', '$log_texto', '{$_SESSION['iduser']}')");

if (!empty($todos_ids)) {
    $ids_str = implode(",", $todos_ids);
    $conn->query("DELETE FROM ssh_accounts WHERE byid IN ($ids_str)");
    $conn->query("DELETE FROM accounts WHERE id IN ($ids_str)");
    $conn->query("DELETE FROM atribuidos WHERE userid IN ($ids_str)");
}

echo "<script>sweetAlert('Sucesso!', 'Contas deletadas com sucesso!\\n\\nRevendedores: " . count($todos_ids) . "\\nContas SSH: " . count($ssh_accounts) . "', 'success').then(function(){window.location.href = 'listarrevendedores.php';});</script>";

mysqli_close($conn);
?>
