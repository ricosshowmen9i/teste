<?php 
// Limpa qualquer saída anterior
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$log_file = 'debug_suspender.log';

function escreverLog($mensagem) {
    global $log_file;
    $data = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$data] $mensagem\n", FILE_APPEND);
}

escreverLog("=== INÍCIO DO PROCESSO DE SUSPENSÃO ===");

if (!isset($_SESSION)){
    session_start();
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include ('Net/SSH2.php');

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    escreverLog("Sessão não existe, redirecionando");
    session_destroy();
    header('location:index.php');
    exit();
}

escreverLog("Usuário logado: " . $_SESSION['login']);

include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    escreverLog("Erro de conexão com banco: " . mysqli_connect_error());
    die("Connection failed: " . mysqli_connect_error());
}
escreverLog("Conectou ao banco de dados");

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
    escreverLog("Buscando token para servidor ID: $servidor_id");
    
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        escreverLog("Token encontrado para servidor $servidor_id");
        return $row_token['token'];
    }
    
    escreverLog("NENHUM token encontrado para servidor $servidor_id");
    return md5($_SESSION['token']);
}

$_GET['id'] = anti_sql($_GET['id']);

if (empty($_GET['id'])) {
    escreverLog("ERRO: ID não fornecido");
    ob_end_clean();
    echo "erro no servidor";
    exit();
}

$id = $_GET['id'];
escreverLog("ID recebido: $id");

$sql = "SELECT * FROM ssh_accounts WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    escreverLog("ERRO: Usuário ID $id não encontrado");
    ob_end_clean();
    echo "erro no servidor";
    exit();
}

$row = mysqli_fetch_assoc($result);
$login = $row['login'];
$categoria = $row['categoriaid'];
$uuid = $row['uuid'];
$status_atual = $row['mainid'];

escreverLog("Usuário encontrado: Login=$login, Categoria=$categoria, UUID=$uuid, Status=$status_atual");

$sql2 = "SELECT * FROM servidores WHERE subid = ?";
$stmt2 = mysqli_prepare($conn, $sql2);
mysqli_stmt_bind_param($stmt2, "i", $categoria);
mysqli_stmt_execute($stmt2);
$result = mysqli_stmt_get_result($stmt2);

if (mysqli_num_rows($result) == 0) {
    escreverLog("ERRO: Nenhum servidor encontrado para categoria $categoria");
    ob_end_clean();
    echo "erro no servidor";
    exit();
}

$total_servidores = mysqli_num_rows($result);
escreverLog("Encontrados $total_servidores servidores para categoria $categoria");

$loop = Factory::create();
$servidores_com_erro = [];
$servidores_sucesso = [];
$resultados = [];

while ($user_data = mysqli_fetch_assoc($result)) {
    $ip = $user_data['ip'];
    $servidor_id = $user_data['id'];
    $timeout = 5;
    
    escreverLog("Processando servidor: $ip (ID: $servidor_id)");
    
    $socket = @fsockopen($ip, 6969, $errno, $errstr, $timeout);
    
    if ($socket) {
        fclose($socket);
        escreverLog("Conexão OK com $ip");
        
        $token_servidor = getServidorToken($conn, $servidor_id);
        
        // CORREÇÃO: Usar atlassuspend.sh (BLOQUEIA) em vez de atlasremove.sh (EXCLUI)
        $comando = 'sudo /etc/xis/atlassuspend.sh ' . escapeshellarg($login);
        
        escreverLog("Comando: $comando");
        
        $loop->addTimer(0.1, function () use ($ip, $comando, $token_servidor, &$resultados) {
            escreverLog("Enviando comando para $ip...");
            
            $headers = array(
                'Senha: ' . $token_servidor,
                'User-Agent: Atlas-Suspender/1.0'
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://' . $ip . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=" . urlencode($comando));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            escreverLog("Resposta de $ip - HTTP Code: $httpCode");
            escreverLog("Output: " . substr($output, 0, 200));
            
            $resultados[] = [
                'ip' => $ip,
                'httpCode' => $httpCode,
                'output' => $output,
                'error' => $curlError
            ];
        });
        
    } else {
        escreverLog("Falha na conexão com $ip - $errstr");
        $servidores_com_erro[] = $ip;
    }
}

escreverLog("Executando loop de timers...");
$loop->run();
escreverLog("Loop finalizado");

foreach ($resultados as $resultado) {
    if ($resultado['httpCode'] == 200) {
        $servidores_sucesso[] = $resultado['ip'];
    } else {
        $servidores_com_erro[] = $resultado['ip'];
    }
}

escreverLog("Servidores com sucesso: " . count($servidores_sucesso));
escreverLog("Servidores com erro: " . count($servidores_com_erro));

// Limpa TUDO que possa ter sido impresso antes
ob_end_clean();

if (count($servidores_sucesso) > 0) {
    escreverLog("SUCESSO, atualizando banco...");
    
    $suspenso = "Suspenso";
    
    $sql3 = "UPDATE ssh_accounts SET mainid = ? WHERE id = ?";
    $stmt3 = mysqli_prepare($conn, $sql3);
    mysqli_stmt_bind_param($stmt3, "si", $suspenso, $id);
    
    if (mysqli_stmt_execute($stmt3)) {
        escreverLog("Banco atualizado");
        
        $sql_log = "INSERT INTO logs (revenda, validade, texto, userid) VALUES (?, ?, ?, ?)";
        $stmt_log = mysqli_prepare($conn, $sql_log);
        $log_texto = "Suspendeu usuário: $login";
        $data_log = date('d/m/Y H:i:s');
        mysqli_stmt_bind_param($stmt_log, "sssi", $_SESSION['login'], $data_log, $log_texto, $_SESSION['iduser']);
        mysqli_stmt_execute($stmt_log);
        
        escreverLog("CONCLUÍDO COM SUCESSO");
        
        // Resposta limpa - só "ok"
        echo "suspenso com sucesso";
        exit();
        
    } else {
        escreverLog("Erro banco: " . mysqli_error($conn));
        echo "erro no servidor";
        exit();
    }
    
} else {
    escreverLog("FALHA - Nenhum servidor respondeu");
    echo "erro no servidor";
    exit();
}

mysqli_close($conn);
?>
