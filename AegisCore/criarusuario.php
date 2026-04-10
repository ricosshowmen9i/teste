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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
        }
        ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
        }
        ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
        }
        ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
        }
        ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important; position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important; animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important; max-width: 100% !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header {
            padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important; align-items: center !important; gap: 10px !important;
            position: relative; z-index: 1;
        }
        .header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
        }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action {
            padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
            transition: all 0.2s !important; display: inline-flex !important;
            align-items: center !important; justify-content: center !important;
            gap: 6px !important; font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
        }
        .btn-primary-action { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
        .btn-primary-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
        .btn-success-action { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger-action  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger-action:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label {
            font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .form-field label i { font-size: 12px; }
        .form-control {
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
            outline: none; transition: all 0.25s;
        }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
        }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .v2ray-toggle {
            display: flex; gap: 6px; background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 3px;
        }
        .v2ray-option {
            flex: 1; padding: 6px; text-align: center; border-radius: 6px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center;
            gap: 4px; font-weight: 600; font-size: 11px; color: rgba(255,255,255,0.5);
        }
        .v2ray-option i { font-size: 14px; }
        .v2ray-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-success-badge {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }
        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; }
        .icon-lock     { color: #e879f9; }
        .icon-group    { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield   { color: #60a5fa; }
        .icon-note     { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time     { color: #fbbf24; }
        .icon-money    { color: #10b981; }

        /* =============================================
           MODAIS â€” estilo unificado
           ============================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-container {
            animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
            max-width: 500px; width: 90%;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.9) translateY(-30px); }
            to   { opacity:1; transform: scale(1)   translateY(0); }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; filter: drop-shadow(0 0 15px currentColor); }
        .modal-big-icon.success i { color:#10b981; }
        .modal-big-icon.error   i { color:#dc2626; }
        .modal-big-icon.warning i { color:#f59e0b; filter: drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#818cf8; }

        .modal-info-card {
            background: rgba(255,255,255,0.05); border-radius:16px;
            padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
        }
        .modal-info-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .modal-info-row:last-child { border-bottom:none; }
        .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
        .modal-info-label i { font-size:18px; }
        .modal-info-value { font-size:13px; font-weight:700; color:white; }
        .modal-info-value.credential { background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:8px; font-family:monospace; letter-spacing:.5px; }
        .modal-info-value.green { color:#10b981; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            border:1px solid rgba(16,185,129,0.3); color:#10b981;
            padding:4px 10px; border-radius:20px; font-size:11px; margin:4px;
        }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(65,88,208,0.1); border-left:3px solid #4158D0;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

        /* BotÃµes modal */
        .btn-modal {
            padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
            cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
            gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
            color:white; text-decoration:none; justify-content:center;
        }
        .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
        .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
        .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
        .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
        .btn-modal.warning   { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .btn-modal.warning:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(245,158,11,.5); color:white; }
        .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
        .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
        .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
        .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

        /* Toast */
        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#10b981,#059669); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        /* Spinner */
        .modal-spinner { display:flex; flex-direction:column; align-items:center; gap:16px; padding:10px 0 20px; }
        .spinner-ring {
            width:64px; height:64px;
            border:4px solid rgba(255,255,255,0.1); border-top-color:#10b981;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .spinner-text { color:rgba(255,255,255,0.7); font-size:14px; font-weight:500; }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button, .action-buttons a { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .dias-select { grid-template-columns:repeat(3,1fr); }
            .modal-container { width:95%; }
            .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
            .modal-footer { flex-direction:column; }
            .btn-modal { width:100%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-user-plus'></i>
            <span>Criar UsuÃ¡rio para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time icon-time'></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-user-plus'></i></div>
                <div>
                    <div class="header-title">Criar UsuÃ¡rio</div>
                    <div class="header-subtitle">Preencha os dados do usuÃ¡rio</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-primary-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarusuario.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: usuario123" minlength="5" maxlength="10" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" minlength="5" maxlength="10" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias (mÃ¡ximo 90 dias)</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="30">
                            <div class="dias-select" id="diasSelector">
                                <div class="dia-option" data-dias="1">1 dia</div>
                                <div class="dia-option" data-dias="7">7 dias</div>
                                <div class="dia-option active" data-dias="30">30 dias</div>
                                <div class="dia-option" data-dias="60">60 dias</div>
                                <div class="dia-option" data-dias="90">90 dias</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                            <div class="v2ray-toggle">
                                <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                                    <i class='bx bx-x-circle'></i> NÃ£o
                                </div>
                                <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                                    <i class='bx bx-check-circle'></i> Sim
                                </div>
                            </div>
                            <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-note icon-note'></i> Notas</label>
                            <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor do UsuÃ¡rio (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor para renovaÃ§Ã£o automÃ¡tica (0 = desativado)
                            </small>
                        </div>
                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Cliente</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp
                            </small>
                        </div>
                    </div>
                    <?php if (!$show_limite_modal): ?>
                    <div class="action-buttons">
                        <button type="reset" class="btn-action btn-danger-action">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser">
                            <i class='bx bx-check'></i> Criar UsuÃ¡rio
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: GERAR ALEATÃ“RIO
     ============================================= -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">â€”</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conexÃ£o</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: SUCESSO AO CRIAR
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> UsuÃ¡rio Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> UsuÃ¡rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <?php if ($show_modal && isset($modal_v2ray) && $modal_v2ray == "sim" && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value">R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($sucess_servers)): ?>
                <div class="modal-server-list">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(255,255,255,0.7);">
                        <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado com sucesso em:
                    </div>
                    <?php foreach ($sucess_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($failed_servers)): ?>
                <div class="modal-server-list" style="margin-top:8px;border:1px solid rgba(220,38,38,0.2);">
                    <div style="font-size:12px;margin-bottom:8px;color:rgba(220,38,38,0.8);">
                        <i class='bx bx-error-circle'></i> Falha em:
                    </div>
                    <?php foreach ($failed_servers as $s): if(!empty($s)): ?>
                    <span class="modal-server-badge fail"><i class='bx bx-x-circle' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">âœ¨ UsuÃ¡rio criado com sucesso! âœ¨</p>
            </div>
            <div class="modal-footer">
                <a href="listarusuarios.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL: ERRO
     ============================================= -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal danger" onclick="fecharModalErro()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function selectV2ray(value) {
        document.getElementById('v2rayInput').value = value;
        if (value === 'sim') {
            document.getElementById('v2raySim').classList.add('active');
            document.getElementById('v2rayNao').classList.remove('active');
        } else {
            document.getElementById('v2rayNao').classList.add('active');
            document.getElementById('v2raySim').classList.remove('active');
        }
    }

    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums = "0123456789";
        var sufixo = "";
        for (var i = 0; i < 4; i++) sufixo += nums[Math.floor(Math.random() * 10)];
        var usuario = "User" + sufixo;

        var tam = Math.floor(Math.random() * 4) + 5;
        var senha = "";
        for (var i = 0; i < tam; i++) senha += nums[Math.floor(Math.random() * 10)];

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;
        document.getElementById('valormensal').value = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$show_limite_modal): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validadefin').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // âœ… FIX: NÃ£o redireciona mais â€” apenas fecha o modal e mantÃ©m o formulÃ¡rio visÃ­vel
        // para que o usuÃ¡rio possa corrigir os dados sem perder o que digitou.
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? date("d/m/Y", strtotime($modal_validade)) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format($modal_valormensal ?? 0, 2, ",", "."); ?>';

        var texto = "âœ… USUÃRIO CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "ðŸ“… Validade: " + v + "\n";
        texto += "ðŸ”— Limite: " + l + " conexÃµes\n";
        texto += "ðŸ’° Valor: " + val + "\n";
        texto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
        texto += "ðŸ“† Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('InformaÃ§Ãµes copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('NÃ£o foi possÃ­vel copiar!', true);
        });
    }

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var text = "ðŸŽ‰ UsuÃ¡rio Criado! ðŸŽ‰\n"
            + "ðŸ”Ž Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "ðŸŽ¯ Validade: <?php echo $show_modal ? date('d/m/Y', strtotime($modal_validade)) : ''; ?>\n"
            + "ðŸ•Ÿ Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "ðŸ’° Valor: R$ <?php echo number_format($modal_valormensal ?? 0, 2, ',', '.'); ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') {
            fecharModalErro();
        } else {
            e.target.classList.remove('show');
        }
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro();
        } else {
            document.querySelectorAll('.modal-overlay.show').forEach(function(m){
                m.classList.remove('show');
            });
        }
    });
</script>
</body>
</html>



