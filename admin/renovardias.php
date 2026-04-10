<?php 
if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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

if (!empty($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $login = $row['login'];
    $senha = $row['senha'];
    $validade = $row['expira'];
    $limite = $row['limite'];
    $_SESSION['categoriarenov'] = $row['categoriaid'];
    
    if ($validade < date('Y-m-d H:i:s')){
        $validade = date('Y-m-d H:i:s');
    }
    
    $novadata = date('Y-m-d H:i:s', strtotime("+31 days", strtotime($validade)));
    $sql = "UPDATE ssh_accounts SET expira = '$novadata', mainid = '' WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
}

date_default_timezone_set('America/Sao_Paulo');

$novadata = date('Y-m-d H:i:s', strtotime($novadata));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($novadata) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));

$sql2 = "SELECT * FROM servidores WHERE subid = '$_SESSION[categoriarenov]'";
$result = $conn->query($sql2);
$servidores = array();

date_default_timezone_set('America/Sao_Paulo');
$datahoje = date('d-m-Y H:i:s');
$sql10 = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Renovou 30 dias para o usuario $login', '$_SESSION[iduser]')";
$result10 = mysqli_query($conn, $sql10);

set_time_limit(0);
ignore_user_abort(true);

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include ('Net/SSH2.php');

$loop = Factory::create();
$servidores_com_erro = [];
$sucess = false;        
$sucess_servers = [];
$failed_servers = [];

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
            $senhatoken = $row_token['token']; // JÁ ESTÁ EM MD5
        } else {
            $senhatoken = md5($_SESSION['token']); // fallback
        }
        // ===== FIM DA CORREÇÃO =====
        
        $loop->addTimer(0.001, function () use ($user_data, $conn, $login, $dias, $senha, $limite, $senhatoken) {
            $comando1 = 'sudo rm -rf /etc/SSHPlus/userteste/'.$login.'.sh';
            $comando2 = 'sudo /etc/xis/atlasremove.sh ' . $login . ' ';
            $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $login . ' ' . $senha . ' ' . $dias . ' ' . $limite . ' ';
            
            $headers = array(
                'Senha: ' . $senhatoken
            );
            
            // Remove arquivo de teste
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
            $output = curl_exec($ch);
            curl_close($ch);
            
            // Remove usuário antigo
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
            $output = curl_exec($ch);
            curl_close($ch);
            
            // Cria novo usuário
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
            $output = curl_exec($ch);
            curl_close($ch);
        });
        
        $sucess_servers[] = $user_data['nome'];
        $sucess = true;
    } else {
        $servidores_com_erro[] = $user_data['ip'];
        $failed_servers[] = $user_data['nome'];
    }
}            

$resposta = array();
if ($sucess == true) {
    $response = [
        'sucesso' => true,
        'mensagem' => 'Dias renovados com sucesso, Relatorio dos Servidores. Servidores Renovados: ' . implode(', ', $sucess_servers) . '.'
    ];

    if (!empty($failed_servers)) {
        $response['mensagem'] .= ' Servidores com erro: ' . implode(', ', $failed_servers) . '.';
    }

    echo json_encode($response);
} else {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao renovar dias!'
    ]);
}

$loop->run();
?>