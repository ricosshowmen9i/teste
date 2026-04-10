<script src="../app-assets/sweetalert.min.js"></script>
<?php
if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

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

date_default_timezone_set('America/Sao_Paulo');

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// CORREÇÃO: Busca token específico do servidor (em vez de md5 da sessão)
function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']); // fallback
}

// CORREÇÃO: Busca toda a árvore recursivamente
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

$_GET['id'] = anti_sql($_GET['id'] ?? '');

if (empty($_GET['id'])) {
    echo "<script>swal('Erro!', 'ID não fornecido!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

$id = $_GET['id'];

// ─── BUSCA DADOS DO REVENDEDOR ────────────────────────────────────────────────
$sql    = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = $conn->query($sql);
$row    = $result->fetch_assoc();

if (!$row) {
    echo "<script>swal('Erro!', 'Revendedor não encontrado!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

$categoria = $row['categoriaid'];
$byid      = $row['byid'];
$suspenso  = $row['suspenso'];

// ─── VERIFICA PERMISSÃO ───────────────────────────────────────────────────────
if ($byid != $_SESSION['iduser']) {
    echo "<script>sweetAlert('Oops...', 'Você não tem permissão para editar este usuário!', 'error').then(function(){window.location.href='../home.php'});</script>";
    exit();
}

// ─── VERIFICA SE JÁ ESTÁ ATIVO ───────────────────────────────────────────────
if ($suspenso != '1') {
    echo "<script>sweetAlert('Aviso!', 'Este revendedor já está ativo!', 'warning').then(function(){window.location.href='listarrevendedores.php'});</script>";
    exit();
}

set_time_limit(0);
ignore_user_abort(true);

// ─── BUSCA TODA A ÁRVORE ──────────────────────────────────────────────────────
$todos_revendedores = [];
buscarTodosRevendedores($conn, $id, $todos_revendedores);
$todos_revendedores = array_unique($todos_revendedores);
$ids_str = implode(",", $todos_revendedores);

// ─── BUSCA USUÁRIOS SSH ──────────────────────────────────────────────────────
$ssh_accounts = [];
// CORREÇÃO: Busca TODOS os usuários (não apenas Suspenso), pois podem ter sido bloqueados no servidor
$sql_ssh      = "SELECT * FROM ssh_accounts WHERE byid IN ($ids_str)";
$result_ssh   = $conn->query($sql_ssh);
while ($row_ssh = $result_ssh->fetch_assoc()) {
    $ssh_accounts[] = $row_ssh;
}

$total_revendedores = count($todos_revendedores);
$total_usuarios     = count($ssh_accounts);

// ─── PREPARA ARQUIVO TXT ──────────────────────────────────────────────────────
$nome_arquivo     = substr(md5(uniqid(rand(), true)), 0, 10) . ".txt";
$caminho_completo = __DIR__ . '/' . $nome_arquivo;

if (!empty($ssh_accounts)) {
    $file = fopen($caminho_completo, "w");
    foreach ($ssh_accounts as $ssh_account) {
        $login_ssh  = $ssh_account['login'];
        $senha_ssh  = $ssh_account['senha'];
        $uuid_ssh   = $ssh_account['uuid'] ?? '';
        $limite_ssh = $ssh_account['limite'] ?? 1;

        $diferenca = strtotime($ssh_account['expira']) - time();
        $dias      = max(1, floor($diferenca / 86400));

        fwrite($file, "$login_ssh $senha_ssh $dias $limite_ssh $uuid_ssh\n");
    }
    fclose($file);
}

// ─── ENVIA PARA SERVIDORES ────────────────────────────────────────────────────
$servidores_ok   = [];
$servidores_erro = [];

if (!empty($ssh_accounts)) {
    $sql_serv    = "SELECT * FROM servidores WHERE subid = '$categoria'";
    $result_serv = $conn->query($sql_serv);

    if ($result_serv && $result_serv->num_rows > 0) {
        while ($user_data = mysqli_fetch_assoc($result_serv)) {
            $ip          = $user_data['ip'];
            $ipeporta    = $ip . ':6969';
            $servidor_id = $user_data['id'];

            // CORREÇÃO: Usa token específico do servidor
            $senha_token = getServidorToken($conn, $servidor_id);

            $socket = @fsockopen($ip, 6969, $errno, $errstr, 5);

            if ($socket) {
                fclose($socket);

                $limiter_content = file_get_contents($caminho_completo);
                $headers = [
                    'Senha: ' . $senha_token,
                    'User-Agent: Atlas-Reativar-Revenda/1.0'
                ];

                // Passo 1: envia o arquivo para /root/
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, 'http://' . $ipeporta);
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
                    // Passo 2: executa sincronizar.py (agora busca em /root/ automaticamente)
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, 'http://' . $ipeporta);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
                        'comando' => 'cd /etc/xis && sudo python3 /etc/xis/sincronizar.py ' . $nome_arquivo . ' > /dev/null 2>/dev/null &'
                    ]));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                    curl_exec($ch2);
                    curl_close($ch2);

                    $servidores_ok[] = $ip;
                } else {
                    $servidores_erro[] = $ip;
                }
            } else {
                $servidores_erro[] = $ip;
            }
        }
    }
}

// ─── ATUALIZA BANCO ──────────────────────────────────────────────────────────
foreach ($todos_revendedores as $rev_id) {
    $conn->query("UPDATE atribuidos SET suspenso = '0' WHERE userid = '$rev_id'");
    $conn->query("UPDATE accounts SET status = 'Ativo' WHERE id = '$rev_id'");
}
$conn->query("UPDATE ssh_accounts SET mainid = '' WHERE byid IN ($ids_str)");

// Log
$data_log  = date('d/m/Y H:i:s');
$log_texto = "Reativou revendedor ID $id + " . ($total_revendedores - 1) . " sub-revendedores e $total_usuarios usuarios";
$conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$_SESSION['login']}', '$data_log', '$log_texto', '{$_SESSION['iduser']}')");

// Limpa arquivo
if (file_exists($caminho_completo)) unlink($caminho_completo);

// ─── RESULTADO ────────────────────────────────────────────────────────────────
$msg = "Revendedor Reativado com sucesso!\\n\\nSub-revendedores reativados: " . ($total_revendedores - 1) . "\\nUsuarios reativados: $total_usuarios";
if (!empty($servidores_ok))   $msg .= "\\n\\nServidores OK: " . implode(", ", $servidores_ok);
if (!empty($servidores_erro)) $msg .= "\\nServidores com falha: " . implode(", ", $servidores_erro);

echo "<script>sweetAlert('Sucesso!', '$msg', 'success').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";

mysqli_close($conn);
?>
