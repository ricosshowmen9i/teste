<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Verificar login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    echo json_encode(['status' => 'error', 'message' => 'Sessão expirada!']);
    exit();
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Erro de conexão com o banco de dados!']);
    exit();
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

// Verificar token
if (!file_exists('../admin/suspenderrev.php')) {
    echo json_encode(['status' => 'error', 'message' => 'Token inválido!']);
    exit();
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Token inválido!']);
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

// Verificar conta do revendedor
$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
if (!$sql5) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao verificar conta do revendedor!']);
    exit();
}
$row = $sql5->fetch_assoc();
$validade_rev = $row['expira'];
$categoria = $row['categoriaid'];
$tipo = $row['tipo'];
$_SESSION['limite'] = $row['limite'];
$_SESSION['tipodeconta'] = $row['tipo'] ?: 'Validade';

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');

// Verificar validade da conta do revendedor
if ($_SESSION['tipodeconta'] == 'Credito') {
    if ($_SESSION['limite'] < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Limite de créditos insuficiente!']);
        exit();
    }
} elseif ($_SESSION['tipodeconta'] == 'Validade') {
    if ($validade_rev < $hoje) {
        echo json_encode(['status' => 'error', 'message' => 'Sua conta está vencida!']);
        exit();
    }
}

//anti sql injection
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID do usuário não informado!']);
    exit();
}

$sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado!']);
    exit();
}

$row = mysqli_fetch_assoc($result);
$login = $row['login'];
$senha = $row['senha'];
$validade = $row['expira'];
$limite = $row['limite'];
$byid = $row['byid'];
$uuid = $row['uuid'];
$categoria_user = $row['categoriaid'];
$notas = $row['lastview'];
$whatsapp = $row['whatsapp'];

// Verificar permissão
if ($byid != $_SESSION['iduser']) {
    echo json_encode(['status' => 'error', 'message' => 'Você não tem permissão para renovar este usuário!']);
    exit();
}

// Verificar se a data de validade é válida
if ($validade < $hoje) {
    $validade = $hoje;
}

// Calcular nova data (30 dias)
$novadata = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($validade)));
$data_atual = date('Y-m-d H:i:s');
$diferenca = strtotime($novadata) - strtotime($data_atual);
$dias_restantes = floor($diferenca / (60 * 60 * 24));

if ($dias_restantes < 1) {
    $dias_restantes = 30;
}

// Registrar log
$datahoje = date('d-m-Y H:i:s');
$sql10 = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Renovou 30 dias para o usuario $login', '$_SESSION[iduser]')";
mysqli_query($conn, $sql10);

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

$loop = Factory::create();
$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria_user'";
$result = $conn->query($sql2);

if (!$result || $result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum servidor encontrado para esta categoria!']);
    exit();
}

$sucess = false;
$sucess_servers = [];
$failed_servers = [];

while ($user_data = mysqli_fetch_assoc($result)) {
    $timeout = 3;
    $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);
    
    if ($socket) {
        fclose($socket);
        
        $servidor_id = $user_data['id'];
        $senha_token = getServidorToken($conn, $servidor_id);
        
        $loop->addTimer(0.001, function () use ($user_data, $login, $dias_restantes, $senha, $limite, $senha_token, $uuid) {
            $headers = array('Senha: ' . $senha_token);
            
            if ($uuid != '' && $uuid != 'Não Gerado') {
                $comando_remover = 'sudo /etc/xis/rem.sh ' . $uuid . ' ' . $login;
                $comando_adicionar = 'sudo /etc/xis/add.sh ' . $uuid . ' ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_remover");
                $output = curl_exec($ch);
                curl_close($ch);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_adicionar");
                $output = curl_exec($ch);
                curl_close($ch);
            } else {
                $comando_remover = 'sudo ./atlasremove.sh ' . $login;
                $comando_criar = 'sudo ./atlascreate.sh ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_remover");
                $output = curl_exec($ch);
                curl_close($ch);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_criar");
                $output = curl_exec($ch);
                curl_close($ch);
            }
        });
        
        $sucess_servers[] = $user_data['nome'];
        $sucess = true;
    } else {
        $failed_servers[] = $user_data['nome'];
    }
}

$loop->run();

if ($sucess == true) {
    // Atualizar créditos se necessário
    if ($tipo == 'Credito') {
        $sql11 = "UPDATE atribuidos SET limite = limite - 1 WHERE userid = '$_SESSION[iduser]'";
        mysqli_query($conn, $sql11);
    }
    
    // Atualizar data de expiração no banco
    $sql = "UPDATE ssh_accounts SET expira = '$novadata', mainid = '0' WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'status'        => 'success',
            'message'       => 'Usuário renovado com sucesso!',
            'servers'       => $sucess_servers,
            'failed'        => $failed_servers,
            'new_expiry'    => date('d/m/Y H:i:s', strtotime($novadata)),
            'dias_renovados'=> 30,
            'login'         => $login,
            'senha'         => $senha,
            'limite'        => $limite,
            'uuid'          => ($uuid && $uuid != 'Não Gerado') ? $uuid : '',
            'notas'         => $notas,
            'whatsapp'      => $whatsapp,
            'validade_anterior' => date('d/m/Y H:i:s', strtotime($validade)),
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar banco de dados: ' . mysqli_error($conn)]);
    }
} else {
    if (count($failed_servers) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao conectar com os servidores: ' . implode(', ', $failed_servers)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar renovação nos servidores!']);
    }
}
?>