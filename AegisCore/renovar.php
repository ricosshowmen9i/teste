<?php
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
                font-size: 14px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
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
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
                font-size: 14px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
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
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
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
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
                font-size: 14px;
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
error_reporting(0);
session_start();

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$login = $_SESSION['login'];
require_once '../vendor/pix/autoload.php';

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

set_time_limit(0);
ignore_user_abort(true);

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$expiracaopix = $_SESSION['expiracaopix'];
include('header2.php');
$valor = $_SESSION['valor'];
$payment_id = $_SESSION['payment_id'];
$qr_code_base64 = $_SESSION['qr_code_base64'];
$qr_code = $_SESSION['qr_code'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX</title>
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
            margin-left: 260px !important;
            padding: 0 !important;
        }
        
        .content-wrapper {
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
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
            max-width: 600px;
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

        .modern-card {
            background: linear-gradient(135deg, #1e293b, #0f172a) !important;
            border-radius: 20px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            overflow: hidden !important;
            position: relative !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
            width: 100% !important;
            animation: fadeIn 0.5s ease !important;
            margin-bottom: 20px !important;
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

        .modern-card .card-body {
            padding: 24px !important;
            position: relative;
            z-index: 1;
        }

        .btn-action {
            padding: 10px 20px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-family: inherit !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
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

        .btn-outline {
            background: transparent !important;
            border: 1.5px solid rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 15px auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .qr-code {
            width: 200px;
            height: 200px;
            object-fit: contain;
        }

        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .info-value {
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .pix-code {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            word-break: break-all;
            color: rgba(255,255,255,0.8);
            margin-top: 15px;
        }

        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--tertiary);
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .app-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                margin: 0 auto !important;
                padding: 10px !important;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .info-value {
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
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
            
            <div class="info-badge">
                <i class='bx bx-qr'></i>
                <span>Pagamento PIX</span>
            </div>

            <div class="modern-card">
                <div class="card-bg-shapes">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                        <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                        <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                        <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                        <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    </svg>
                </div>
                <div class="card-header">
                    <div class="header-icon">
                        <i class='bx bx-qr'></i>
                    </div>
                    <div>
                        <div class="header-title">Pagamento PIX</div>
                        <div class="header-subtitle">Escaneie o QR Code ou copie o cÃ³digo</div>
                    </div>
                </div>
                <div class="card-body" style="text-align: center;">
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">NÂ° Pedido</span>
                            <span class="info-value">#<?php echo $payment_id; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valor</span>
                            <span class="info-value">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="timer" id="tempo-restante">
                        Carregando...
                    </div>

                    <div class="qr-code-container">
                        <img class="qr-code" src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>

                    <div class="pix-code">
                        <i class='bx bx-copy'></i> CÃ³digo PIX copiÃ¡vel:<br>
                        <input type="text" id="qrcode" class="form-control" value="<?php echo htmlspecialchars($qr_code); ?>" style="background: rgba(0,0,0,0.3); border: none; color: white; text-align: center; margin-top: 8px;" readonly>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-action btn-primary" onclick="copiarCodigo()">
                            <i class='bx bx-copy'></i> Copiar CÃ³digo
                        </button>
                        <button class="btn-action btn-outline" onclick="window.location.href='pagamento.php'">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                    </div>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 10px;">
                        <i class='bx bx-info-circle' style="color: #f59e0b;"></i>
                        <small style="color: rgba(255,255,255,0.6);">ApÃ³s efetuar o pagamento, aguarde a confirmaÃ§Ã£o. O prazo pode levar atÃ© 5 minutos.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarCodigo() {
            var copyText = document.getElementById("qrcode");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            swal({
                title: "Copiado!",
                text: "CÃ³digo PIX copiado para a Ã¡rea de transferÃªncia",
                icon: "success",
                timer: 2000,
                buttons: false
            });
        }

        function atualizarTempoRestante() {
            var agora = new Date();
            var expira = new Date('<?php echo $expiracaopix ?>');
            var diferenca = expira - agora;
            
            if (diferenca > 0) {
                var minutos = Math.floor((diferenca / 1000) / 60);
                var segundos = Math.floor((diferenca / 1000) % 60);
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time"></i> Tempo restante: ' + minutos + 'm ' + segundos + 's';
            } else {
                document.getElementById('tempo-restante').innerHTML = '<i class="bx bx-time-five"></i> Tempo expirado!';
                document.getElementById('tempo-restante').style.color = '#dc2626';
            }
        }

        setInterval(atualizarTempoRestante, 1000);
        atualizarTempoRestante();
    </script>
</body>
</html>



