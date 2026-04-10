<?php
include_once('../vendor/event/autoload.php');
use React\EventLoop\Factory;

if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit();
}

include 'header2.php';
include('../AegisCore/conexao.php');

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

function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']); // fallback
}

// Função recursiva — busca toda a árvore de sub-revendedores
function buscarTodosRevendedores($conn, $id_pai, &$todos_ids = []) {
    if (!in_array($id_pai, $todos_ids)) {
        $todos_ids[] = $id_pai;
    }
    $sql    = "SELECT id FROM accounts WHERE byid = '$id_pai'";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        buscarTodosRevendedores($conn, $row['id'], $todos_ids);
    }
    return $todos_ids;
}

$id = isset($_GET['id']) ? anti_sql($_GET['id']) : 0;

if (empty($id)) {
    echo "<script>swal('Erro!', 'ID não fornecido!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

// ─── BUSCA DADOS DO REVENDEDOR ────────────────────────────────────────────────
$sql_rev    = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result_rev = $conn->query($sql_rev);

if ($result_rev->num_rows == 0) {
    echo "<script>swal('Erro!', 'Revendedor não encontrado!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

$row_rev   = $result_rev->fetch_assoc();
$categoria = $row_rev['categoriaid'];
$byid      = $row_rev['byid'];
$suspenso  = $row_rev['suspenso'];

// ─── VERIFICA PERMISSÃO ───────────────────────────────────────────────────────
if ($byid != $_SESSION['iduser'] && $_SESSION['login'] != 'admin') {
    echo "<script>swal('Oops...', 'Você não tem permissão para suspender este revendedor!', 'error').then(function(){window.location.href='listarrevendedores.php'});</script>";
    exit();
}

// ─── VERIFICA SE JÁ ESTÁ SUSPENSO ────────────────────────────────────────────
if ($suspenso == '1') {
    echo "<script>swal('Aviso!', 'Este revendedor já está suspenso!', 'warning').then(function(){window.location.href='listarrevendedores.php'});</script>";
    exit();
}

set_time_limit(0);
ignore_user_abort(true);

// ─── BUSCA TODA A ÁRVORE ──────────────────────────────────────────────────────
$todos_revendedores = [];
buscarTodosRevendedores($conn, $id, $todos_revendedores);
$todos_revendedores = array_unique($todos_revendedores);

// ─── BUSCA TODOS OS USUÁRIOS SSH ──────────────────────────────────────────────
$ssh_accounts = [];

if (!empty($todos_revendedores)) {
    $ids_str    = implode(",", $todos_revendedores);
    $sql_ssh    = "SELECT login, uuid, categoriaid FROM ssh_accounts WHERE byid IN ($ids_str)";
    $result_ssh = mysqli_query($conn, $sql_ssh);
    while ($row_ssh = mysqli_fetch_assoc($result_ssh)) {
        $ssh_accounts[] = $row_ssh;
    }
}

$total_revendedores = count($todos_revendedores);
$total_usuarios     = count($ssh_accounts);

// ─── CRIA ARQUIVO COM LOGINS ──────────────────────────────────────────────────
$nome_arquivo     = substr(md5(uniqid(rand(), true)), 0, 10) . ".txt";
$caminho_completo = __DIR__ . '/' . $nome_arquivo;

if (!empty($ssh_accounts)) {
    $file = fopen($caminho_completo, "w");
    foreach ($ssh_accounts as $ssh_account) {
        // Só o login — para suspend.py
        fwrite($file, $ssh_account['login'] . "\n");
    }
    fclose($file);
}

// ─── ENVIA PARA SERVIDORES ────────────────────────────────────────────────────
$sql_servidores    = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result_servidores = $conn->query($sql_servidores);

$sucesso           = true;
$servidores_sucesso = [];
$servidores_erro    = [];

if ($result_servidores && $result_servidores->num_rows > 0 && !empty($ssh_accounts)) {
    $loop = Factory::create();

    while ($user_data = mysqli_fetch_assoc($result_servidores)) {
        $ip          = $user_data['ip'];
        $servidor_id = $user_data['id'];
        $senha_token = getServidorToken($conn, $servidor_id);

        $socket = @fsockopen($ip, 6969, $errno, $errstr, 5);

        if ($socket) {
            fclose($socket);

            $loop->addTimer(0.1, function () use ($ip, $caminho_completo, $nome_arquivo, $senha_token, &$servidores_sucesso, &$servidores_erro) {
                if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) {
                    $servidores_sucesso[] = $ip;
                    return;
                }

                $limiter_content = file_get_contents($caminho_completo);
                $headers = [
                    'Senha: ' . $senha_token,
                    'User-Agent: Atlas-Suspender-Manual/1.0'
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
                    // CORREÇÃO: Usa suspend.py (BLOQUEIA) em vez de delete.py (EXCLUI)
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, 'http://' . $ip . ':6969');
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
                        'comando' => 'cd /etc/xis && sudo python3 /etc/xis/suspend.py ' . $nome_arquivo . ' > /dev/null 2>/dev/null &'
                    ]));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                    curl_exec($ch2);
                    curl_close($ch2);

                    $servidores_sucesso[] = $ip;
                } else {
                    $servidores_erro[] = $ip;
                }
            });

        } else {
            $servidores_erro[] = $ip;
        }
    }

    $loop->run();
}

// ─── ATUALIZA BANCO ──────────────────────────────────────────────────────────
foreach ($todos_revendedores as $rev_id) {
    $conn->query("UPDATE atribuidos SET suspenso = '1' WHERE userid = '$rev_id'");
    $conn->query("UPDATE accounts SET status = 'Suspenso' WHERE id = '$rev_id'");
}

if (!empty($todos_revendedores)) {
    $ids_str = implode(",", $todos_revendedores);
    $conn->query("UPDATE ssh_accounts SET mainid = 'Suspenso', status = 'Offline' WHERE byid IN ($ids_str)");
}

// Log
$data_log  = date('d/m/Y H:i:s');
$log_texto = "Suspendeu MANUALMENTE revendedor ID $id + " . ($total_revendedores - 1) . " sub-revendedores e $total_usuarios usuarios";
$conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$_SESSION['login']}', '$data_log', '$log_texto', '{$_SESSION['iduser']}')");

// Limpa arquivo temporário
if (file_exists($caminho_completo)) {
    unlink($caminho_completo);
}

// ─── RESULTADO ────────────────────────────────────────────────────────────────
$msg = "Revendedor suspenso com sucesso!\\n\\nRevendedores suspensos: $total_revendedores\\nUsuarios suspensos: $total_usuarios";
if (!empty($servidores_sucesso)) $msg .= "\\n\\nServidores OK: " . implode(", ", $servidores_sucesso);
if (!empty($servidores_erro))    $msg .= "\\n\\nServidores com falha: " . implode(", ", $servidores_erro);

echo "<script>swal('Sucesso!', '$msg', 'success').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";

mysqli_close($conn);
?>
