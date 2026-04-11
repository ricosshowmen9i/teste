<?php
error_reporting(0);
session_start();

date_default_timezone_set('America/Sao_Paulo');

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');
include('../vendor/event/autoload.php');
use React\EventLoop\Factory;
include('conexao.php');
include('functions.whatsapp.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (isset($_SESSION['mensagem_enviada'])) {
    unset($_SESSION['mensagem_enviada']);
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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
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

$sql = "SELECT limite FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$result = $conn->prepare($sql);
$result->execute();
$result->bind_result($limiteatual);
$result->fetch();
$result->close();

$slq2 = "SELECT sum(limite) AS limiteusado FROM atribuidos where byid='" . $_SESSION['iduser'] . "' ";
$result = $conn->prepare($slq2);
$result->execute();
$result->bind_result($limiteusado);
$result->fetch();
$result->close();

$sql3 = "SELECT * FROM atribuidos WHERE byid = '$_SESSION[iduser]'";
$sql3 = $conn->prepare($sql3);
$sql3->execute();
$sql3->store_result();
$num_rows = $sql3->num_rows;
$numerodereven = $num_rows;

$slq2 = "SELECT sum(limite) AS numusuarios FROM ssh_accounts where byid='" . $_SESSION['iduser'] . "' ";
$result = $conn->prepare($slq2);
$result->execute();
$result->bind_result($numusuarios);
$result->fetch();
$result->close();

$limiteusado = $limiteusado + $numusuarios;
$restante = $_SESSION['limite'] - $limiteusado;
$_SESSION['restante'] = $restante;

$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row = $sql5->fetch_assoc();
$validade = $row['expira'];
$categoria = $row['categoriaid'];
$tipo = $row['tipo'];
$_SESSION['tipodeconta'] = $row['tipo'];
$_SESSION['limite'] = $row['limite'];

if ($tipo == 'Credito') {
    $tipo_txt = 'Restam ' . $_SESSION['limite'] . ' CrÃ©ditos';
} else {
    $tipo_txt = 'Limite usado: ' . $limiteusado . ' de ' . $_SESSION['limite'];
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$hoje = date('Y-m-d H:i:s');

// VARIÃVEIS PARA CONTROLE DE MODAL DE ERRO
$show_error_modal = false;
$error_message = '';
$error_type = '';

// Verificar se Ã© para mostrar o modal de erro sem redirecionar
$show_limite_modal = false;
if (isset($_GET['limite_erro']) && $_GET['limite_erro'] == 1) {
    $show_limite_modal = true;
    if (isset($_SESSION['modal_limite_erro'])) {
        if ($_SESSION['modal_limite_erro'] == 'vencido') {
            $error_message = $_SESSION['modal_limite_mensagem'] ?? 'Sua conta expirou! Entre em contato com o suporte.';
            $error_type = 'vencido';
        } else {
            $error_message = $_SESSION['modal_limite_mensagem'] ?? 'VocÃª atingiu o limite mÃ¡ximo de usuÃ¡rios!';
            $error_type = 'limite';
        }
        unset($_SESSION['modal_limite_erro']);
        unset($_SESSION['modal_limite_mensagem']);
    }
    $show_error_modal = true;
}

// Verificar limite e validade SOMENTE se NÃƒO for para mostrar o modal de erro
if (!$show_limite_modal) {
    if ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validade < $hoje) {
            $_SESSION['modal_limite_erro'] = 'vencido';
            $_SESSION['modal_limite_mensagem'] = 'Sua conta expirou! Entre em contato com o suporte para renovar.';
            header('Location: criarusuario.php?limite_erro=1');
            exit();
        }
        if ($restante < 1) {
            $_SESSION['modal_limite_erro'] = 'limite';
            $_SESSION['modal_limite_mensagem'] = 'VocÃª atingiu o limite mÃ¡ximo de usuÃ¡rios! Limite disponÃ­vel: 0 de ' . $_SESSION['limite'];
            header('Location: criarusuario.php?limite_erro=1');
            exit();
        }
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

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result = $conn->query($sql2);
$servidores = [];
while ($row = $result->fetch_assoc()) {
    $servidores[] = $row;
}

include('header2.php');

$mensagem_modal = '';
$sql_mensagem_modal = "SELECT mensagem FROM mensagens_modal WHERE funcao = 'criarusuario' AND byid = '$_SESSION[iduser]' AND ativo = 'ativada'";
$result_mensagem_modal = mysqli_query($conn, $sql_mensagem_modal);
if ($result_mensagem_modal && mysqli_num_rows($result_mensagem_modal) > 0) {
    $row_mensagem_modal = mysqli_fetch_assoc($result_mensagem_modal);
    $mensagem_modal = $row_mensagem_modal['mensagem'];
}

if (isset($_POST['criaruser'])) {
    $usuariofin   = $_POST['usuariofin'];
    $senhafin     = $_POST['senhafin'];
    $validadefin  = $_POST['validadefin'];
    $limitefin    = $_POST['limitefin'];
    $notas        = $_POST['notas'];
    $valormensal = $_POST['valormensal'] ?? '0';

    $usuariofin   = anti_sql($usuariofin);
    $senhafin     = anti_sql($senhafin);
    $validadefin  = anti_sql($validadefin);
    $notas        = anti_sql($notas);
    $limitefin    = anti_sql($limitefin);
    $valormensal = anti_sql($valormensal);

    if (strlen($usuariofin) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuariofin) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhafin) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhafin) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif ($validadefin > 90) {
        $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
        $show_error_modal = true;
    } elseif ($usuariofin == "") {
        $error_message = 'UsuÃ¡rio nÃ£o pode ser vazio!';
        $show_error_modal = true;
    } elseif ($senhafin == "") {
        $error_message = 'Senha nÃ£o pode ser vazia!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuariofin)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhafin)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_POST['limitefin'] > $_SESSION['limite']) {
        $error_message = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['limite'];
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] == 'Credito') {
        if ($limitefin > $_SESSION['limite']) {
            $error_message = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['limite'];
            $show_error_modal = true;
        }
    } elseif ($_POST['limitefin'] > $_SESSION['restante']) {
        $error_message = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['restante'];
        $show_error_modal = true;
    }

    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuariofin'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $_SESSION['usuariofin'] = $usuariofin;
        $_SESSION['senhafin']   = $senhafin;
        $_SESSION['validadefin'] = $validadefin;
        $_SESSION['limitefin']  = $limitefin;

        $sql4 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result4 = $conn->query($sql4);
        $rows = mysqli_fetch_all($result4, MYSQLI_ASSOC);

        $loop = Factory::create();
        $servidores_com_erro = [];
        define('SCRIPT_PATH', './atlascreate.sh');
        $sucess_servers = [];
        $failed_servers = [];
        $sucess = false;

        $_POST['v2ray'] = anti_sql($_POST['v2ray']);

        if ($_POST['v2ray'] == "sim") {
            $v2ray = "sim";
            $formattedUuid = generateUUID();
            $_SESSION['uuid'] = $formattedUuid;
        } else {
            $v2ray = "nao";
            $_SESSION['uuid'] = "";
        }

        foreach ($rows as $user_data) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);

                if ($v2ray == "sim") {
                    $comando = "sudo /etc/xis/add.sh $formattedUuid $usuariofin $senhafin $validadefin $limitefin ";
                } else {
                    $comando = "sudo /etc/xis/atlascreate.sh $usuariofin $senhafin $validadefin $limitefin ";
                }

                $headers = array('Senha: ' . $senha_token);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando");
                $output = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200 && (strpos($output, 'sucesso') !== false || strpos($output, 'success') !== false || empty($output))) {
                    $sucess_servers[] = $user_data['nome'];
                    $sucess = true;
                } else {
                    $failed_servers[] = $user_data['nome'];
                }
                $conectado = true;
            }

            if (!$conectado) {
                $servidores_com_erro[] = $user_data['ip'];
                $failed_servers[] = $user_data['nome'];
            }
            $loop->run();
        }

        if (!$sucess) {
            $error_message = 'Erro ao criar usuÃ¡rio em todos os servidores!';
            $show_error_modal = true;
        } else {
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);

            $datahoje = date('d-m-Y H:i:s');
            $sql10 = "INSERT INTO logs (revenda, byid, validade, texto, userid) VALUES ('$_SESSION[login]', '$_SESSION[byid]', '$datahoje', 'Criou um Usuario $usuariofin de $validadefin Dias ', '$_SESSION[iduser]')";
            mysqli_query($conn, $sql10);

            $_SESSION['whatsapp'] = $_POST['whatsapp'];

            $data = date('Y-m-d H:i:s');
            $data = strtotime($data);
            $data = strtotime("+" . $validadefin . " days", $data);
            $data = date('Y-m-d H:i:s', $data);
            $validadefin = $data;

            $sql9 = "INSERT INTO ssh_accounts (login, senha, expira, limite, byid, categoriaid, lastview ,bycredit, mainid, status, whatsapp, valormensal, uuid) VALUES ('$usuariofin', '$senhafin', '$validadefin', '$limitefin', '$_SESSION[iduser]', '$categoria', '$notas', '0', 'NULL', 'Offline', '$whatsapp', '$valormensal', '$formattedUuid')";
            $result9 = mysqli_query($conn, $sql9);
            
            // DISPARAR WHATSAPP VIA BACKEND
            if (!empty($whatsapp)) {
                $dados_msg = [
                    'usuario'  => $usuariofin,
                    'senha'    => $senhafin,
                    'validade' => date('d/m/Y', strtotime($validadefin)),
                    'limite'   => $limitefin,
                    'whatsapp' => $whatsapp
                ];
                dispararMensagemAutomatica($conn, $_SESSION['iduser'], 'criarusuario', $dados_msg);
            }

            if ($_SESSION['tipodeconta'] == 'Credito') {
                $total = $_SESSION['limite'] - $limitefin;
                $sql11 = "UPDATE atribuidos SET limite = '$total' WHERE userid = '$_SESSION[iduser]'";
                mysqli_query($conn, $sql11);
            }

            $_SESSION['modal_usuario']      = $usuariofin;
            $_SESSION['modal_senha']        = $senhafin;
            $_SESSION['modal_limite']       = $limitefin;
            $_SESSION['modal_validade']     = $validadefin;
            $_SESSION['modal_uuid']         = $_SESSION['uuid'];
            $_SESSION['modal_v2ray']        = $v2ray;
            $_SESSION['modal_whatsapp']     = $_POST['whatsapp'];
            $_SESSION['modal_valormensal'] = $valormensal;
            $_SESSION['modal_mensagem']     = $mensagem_modal;
            $_SESSION['sucess_servers']     = $sucess_servers;
            $_SESSION['failed_servers']     = $failed_servers;
            $_SESSION['show_modal']         = true;

            echo "<script>window.location.href = 'criarusuario.php?modal=1&sucess=" . urlencode($sucess_servers_str) . "&failed=" . urlencode($failed_servers_str) . "';</script>";
            exit();
        }
    }
    // âœ… FIX: $loop->run() removido daqui â€” estava sendo chamado mesmo com erro de validaÃ§Ã£o,
    // causando output inesperado que quebrava a pÃ¡gina e sumia com o formulÃ¡rio.
    // O loop jÃ¡ Ã© executado dentro do foreach acima, somente quando necessÃ¡rio.
}

$show_modal    = false;
$sucess_servers = isset($_GET['sucess']) ? explode(", ", $_GET['sucess']) : array();
$failed_servers = isset($_GET['failed']) ? explode(", ", $_GET['failed']) : array();

if (empty($sucess_servers[0]) && isset($_SESSION['sucess_servers'])) {
    $sucess_servers = $_SESSION['sucess_servers'];
    $failed_servers = $_SESSION['failed_servers'];
}

if (isset($_GET['modal']) && $_GET['modal'] == 1 && isset($_SESSION['show_modal']) && $_SESSION['show_modal'] === true) {
    $show_modal          = true;
    $modal_usuario       = $_SESSION['modal_usuario'];
    $modal_senha         = $_SESSION['modal_senha'];
    $modal_limite        = $_SESSION['modal_limite'];
    $modal_validade      = $_SESSION['modal_validade'];
    $modal_uuid          = $_SESSION['modal_uuid'];
    $modal_v2ray         = $_SESSION['modal_v2ray'];
    $modal_valormensal = $_SESSION['modal_valormensal'] ?? '0';
    $modal_mensagem      = $_SESSION['modal_mensagem'] ?? '';

    $mensagem_final = $modal_mensagem;
    if (!empty($mensagem_final)) {
        $mensagem_final = str_replace('{usuario}', $modal_usuario, $mensagem_final);
        $mensagem_final = str_replace('{login}',   $modal_usuario, $mensagem_final);
        $mensagem_final = str_replace('{senha}',   $modal_senha,   $mensagem_final);
        $mensagem_final = str_replace('{validade}', date('d/m/Y', strtotime($modal_validade)), $mensagem_final);
        $mensagem_final = str_replace('{limite}',  $modal_limite,  $mensagem_final);
        $mensagem_final = str_replace('{dominio}', $_SERVER['HTTP_HOST'], $mensagem_final);
        $mensagem_final = nl2br(htmlspecialchars($mensagem_final));
    }

    unset($_SESSION['modal_usuario'], $_SESSION['modal_senha'], $_SESSION['modal_limite'],
          $_SESSION['modal_validade'], $_SESSION['modal_uuid'], $_SESSION['modal_v2ray'],
          $_SESSION['modal_whatsapp'], $_SESSION['modal_valormensal'], $_SESSION['modal_mensagem'],
          $_SESSION['sucess_servers'], $_SESSION['failed_servers'], $_SESSION['show_modal']);
}

$sucess_servers = array_filter($sucess_servers);
$failed_servers = array_filter($failed_servers);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Criar Usuário</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="temas_visual.css">
<style>
:root{--primaria:#10b981;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* Stats Card */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{
    width:60px;height:60px;
    background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;
}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{
    font-size:36px;font-weight:800;
    background:linear-gradient(135deg,#fff,var(--primaria,#10b981));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;
}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Modern Card */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;margin-bottom:16px;transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header.primary{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.header-icon{
    width:36px;height:36px;background:rgba(255,255,255,0.2);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body{padding:16px;}

/* Botões */
.btn{
    padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    gap:6px;color:white;transition:all .2s;text-decoration:none;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-primary{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-warning{background:linear-gradient(135deg,var(--aviso,#f59e0b),#f97316);}
.btn-sm{padding:5px 12px;font-size:11px;border-radius:8px;}

/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-field{display:flex;flex-direction:column;gap:4px;}
.form-field.full-width{grid-column:1/-1;}
.form-field label{
    font-size:9px;font-weight:700;color:rgba(255,255,255,.4);
    text-transform:uppercase;letter-spacing:.5px;
    display:flex;align-items:center;gap:4px;
}
.form-field label i{font-size:12px;}
.form-control{
    width:100%;padding:8px 12px;
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:9px;color:#fff;font-size:12px;font-family:inherit;
    outline:none;transition:all .25s;
}
.form-control:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.09);}
.form-control::placeholder{color:rgba(255,255,255,.2);}
select.form-control option{background:var(--fundo_claro,#1e293b);}

/* Dias Select */
.dias-select{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:4px;}
.dia-option{
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:8px;padding:8px 4px;text-align:center;cursor:pointer;
    transition:all .3s;font-size:12px;font-weight:600;color:rgba(255,255,255,.7);
}
.dia-option:hover{background:rgba(255,255,255,.1);border-color:var(--primaria,#10b981);}
.dia-option.active{
    background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));
    color:white;border-color:transparent;
}

/* V2Ray */
.v2ray-toggle{
    display:flex;gap:6px;background:rgba(255,255,255,.06);
    border:1.5px solid rgba(255,255,255,.08);border-radius:8px;padding:3px;
}
.v2ray-option{
    flex:1;padding:6px;text-align:center;border-radius:6px;cursor:pointer;
    transition:all .3s;display:flex;align-items:center;justify-content:center;
    gap:4px;font-weight:600;font-size:11px;color:rgba(255,255,255,.5);
}
.v2ray-option i{font-size:14px;}
.v2ray-option.active{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));color:white;}
.v2ray-option:not(.active):hover{background:rgba(255,255,255,.1);}
.text-success-badge{
    background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);
    color:white;padding:2px 6px;border-radius:4px;font-size:8px;font-weight:700;margin-left:4px;
}

.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;flex-wrap:wrap;}

/* Modais */
.modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.85);
    display:none;align-items:center;justify-content:center;
    z-index:9999;backdrop-filter:blur(8px);padding:16px;
}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:520px;width:90%;}
.modal-container.wide{max-width:660px;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{
    background:var(--fundo_claro,#1e293b);
    border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);
}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.modal-header.error{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.modal-header.primary{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;}
.modal-body{padding:18px;max-height:65vh;overflow-y:auto;}
.modal-footer{
    border-top:1px solid rgba(255,255,255,.07);
    padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;
}

/* Info rows */
.modal-info-card{background:rgba(255,255,255,.05);border-radius:12px;padding:12px;margin-bottom:12px;}
.modal-info-row{
    display:flex;align-items:center;justify-content:space-between;
    padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);
}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:11px;font-weight:600;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:6px;}
.modal-info-label i{font-size:15px;}
.modal-info-value{font-size:12px;font-weight:700;color:white;}
.modal-info-value.credential{
    background:rgba(0,0,0,.3);padding:2px 8px;
    border-radius:6px;font-family:monospace;letter-spacing:.5px;
}

/* Servidores */
.modal-server-list{background:rgba(0,0,0,.3);border-radius:10px;padding:10px;margin-top:10px;}
.modal-server-badge{
    display:inline-block;background:rgba(16,185,129,.2);
    border:1px solid rgba(16,185,129,.3);color:#10b981;
    padding:3px 8px;border-radius:16px;font-size:10px;margin:3px;
}
.modal-server-badge.fail{
    background:rgba(220,38,38,.2);border-color:rgba(220,38,38,.3);color:#dc2626;
}

/* Mensagem */
.mensagem-box{
    background:rgba(65,88,208,.1);border-left:3px solid var(--primaria,#10b981);
    border-radius:8px;padding:10px;margin-top:10px;font-size:11px;line-height:1.6;
    color:rgba(255,255,255,.85);
}

/* Toast */
.toast-notification{
    position:fixed;bottom:20px;right:20px;color:#fff;
    padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;
    z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;
}
.toast-notification.ok{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.toast-notification.err{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .form-grid{grid-template-columns:1fr;}
    .dias-select{grid-template-columns:repeat(3,1fr);}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
    .stats-card{padding:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
}
</style>
</head>
<body>
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-user-plus'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Criar Usuário</div>
        <div class="stats-card-value">Novo</div>
        <div class="stats-card-subtitle"><?php echo $tipo_txt; ?><?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?> · Validade: <?php echo date('d/m/Y', strtotime($validade)); ?><?php endif; ?></div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-user-plus'></i></div>
</div>

<!-- Card Formulário -->
<div class="modern-card">
    <div class="card-header primary">
        <div class="header-icon"><i class='bx bx-user-plus'></i></div>
        <div>
            <div class="header-title">Criar Usuário</div>
            <div class="header-subtitle">Preencha os dados do novo usuário</div>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$show_limite_modal): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalGerar()" style="margin-bottom:16px;">
            <i class='bx bx-shuffle'></i> Gerar Aleatório
        </button>
        <?php endif; ?>

        <form method="POST" action="criarusuario.php">
            <div class="form-grid">

                <!-- Login -->
                <div class="form-field">
                    <label><i class='bx bx-user'></i> Login (5–10 caracteres)</label>
                    <input type="text" class="form-control" name="usuariofin" id="usuariofin"
                           placeholder="ex: usuario123" minlength="5" maxlength="10" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                </div>

                <!-- Senha -->
                <div class="form-field">
                    <label><i class='bx bx-lock-alt'></i> Senha (5–10 caracteres)</label>
                    <input type="text" class="form-control" name="senhafin" id="senhafin"
                           placeholder="ex: senha123" minlength="5" maxlength="10" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                </div>

                <!-- Limite -->
                <div class="form-field">
                    <label><i class='bx bx-layer'></i> Limite</label>
                    <input type="number" class="form-control" name="limitefin" id="limitefin" value="1" min="1" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                </div>

                <!-- Dias -->
                <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                <div class="form-field">
                    <label><i class='bx bx-calendar'></i> Dias (máx. 90)</label>
                    <input type="hidden" name="validadefin" id="validadefin" value="30">
                    <div class="dias-select">
                        <div class="dia-option" data-dias="1">1 dia</div>
                        <div class="dia-option" data-dias="7">7 dias</div>
                        <div class="dia-option active" data-dias="30">30 dias</div>
                        <div class="dia-option" data-dias="60">60 dias</div>
                        <div class="dia-option" data-dias="90">90 dias</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- V2Ray -->
                <div class="form-field">
                    <label><i class='bx bx-shield-quarter'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                    <div class="v2ray-toggle">
                        <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                            <i class='bx bx-x-circle'></i> Não
                        </div>
                        <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                            <i class='bx bx-check-circle'></i> Sim
                        </div>
                    </div>
                    <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                </div>

                <!-- Notas -->
                <div class="form-field">
                    <label><i class='bx bx-note'></i> Notas</label>
                    <input type="text" class="form-control" name="notas" placeholder="Observações" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                </div>

                <!-- Valor Mensal -->
                <div class="form-field">
                    <label><i class='bx bx-dollar'></i> Valor Mensal (R$)</label>
                    <input type="number" class="form-control" step="0.01" min="0"
                           name="valormensal" id="valormensal" value="0" placeholder="0,00" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                </div>

                <!-- WhatsApp -->
                <div class="form-field full-width">
                    <label><i class='bx bxl-whatsapp'></i> WhatsApp do Cliente</label>
                    <input type="text" class="form-control" name="whatsapp" id="whatsapp_input"
                           placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                    <small style="color:rgba(255,255,255,.3);font-size:9px;margin-top:3px;">
                        <i class='bx bx-info-circle'></i> Com DDI. Ex: 5511999999999 — mensagem enviada automaticamente
                    </small>
                </div>

            </div>
            <?php if (!$show_limite_modal): ?>
            <div class="action-buttons">
                <button type="reset" class="btn btn-danger"><i class='bx bx-x'></i> Cancelar</button>
                <button type="submit" name="criaruser" class="btn btn-success">
                    <i class='bx bx-check'></i> Criar Usuário
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

</div></div></div>

<!-- ══════════════════════════════════════════
     MODAL: GERAR ALEATÓRIO
══════════════════════════════════════════ -->
<div id="modalGerar" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header primary">
        <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
        <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-user'></i> Login gerado</div>
                <div class="modal-info-value credential" id="gerar-login-preview">—</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha gerada</div>
                <div class="modal-info-value credential" id="gerar-senha-preview">—</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-group'></i> Limite</div>
                <div class="modal-info-value">1 conexão</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar'></i> Dias</div>
                <div class="modal-info-value">30 dias</div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-success" onclick="fecharModal('modalGerar')">
            <i class='bx bx-check'></i> OK, usar
        </button>
        <button class="btn btn-primary" onclick="gerarNovamente()">
            <i class='bx bx-refresh'></i> Gerar outros
        </button>
    </div>
</div></div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: SUCESSO
══════════════════════════════════════════ -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
<div class="modal-container wide"><div class="modal-content">
    <div class="modal-header success">
        <h5><i class='bx bx-check-circle'></i> Usuário Criado com Sucesso!</h5>
        <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-user'></i> Usuário</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar-check'></i> Validade</div>
                <div class="modal-info-value"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-group'></i> Limite</div>
                <div class="modal-info-value"><?php echo $show_modal ? $modal_limite.' conexões' : ''; ?></div>
            </div>
            <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-shield-quarter'></i> UUID V2Ray</div>
                <div class="modal-info-value" style="font-size:10px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
            </div>
            <?php endif; ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-dollar'></i> Valor</div>
                <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
            </div>
        </div>

        <?php if (!empty($sucess_servers)): ?>
        <div class="modal-server-list">
            <div style="font-size:11px;margin-bottom:6px;color:rgba(255,255,255,.6);">
                <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado nos servidores:
            </div>
            <?php foreach($sucess_servers as $s): if(!empty($s)): ?>
            <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($failed_servers)): ?>
        <div class="modal-server-list" style="margin-top:8px;">
            <div style="font-size:11px;margin-bottom:6px;color:rgba(220,38,38,.8);">
                <i class='bx bx-error-circle'></i> Falha nos servidores:
            </div>
            <?php foreach($failed_servers as $s): if(!empty($s)): ?>
            <span class="modal-server-badge fail"><?php echo htmlspecialchars($s); ?></span>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_final)): ?>
        <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
        <a href="listarusuarios.php" class="btn btn-danger"><i class='bx bx-list-ul'></i> Lista</a>
        <button class="btn btn-warning" onclick="shareWhatsApp()"><i class='bx bxl-whatsapp'></i> Compartilhar</button>
        <button class="btn btn-primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
    </div>
</div></div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: ERRO
══════════════════════════════════════════ -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header error">
        <h5><i class='bx bx-error-circle'></i> Erro!</h5>
        <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px;">
            <i class='bx bx-error-circle' style="font-size:54px;color:var(--erro,#dc2626);"></i>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,.85);font-size:13px;">
            <?php echo htmlspecialchars($error_message); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-danger" onclick="fecharModalErro()">
            <i class='bx bx-check'></i> OK
        </button>
    </div>
</div></div>
</div>

<script>
// ── Dados do modal (PHP → JS) ──────────────────────────────
var MODAL_USUARIO  = <?php echo json_encode($show_modal ? $modal_usuario  : ''); ?>;
var MODAL_SENHA    = <?php echo json_encode($show_modal ? $modal_senha    : ''); ?>;
var MODAL_VALIDADE = <?php echo json_encode($show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''); ?>;
var MODAL_LIMITE   = <?php echo json_encode($show_modal ? $modal_limite   : ''); ?>;
var MODAL_UUID     = <?php echo json_encode($show_modal ? ($modal_uuid ?? '') : ''); ?>;
var MODAL_VALOR    = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>';
var MODAL_DOMINIO  = <?php echo json_encode($_SERVER['HTTP_HOST']); ?>;

// ── Modais ─────────────────────────────────────────────────
function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }

function fecharModalErro(){
    document.getElementById('modalErro').classList.remove('show');
    <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
    setTimeout(function(){ window.location.href = '../home.php'; }, 300);
    <?php endif; ?>
}

document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click', function(e){
        if(e.target !== o) return;
        if(o.id === 'modalErro') fecharModalErro();
        else o.classList.remove('show');
    });
});
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
        if(document.getElementById('modalErro').classList.contains('show')) fecharModalErro();
        else document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
    }
});

// ── V2Ray ──────────────────────────────────────────────────
function selectV2ray(v){
    document.getElementById('v2rayInput').value = v;
    document.getElementById('v2raySim').classList.toggle('active', v === 'sim');
    document.getElementById('v2rayNao').classList.toggle('active', v === 'nao');
}

// ── Seletor de dias ────────────────────────────────────────
<?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
document.querySelectorAll('.dia-option').forEach(function(opt){
    opt.addEventListener('click', function(){
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('validadefin').value = this.dataset.dias;
    });
});
<?php endif; ?>

// ── Gerar aleatório ────────────────────────────────────────
function gerarDados(){
    var nums = '0123456789', sufixo = '';
    for(var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
    var usuario = 'User' + sufixo;
    var tam = Math.floor(Math.random() * 4) + 5, senha = '';
    for(var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];
    document.getElementById('usuariofin').value  = usuario;
    document.getElementById('senhafin').value    = senha;
    document.getElementById('limitefin').value   = 1;
    document.getElementById('valormensal').value = '0';
    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
    document.querySelectorAll('.dia-option')[2].classList.add('active');
    document.getElementById('validadefin').value = '30';
    <?php endif; ?>
    return { usuario: usuario, senha: senha };
}
function abrirModalGerar(){
    var d = gerarDados();
    document.getElementById('gerar-login-preview').textContent = d.usuario;
    document.getElementById('gerar-senha-preview').textContent = d.senha;
    abrirModal('modalGerar');
}
function gerarNovamente(){
    var d = gerarDados();
    document.getElementById('gerar-login-preview').textContent = d.usuario;
    document.getElementById('gerar-senha-preview').textContent = d.senha;
    mostrarToast('Novos dados gerados!', 'ok');
}

// ── Copiar dados ───────────────────────────────────────────
function copiarDados(){
    var t = '✅ USUÁRIO CRIADO COM SUCESSO!\n'
          + '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n'
          + '👤 Login: '    + MODAL_USUARIO  + '\n'
          + '🔑 Senha: '    + MODAL_SENHA    + '\n'
          + '📅 Validade: ' + MODAL_VALIDADE + '\n'
          + '🔗 Limite: '   + MODAL_LIMITE   + ' conexões\n'
          + '💰 Valor: '    + MODAL_VALOR    + '\n';
    if(MODAL_UUID) t += '🛡 UUID: ' + MODAL_UUID + '\n';
    t += '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n'
       + '🌐 https://' + MODAL_DOMINIO + '/\n'
       + '📆 Data: ' + new Date().toLocaleString('pt-BR') + '\n';
    navigator.clipboard.writeText(t)
        .then(function(){ mostrarToast('Copiado!', 'ok'); })
        .catch(function(){ mostrarToast('Erro ao copiar!', 'err'); });
}

// ── Compartilhar WhatsApp (link) ───────────────────────────
function shareWhatsApp(){
    var txt = '✅ *Usuário Criado!*\n\n'
            + '👤 Login: '    + MODAL_USUARIO  + '\n'
            + '🔑 Senha: '    + MODAL_SENHA    + '\n'
            + '📅 Validade: ' + MODAL_VALIDADE + '\n'
            + '🔗 Limite: '   + MODAL_LIMITE   + ' conexões\n'
            + '💰 Valor: '    + MODAL_VALOR    + '\n';
    if(MODAL_UUID) txt += '🛡 UUID: ' + MODAL_UUID + '\n';
    txt += '\n🌐 https://' + MODAL_DOMINIO + '/';
    window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(txt), '_blank');
}

// ── Toast ──────────────────────────────────────────────────
function mostrarToast(msg, tipo){
    var t = document.createElement('div');
    t.className = 'toast-notification ' + (tipo || 'ok');
    t.innerHTML = '<i class="bx ' + (tipo === 'err' ? 'bx-error-circle' : 'bx-check-circle') + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3500);
}
</script>
</body>
</html>
