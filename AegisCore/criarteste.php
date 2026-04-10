<?php
error_reporting(0);
session_start();

date_default_timezone_set('America/Sao_Paulo');

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');
include('conexao.php');
include('../vendor/event/autoload.php');
include('functions.whatsapp.php'); // âœ… necessÃ¡rio para dispararMensagemAutomatica
use React\EventLoop\Factory;

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (isset($_SESSION['mensagem_enviada'])) {
    unset($_SESSION['mensagem_enviada']);
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
$validade    = $row['expira'];
$categoria   = $row['categoriaid'];
$tipo        = $row['tipo'];
$_SESSION['tipodeconta'] = $row['tipo'];
$_SESSION['limite']      = $row['limite'];

if ($tipo == 'Credito') {
    $tipo_txt = 'Restam ' . $_SESSION['limite'] . ' CrÃ©ditos';
} else {
    $tipo_txt = 'Limite usado: ' . $limiteusado . ' de ' . $_SESSION['limite'];
}

$hoje = date('Y-m-d H:i:s');

// âœ… VARIÃVEIS DE CONTROLE DE MODAL (igual ao criarusuario.php)
$show_error_modal = false;
$error_message    = '';
$error_type       = '';
$show_limite_modal = false;

// Limite fixo de 720 minutos (12h)
$testelimite = 720;

// âœ… Verificar se Ã© redirecionamento de limite/vencimento
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

// âœ… Verificar limite e validade SOMENTE se NÃƒO for redirecionamento de erro
if (!$show_limite_modal) {
    if ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validade < $hoje) {
            $_SESSION['modal_limite_erro'] = 'vencido';
            $_SESSION['modal_limite_mensagem'] = 'Sua conta expirou! Entre em contato com o suporte para renovar.';
            header('Location: criarteste.php?limite_erro=1');
            exit();
        }
        if ($restante < 1) {
            $_SESSION['modal_limite_erro'] = 'limite';
            $_SESSION['modal_limite_mensagem'] = 'VocÃª atingiu o limite mÃ¡ximo de usuÃ¡rios! Limite disponÃ­vel: 0 de ' . $_SESSION['limite'];
            header('Location: criarteste.php?limite_erro=1');
            exit();
        }
    }
}

$sql5b = "SELECT * FROM accounts WHERE id = '1'";
$sql5b = $conn->query($sql5b);
$rowb  = $sql5b->fetch_assoc();

$sql2   = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result = $conn->query($sql2);
$servidores = [];
while ($row = $result->fetch_assoc()) {
    $servidores[] = $row;
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
    return md5($_SESSION['token']);
}

include('header2.php');

// âœ… Mensagem modal customizada (igual ao criarusuario.php)
$mensagem_modal = '';
$sql_mensagem_modal = "SELECT mensagem FROM mensagens_modal WHERE funcao = 'criarteste' AND byid = '$_SESSION[iduser]' AND ativo = 'ativada'";
$result_mensagem_modal = mysqli_query($conn, $sql_mensagem_modal);
if ($result_mensagem_modal && mysqli_num_rows($result_mensagem_modal) > 0) {
    $row_mensagem_modal = mysqli_fetch_assoc($result_mensagem_modal);
    $mensagem_modal = $row_mensagem_modal['mensagem'];
}

if (isset($_POST['criaruser'])) {
    ignore_user_abort(true);
    set_time_limit(0);

    $usuariofin  = anti_sql($_POST['usuariofin']);
    $senhafin    = anti_sql($_POST['senhafin']);
    $validadefin = anti_sql($_POST['validadefin']);
    $notas       = anti_sql($_POST['notas'] ?? '');
    $limitefin   = anti_sql($_POST['limitefin']);
    $whatsapp    = anti_sql($_POST['whatsapp'] ?? '');
    $valormensal = anti_sql($_POST['valormensal'] ?? '0');

    if ($validadefin > $testelimite) {
        $error_message    = "VocÃª nÃ£o pode criar um teste com mais de $testelimite Minutos!";
        $show_error_modal = true;
    } elseif ($usuariofin == "") {
        $error_message    = 'UsuÃ¡rio nÃ£o pode ser vazio!';
        $show_error_modal = true;
    } elseif ($senhafin == "") {
        $error_message    = 'Senha nÃ£o pode ser vazia!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuariofin)) {
        $error_message    = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhafin)) {
        $error_message    = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_POST['limitefin'] > $_SESSION['limite']) {
        $error_message    = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['limite'];
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] == 'Credito' && $limitefin > $_SESSION['limite']) {
        $error_message    = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['limite'];
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito' && $_POST['limitefin'] > $_SESSION['restante']) {
        $error_message    = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['restante'];
        $show_error_modal = true;
    }

    if (!$show_error_modal) {
        $sql    = "SELECT * FROM ssh_accounts WHERE login = '$usuariofin'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $error_message    = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $_SESSION['usuariofin']  = $usuariofin;
        $_SESSION['senhafin']    = $senhafin;
        $_SESSION['validadefin'] = $validadefin;
        $_SESSION['limitefin']   = $limitefin;

        $sql4    = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result4 = $conn->query($sql4);
        $rows    = mysqli_fetch_all($result4, MYSQLI_ASSOC);

        $loop                = Factory::create();
        $servidores_com_erro = [];
        $sucess_servers      = [];
        $failed_servers      = [];
        $sucess              = false;

        $_POST['v2ray'] = anti_sql($_POST['v2ray'] ?? 'nao');
        if ($_POST['v2ray'] == "sim") {
            $v2ray         = "sim";
            $formattedUuid = generateUUID();
            $_SESSION['uuid'] = $formattedUuid;
        } else {
            $v2ray            = "nao";
            $formattedUuid    = "";
            $_SESSION['uuid'] = "";
        }

        foreach ($rows as $user_data) {
            $conectado = false;
            $timeout   = 3;
            $socket    = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);

                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);

                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuariofin, $senhafin, $validadefin, $limitefin, $senha_token, $v2ray, $formattedUuid, $notas) {
                    if ($v2ray == "sim") {
                        $comando = "sudo /etc/xis/addteste.sh $formattedUuid $usuariofin $senhafin $validadefin $limitefin ";
                    } else {
                        $comando = "sudo /etc/xis/atlasteste.sh $usuariofin $senhafin $validadefin $limitefin ";
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
                    curl_close($ch);
                });

                $conectado        = true;
                $sucess_servers[] = $user_data['nome'];
                $sucess           = true;
            }

            if (!$conectado) {
                $servidores_com_erro[] = $user_data['ip'];
                $failed_servers[]      = $user_data['nome'];
            }
            $loop->run();
        }

        if (!$sucess) {
            $error_message    = 'Erro ao criar usuÃ¡rio em todos os servidores!';
            $show_error_modal = true;
        } else {
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);

            $datahoje = date('d-m-Y H:i:s');
            $sql10    = "INSERT INTO logs (revenda, byid, validade, texto, userid) VALUES ('$_SESSION[login]', '$_SESSION[byid]', '$datahoje', 'Criou um Teste $usuariofin de $validadefin Minutos ', '$_SESSION[iduser]')";
            mysqli_query($conn, $sql10);

            $_SESSION['whatsapp'] = $whatsapp;

            $data           = date('Y-m-d H:i:s');
            $data           = strtotime($data);
            $data           = strtotime("+" . $validadefin . " minutes", $data);
            $data           = date('Y-m-d H:i:s', $data);
            $validadefin_db = $data;

            $sql9 = "INSERT INTO ssh_accounts (login, senha, expira, limite, byid, categoriaid, status, bycredit, mainid, lastview, uuid, whatsapp, valormensal) VALUES ('$usuariofin', '$senhafin', '$validadefin_db', '$limitefin', '$_SESSION[iduser]', '$categoria', 'Offline', '0', '0', '$notas', '$formattedUuid', '$whatsapp', '$valormensal')";
            mysqli_query($conn, $sql9);

            // âœ… DISPARAR WHATSAPP AUTOMÃTICO (igual ao criarusuario.php)
            if (!empty($whatsapp)) {
                $dados_msg = [
                    'usuario'  => $usuariofin,
                    'senha'    => $senhafin,
                    'validade' => date('d/m/Y H:i', strtotime($validadefin_db)),
                    'limite'   => $limitefin,
                    'whatsapp' => $whatsapp,
                    'minutos'  => $_POST['validadefin'],
                ];
                dispararMensagemAutomatica($conn, $_SESSION['iduser'], 'criarteste', $dados_msg);
            }

            // Salvar na sessÃ£o para o modal
            $_SESSION['modal_usuario']     = $usuariofin;
            $_SESSION['modal_senha']       = $senhafin;
            $_SESSION['modal_limite']      = $limitefin;
            $_SESSION['modal_minutos']     = $_POST['validadefin'];
            $_SESSION['modal_expira']      = $validadefin_db;
            $_SESSION['modal_uuid']        = $_SESSION['uuid'];
            $_SESSION['modal_v2ray']       = $v2ray;
            $_SESSION['modal_notas']       = $notas;
            $_SESSION['modal_valormensal'] = $valormensal;
            $_SESSION['modal_mensagem']    = $mensagem_modal;
            $_SESSION['sucess_servers']    = $sucess_servers;
            $_SESSION['failed_servers']    = $failed_servers;
            $_SESSION['show_modal']        = true;

            echo "<script>window.location.href = 'criarteste.php?modal=1';</script>";
            exit();
        }
        // âœ… FIX: $loop->run() removido daqui â€” igual Ã  correÃ§Ã£o do criarusuario.php
    }
}

// Modal de sucesso
$show_modal     = false;
$sucess_servers = [];
$failed_servers = [];
$mensagem_final = '';

if (isset($_GET['modal']) && $_GET['modal'] == 1 && isset($_SESSION['show_modal']) && $_SESSION['show_modal'] === true) {
    $show_modal          = true;
    $modal_usuario       = $_SESSION['modal_usuario'];
    $modal_senha         = $_SESSION['modal_senha'];
    $modal_limite        = $_SESSION['modal_limite'];
    $modal_minutos       = $_SESSION['modal_minutos'];
    $modal_expira        = $_SESSION['modal_expira'];
    $modal_uuid          = $_SESSION['modal_uuid'];
    $modal_v2ray         = $_SESSION['modal_v2ray'];
    $modal_notas         = $_SESSION['modal_notas'];
    $modal_valormensal   = $_SESSION['modal_valormensal'] ?? '0';
    $modal_mensagem      = $_SESSION['modal_mensagem'] ?? '';
    $sucess_servers      = $_SESSION['sucess_servers'] ?? [];
    $failed_servers      = $_SESSION['failed_servers'] ?? [];

    // âœ… Processar mensagem personalizada (igual ao criarusuario.php)
    $mensagem_final = $modal_mensagem;
    if (!empty($mensagem_final)) {
        $min = (int)$modal_minutos;
        $horas_fmt = $min >= 60 ? floor($min/60) . 'h' . ($min%60 > 0 ? ' ' . ($min%60) . 'min' : '') : $min . 'min';
        $mensagem_final = str_replace('{usuario}',  $modal_usuario, $mensagem_final);
        $mensagem_final = str_replace('{login}',    $modal_usuario, $mensagem_final);
        $mensagem_final = str_replace('{senha}',    $modal_senha,   $mensagem_final);
        $mensagem_final = str_replace('{validade}', date('d/m/Y H:i', strtotime($modal_expira)), $mensagem_final);
        $mensagem_final = str_replace('{duracao}',  $horas_fmt,     $mensagem_final);
        $mensagem_final = str_replace('{minutos}',  $modal_minutos, $mensagem_final);
        $mensagem_final = str_replace('{limite}',   $modal_limite,  $mensagem_final);
        $mensagem_final = str_replace('{dominio}',  $_SERVER['HTTP_HOST'], $mensagem_final);
        $mensagem_final = nl2br(htmlspecialchars($mensagem_final));
    }

    unset($_SESSION['modal_usuario'], $_SESSION['modal_senha'], $_SESSION['modal_limite'],
          $_SESSION['modal_minutos'], $_SESSION['modal_expira'], $_SESSION['modal_uuid'],
          $_SESSION['modal_v2ray'], $_SESSION['modal_notas'], $_SESSION['modal_valormensal'],
          $_SESSION['modal_mensagem'], $_SESSION['sucess_servers'], $_SESSION['failed_servers'],
          $_SESSION['show_modal']);
}

$sucess_servers = array_filter($sucess_servers);
$failed_servers = array_filter($failed_servers);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Teste</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Teste</title>
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
            border-left: 4px solid var(--warning) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--warning); }

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
            background: linear-gradient(135deg, #f59e0b, #f97316);
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
        .btn-warning-action { background: linear-gradient(135deg, #f59e0b, #f97316) !important; color: white !important; }
        .btn-warning-action:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(245,158,11,0.5) !important; }
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
        .form-control:focus { border-color: rgba(245,158,11,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }
        .form-control option { background: #1e293b; color: white; }

        .horas-select { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 4px; }
        .hora-option {
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 8px; padding: 6px 2px; text-align: center; cursor: pointer;
            transition: all 0.3s; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.7);
            display: flex; flex-direction: column; align-items: center; gap: 1px;
        }
        .hora-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(245,158,11,0.6); }
        .hora-option.active { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; border-color: transparent; }
        .hora-option .hora-label { font-size: 12px; font-weight: 800; }
        .hora-option .hora-sub   { font-size: 8px; opacity: 0.8; }

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
        .v2ray-option.active { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; }
        .v2ray-option:not(.active):hover { background: rgba(255,255,255,0.1); }

        .text-beta {
            background: linear-gradient(135deg, #10b981, #059669); color: white;
            padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 700; margin-left: 4px;
        }

        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user     { color: #818cf8; } .icon-lock   { color: #e879f9; }
        .icon-group    { color: #34d399; } .icon-clock  { color: #f59e0b; }
        .icon-shield   { color: #60a5fa; } .icon-note   { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }

        /* =============================================
           MODAIS
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
        .modal-header.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header.info    { background: linear-gradient(135deg, #f59e0b, #f97316); }

        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }

        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
        }

        .modal-big-icon { text-align:center; margin-bottom:20px; }
        .modal-big-icon i { font-size:70px; }
        .modal-big-icon.success i { color:#10b981; filter:drop-shadow(0 0 15px rgba(16,185,129,.5)); }
        .modal-big-icon.error   i { color:#dc2626; filter:drop-shadow(0 0 12px rgba(220,38,38,.5)); }
        .modal-big-icon.warning i { color:#f59e0b; filter:drop-shadow(0 0 12px rgba(245,158,11,.4)); }
        .modal-big-icon.info    i { color:#f59e0b; filter:drop-shadow(0 0 12px rgba(245,158,11,.4)); }

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
        .modal-info-value.green  { color:#10b981; }
        .modal-info-value.orange { color:#f59e0b; }

        .modal-server-list { background:rgba(0,0,0,0.3); border-radius:12px; padding:12px; margin-top:12px; }
        .modal-server-badge { display:inline-block; background:rgba(16,185,129,0.2); border:1px solid rgba(16,185,129,0.3); color:#10b981; padding:4px 10px; border-radius:20px; font-size:11px; margin:4px; }
        .modal-server-badge.fail { background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.3); color:#dc2626; }

        .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
        .modal-success-title { text-align:center; color:#f59e0b; font-weight:700; font-size:14px; margin-top:12px; }

        .mensagem-box {
            background:rgba(245,158,11,0.1); border-left:3px solid #f59e0b;
            border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
        }
        .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

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

        .toast-notification {
            position:fixed; bottom:24px; right:24px;
            background:linear-gradient(135deg,#f59e0b,#f97316); color:white;
            padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
            z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
            font-weight:600; font-size:13px;
        }
        @keyframes slideIn {
            from { transform:translateX(110%); opacity:0; }
            to   { transform:translateX(0);    opacity:1; }
        }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { margin:0 auto !important; padding:5px !important; }
            .form-grid { grid-template-columns:1fr; }
            .horas-select { grid-template-columns:repeat(4,1fr); }
            .action-buttons { flex-direction:column !important; gap:8px !important; }
            .action-buttons button { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
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
            <i class='bx bx-timer'></i>
            <span>Criar Teste para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time' style="color:var(--tertiary);"></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(245,158,11,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(249,115,22,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(245,158,11,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(249,115,22,0.04)"/>
                </svg>
            </div>

            <div class="card-header">
                <div class="header-icon"><i class='bx bx-timer'></i></div>
                <div>
                    <div class="header-title">Criar Teste</div>
                    <div class="header-subtitle">Preencha os dados do teste</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#f59e0b;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-warning-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarteste.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: teste123" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-time icon-clock'></i> DuraÃ§Ã£o do Teste</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="180">
                            <div class="horas-select" id="horasSelector">
                                <div class="hora-option" data-minutos="60">
                                    <span class="hora-label">1h</span>
                                    <span class="hora-sub">60 min</span>
                                </div>
                                <div class="hora-option active" data-minutos="180">
                                    <span class="hora-label">3h</span>
                                    <span class="hora-sub">180 min</span>
                                </div>
                                <div class="hora-option" data-minutos="360">
                                    <span class="hora-label">6h</span>
                                    <span class="hora-sub">360 min</span>
                                </div>
                                <div class="hora-option" data-minutos="720">
                                    <span class="hora-label">12h</span>
                                    <span class="hora-sub">720 min</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-beta">BETA</span></label>
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
                            <label><i class='bx bx-dollar' style="color:#10b981;"></i> Valor do Teste (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor cobrado pelo teste (0 = gratuito)
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
                        <button type="reset" class="btn-action btn-danger-action"><i class='bx bx-x'></i> Cancelar</button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser"><i class='bx bx-check'></i> Criar Teste</button>
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
                        <div class="modal-info-label"><i class='bx bx-time' style="color:#f59e0b;"></i> DuraÃ§Ã£o</div>
                        <div class="modal-info-value orange">3 horas (180 min)</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal warning" onclick="fecharModal('modalGerar')">
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
     MODAL: SUCESSO
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> Teste Criado com Sucesso!</h5>
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
                        <div class="modal-info-label"><i class='bx bx-time' style="color:#f59e0b;"></i> DuraÃ§Ã£o</div>
                        <div class="modal-info-value orange">
                            <?php if ($show_modal):
                                $min = (int)$modal_minutos;
                                if ($min >= 60) echo floor($min/60) . 'h' . ($min%60 > 0 ? ' ' . ($min%60) . 'min' : '') . ' (' . $min . ' min)';
                                else echo $min . ' minutos';
                            endif; ?>
                        </div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-x' style="color:#f87171;"></i> Expira em</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y H:i', strtotime($modal_expira)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value green">R$ <?php echo $show_modal ? number_format($modal_valormensal, 2, ',', '.') : '0,00'; ?></div>
                    </div>
                    <?php if ($show_modal && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($show_modal && !empty($modal_notas)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-note' style="color:#a78bfa;"></i> Notas</div>
                        <div class="modal-info-value"><?php echo htmlspecialchars($modal_notas); ?></div>
                    </div>
                    <?php endif; ?>
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
                <p class="modal-success-title">â±ï¸ Teste criado com sucesso!</p>
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
    /* â”€â”€ SELETOR DE HORAS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.querySelectorAll('.hora-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.hora-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.minutos;
        });
    });

    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums   = "0123456789";
        var letras = "abcdefghijklmnopqrstuvwxyz";
        var u_num  = "";
        for (var i = 0; i < 1; i++) u_num += nums.charAt(Math.floor(Math.random() * nums.length));
        var u_let  = "";
        for (var i = 0; i < 3; i++) u_let += letras.charAt(Math.floor(Math.random() * letras.length));
        for (var i = 0; i < 3; i++) u_num  += nums.charAt(Math.floor(Math.random() * nums.length));
        var usuario = u_num + u_let;
        var senha   = usuario;

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;

        document.querySelectorAll('.hora-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.hora-option')[1].classList.add('active');
        document.getElementById('validadefin').value = '180';

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

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) { document.getElementById(id).classList.add('show'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('show'); }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        // Conta vencida ou limite atingido â†’ redireciona para home
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // Erro de validaÃ§Ã£o â†’ apenas fecha o modal, mantÃ©m formulÃ¡rio para correÃ§Ã£o
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u      = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s      = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var min    = <?php echo $show_modal ? (int)$modal_minutos : 0; ?>;
        var expira = '<?php echo $show_modal ? date("d/m/Y H:i", strtotime($modal_expira)) : ""; ?>';
        var l      = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val    = 'R$ <?php echo $show_modal ? number_format($modal_valormensal, 2, ",", ".") : "0,00"; ?>';

        var horas  = Math.floor(min / 60);
        var duracao = horas > 0 ? horas + 'h (' + min + ' min)' : min + ' min';

        var texto = "â±ï¸ TESTE CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "â±ï¸ DuraÃ§Ã£o: " + duracao + "\n";
        texto += "ðŸ“… Expira: " + expira + "\n";
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

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var min    = <?php echo $show_modal ? (int)$modal_minutos : 0; ?>;
        var horas  = Math.floor(min / 60);
        var duracao = horas > 0 ? horas + 'h (' + min + ' min)' : min + ' min';

        var text = "â±ï¸ Teste Criado!\n"
            + "ðŸ‘¤ Login: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "â±ï¸ DuraÃ§Ã£o: " + duracao + "\n"
            + "ðŸ“… Expira: <?php echo $show_modal ? date('d/m/Y H:i', strtotime($modal_expira)) : ''; ?>\n"
            + "ðŸ”— Limite: <?php echo $show_modal ? $modal_limite : ''; ?> conexÃµes\n"
            + "ðŸ’° Valor: R$ <?php echo $show_modal ? number_format($modal_valormensal, 2, ',', '.') : '0,00'; ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') { fecharModalErro(); return; }
        e.target.classList.remove('show');
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro(); return;
        }
        document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
    });
</script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-timer'></i>
            <span>Criar Teste para Clientes</span>
        </div>

        <?php if (!$show_limite_modal): ?>
        <div class="status-info">
            <div class="status-item">
                <i class='bx bx-info-circle'></i>
                <span><?php echo $tipo_txt; ?></span>
            </div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item">
                <i class='bx bx-time' style="color:var(--tertiary);"></i>
                <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(245,158,11,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(249,115,22,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(245,158,11,0.05)" transform="rotate(25,97,74)"/>
                    <circle cx="50%" cy="2%"  r="20" fill="rgba(249,115,22,0.04)"/>
                </svg>
            </div>

            <div class="card-header">
                <div class="header-icon"><i class='bx bx-timer'></i></div>
                <div>
                    <div class="header-title">Criar Teste</div>
                    <div class="header-subtitle">Preencha os dados do teste</div>
                </div>
                <?php if (!$show_limite_modal): ?>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#f59e0b;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if (!$show_limite_modal): ?>
                <button type="button" class="btn-action btn-warning-action" onclick="abrirModalGerar()">
                    <i class='bx bx-shuffle'></i> Gerar AleatÃ³rio
                </button>
                <?php endif; ?>

                <form action="criarteste.php" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login</label>
                            <input type="text" class="form-control" name="usuariofin" placeholder="ex: teste123" id="usuariofin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha</label>
                            <input type="text" class="form-control" name="senhafin" placeholder="ex: senha123" id="senhafin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> Limite</label>
                            <input type="number" class="form-control" value="1" min="1" name="limitefin" id="limitefin" required <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-time icon-clock'></i> DuraÃ§Ã£o do Teste</label>
                            <input type="hidden" name="validadefin" id="validadefin" value="180">
                            <div class="horas-select" id="horasSelector">
                                <div class="hora-option" data-minutos="60">
                                    <span class="hora-label">1h</span>
                                    <span class="hora-sub">60 min</span>
                                </div>
                                <div class="hora-option active" data-minutos="180">
                                    <span class="hora-label">3h</span>
                                    <span class="hora-sub">180 min</span>
                                </div>
                                <div class="hora-option" data-minutos="360">
                                    <span class="hora-label">6h</span>
                                    <span class="hora-sub">360 min</span>
                                </div>
                                <div class="hora-option" data-minutos="720">
                                    <span class="hora-label">12h</span>
                                    <span class="hora-sub">720 min</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-shield-quarter icon-shield'></i> V2Ray <span class="text-beta">BETA</span></label>
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
                            <label><i class='bx bx-dollar' style="color:#10b981;"></i> Valor do Teste (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valormensal" id="valormensal" placeholder="0,00" value="0" <?php echo $show_limite_modal ? 'disabled' : ''; ?>>
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor cobrado pelo teste (0 = gratuito)
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
                        <button type="reset" class="btn-action btn-danger-action"><i class='bx bx-x'></i> Cancelar</button>
                        <button type="submit" class="btn-action btn-success-action" name="criaruser"><i class='bx bx-check'></i> Criar Teste</button>
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
                        <div class="modal-info-label"><i class='bx bx-time' style="color:#f59e0b;"></i> DuraÃ§Ã£o</div>
                        <div class="modal-info-value orange">3 horas (180 min)</div>
                    </div>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formulÃ¡rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal warning" onclick="fecharModal('modalGerar')">
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
     MODAL: SUCESSO
     ============================================= -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> Teste Criado com Sucesso!</h5>
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
                        <div class="modal-info-label"><i class='bx bx-time' style="color:#f59e0b;"></i> DuraÃ§Ã£o</div>
                        <div class="modal-info-value orange">
                            <?php if ($show_modal):
                                $min = (int)$modal_minutos;
                                if ($min >= 60) echo floor($min/60) . 'h' . ($min%60 > 0 ? ' ' . ($min%60) . 'min' : '') . ' (' . $min . ' min)';
                                else echo $min . ' minutos';
                            endif; ?>
                        </div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-x' style="color:#f87171;"></i> Expira em</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y H:i', strtotime($modal_expira)) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexÃµes' : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value green">R$ <?php echo $show_modal ? number_format($modal_valormensal, 2, ',', '.') : '0,00'; ?></div>
                    </div>
                    <?php if ($show_modal && !empty($modal_uuid)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID V2Ray</div>
                        <div class="modal-info-value" style="font-size:11px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($show_modal && !empty($modal_notas)): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-note' style="color:#a78bfa;"></i> Notas</div>
                        <div class="modal-info-value"><?php echo htmlspecialchars($modal_notas); ?></div>
                    </div>
                    <?php endif; ?>
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
                <p class="modal-success-title">â±ï¸ Teste criado com sucesso!</p>
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
    /* â”€â”€ SELETOR DE HORAS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.querySelectorAll('.hora-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.hora-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validadefin').value = this.dataset.minutos;
        });
    });

    /* â”€â”€ V2RAY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

    /* â”€â”€ GERAR ALEATÃ“RIO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function gerarDados() {
        var nums   = "0123456789";
        var letras = "abcdefghijklmnopqrstuvwxyz";
        var u_num  = "";
        for (var i = 0; i < 1; i++) u_num += nums.charAt(Math.floor(Math.random() * nums.length));
        var u_let  = "";
        for (var i = 0; i < 3; i++) u_let += letras.charAt(Math.floor(Math.random() * letras.length));
        for (var i = 0; i < 3; i++) u_num  += nums.charAt(Math.floor(Math.random() * nums.length));
        var usuario = u_num + u_let;
        var senha   = usuario;

        document.getElementById('usuariofin').value = usuario;
        document.getElementById('senhafin').value   = senha;
        document.getElementById('limitefin').value  = 1;

        document.querySelectorAll('.hora-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.hora-option')[1].classList.add('active');
        document.getElementById('validadefin').value = '180';

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

    /* â”€â”€ HELPERS MODAIS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function abrirModal(id) { document.getElementById(id).classList.add('show'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('show'); }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
        <?php if ($error_type == 'limite' || $error_type == 'vencido'): ?>
        // Conta vencida ou limite atingido â†’ redireciona para home
        setTimeout(function(){ window.location.href = '../home.php'; }, 300);
        <?php else: ?>
        // Erro de validaÃ§Ã£o â†’ apenas fecha o modal, mantÃ©m formulÃ¡rio para correÃ§Ã£o
        <?php endif; ?>
    }

    /* â”€â”€ COPIAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function copiarDados() {
        var u      = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s      = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var min    = <?php echo $show_modal ? (int)$modal_minutos : 0; ?>;
        var expira = '<?php echo $show_modal ? date("d/m/Y H:i", strtotime($modal_expira)) : ""; ?>';
        var l      = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val    = 'R$ <?php echo $show_modal ? number_format($modal_valormensal, 2, ",", ".") : "0,00"; ?>';

        var horas  = Math.floor(min / 60);
        var duracao = horas > 0 ? horas + 'h (' + min + ' min)' : min + ' min';

        var texto = "â±ï¸ TESTE CRIADO COM SUCESSO!\n";
        texto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
        texto += "ðŸ‘¤ Login: " + u + "\n";
        texto += "ðŸ”‘ Senha: " + s + "\n";
        texto += "â±ï¸ DuraÃ§Ã£o: " + duracao + "\n";
        texto += "ðŸ“… Expira: " + expira + "\n";
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

    /* â”€â”€ WHATSAPP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function shareOnWhatsApp() {
        var min    = <?php echo $show_modal ? (int)$modal_minutos : 0; ?>;
        var horas  = Math.floor(min / 60);
        var duracao = horas > 0 ? horas + 'h (' + min + ' min)' : min + ' min';

        var text = "â±ï¸ Teste Criado!\n"
            + "ðŸ‘¤ Login: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "ðŸ”‘ Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "â±ï¸ DuraÃ§Ã£o: " + duracao + "\n"
            + "ðŸ“… Expira: <?php echo $show_modal ? date('d/m/Y H:i', strtotime($modal_expira)) : ''; ?>\n"
            + "ðŸ”— Limite: <?php echo $show_modal ? $modal_limite : ''; ?> conexÃµes\n"
            + "ðŸ’° Valor: R$ <?php echo $show_modal ? number_format($modal_valormensal, 2, ',', '.') : '0,00'; ?>\n"
            + "ðŸ”— Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* â”€â”€ FECHAR AO CLICAR FORA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') { fecharModalErro(); return; }
        e.target.classList.remove('show');
    });

    /* â”€â”€ ESC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro(); return;
        }
        document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
    });
</script>
</body>
</html>



