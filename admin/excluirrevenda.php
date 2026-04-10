<script src="../app-assets/sweetalert.min.js"></script>
<?php 
use React\EventLoop\Factory;

if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

include('../vendor/event/autoload.php');

// Verificação de sessão
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit;
}

include('../AegisCore/conexao.php');
include('headeradmin2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Verificação de token
if (!file_exists('suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
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

if ($_SESSION['login'] !== 'admin') {
    session_destroy();
    header('Location: index.php');
    exit();
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

// Anti SQL injection na $_GET['id']
$id = isset($_GET['id']) ? anti_sql($_GET['id']) : 0;

if (empty($id)) {
    echo "<script>alert('ID do revendedor não informado!');</script>";
    echo "<script>location.href='listarrevendedores.php';</script>";
    exit;
}

// ============================================
// FUNÇÃO RECURSIVA PARA BUSCAR TODOS OS SUBORDINADOS
// ============================================
function getAllSubordinates($conn, $userId, &$allUsers = []) {
    $allUsers[] = $userId;
    
    $sql = "SELECT id FROM accounts WHERE byid = '$userId'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $subId = $row['id'];
            if (!in_array($subId, $allUsers)) {
                getAllSubordinates($conn, $subId, $allUsers);
            }
        }
    }
    
    return $allUsers;
}

// ============================================
// BUSCAR CATEGORIA DO REVENDEDOR
// ============================================
$categoria = 0;
$sql_cat = "SELECT categoriaid FROM atribuidos WHERE userid = '$id'";
$result_cat = $conn->query($sql_cat);
if ($result_cat && $result_cat->num_rows > 0) {
    $row_cat = $result_cat->fetch_assoc();
    $categoria = $row_cat['categoriaid'];
}

// ============================================
// BUSCAR TODOS OS USUÁRIOS (REVENDEDOR PRINCIPAL + TODOS SUBORDINADOS)
// ============================================
$allUsers = getAllSubordinates($conn, $id);
$allUsers = array_unique($allUsers);

// ============================================
// BUSCAR TODAS AS CONTAS SSH DOS USUÁRIOS
// ============================================
$ssh_accounts = [];
$userIds = implode("','", $allUsers);

if (!empty($userIds)) {
    $sql_ssh = "SELECT * FROM ssh_accounts WHERE byid IN ('$userIds')";
    $result_ssh = $conn->query($sql_ssh);
    if ($result_ssh && $result_ssh->num_rows > 0) {
        while ($row = $result_ssh->fetch_assoc()) {
            $ssh_accounts[] = $row;
        }
    }
}

// ============================================
// PRIMEIRO: DELETAR AS CONTAS NOS SERVIDORES SSH
// ============================================
echo "<div style='padding:20px;'>";
echo "<h3>Excluindo revendedor e subordinados...</h3>";

$total_deletadas = 0;
$total_erros = 0;

if (count($ssh_accounts) == 0) {
    echo "<p>Nenhuma conta SSH encontrada para excluir.</p>";
} else {
    echo "<p>Total de contas SSH a serem excluídas: <strong>" . count($ssh_accounts) . "</strong></p>";
    
    // CORREÇÃO: Criar arquivo TXT com todos os logins para exclusão em lote
    $nome_arquivo = substr(md5(uniqid(rand(), true)), 0, 10) . ".txt";
    $caminho_completo = __DIR__ . '/' . $nome_arquivo;
    
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
    
    $sql_servers = "SELECT * FROM servidores WHERE subid = '$categoria'";
    $result_servers = $conn->query($sql_servers);
    
    if ($result_servers && $result_servers->num_rows > 0) {
        echo "<p>Servidores encontrados: " . $result_servers->num_rows . "</p>";
        
        // CORREÇÃO: Usar loop com timers e executar $loop->run() ANTES de deletar do banco
        $loop = Factory::create();
        $servidores_ok = [];
        $servidores_falha = [];
        
        while ($server = mysqli_fetch_assoc($result_servers)) {
            $ip = $server['ip'];
            $servidor_id = $server['id'];
            $senha_token = getServidorToken($conn, $servidor_id);
            
            // Testar conexão com o módulo Python
            $socket = @fsockopen($ip, 6969, $errno, $errstr, 3);
            if (!$socket) {
                echo "<p style='color:red;'>Não foi possível conectar ao servidor $ip (porta 6969)</p>";
                $servidores_falha[] = $ip;
                continue;
            }
            fclose($socket);
            
            $loop->addTimer(0.1, function () use ($ip, $caminho_completo, $nome_arquivo, $senha_token, &$servidores_ok, &$servidores_falha, &$total_deletadas, &$total_erros) {
                if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) {
                    $servidores_ok[] = $ip;
                    return;
                }
                
                $limiter_content = file_get_contents($caminho_completo);
                
                $headers = array(
                    'Senha: ' . $senha_token,
                    'User-Agent: Atlas-Excluir-Revenda/1.0'
                );
                
                // Enviar arquivo para /root/
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
                
                // CORREÇÃO: Usar delete.py (que agora busca em /root/ automaticamente)
                if ($httpCode1 == 200) {
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, 'http://' . $ip . ':6969');
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'cd /etc/xis && sudo python3 /etc/xis/delete.py ' . $nome_arquivo)));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 60);
                    $output2 = curl_exec($ch2);
                    curl_close($ch2);
                    
                    $servidores_ok[] = $ip;
                    $total_deletadas++;
                } else {
                    $servidores_falha[] = $ip;
                    $total_erros++;
                }
            });
        }
        
        // CORREÇÃO CRÍTICA: Executar $loop->run() ANTES de deletar do banco
        // Isso garante que os comandos de exclusão no servidor sejam executados primeiro
        $loop->run();
        
        // Limpar arquivo temporário
        if (file_exists($caminho_completo)) {
            unlink($caminho_completo);
        }
        
        echo "<hr>";
        echo "<h3>Resumo da exclusão nos servidores:</h3>";
        echo "<p>Servidores OK: <strong style='color:green;'>" . count($servidores_ok) . "</strong></p>";
        echo "<p>Servidores com falha: <strong style='color:red;'>" . count($servidores_falha) . "</strong></p>";
        
    } else {
        echo "<p style='color:orange;'>Nenhum servidor encontrado para a categoria $categoria</p>";
    }
}

// ============================================
// SEGUNDO: DELETAR DO BANCO DE DADOS (APÓS confirmar exclusão nos servidores)
// ============================================
echo "<hr>";
echo "<h3>Excluindo registros do banco de dados...</h3>";

// Registrar log
date_default_timezone_set('America/Sao_Paulo');
$datahoje = date('d-m-Y H:i:s');
$sql_log = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Excluiu revendedor ID: $id e todos os seus subordinados. Total de contas SSH: " . count($ssh_accounts) . "', '$_SESSION[iduser]')";
mysqli_query($conn, $sql_log);

// Deletar todas as contas SSH
if (!empty($ssh_accounts)) {
    $sql_del_ssh = "DELETE FROM ssh_accounts WHERE byid IN ('$userIds')";
    if ($conn->query($sql_del_ssh)) {
        echo "<p>Contas SSH deletadas do banco: " . mysqli_affected_rows($conn) . "</p>";
    } else {
        echo "<p style='color:red;'>Erro ao deletar contas SSH: " . $conn->error . "</p>";
    }
}

// Deletar todos os usuários (revendedores)
$sql_del_users = "DELETE FROM accounts WHERE id IN ('$userIds')";
if ($conn->query($sql_del_users)) {
    echo "<p>Usuários deletados do banco: " . mysqli_affected_rows($conn) . "</p>";
}

// Deletar atribuições
$sql_del_atrib = "DELETE FROM atribuidos WHERE userid IN ('$userIds')";
if ($conn->query($sql_del_atrib)) {
    echo "<p>Atribuições deletadas do banco: " . mysqli_affected_rows($conn) . "</p>";
}

echo "<hr>";
echo "<h3 style='color:green;'>Processo concluído!</h3>";
echo "</div>";

// Script para mostrar resultado
echo "<script src='../app-assets/sweetalert.min.js'></script>";
echo "<script>
    swal({
        title: 'Processo Concluído!',
        text: 'Revendedor e todos os subordinados foram excluídos!\\n\\nTotal de contas SSH deletadas: " . count($ssh_accounts) . "',
        icon: 'success',
        button: 'OK'
    }).then(function() {
        window.location.href = 'listarrevendedores.php';
    });
</script>";

mysqli_close($conn);
?>
