<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

//se a sessÃ£o nÃ£o existir, redireciona para o login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit();
}
 
include 'conexao.php';
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
include('header2.php');

// Verificar e criar colunas se nÃ£o existirem
$check_columns = [
    'mp_access_token' => "ALTER TABLE accounts ADD COLUMN mp_access_token TEXT NULL",
    'mp_public_key' => "ALTER TABLE accounts ADD COLUMN mp_public_key TEXT NULL",
    'mp_webhook_secret' => "ALTER TABLE accounts ADD COLUMN mp_webhook_secret TEXT NULL",
    'mp_invoice_description' => "ALTER TABLE accounts ADD COLUMN mp_invoice_description VARCHAR(100) DEFAULT 'PAINEL PRO'",
    'mp_active' => "ALTER TABLE accounts ADD COLUMN mp_active TINYINT(1) DEFAULT 0"
];

foreach ($check_columns as $column => $sql) {
    $result = $conn->query("SHOW COLUMNS FROM accounts LIKE '$column'");
    if ($result->num_rows == 0) {
        $conn->query($sql);
    }
}

$id = $_SESSION['iduser'];

$sql = "SELECT * FROM accounts WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$nome = $row['nome'];
$email = $row['contato'];
$accesstoken = $row['accesstoken'];
$valorrevenda = $row['valorrevenda'];
$valorusuario = $row['valorusuario'];
$valordocredito = $row['mainid'];
$tokenpaghiper = $row['acesstokenpaghiper'];
$metodopag = $row['formadepag'] ?? '1';
$tokenapipaghiper = $row['tokenpaghiper'];

// Campos do Mercado Pago
$mp_access_token = $row['mp_access_token'] ?? '';
$mp_public_key = $row['mp_public_key'] ?? '';
$mp_webhook_secret = $row['mp_webhook_secret'] ?? '';
$mp_invoice_description = $row['mp_invoice_description'] ?? 'PAINEL PRO';
$mp_active = $row['mp_active'] ?? 0;

// Gerar URL do webhook
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$dominio = $_SERVER['HTTP_HOST'];
$webhook_url = $protocol . $dominio . '/api/webhooks/mercadopago.php';

if (!file_exists('../admin/suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

$error_message = '';
$show_error_modal = false;
$show_success_modal = false;

if(isset($_POST['salvar'])){
    $nomepag = anti_sql($_POST['nomepag']);
    $emailpag = anti_sql($_POST['emailpag']);
    $valoruser = anti_sql($_POST['valoruser']);
    $valorrev = anti_sql($_POST['valorrev']);
    $valorcredit = anti_sql($_POST['valorcredit']);
    $metodopag = anti_sql($_POST['metodopag']);
    $mp_active = isset($_POST['mp_active']) ? 1 : 0;
    
    // Campos do Mercado Pago
    $mp_access_token = anti_sql($_POST['mp_access_token'] ?? '');
    $mp_public_key = anti_sql($_POST['mp_public_key'] ?? '');
    $mp_webhook_secret = anti_sql($_POST['mp_webhook_secret'] ?? '');
    $mp_invoice_description = anti_sql($_POST['mp_invoice_description'] ?? 'PAINEL PRO');
    
    // ValidaÃ§Ãµes
    if (empty($nomepag)) {
        $error_message = 'Nome no comprovante nÃ£o pode ser vazio!';
        $show_error_modal = true;
    } elseif (empty($emailpag)) {
        $error_message = 'Email nÃ£o pode ser vazio!';
        $show_error_modal = true;
    } elseif (!filter_var($emailpag, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email invÃ¡lido!';
        $show_error_modal = true;
    } elseif (empty($valoruser) || !is_numeric($valoruser) || $valoruser <= 0) {
        $error_message = 'Valor do usuÃ¡rio deve ser um nÃºmero positivo!';
        $show_error_modal = true;
    } elseif (empty($valorrev) || !is_numeric($valorrev) || $valorrev <= 0) {
        $error_message = 'Valor do revendedor deve ser um nÃºmero positivo!';
        $show_error_modal = true;
    } elseif (empty($valorcredit) || !is_numeric($valorcredit) || $valorcredit <= 0) {
        $error_message = 'Valor do crÃ©dito deve ser um nÃºmero positivo!';
        $show_error_modal = true;
    } elseif ($mp_active == 1 && empty($mp_access_token)) {
        $error_message = 'Access Token do Mercado Pago Ã© obrigatÃ³rio!';
        $show_error_modal = true;
    } elseif ($mp_active == 1 && empty($mp_public_key)) {
        $error_message = 'Public Key do Mercado Pago Ã© obrigatÃ³ria!';
        $show_error_modal = true;
    } elseif ($mp_active == 1 && empty($mp_webhook_secret)) {
        $error_message = 'Chave de Assinatura do Webhook Ã© obrigatÃ³ria!';
        $show_error_modal = true;
    }
    
    if (!$show_error_modal) {
        date_default_timezone_set('America/Sao_Paulo');
        $datahoje = date('d-m-Y H:i:s');
        $sql10 = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Alterou a Forma de Pagamento', '$_SESSION[iduser]')";
        $result10 = mysqli_query($conn, $sql10);
        
        $sql = "UPDATE accounts SET 
                formadepag='$metodopag',
                nome='$nomepag', 
                contato='$emailpag', 
                valorusuario='$valoruser', 
                valorrevenda='$valorrev', 
                mainid='$valorcredit',
                mp_active='$mp_active',
                mp_access_token='$mp_access_token',
                mp_public_key='$mp_public_key',
                mp_webhook_secret='$mp_webhook_secret',
                mp_invoice_description='$mp_invoice_description'
                WHERE id='$id'";
        
        $query = mysqli_query($conn, $sql);
        if($query){
            $show_success_modal = true;
            // Atualizar as variÃ¡veis para exibiÃ§Ã£o
            $nome = $nomepag;
            $email = $emailpag;
            $valorusuario = $valoruser;
            $valorrevenda = $valorrev;
            $valordocredito = $valorcredit;
        } else {
            $error_message = 'Erro ao salvar os dados: ' . mysqli_error($conn);
            $show_error_modal = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConfiguraÃ§Ãµes de Pagamento</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConfiguraÃ§Ãµes de Pagamento</title>
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
            margin-left: 240px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 900px;
            margin: 0 auto 0 5px !important;
            padding: 0px !important;
        }
        
        .content-header {
            display: none !important;
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
            flex-wrap: wrap;
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

        .header-buttons {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .btn-tutorial, .btn-copy-webhook {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-tutorial:hover, .btn-copy-webhook:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }

        .status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.3);
            padding: 4px 12px;
            border-radius: 30px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-badge.active {
            background: rgba(16,185,129,0.2);
            color: #10b981;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .status-badge.inactive {
            background: rgba(100,116,139,0.2);
            color: #94a3b8;
            border: 1px solid rgba(100,116,139,0.3);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 22px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #334155;
            transition: 0.3s;
            border-radius: 22px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #10b981;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(22px);
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

        .form-control, .form-select {
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

        .form-control:focus, .form-select:focus {
            border-color: rgba(65,88,208,0.6);
            background: rgba(255,255,255,0.09);
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
        }

        .form-select option {
            background: #1e293b;
            color: white;
        }

        /* Campo com toggle de visibilidade */
        .password-field {
            position: relative;
        }

        .password-field .form-control {
            padding-right: 35px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            cursor: pointer;
            font-size: 16px;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: white;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .info-note {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .info-note small {
            color: rgba(255,255,255,0.6);
            font-size: 10px;
        }

        .info-note i {
            color: #10b981;
        }

        .webhook-box {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 5px;
            flex-wrap: wrap;
        }

        .webhook-url {
            font-family: monospace;
            font-size: 11px;
            color: #60a5fa;
            word-break: break-all;
            flex: 1;
        }

        .btn-copy-mini {
            background: rgba(59,130,246,0.2);
            border: 1px solid rgba(59,130,246,0.3);
            color: #60a5fa;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-copy-mini:hover {
            background: rgba(59,130,246,0.4);
        }

        /* MODAL TUTORIAL - CORRIGIDO */
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
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            animation: modalFadeIn 0.3s ease;
            max-width: 450px;
            width: 90%;
            margin: 0 auto;
        }

        .modal-container.tutorial {
            max-width: 700px;
            width: 90%;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            flex-direction: column;
            max-height: 85vh;
        }

        .modal-header {
            color: white;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .modal-header.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .modal-header.error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .modal-header.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .modal-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
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

        .modal-body {
            padding: 20px 24px;
            color: white;
            overflow-y: auto;
            flex: 1;
        }

        .modal-body.tutorial-body {
            text-align: left;
            padding: 20px 24px;
        }

        .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 16px 24px;
            display: flex;
            justify-content: center;
            flex-shrink: 0;
        }

        .success-icon {
            font-size: 60px;
            color: #10b981;
            margin-bottom: 15px;
        }

        .error-icon {
            font-size: 60px;
            color: #dc2626;
            margin-bottom: 15px;
        }

        /* ConteÃºdo do tutorial */
        .tutorial-content {
            padding: 0;
        }

        .tutorial-step {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .tutorial-step:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .step-number {
            display: inline-block;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            text-align: center;
            line-height: 28px;
            font-size: 14px;
            font-weight: bold;
            margin-right: 10px;
        }

        .step-title {
            font-size: 16px;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
        }

        .step-text {
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            line-height: 1.6;
            margin-left: 38px;
        }

        .step-text ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .step-text li {
            margin-bottom: 6px;
        }

        .warning-box {
            background: rgba(245,158,11,0.1);
            border-left: 3px solid #f59e0b;
            padding: 12px 16px;
            margin-top: 20px;
            border-radius: 8px;
        }

        .warning-box i {
            color: #f59e0b;
            margin-right: 8px;
        }

        .warning-box strong {
            color: #f59e0b;
        }

        .btn-modal {
            padding: 9px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            color: white;
        }

        .btn-modal-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .btn-modal-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59,130,246,0.5);
        }

        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            font-weight: 600;
            font-size: 13px;
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
                padding: 10px !important;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modern-card .card-header {
                flex-direction: column !important;
                align-items: flex-start !important;
            }

            .header-buttons {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
            }

            .btn-back {
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
            }

            .btn-action {
                width: 100%;
            }

            .webhook-box {
                flex-direction: column;
                align-items: flex-start;
            }

            .step-text {
                margin-left: 0;
                margin-top: 10px;
            }

            .modal-body.tutorial-body {
                padding: 16px;
            }

            .modal-header {
                padding: 14px 16px;
            }

            .modal-header h5 {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-credit-card'></i>
                <span>ConfiguraÃ§Ãµes de Pagamento</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                        <polygon points="8%,72%  2%,58%  16%,62%  13%,76%  4%,79%  1%,66%" fill="rgba(139,92,246,0.05)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                        <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-credit-card'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes de Pagamento</div>
                        <div class="header-subtitle">Configure suas formas de pagamento</div>
                    </div>
                    <div class="header-buttons">
                        <button type="button" class="btn-tutorial" onclick="abrirModalTutorial()">
                            <i class='bx bx-help-circle'></i> Tutorial
                        </button>
                        <button type="button" class="btn-copy-webhook" onclick="copiarWebhook()">
                            <i class='bx bx-copy'></i> Copiar Webhook
                        </button>
                    </div>
                    <a href="home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                
                <div class="card-body">
                    <form class="form" action="formaspag.php" method="POST">
                        <div class="form-grid">
                            <!-- Nome no Comprovante -->
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user'></i>
                                    Nome no Comprovante
                                </label>
                                <input type="text" class="form-control" name="nomepag" placeholder="Nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                            </div>

                            <!-- Email -->
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-envelope'></i>
                                    Seu Email
                                </label>
                                <input type="email" class="form-control" name="emailpag" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>

                            <!-- MÃ©todo de Pagamento -->
                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bx-credit-card'></i>
                                    MÃ©todo de Pagamento
                                </label>
                                <select class="form-select" name="metodopag" id="metodopag" required>
                                    <option value="1" <?php echo ($metodopag == 1) ? 'selected' : ''; ?>>Mercado Pago</option>
                                </select>
                            </div>

                            <!-- Status do Mercado Pago - Ativar/Desativar -->
                            <div class="form-field full-width">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class='bx bx-credit-card' style="color: #10b981;"></i>
                                        <span style="font-size: 12px; font-weight: 500;">Status do Mercado Pago</span>
                                        <span class="status-badge <?php echo $mp_active == 1 ? 'active' : 'inactive'; ?>">
                                            <i class='bx <?php echo $mp_active == 1 ? 'bx-check-circle' : 'bx-x-circle'; ?>'></i>
                                            <?php echo $mp_active == 1 ? 'ATIVADO' : 'DESATIVADO'; ?>
                                        </span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="mp_active" value="1" <?php echo $mp_active == 1 ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 5px; display: block;">
                                    <i class='bx bx-info-circle'></i> Ative para comeÃ§ar a receber pagamentos via Mercado Pago
                                </small>
                            </div>

                            <!-- Mercado Pago Fields -->
                            <div id="mpFields" style="grid-column: 1/-1;">
                                <div style="margin-bottom: 15px;">
                                    <div class="info-note">
                                        <i class='bx bx-info-circle'></i>
                                        <small>Configure suas credenciais do Mercado Pago. Obtenha essas informaÃ§Ãµes em: Mercado Pago > Seu negÃ³cio > Credenciais</small>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-key'></i>
                                            ACCESS TOKEN
                                        </label>
                                        <div class="password-field">
                                            <input type="password" class="form-control" name="mp_access_token" id="mp_access_token" placeholder="Cole seu Access Token aqui" value="<?php echo htmlspecialchars($mp_access_token); ?>">
                                            <button type="button" class="toggle-password" onclick="togglePassword('mp_access_token')">
                                                <i class='bx bx-show-alt'></i>
                                            </button>
                                        </div>
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> Encontrado em: Mercado Pago > Seu negÃ³cio > Credenciais
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-key'></i>
                                            PUBLIC KEY
                                        </label>
                                        <div class="password-field">
                                            <input type="text" class="form-control" name="mp_public_key" placeholder="Cole sua Public Key aqui" value="<?php echo htmlspecialchars($mp_public_key); ?>">
                                        </div>
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> Chave pÃºblica para o frontend - tambÃ©m nas Credenciais
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-key'></i>
                                            CHAVE DE ASSINATURA DO WEBHOOK
                                        </label>
                                        <div class="password-field">
                                            <input type="password" class="form-control" name="mp_webhook_secret" id="mp_webhook_secret" placeholder="Chave de assinatura do webhook" value="<?php echo htmlspecialchars($mp_webhook_secret); ?>">
                                            <button type="button" class="toggle-password" onclick="togglePassword('mp_webhook_secret')">
                                                <i class='bx bx-show-alt'></i>
                                            </button>
                                        </div>
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> DisponÃ­vel em: Mercado Pago > Webhooks > Detalhes
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-receipt'></i>
                                            DESCRIÃ‡ÃƒO NA FATURA (MAX 22 CARACTERES)
                                        </label>
                                        <input type="text" class="form-control" name="mp_invoice_description" placeholder="DescriÃ§Ã£o na fatura" maxlength="22" value="<?php echo htmlspecialchars($mp_invoice_description); ?>">
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> MÃ¡ximo 22 caracteres
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <div class="info-note" style="background: rgba(59,130,246,0.1); border-left-color: #3b82f6;">
                                            <i class='bx bx-link-external'></i>
                                            <small>URL do Webhook:</small>
                                            <div class="webhook-box">
                                                <code class="webhook-url" id="webhookUrl"><?php echo $webhook_url; ?></code>
                                                <button type="button" class="btn-copy-mini" onclick="copiarWebhookUrl()">
                                                    <i class='bx bx-copy'></i> Copiar
                                                </button>
                                            </div>
                                            <small>Configure esta URL no painel do Mercado Pago em Webhooks</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Valores -->
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user'></i>
                                    Valor do UsuÃ¡rio Final (R$)
                                </label>
                                <input type="number" class="form-control" step="0.01" min="0.01" name="valoruser" placeholder="Valor" value="<?php echo htmlspecialchars($valorusuario); ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-store-alt'></i>
                                    Valor do Revendedor (R$)
                                </label>
                                <input type="number" class="form-control" step="0.01" min="0.01" name="valorrev" placeholder="Valor" value="<?php echo htmlspecialchars($valorrevenda); ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-coin-stack'></i>
                                    Valor de Cada CrÃ©dito (R$)
                                </label>
                                <input type="number" class="form-control" step="0.01" min="0.01" name="valorcredit" placeholder="Valor" value="<?php echo htmlspecialchars($valordocredito); ?>" required>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="home.php" class="btn-action btn-danger">
                                <i class='bx bx-x'></i> Cancelar
                            </a>
                            <button type="submit" class="btn-action btn-success" name="salvar">
                                <i class='bx bx-check'></i> Salvar ConfiguraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay <?php echo $show_success_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">ConfiguraÃ§Ãµes Salvas!</h3>
                    <p style="color: rgba(255,255,255,0.8);">Suas configuraÃ§Ãµes de pagamento foram atualizadas com sucesso.</p>
                    <p style="color: #10b981; margin-top: 10px; font-size: 13px;">
                        <i class='bx bx-check'></i> Mercado Pago <?php echo $mp_active == 1 ? 'ATIVADO' : 'DESATIVADO'; ?>
                    </p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')" style="min-width: 120px;">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $error_message; ?></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')" style="min-width: 120px;">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tutorial - Corrigido com rolagem interna -->
    <div id="modalTutorial" class="modal-overlay">
        <div class="modal-container tutorial">
            <div class="modal-content">
                <div class="modal-header info">
                    <h5>
                        <i class='bx bx-help-circle'></i>
                        Tutorial de ConfiguraÃ§Ã£o do Mercado Pago
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalTutorial')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body tutorial-body">
                    <div class="tutorial-content">
                        <div class="tutorial-step">
                            <div class="step-title">
                                <span class="step-number">1</span> ConfiguraÃ§Ã£o do Checkout Transparente
                            </div>
                            <div class="step-text">
                                <ul>
                                    <li>Acesse o Painel de Desenvolvedores do Mercado Pago</li>
                                    <li>VÃ¡ para "Suas integraÃ§Ãµes" e crie uma nova aplicaÃ§Ã£o ou selecione uma existente</li>
                                    <li>Na seÃ§Ã£o "Credenciais", certifique-se de que o modo "ProduÃ§Ã£o" estÃ¡ habilitado se for usar em produÃ§Ã£o</li>
                                    <li>Copie o "Access Token" e "Public Key" e cole nos campos correspondentes abaixo</li>
                                    <li><strong>Importante:</strong> Habilite o "Checkout Transparente" nas configuraÃ§Ãµes da aplicaÃ§Ã£o</li>
                                </ul>
                            </div>
                        </div>

                        <div class="tutorial-step">
                            <div class="step-title">
                                <span class="step-number">2</span> ConfiguraÃ§Ã£o dos Webhooks
                            </div>
                            <div class="step-text">
                                <ul>
                                    <li>No painel de desenvolvedores, vÃ¡ para a seÃ§Ã£o "Webhooks"</li>
                                    <li>Clique em "Configurar Webhook"</li>
                                    <li>Cole a URL fornecida no campo "URL": <br>
                                        <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 6px; display: inline-block; margin-top: 5px; word-break: break-all;"><?php echo $webhook_url; ?></code>
                                    </li>
                                    <li>Em "Eventos a notificar", selecione APENAS: <strong>Pagamentos</strong></li>
                                    <li>Deixe os outros eventos desmarcados para evitar notificaÃ§Ãµes desnecessÃ¡rias</li>
                                    <li>Copie a chave de assinatura segura gerada</li>
                                    <li>Cole a chave de assinatura no campo "Chave de Assinatura do Webhook"</li>
                                    <li>Clique em "Salvar" no painel do Mercado Pago</li>
                                </ul>
                            </div>
                        </div>

                        <div class="tutorial-step">
                            <div class="step-title">
                                <span class="step-number">3</span> Testando a ConfiguraÃ§Ã£o
                            </div>
                            <div class="step-text">
                                <ul>
                                    <li>ApÃ³s salvar as configuraÃ§Ãµes, faÃ§a um teste de pagamento</li>
                                    <li>Verifique se os webhooks estÃ£o sendo recebidos corretamente</li>
                                    <li>Monitore os logs para identificar possÃ­veis problemas</li>
                                    <li>Use as credenciais de teste antes de ir para produÃ§Ã£o</li>
                                </ul>
                            </div>
                        </div>

                        <div class="warning-box">
                            <i class='bx bx-shield-alt'></i>
                            <strong>ObservaÃ§Ãµes Importantes</strong><br>
                            <small>â€¢ O Checkout Transparente permite uma experiÃªncia de pagamento integrada ao seu site<br>
                            â€¢ Certifique-se de que suas credenciais estejam no modo correto (teste/produÃ§Ã£o)<br>
                            â€¢ A chave de assinatura do webhook Ã© essencial para a seguranÃ§a das notificaÃ§Ãµes<br>
                            â€¢ Mantenha suas credenciais e chaves sempre em seguranÃ§a<br>
                            â€¢ Teste sempre em ambiente de desenvolvimento antes de colocar em produÃ§Ã£o</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalTutorial')" style="min-width: 120px;">
                        <i class='bx bx-check'></i> Entendi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            <?php if ($show_success_modal): ?>
            setTimeout(function() {
                window.location.href = 'formaspag.php';
            }, 500);
            <?php endif; ?>
        }

        function abrirModalTutorial() {
            document.getElementById('modalTutorial').classList.add('show');
        }

        function copiarWebhook() {
            const url = document.getElementById('webhookUrl').textContent;
            navigator.clipboard.writeText(url).then(function() {
                mostrarToast('âœ… URL do Webhook copiada com sucesso!');
            });
        }

        function copiarWebhookUrl() {
            const url = document.getElementById('webhookUrl').textContent;
            navigator.clipboard.writeText(url).then(function() {
                mostrarToast('âœ… URL do Webhook copiada com sucesso!');
            });
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            if (field.type === 'password') {
                field.type = 'text';
                button.innerHTML = '<i class="bx bx-hide"></i>';
            } else {
                field.type = 'password';
                button.innerHTML = '<i class="bx bx-show-alt"></i>';
            }
        }

        function mostrarToast(msg) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = `<i class='bx bx-check-circle' style="font-size:20px;"></i> ${msg}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                <?php if ($show_success_modal): ?>
                setTimeout(function() {
                    window.location.href = 'formaspag.php';
                }, 500);
                <?php endif; ?>
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                document.getElementById('modalTutorial').classList.remove('show');
                <?php if ($show_success_modal): ?>
                setTimeout(function() {
                    window.location.href = 'formaspag.php';
                }, 500);
                <?php endif; ?>
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
                <i class='bx bx-credit-card'></i>
                <span>ConfiguraÃ§Ãµes de Pagamento</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                        <polygon points="8%,72%  2%,58%  16%,62%  13%,76%  4%,79%  1%,66%" fill="rgba(139,92,246,0.05)"/>
                        <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                        <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
                    </svg>
                </div>

                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-credit-card'></i>
                    </div>
                    <div>
                        <div class="header-title">ConfiguraÃ§Ãµes de Pagamento</div>
                        <div class="header-subtitle">Configure suas formas de pagamento</div>
                    </div>
                    <div class="header-buttons">
                        <button type="button" class="btn-tutorial" onclick="abrirModalTutorial()">
                            <i class='bx bx-help-circle'></i> Tutorial
                        </button>
                        <button type="button" class="btn-copy-webhook" onclick="copiarWebhook()">
                            <i class='bx bx-copy'></i> Copiar Webhook
                        </button>
                    </div>
                    <a href="home.php" class="btn-back">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
                
                <div class="card-body">
                    <form class="form" action="formaspag.php" method="POST">
                        <div class="form-grid">
                            <!-- Nome no Comprovante -->
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user'></i>
                                    Nome no Comprovante
                                </label>
                                <input type="text" class="form-control" name="nomepag" placeholder="Nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                            </div>

                            <!-- Email -->
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-envelope'></i>
                                    Seu Email
                                </label>
                                <input type="email" class="form-control" name="emailpag" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>

                            <!-- MÃ©todo de Pagamento -->
                            <div class="form-field full-width">
                                <label>
                                    <i class='bx bx-credit-card'></i>
                                    MÃ©todo de Pagamento
                                </label>
                                <select class="form-select" name="metodopag" id="metodopag" required>
                                    <option value="1" <?php echo ($metodopag == 1) ? 'selected' : ''; ?>>Mercado Pago</option>
                                </select>
                            </div>

                            <!-- Status do Mercado Pago - Ativar/Desativar -->
                            <div class="form-field full-width">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class='bx bx-credit-card' style="color: #10b981;"></i>
                                        <span style="font-size: 12px; font-weight: 500;">Status do Mercado Pago</span>
                                        <span class="status-badge <?php echo $mp_active == 1 ? 'active' : 'inactive'; ?>">
                                            <i class='bx <?php echo $mp_active == 1 ? 'bx-check-circle' : 'bx-x-circle'; ?>'></i>
                                            <?php echo $mp_active == 1 ? 'ATIVADO' : 'DESATIVADO'; ?>
                                        </span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="mp_active" value="1" <?php echo $mp_active == 1 ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <small style="color: rgba(255,255,255,0.3); margin-top: 5px; display: block;">
                                    <i class='bx bx-info-circle'></i> Ative para comeÃ§ar a receber pagamentos via Mercado Pago
                                </small>
                            </div>

                            <!-- Mercado Pago Fields -->
                            <div id="mpFields" style="grid-column: 1/-1;">
                                <div style="margin-bottom: 15px;">
                                    <div class="info-note">
                                        <i class='bx bx-info-circle'></i>
                                        <small>Configure suas credenciais do Mercado Pago. Obtenha essas informaÃ§Ãµes em: Mercado Pago > Seu negÃ³cio > Credenciais</small>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-key'></i>
                                            ACCESS TOKEN
                                        </label>
                                        <div class="password-field">
                                            <input type="password" class="form-control" name="mp_access_token" id="mp_access_token" placeholder="Cole seu Access Token aqui" value="<?php echo htmlspecialchars($mp_access_token); ?>">
                                            <button type="button" class="toggle-password" onclick="togglePassword('mp_access_token')">
                                                <i class='bx bx-show-alt'></i>
                                            </button>
                                        </div>
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> Encontrado em: Mercado Pago > Seu negÃ³cio > Credenciais
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-key'></i>
                                            PUBLIC KEY
                                        </label>
                                        <div class="password-field">
                                            <input type="text" class="form-control" name="mp_public_key" placeholder="Cole sua Public Key aqui" value="<?php echo htmlspecialchars($mp_public_key); ?>">
                                        </div>
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> Chave pÃºblica para o frontend - tambÃ©m nas Credenciais
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-key'></i>
                                            CHAVE DE ASSINATURA DO WEBHOOK
                                        </label>
                                        <div class="password-field">
                                            <input type="password" class="form-control" name="mp_webhook_secret" id="mp_webhook_secret" placeholder="Chave de assinatura do webhook" value="<?php echo htmlspecialchars($mp_webhook_secret); ?>">
                                            <button type="button" class="toggle-password" onclick="togglePassword('mp_webhook_secret')">
                                                <i class='bx bx-show-alt'></i>
                                            </button>
                                        </div>
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> DisponÃ­vel em: Mercado Pago > Webhooks > Detalhes
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <label>
                                            <i class='bx bx-receipt'></i>
                                            DESCRIÃ‡ÃƒO NA FATURA (MAX 22 CARACTERES)
                                        </label>
                                        <input type="text" class="form-control" name="mp_invoice_description" placeholder="DescriÃ§Ã£o na fatura" maxlength="22" value="<?php echo htmlspecialchars($mp_invoice_description); ?>">
                                        <small style="color: rgba(255,255,255,0.3); margin-top: 3px; display: block; font-size: 9px;">
                                            <i class='bx bx-info-circle'></i> MÃ¡ximo 22 caracteres
                                        </small>
                                    </div>

                                    <div class="form-field full-width">
                                        <div class="info-note" style="background: rgba(59,130,246,0.1); border-left-color: #3b82f6;">
                                            <i class='bx bx-link-external'></i>
                                            <small>URL do Webhook:</small>
                                            <div class="webhook-box">
                                                <code class="webhook-url" id="webhookUrl"><?php echo $webhook_url; ?></code>
                                                <button type="button" class="btn-copy-mini" onclick="copiarWebhookUrl()">
                                                    <i class='bx bx-copy'></i> Copiar
                                                </button>
                                            </div>
                                            <small>Configure esta URL no painel do Mercado Pago em Webhooks</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Valores -->
                            <div class="form-field">
                                <label>
                                    <i class='bx bx-user'></i>
                                    Valor do UsuÃ¡rio Final (R$)
                                </label>
                                <input type="number" class="form-control" step="0.01" min="0.01" name="valoruser" placeholder="Valor" value="<?php echo htmlspecialchars($valorusuario); ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-store-alt'></i>
                                    Valor do Revendedor (R$)
                                </label>
                                <input type="number" class="form-control" step="0.01" min="0.01" name="valorrev" placeholder="Valor" value="<?php echo htmlspecialchars($valorrevenda); ?>" required>
                            </div>

                            <div class="form-field">
                                <label>
                                    <i class='bx bx-coin-stack'></i>
                                    Valor de Cada CrÃ©dito (R$)
                                </label>
                                <input type="number" class="form-control" step="0.01" min="0.01" name="valorcredit" placeholder="Valor" value="<?php echo htmlspecialchars($valordocredito); ?>" required>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="home.php" class="btn-action btn-danger">
                                <i class='bx bx-x'></i> Cancelar
                            </a>
                            <button type="submit" class="btn-action btn-success" name="salvar">
                                <i class='bx bx-check'></i> Salvar ConfiguraÃ§Ãµes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="modal-overlay <?php echo $show_success_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header success">
                    <h5>
                        <i class='bx bx-check-circle'></i>
                        Sucesso!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalSucesso')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="success-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">ConfiguraÃ§Ãµes Salvas!</h3>
                    <p style="color: rgba(255,255,255,0.8);">Suas configuraÃ§Ãµes de pagamento foram atualizadas com sucesso.</p>
                    <p style="color: #10b981; margin-top: 10px; font-size: 13px;">
                        <i class='bx bx-check'></i> Mercado Pago <?php echo $mp_active == 1 ? 'ATIVADO' : 'DESATIVADO'; ?>
                    </p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalSucesso')" style="min-width: 120px;">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Erro -->
    <div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content">
                <div class="modal-header error">
                    <h5>
                        <i class='bx bx-error-circle'></i>
                        Erro!
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErro')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="error-icon">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 10px;">Ops! Algo deu errado</h3>
                    <p style="color: rgba(255,255,255,0.8);"><?php echo $error_message; ?></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-danger" onclick="fecharModal('modalErro')" style="min-width: 120px;">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tutorial - Corrigido com rolagem interna -->
    <div id="modalTutorial" class="modal-overlay">
        <div class="modal-container tutorial">
            <div class="modal-content">
                <div class="modal-header info">
                    <h5>
                        <i class='bx bx-help-circle'></i>
                        Tutorial de ConfiguraÃ§Ã£o do Mercado Pago
                    </h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalTutorial')">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="modal-body tutorial-body">
                    <div class="tutorial-content">
                        <div class="tutorial-step">
                            <div class="step-title">
                                <span class="step-number">1</span> ConfiguraÃ§Ã£o do Checkout Transparente
                            </div>
                            <div class="step-text">
                                <ul>
                                    <li>Acesse o Painel de Desenvolvedores do Mercado Pago</li>
                                    <li>VÃ¡ para "Suas integraÃ§Ãµes" e crie uma nova aplicaÃ§Ã£o ou selecione uma existente</li>
                                    <li>Na seÃ§Ã£o "Credenciais", certifique-se de que o modo "ProduÃ§Ã£o" estÃ¡ habilitado se for usar em produÃ§Ã£o</li>
                                    <li>Copie o "Access Token" e "Public Key" e cole nos campos correspondentes abaixo</li>
                                    <li><strong>Importante:</strong> Habilite o "Checkout Transparente" nas configuraÃ§Ãµes da aplicaÃ§Ã£o</li>
                                </ul>
                            </div>
                        </div>

                        <div class="tutorial-step">
                            <div class="step-title">
                                <span class="step-number">2</span> ConfiguraÃ§Ã£o dos Webhooks
                            </div>
                            <div class="step-text">
                                <ul>
                                    <li>No painel de desenvolvedores, vÃ¡ para a seÃ§Ã£o "Webhooks"</li>
                                    <li>Clique em "Configurar Webhook"</li>
                                    <li>Cole a URL fornecida no campo "URL": <br>
                                        <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 6px; display: inline-block; margin-top: 5px; word-break: break-all;"><?php echo $webhook_url; ?></code>
                                    </li>
                                    <li>Em "Eventos a notificar", selecione APENAS: <strong>Pagamentos</strong></li>
                                    <li>Deixe os outros eventos desmarcados para evitar notificaÃ§Ãµes desnecessÃ¡rias</li>
                                    <li>Copie a chave de assinatura segura gerada</li>
                                    <li>Cole a chave de assinatura no campo "Chave de Assinatura do Webhook"</li>
                                    <li>Clique em "Salvar" no painel do Mercado Pago</li>
                                </ul>
                            </div>
                        </div>

                        <div class="tutorial-step">
                            <div class="step-title">
                                <span class="step-number">3</span> Testando a ConfiguraÃ§Ã£o
                            </div>
                            <div class="step-text">
                                <ul>
                                    <li>ApÃ³s salvar as configuraÃ§Ãµes, faÃ§a um teste de pagamento</li>
                                    <li>Verifique se os webhooks estÃ£o sendo recebidos corretamente</li>
                                    <li>Monitore os logs para identificar possÃ­veis problemas</li>
                                    <li>Use as credenciais de teste antes de ir para produÃ§Ã£o</li>
                                </ul>
                            </div>
                        </div>

                        <div class="warning-box">
                            <i class='bx bx-shield-alt'></i>
                            <strong>ObservaÃ§Ãµes Importantes</strong><br>
                            <small>â€¢ O Checkout Transparente permite uma experiÃªncia de pagamento integrada ao seu site<br>
                            â€¢ Certifique-se de que suas credenciais estejam no modo correto (teste/produÃ§Ã£o)<br>
                            â€¢ A chave de assinatura do webhook Ã© essencial para a seguranÃ§a das notificaÃ§Ãµes<br>
                            â€¢ Mantenha suas credenciais e chaves sempre em seguranÃ§a<br>
                            â€¢ Teste sempre em ambiente de desenvolvimento antes de colocar em produÃ§Ã£o</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-success" onclick="fecharModal('modalTutorial')" style="min-width: 120px;">
                        <i class='bx bx-check'></i> Entendi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            <?php if ($show_success_modal): ?>
            setTimeout(function() {
                window.location.href = 'formaspag.php';
            }, 500);
            <?php endif; ?>
        }

        function abrirModalTutorial() {
            document.getElementById('modalTutorial').classList.add('show');
        }

        function copiarWebhook() {
            const url = document.getElementById('webhookUrl').textContent;
            navigator.clipboard.writeText(url).then(function() {
                mostrarToast('âœ… URL do Webhook copiada com sucesso!');
            });
        }

        function copiarWebhookUrl() {
            const url = document.getElementById('webhookUrl').textContent;
            navigator.clipboard.writeText(url).then(function() {
                mostrarToast('âœ… URL do Webhook copiada com sucesso!');
            });
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            if (field.type === 'password') {
                field.type = 'text';
                button.innerHTML = '<i class="bx bx-hide"></i>';
            } else {
                field.type = 'password';
                button.innerHTML = '<i class="bx bx-show-alt"></i>';
            }
        }

        function mostrarToast(msg) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = `<i class='bx bx-check-circle' style="font-size:20px;"></i> ${msg}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
                <?php if ($show_success_modal): ?>
                setTimeout(function() {
                    window.location.href = 'formaspag.php';
                }, 500);
                <?php endif; ?>
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('modalSucesso').classList.remove('show');
                document.getElementById('modalErro').classList.remove('show');
                document.getElementById('modalTutorial').classList.remove('show');
                <?php if ($show_success_modal): ?>
                setTimeout(function() {
                    window.location.href = 'formaspag.php';
                }, 500);
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>



