<?php
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
        }
        ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
        }
        ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
        }
        ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
error_reporting(0);
session_start();

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
}

include 'header2.php';
include('conexao.php');

require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

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
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// FunÃ§Ã£o para buscar token do servidor
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
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function ($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//anti sql injection na $_GET['id']
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql = "SELECT * FROM ssh_accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['logineditar'] = $row['login'];
        $_SESSION['senhaeditar'] = $row['senha'];
        $_SESSION['validadeeditar'] = $row['expira'];
        $_SESSION['limiteeditar'] = $row['limite'];
        $byid = $row['byid'];
        $notas = $row['lastview'];
        $whatsapp = $row['whatsapp'];
        $valormensal = $row['valormensal'];
        $_SESSION['byidusereditar'] = $row['byid'];
        $uuid = $row['uuid'];
        if ($uuid == '') {
            $uuid = 'NÃ£o Gerado';
        }
    } else {
        echo "<script>alert('UsuÃ¡rio nÃ£o encontrado!');window.location.href='listarusuarios.php';</script>";
        exit();
    }
}

if ($_SESSION['byidusereditar'] != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este usuÃ¡rio!');window.location.href='../home.php';</script>";
    exit();
}

$logineditar = $_SESSION['logineditar'];
$senhaeditar = $_SESSION['senhaeditar'];
$validadeeditar = $_SESSION['validadeeditar'];
$limiteeditar = $_SESSION['limiteeditar'];

$validadeeditar = date('Y-m-d H:i:s', strtotime($validadeeditar));
$data = date('Y-m-d H:i:s');
$diferenca = strtotime($validadeeditar) - strtotime($data);
$dias = floor($diferenca / (60 * 60 * 24));
$dias = $dias + 1;
if ($dias < 1) $dias = 1;

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

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta expirou!');window.location.href='../home.php';</script>";
        exit();
    }
}

$sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
$result77 = $conn->query($sql2);
$servidores = [];
while ($row = $result77->fetch_assoc()) {
    $servidores[] = $row;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;
$sucess_message = '';
$sucess_servers_str = '';
$failed_servers_str = '';
$user_info = array(); // Array para armazenar informaÃ§Ãµes do usuÃ¡rio editado

if (isset($_POST['editauser'])) {
    $usuarioedit = anti_sql($_POST['usuarioedit']);
    $senhaedit = anti_sql($_POST['senhaedit']);
    $validadeedit = anti_sql($_POST['validadeedit']);
    $limiteedit = anti_sql($_POST['limiteedit']);
    $notas = anti_sql($_POST['notas']);
    $valormensal = anti_sql($_POST['valormensal']);
    $whatsapp = anti_sql($_POST['whatsapp']);

    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuarioedit) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senhaedit) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senhaedit)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] != 'Credito') {
        if ($validadeedit > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        }
        if ($validadeedit < 1) {
            $validadeedit = 1;
        }
        // Verificar limite
        $novo_limite = $limiteedit;
        $limite_antigo = $limiteeditar;
        $limite_disponivel = $restante + $limite_antigo;
        
        if ($novo_limite > $limite_disponivel) {
            $error_message = 'Limite insuficiente! Limite disponÃ­vel: ' . $limite_disponivel;
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql = "SELECT * FROM ssh_accounts WHERE login = '$usuarioedit' AND id != '$id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $error_message = 'UsuÃ¡rio jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $data_nova = date('Y-m-d H:i:s');
        $data_nova = strtotime("+" . $validadeedit . " days", strtotime($data_nova));
        $data_nova = date('Y-m-d H:i:s', $data_nova);

        if ($_SESSION['tipodeconta'] == "Credito") {
            $validadeedit = $dias;
            $limiteedit = $_SESSION['limiteeditar'];
            $data_nova = $validadeeditar;
        }

        // Armazenar informaÃ§Ãµes do usuÃ¡rio para o modal
        $user_info = array(
            'login' => $usuarioedit,
            'senha' => $senhaedit,
            'validade' => ($_SESSION['tipodeconta'] == "Credito") ? $validadeeditar : $data_nova,
            'limite' => $limiteedit,
            'whatsapp' => $whatsapp,
            'notas' => $notas,
            'valormensal' => $valormensal,
            'uuid' => $uuid
        );

        $sql2 = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result77 = $conn->query($sql2);
        
        $loop = Factory::create();
        $sucess_servers = array();
        $failed_servers = array();
        $sucess = false;
        
        while ($user_data = mysqli_fetch_assoc($result77)) {
            $conectado = false;
            $timeout = 3;
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                
                $servidor_id = $user_data['id'];
                $senha_token = getServidorToken($conn, $servidor_id);
                
                $loop->addTimer(0.001, function () use ($user_data, $conn, $usuarioedit, $senhaedit, $validadeedit, $limiteedit, $notas, $valormensal, $logineditar, $senha_token) {
                    $comando1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar . ' ';
                    $comando2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar . '.sh';
                    $comando3 = 'sudo /etc/xis/atlascreate.sh ' . $usuarioedit . ' ' . $senhaedit . ' ' . $validadeedit . ' ' . $limiteedit . ' ';
                    
                    $headers = array(
                        'Senha: ' . $senha_token
                    );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando1");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando2");
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_data['ip'] . ':6969');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando3");
                    curl_exec($ch);
                    curl_close($ch);
                });

                $sucess_servers[] = $user_data['nome'];
                $conectado = true;
                $sucess = true;
            }

            if (!$conectado) {
                $failed_servers[] = $user_data['nome'];
            }
        }

        if ($sucess == true) {
            $_SESSION['usuariofin'] = $usuarioedit;
            $_SESSION['senhafin'] = $senhaedit;
            $sucess_servers_str = implode(", ", $sucess_servers);
            $failed_servers_str = implode(", ", $failed_servers);
            
            if ($_SESSION['tipodeconta'] == "Credito") {
                $_SESSION['validadefin'] = $_SESSION['validadeeditar'];
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', mainid = '', lastview = '$notas', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            } else {
                $_SESSION['validadefin'] = $data_nova;
                $_SESSION['limitefin'] = $limiteedit;
                $sql = "UPDATE ssh_accounts SET login = '$usuarioedit', senha = '$senhaedit', expira = '$data_nova', limite = '$limiteedit', mainid = '', lastview = '$notas', valormensal = '$valormensal', whatsapp = '$whatsapp' WHERE id = '$id'";
                if (mysqli_query($conn, $sql)) {
                    $show_success_modal = true;
                    $sucess_message = "UsuÃ¡rio editado com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar banco de dados: " . mysqli_error($conn);
                    $show_error_modal = true;
                }
            }
        } else {
            $error_message = 'Erro ao editar usuÃ¡rio nos servidores!';
            $show_error_modal = true;
        }
        $loop->run();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar UsuÃ¡rio</title>
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
            --info: #3b82f6;
            --dark: #2c3e50;
            --light: #f8fafc;
            --border: #eef2f6;
            
            --icon-user: #4361ee;
            --icon-lock: #f72585;
            --icon-group: #4cc9f0;
            --icon-whatsapp: #25D366;
            --icon-calendar: #7209b7;
            --icon-shield: #f8961e;
            --icon-note: #06d6a0;
            --icon-time: #b5179e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
        }
        ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
        }
        
        .app-content {
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 780px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .row, .match-height, [class*="col-"] {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .info-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: white !important;
            color: var(--dark) !important;
            padding: 8px 16px !important;
            border-radius: 30px !important;
            font-size: 13px !important;
            margin-top: 5px !important;
            margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }

        .info-badge i {
            font-size: 22px;
            color: var(--primary);
        }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }

        .status-item {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        .status-item i {
            font-size: 20px !important;
            color: var(--tertiary) !important;
        }

        .status-item span {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 8px !important;
            max-width: 100% !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-bg-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .modern-card .card-header {
            padding: 16px 20px 12px !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #C850C0, #4158D0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .header-subtitle {
            font-size: 10px;
            color: rgba(255,255,255,0.35);
        }

        .limite-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }

        .modern-card .card-body {
            padding: 18px 20px !important;
            position: relative;
            z-index: 1;
        }

        .btn-back {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 6px 14px !important;
            border-radius: 30px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: all 0.3s !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            backdrop-filter: blur(5px) !important;
            margin-left: 10px !important;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important;
        }

        .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5) !important;
        }

        .btn-action {
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
            margin-bottom: 15px !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4158D0, #6366f1) !important;
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(65, 88, 208, 0.5) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
        }

        .btn-success:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.5) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(220, 38, 38, 0.5) !important;
        }

        .btn-copy-all {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            color: white !important;
        }

        .btn-copy-all:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.5) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-field label i {
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 9px;
            color: white;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: all 0.25s;
        }

        .form-control:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .icon-user { color: #818cf8; }
        .icon-lock { color: #e879f9; }
        .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; }
        .icon-shield { color: #60a5fa; }
        .icon-note { color: #a78bfa; }
        .icon-whatsapp { color: #34d399; }
        .icon-time { color: #fbbf24; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
            max-width: 500px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header-custom {
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header-custom.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header-custom.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header-custom h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body-custom {
            padding: 24px;
            color: white;
        }

        .modal-footer-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .success-icon, .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 70px;
            color: #10b981;
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5));
        }

        .error-icon i {
            font-size: 70px;
            color: #dc2626;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
        }

        /* Info Cards no Modal */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            font-size: 18px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: white;
            font-family: monospace;
        }

        .info-value.credential {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .server-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }

        .server-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 4px;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 5px !important;
            }

            .header-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 16px !important;
            }

            .header-title {
                font-size: 13px !important;
                white-space: nowrap !important;
            }

            .header-subtitle {
                font-size: 9px !important;
                white-space: nowrap !important;
            }

            .limite-badge {
                margin-left: 0 !important;
                order: 3 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .btn-back {
                margin-left: 0 !important;
                order: 4 !important;
                width: 100% !important;
                justify-content: center !important;
                margin-top: 5px !important;
            }

            .action-buttons {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .action-buttons .btn-danger,
            .action-buttons .btn-success {
                width: 100% !important;
                margin: 0 !important;
            }

            .btn-action {
                width: 100%;
            }

            .modal-container {
                width: 95%;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-user-edit'></i>
                <span>Editar UsuÃ¡rio</span>
            </div>

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

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-user-edit'></i>
                    </div>
                    <div>
                        <div class="header-title">Editar UsuÃ¡rio</div>
                        <div class="header-subtitle">Modifique as informaÃ§Ãµes do usuÃ¡rio</div>
                    </div>
                    <div class="limite-badge">
                        <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                        <?php echo $tipo_txt; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <form class="form" action="editarlogin.php?id=<?php echo $id; ?>" method="POST">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user icon-user'></i>
                                    Login (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($logineditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-lock-alt icon-lock'></i>
                                    Senha (5 a 10 caracteres)
                                </label>
                                <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senhaeditar); ?>" minlength="5" maxlength="10" required>
                            </div>

                            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-layer icon-group'></i>
                                    Limite (MÃ¡x. <?php echo $restante + $limiteeditar; ?>)
                                </label>
                                <input type="number" class="form-control" min="1" max="<?php echo $restante + $limiteeditar; ?>" name="limiteedit" value="<?php echo $limiteeditar; ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-calendar icon-calendar'></i>
                                    Dias de Validade (1 a 90 dias)
                                </label>
                                <input type="number" class="form-control" name="validadeedit" id="validadeedit" 
                                       min="1" max="90" value="<?php echo $dias; ?>" 
                                       placeholder="Digite a quantidade de dias" required>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> Digite um valor entre 1 e 90 dias
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-dollar icon-credit'></i>
                                    Valor Mensal (R$)
                                </label>
                                <input type="text" class="form-control" name="valormensal" placeholder="Valor Mensal" value="<?php echo htmlspecialchars($valormensal ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-shield-quarter icon-shield'></i>
                                    UUID V2Ray
                                </label>
                                <input type="text" class="form-control" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>" readonly>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle'></i> UUID gerado automaticamente
                                </small>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-note icon-note'></i>
                                    Notas
                                </label>
                                <input type="text" class="form-control" name="notas" placeholder="ObservaÃ§Ãµes" value="<?php echo htmlspecialchars($notas ?? ''); ?>">
                            </div>

                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bxl-whatsapp icon-whatsapp'></i>
                                    WhatsApp do Cliente
                                </label>
                                <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp ?? ''); ?>">
                                <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                    <i class='bx bx-info-circle' style="color: #a78bfa;"></i> NÃºmero igual ao WhatsApp
                                </small>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-danger" onclick="window.location.href='listarusuarios.php'">
                                <i class='bx bx-x'></i> Cancelar
                            </button>
                            <button type="submit" class="btn-action btn-success" name="editauser">
                                <i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso com InformaÃ§Ãµes do UsuÃ¡rio -->
    <div id="modalSucesso" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        UsuÃ¡rio Editado com Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalSucesso()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    
                    <!-- InformaÃ§Ãµes do UsuÃ¡rio Editado -->
                    <div class="info-card" id="infoUsuario">
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-user' style="color: #818cf8;"></i>
                                Login:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['login'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-lock-alt' style="color: #e879f9;"></i>
                                Senha:
                            </div>
                            <div class="info-value credential">
                                <?php echo htmlspecialchars($user_info['senha'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-calendar' style="color: #fbbf24;"></i>
                                Validade:
                            </div>
                            <div class="info-value">
                                <?php 
                                if (!empty($user_info['validade'])) {
                                    echo date('d/m/Y H:i:s', strtotime($user_info['validade']));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-layer' style="color: #34d399;"></i>
                                Limite:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['limite'] ?? ''); ?> conexÃµes
                            </div>
                        </div>
                        <?php if (!empty($user_info['whatsapp'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bxl-whatsapp' style="color: #25D366;"></i>
                                WhatsApp:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['whatsapp']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['valormensal'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-dollar' style="color: #f59e0b;"></i>
                                Valor Mensal:
                            </div>
                            <div class="info-value">
                                R$ <?php echo number_format($user_info['valormensal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['uuid']) && $user_info['uuid'] != 'NÃ£o Gerado'): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-shield-quarter' style="color: #60a5fa;"></i>
                                UUID:
                            </div>
                            <div class="info-value" style="font-size: 11px; word-break: break-all;">
                                <?php echo htmlspecialchars($user_info['uuid']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_info['notas'])): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class='bx bx-note' style="color: #a78bfa;"></i>
                                ObservaÃ§Ãµes:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($user_info['notas']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Servidores onde foi editado -->
                    <?php if (!empty($sucess_servers_str)): ?>
                    <div class="server-list">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(255,255,255,0.7);">
                            <i class='bx bx-server'></i> Servidores atualizados:
                        </div>
                        <div>
                            <?php 
                            $servers_array = explode(", ", $sucess_servers_str);
                            foreach ($servers_array as $server): 
                            ?>
                            <span class="server-badge">
                                <i class='bx bx-check-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($failed_servers_str)): ?>
                    <div class="server-list" style="border-color: rgba(220, 38, 38, 0.3); margin-top: 8px;">
                        <div style="font-size: 12px; margin-bottom: 8px; color: rgba(220, 38, 38, 0.8);">
                            <i class='bx bx-error-circle'></i> Servidores com falha:
                        </div>
                        <div>
                            <?php 
                            $failed_array = explode(", ", $failed_servers_str);
                            foreach ($failed_array as $server): 
                            ?>
                            <span class="server-badge" style="background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #dc2626;">
                                <i class='bx bx-x-circle' style="font-size: 10px;"></i> <?php echo htmlspecialchars($server); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-action btn-copy-all" onclick="copiarTodasInformacoes()">
                        <i class='bx bx-copy'></i> Copiar Todas as InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-action btn-success" onclick="fecharModalSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModalErro()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="modal-body-custom">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px; text-align: center;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8); text-align: center;"><?php echo $error_message; ?></p>
                </div>
                <div class="modal-footer-custom" style="justify-content: center;">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModalErro()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        // ValidaÃ§Ã£o do campo de dias
        document.addEventListener('DOMContentLoaded', function() {
            const diasInput = document.getElementById('validadeedit');
            if (diasInput) {
                diasInput.addEventListener('change', function() {
                    let valor = parseInt(this.value);
                    if (isNaN(valor) || valor < 1) {
                        this.value = 1;
                    } else if (valor > 90) {
                        this.value = 90;
                        alert('O valor mÃ¡ximo permitido Ã© 90 dias!');
                    }
                });
                
                // ValidaÃ§Ã£o tambÃ©m no input
                diasInput.addEventListener('input', function() {
                    let valor = parseInt(this.value);
                    if (!isNaN(valor)) {
                        if (valor < 1) {
                            this.value = 1;
                        } else if (valor > 90) {
                            this.value = 90;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        // FunÃ§Ã£o para copiar todas as informaÃ§Ãµes do usuÃ¡rio
        function copiarTodasInformacoes() {
            // Coletar todas as informaÃ§Ãµes do usuÃ¡rio
            const infoUsuario = document.getElementById('infoUsuario');
            const linhas = infoUsuario.querySelectorAll('.info-row');
            
            let textoCompleto = "âœ… USUÃRIO EDITADO COM SUCESSO!\n";
            textoCompleto += "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n\n";
            
            linhas.forEach(linha => {
                const label = linha.querySelector('.info-label').innerText.trim();
                const value = linha.querySelector('.info-value').innerText.trim();
                if (value) {
                    textoCompleto += `${label}: ${value}\n`;
                }
            });
            
            // Adicionar informaÃ§Ãµes dos servidores se existirem
            <?php if (!empty($sucess_servers_str)): ?>
            textoCompleto += "\nðŸ“¡ SERVIDORES ATUALIZADOS:\n";
            <?php 
            $servers_array = explode(", ", $sucess_servers_str);
            foreach ($servers_array as $server): 
            ?>
            textoCompleto += "  âœ“ <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($failed_servers_str)): ?>
            textoCompleto += "\nâš ï¸ SERVIDORES COM FALHA:\n";
            <?php 
            $failed_array = explode(", ", $failed_servers_str);
            foreach ($failed_array as $server): 
            ?>
            textoCompleto += "  âœ— <?php echo addslashes($server); ?>\n";
            <?php endforeach; ?>
            <?php endif; ?>
            
            textoCompleto += "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
            textoCompleto += "ðŸ“… Data: <?php echo date('d/m/Y H:i:s'); ?>\n";
            
            // Copiar para Ã¡rea de transferÃªncia
            navigator.clipboard.writeText(textoCompleto).then(function() {
                // Criar toast de notificaÃ§Ã£o
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = '<i class="bx bx-check-circle" style="font-size: 20px;"></i> InformaÃ§Ãµes copiadas com sucesso!';
                document.body.appendChild(toast);
                
                // Remover toast apÃ³s 3 segundos
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Mostrar modais se houver mensagens
        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalSucesso').classList.add('show');
        });
        <?php endif; ?>
        
        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalErro').classList.add('show');
        });
        <?php endif; ?>

        function fecharModalSucesso() {
            document.getElementById('modalSucesso').classList.remove('show');
            window.location.href = 'listarusuarios.php';
        }

        function fecharModalErro() {
            document.getElementById('modalErro').classList.remove('show');
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                if (event.target.id === 'modalSucesso' || document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                event.target.classList.remove('show');
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (document.getElementById('modalSucesso').classList.contains('show')) {
                    window.location.href = 'listarusuarios.php';
                }
                document.getElementById('modalSucesso')?.classList.remove('show');
                document.getElementById('modalErro')?.classList.remove('show');
            }
        });
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
</html>



