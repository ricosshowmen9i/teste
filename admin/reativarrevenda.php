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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']);
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

$id = isset($_GET['id']) ? anti_sql($_GET['id']) : 0;

if (empty($id)) {
    echo "<script>
        alert('ID não fornecido!');
        window.location.href = 'listarrevendedores.php';
    </script>";
    exit();
}

$sql = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $categoria = $row['categoriaid'];
} else {
    echo "<script>
        alert('Revendedor não encontrado!');
        window.location.href = 'listarrevendedores.php';
    </script>";
    exit();
}

$contas = array();
$ssh_accounts = array();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include ('Net/SSH2.php');

function buscarTodosRevendedores($conn, $id_pai, &$todos_ids = []) {
    if (!in_array($id_pai, $todos_ids)) {
        $todos_ids[] = $id_pai;
    }
    
    $sql = "SELECT id FROM accounts WHERE byid = '$id_pai'";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        buscarTodosRevendedores($conn, $row['id'], $todos_ids);
    }
    
    return $todos_ids;
}

$todos_revendedores = [];
buscarTodosRevendedores($conn, $id, $todos_revendedores);
$todos_revendedores = array_unique($todos_revendedores);

$ssh_accounts = [];

if (!empty($todos_revendedores)) {
    $ids_str = implode(",", $todos_revendedores);
    
    // CORREÇÃO: Buscar TODOS os usuários SSH (não apenas os marcados como Suspenso)
    // pois na suspensão anterior eles podem ter sido bloqueados no servidor
    $sql_ssh = "SELECT login, senha, expira, limite, uuid FROM ssh_accounts WHERE byid IN ($ids_str)";
    $result_ssh = $conn->query($sql_ssh);
    
    while ($row_ssh = $result_ssh->fetch_assoc()) {
        $ssh_accounts[] = $row_ssh;
    }
}

function calcularDiasRestantes($data_expira) {
    if (empty($data_expira)) return 30;
    
    $hoje = new DateTime();
    $expira = new DateTime($data_expira);
    if ($expira < $hoje) return 1;
    
    $diferenca = $hoje->diff($expira);
    return max(1, $diferenca->days);
}

$nome_arquivo = md5(uniqid(rand(), true));
$nome_arquivo = substr($nome_arquivo, 0, 10) . ".txt";
$caminho_completo = __DIR__ . '/' . $nome_arquivo;

// CORREÇÃO: Formato do arquivo: login senha dias limite uuid
if (!empty($ssh_accounts)) {
    $file = fopen($caminho_completo, "w");
    foreach ($ssh_accounts as $ssh_account) {
        $login = $ssh_account['login'];
        $senha = $ssh_account['senha'];
        $dias = calcularDiasRestantes($ssh_account['expira']);
        $limite = $ssh_account['limite'] ?? 1;
        $uuid = $ssh_account['uuid'] ?? '';
        
        fwrite($file, "$login $senha $dias $limite $uuid\n");
    }
    fclose($file);
}

$sql_servidores = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result_servidores = $conn->query($sql_servidores);

$total_revendedores = count($todos_revendedores);
$total_usuarios = count($ssh_accounts);
$servidores_sucesso = [];
$servidores_erro = [];
$sucesso_servidor = false;

if ($result_servidores->num_rows > 0) {
    $loop = Factory::create();
    
    while ($servidor = mysqli_fetch_assoc($result_servidores)) {
        $ip = $servidor['ip'];
        $servidor_id = $servidor['id'];
        $timeout = 5;
        
        $token_servidor = getServidorToken($conn, $servidor_id);
        
        $socket = @fsockopen($ip, 6969, $errno, $errstr, $timeout);
        
        if ($socket) {
            fclose($socket);
            
            $loop->addTimer(0.1, function () use ($ip, $caminho_completo, $nome_arquivo, $token_servidor, &$servidores_sucesso, &$servidores_erro, &$sucesso_servidor) {
                if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) {
                    $servidores_sucesso[] = $ip;
                    $sucesso_servidor = true;
                    return;
                }
                
                $limiter_content = file_get_contents($caminho_completo);
                
                $headers = array(
                    'Senha: ' . $token_servidor,
                    'User-Agent: Atlas-Reativar-Revenda/1.0'
                );
                
                // Enviar arquivo para /root/ no servidor
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, 'http://' . $ip . ':6969');
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch1, CURLOPT_POST, 1);
                curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'echo "' . addslashes($limiter_content) . '" > /root/' . $nome_arquivo)));
                curl_setopt($ch1, CURLOPT_TIMEOUT, 30);
                $output1 = curl_exec($ch1);
                $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
                curl_close($ch1);
                
                // CORREÇÃO: sincronizar.py agora busca em /root/ automaticamente
                if ($httpCode1 == 200) {
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, 'http://' . $ip . ':6969');
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'cd /etc/xis && sudo python3 /etc/xis/sincronizar.py ' . $nome_arquivo . ' > /dev/null 2>/dev/null &')));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                    $output2 = curl_exec($ch2);
                    curl_close($ch2);
                    
                    $servidores_sucesso[] = $ip;
                    $sucesso_servidor = true;
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

// ─── ATUALIZAR BANCO DE DADOS ────────────────────────────────────────────────

// 1. Marca todos os revendedores como não suspensos
foreach ($todos_revendedores as $rev_id) {
    $sql_update_rev = "UPDATE atribuidos SET suspenso = '0' WHERE userid = '$rev_id'";
    $conn->query($sql_update_rev);
}

// 2. Limpa o mainid dos usuários SSH
if (!empty($todos_revendedores)) {
    $ids_str = implode(",", $todos_revendedores);
    $sql_update_ssh = "UPDATE ssh_accounts SET mainid = '', status = 'Offline' WHERE byid IN ($ids_str)";
    $conn->query($sql_update_ssh);
}

// 3. Marca a conta do revendedor principal como Ativo
// CORREÇÃO: Atualiza TODOS os revendedores da árvore, não só o principal
foreach ($todos_revendedores as $rev_id) {
    $sql_update_acc = "UPDATE accounts SET status = 'Ativo' WHERE id = '$rev_id'";
    $conn->query($sql_update_acc);
}

// ─── LOG ─────────────────────────────────────────────────────────────────────
$data_log = date('d/m/Y H:i:s');
$log_texto = "Admin reativou revendedor ID $id + " . ($total_revendedores-1) . " sub-revendedores e $total_usuarios usuários";
$sql_log = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$data_log', '$log_texto', '$_SESSION[iduser]')";
$conn->query($sql_log);

// ─── LIMPEZA ─────────────────────────────────────────────────────────────────
if (file_exists($caminho_completo)) {
    unlink($caminho_completo);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reativando Revendedor...</title>
    <script src="../app-assets/sweetalert.min.js"></script>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
    <div class="loading">
        <div class="spinner"></div>
        <p>Reativando revendedor e todos os usuários...</p>
    </div>

    <script>
    <?php if ($sucesso_servidor || $total_usuarios > 0 || $total_revendedores > 0): ?>
        setTimeout(function() {
            swal({
                title: "Sucesso!",
                text: "Contas Reativadas com sucesso!\n\nEstatísticas:\n- Revendedores reativados: <?php echo $total_revendedores; ?>\n- Usuários reativados: <?php echo $total_usuarios; ?>\n<?php if (!empty($servidores_sucesso)): ?>- Servidores: <?php echo implode(", ", array_unique($servidores_sucesso)); ?><?php endif; ?><?php if (!empty($servidores_erro)): ?>\n\nFalha nos servidores: <?php echo implode(", ", array_unique($servidores_erro)); ?><?php endif; ?>",
                icon: "success",
                button: "OK"
            }).then(function() {
                window.location.href = 'listarrevendedores.php';
            });
        }, 1000);
    <?php else: ?>
        setTimeout(function() {
            swal({
                title: "Erro!",
                text: "Erro ao reativar contas!\n\nNenhum servidor respondeu.",
                icon: "error",
                button: "OK"
            }).then(function() {
                window.location.href = 'listarrevendedores.php';
            });
        }, 1000);
    <?php endif; ?>
    </script>
</body>
</html>


