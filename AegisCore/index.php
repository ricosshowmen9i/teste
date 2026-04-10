<?php
error_reporting(0);

session_start();

// Verificar cookies "Lembrar-me"
$saved_login = '';
$saved_senha = '';
if (isset($_COOKIE['remember_login']) && isset($_COOKIE['remember_senha'])) {
    $saved_login = base64_decode($_COOKIE['remember_login']);
    $saved_senha = base64_decode($_COOKIE['remember_senha']);
}

#verifica se o arquivo existe   
if (file_exists('AegisCore/conexao.php')) {
    include("AegisCore/conexao.php");
} else {
    header('Location: install.php');
    exit;
}
try {
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
} 
catch (mysqli_sql_exception $e) {
    header('Location: install.php');
    exit;
}

// Verifica e atualiza a atividade da sessão
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    $_SESSION['session_expired'] = true;
    session_unset();
    session_destroy(); 
    header('Location: index.php?expired=1');
    exit();
}

$_SESSION['last_activity'] = time();
require("vendor/autoload.php");
use Telegram\Bot\Api;
$dominio = $_SERVER['HTTP_HOST'];
$telegram = new Api('5997467208:AAHFCOmoL1tWoTpPwHZfxTv4DUWL3nJvdOk');

$path = $_SERVER['PHP_SELF'];
if ($path == '/index.php') {
} else {
    $telegram->sendMessage([
        'chat_id' => '2017803306',
        'text' => "O dominio $dominio Esta Usando Outra Pasta $path"
    ]);
}

$sql = "SELECT * FROM configs";
$result = $conn -> query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $nomepainel = $row["nomepainel"];
        $logo = $row["logo"];
        $icon = $row["icon"];
        $csspersonali = $row["corfundologo"];
    }
}

include_once("AegisCore/temas.php");
$_temaGlobal = initTemas($conn);
// Usar tema global ativo para manter consistência com outras páginas
if (is_array($_temaGlobal) && !empty($_temaGlobal['classe'])) {
    $temaLogin = $_temaGlobal;
} else {
    $temaLogin = getTemaLogin($conn);
}

function removeZipFiles($directory) {
    if (is_dir($directory)) {
        if ($handle = opendir($directory)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    $filePath = $directory . '/' . $file;
                    if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                        unlink($filePath);
                    }
                }
            }
            closedir($handle);
        }
    }
}

removeZipFiles(__DIR__);

// Processamento do formulário
$alert_message = '';
$alert_type = '';
$show_modal = false;

if (isset($_POST['submit'])) {
    $login = mysqli_real_escape_string($conn, $_POST['login']);
    $senha = mysqli_real_escape_string($conn, $_POST['senha']);
    
    // Verificações de segurança
    if (strpos($login, "'") !== false || strpos($senha, "'") !== false) {
        $alert_message = 'Caracteres inválidos detectados.';
        $alert_type = 'error';
        $show_modal = true;
    } else {
        // PRIMEIRO: Verificar na tabela accounts (revendedores e admin)
        $sql = "SELECT * FROM accounts WHERE login = ? AND senha = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $login, $senha);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $_SESSION['iduser'] = $row['id'];
            $_SESSION['login'] = $row['login'];
            $_SESSION['senha'] = $row['senha'];
            
            // Processar "Lembrar-me"
            if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                setcookie('remember_login', base64_encode($login), time() + (86400 * 30), "/");
                setcookie('remember_senha', base64_encode($senha), time() + (86400 * 30), "/");
            } else {
                setcookie('remember_login', '', time() - 3600, "/");
                setcookie('remember_senha', '', time() - 3600, "/");
            }
            
            if ($row['id'] == 1) {
                // ADMINISTRADOR vai para pasta admin
                echo "<script>window.location.href='admin/home.php';</script>";
            } else {
                // REVENDEDOR - vai para pasta revendedor (home.php)
                echo "<script>window.location.href='home.php';</script>";
            }
            exit;
        }
        
        // SEGUNDO: Verificar na tabela ssh_accounts (usuários comuns)
        $sql_user = "SELECT * FROM ssh_accounts WHERE login = ? AND senha = ?";
        $stmt_user = mysqli_prepare($conn, $sql_user);
        mysqli_stmt_bind_param($stmt_user, "ss", $login, $senha);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        
        if (mysqli_num_rows($result_user) > 0) {
            $row_user = mysqli_fetch_assoc($result_user);
            
            // ✅ CORREÇÃO: Usuário SUSPENSO ou EXPIRADO ainda pode acessar para renovar
            // Apenas verificar se o login/senha estão corretos, não bloquear por suspensão ou expiração
            
            // Usuário comum - criar sessão (mesmo se suspenso ou expirado)
            $_SESSION['usuario_id'] = $row_user['id'];
            $_SESSION['usuario_login'] = $row_user['login'];
            $_SESSION['usuario_senha'] = $row_user['senha'];
            $_SESSION['usuario_limite'] = $row_user['limite'];
            $_SESSION['usuario_expira'] = $row_user['expira'];
            $_SESSION['usuario_byid'] = $row_user['byid'];
            $_SESSION['usuario_valor_proprio'] = floatval($row_user['valormensal'] ?? 0);
            $_SESSION['usuario_suspenso'] = $row_user['mainid'] ?? '';
            $_SESSION['usuario_renovacao'] = true;
            
            // Buscar dados do revendedor (byid)
            $sql_rev = "SELECT * FROM accounts WHERE id = '{$row_user['byid']}'";
            $result_rev = $conn->query($sql_rev);
            if ($result_rev && $result_rev->num_rows > 0) {
                $rev_data = $result_rev->fetch_assoc();
                $_SESSION['revendedor_id'] = $rev_data['id'];
                $_SESSION['revendedor_nome'] = $rev_data['nome'];
                $_SESSION['revendedor_email'] = $rev_data['contato'];
                $_SESSION['revendedor_mp_token'] = $rev_data['mp_access_token'] ?? '';
                $_SESSION['revendedor_mp_public_key'] = $rev_data['mp_public_key'] ?? '';
                $_SESSION['valor_padrao'] = floatval($rev_data['valorusuario'] ?? 0);
            }
            
            // ✅ Definir valor de renovação: se usuário tem valor próprio >0 usa ele, senão usa valor padrão
            $valor_renovacao = ($_SESSION['usuario_valor_proprio'] > 0) ? $_SESSION['usuario_valor_proprio'] : $_SESSION['valor_padrao'];
            $_SESSION['valor_renovacao'] = $valor_renovacao;
            
            // Processar "Lembrar-me"
            if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                setcookie('remember_login', base64_encode($login), time() + (86400 * 30), "/");
                setcookie('remember_senha', base64_encode($senha), time() + (86400 * 30), "/");
            } else {
                setcookie('remember_login', '', time() - 3600, "/");
                setcookie('remember_senha', '', time() - 3600, "/");
            }
            
            // USUÁRIO COMUM - vai para pasta usuario/index.php (mesmo se suspenso ou expirado)
            echo "<script>window.location.href='usuario/index.php';</script>";
            exit;
        } else {
            $alert_message = 'Login ou Senha Incorretos!';
            $alert_type = 'error';
            $show_modal = true;
        }
    }
}

// Verificar se a sessão expirou
$session_expired = isset($_GET['expired']) && $_GET['expired'] == 1;
if ($session_expired) {
    $alert_message = 'Sua sessão expirou por inatividade! Por favor, faça login novamente.';
    $alert_type = 'expired';
    $show_modal = true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="author" content="Thomas">
    <title><?php echo $nomepainel; ?> - Login</title>
    <link rel="apple-touch-icon" href="<?php echo $icon; ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="AegisCore/temas_visual.css">
<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-x: hidden;
}

.login-container {
    width: 100%;
    max-width: 450px;
    animation: fadeInUp 0.6s ease;
    position: relative;
    z-index: 1;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-card {
    padding: 50px 40px;
    border-radius: 25px;
    position: relative;
    overflow: hidden;
}
/* Fundo SÓLIDO — sobrescreve glassmorphism do temas_visual.css */
body[class] .login-card {
    background: linear-gradient(135deg, #1e293b, #0f172a) !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4) !important;
    border: 1px solid var(--bdr, rgba(255,255,255,0.08)) !important;
    border-radius: 25px !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.logo-login {
    display: block;
    max-width: 180px;
    height: auto;
    margin: 0 auto 30px;
    animation: logoFloat 3s ease-in-out infinite;
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.login-title {
    text-align: center;
    background: linear-gradient(135deg, #fff, var(--acc2, #34d399));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.login-subtitle {
    text-align: center;
    color: rgba(255,255,255,0.5);
    font-size: 14px;
    margin-bottom: 35px;
}

.input-group {
    position: relative;
    margin-bottom: 25px;
}

.input-icon {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    z-index: 2;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.icon-user {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.icon-password {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.input-group:hover .input-icon {
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 6px 15px rgba(0,0,0,0.3);
}

.icon-user:hover {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
}

.icon-password:hover {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.input-group input {
    width: 100%;
    padding: 15px 20px 15px 65px;
    border: 1.5px solid rgba(255,255,255,0.08);
    border-radius: 50px;
    font-size: 16px;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s ease;
    background: rgba(255,255,255,0.06);
    color: white;
}

.input-group input:focus {
    outline: none;
    border-color: var(--bdr-h, rgba(99,102,241,0.6));
    background: rgba(255,255,255,0.09);
    box-shadow: 0 0 0 4px var(--bdr, rgba(99,102,241,0.1));
}

.input-group input:focus + .input-icon {
    transform: translateY(-50%) scale(1.05);
}

.input-group input::placeholder {
    color: rgba(255,255,255,0.3);
}

.remember-me {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
    padding-left: 5px;
}

.remember-me input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #10b981;
}

.remember-me label {
    color: rgba(255,255,255,0.6);
    font-size: 13px;
    cursor: pointer;
    user-select: none;
    transition: color 0.3s;
}

.remember-me label:hover {
    color: rgba(255,255,255,0.9);
}

.btn-login {
    width: 100%;
    padding: 16px;
    background: var(--grad, linear-gradient(135deg, #10b981, #059669));
    border: none;
    border-radius: 50px;
    color: white;
    font-size: 18px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    position: relative;
    overflow: hidden;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-login .btn-text {
    position: relative;
    z-index: 2;
}

.btn-login .btn-icon {
    font-size: 20px;
    position: relative;
    z-index: 2;
    transition: transform 0.3s ease;
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.6s ease;
    z-index: 1;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.4);
    filter: brightness(1.1);
}

.btn-login:hover .btn-icon {
    transform: translateX(5px);
}

.btn-login:active {
    transform: translateY(2px) scale(0.97);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transition: all 0.05s linear;
}

.btn-login .ripple {
    position: absolute;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.7);
    transform: scale(0);
    animation: rippleAnimation 0.6s linear;
    pointer-events: none;
    z-index: 1;
}

@keyframes rippleAnimation {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
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
    animation: modalFadeIn 0.4s ease;
    max-width: 420px;
    width: 90%;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-content-custom {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
}

.modal-header-custom {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header-custom.error {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
}

.modal-header-custom.success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.modal-header-custom.warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.modal-header-custom h5 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 600;
    color: white;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    opacity: 0.8;
    transition: all 0.2s;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    opacity: 1;
    background: rgba(255,255,255,0.1);
    transform: scale(1.1);
}

.modal-body-custom {
    padding: 32px 24px;
    color: white;
    text-align: center;
}

.modal-icon {
    text-align: center;
    margin-bottom: 20px;
}

.modal-icon i {
    font-size: 72px;
    display: inline-block;
    animation: iconPulse 0.5s ease;
}

@keyframes iconPulse {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.modal-icon .bx-error-circle {
    color: #dc2626;
    filter: drop-shadow(0 0 10px rgba(220,38,38,0.5));
}

.modal-icon .bx-check-circle {
    color: #10b981;
    filter: drop-shadow(0 0 10px rgba(16,185,129,0.5));
}

.modal-icon .bx-time-five {
    color: #f59e0b;
    filter: drop-shadow(0 0 10px rgba(245,158,11,0.5));
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 12px;
    background: linear-gradient(135deg, #fff, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.modal-message {
    color: rgba(255,255,255,0.7);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 10px;
}

.modal-footer-custom {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding: 16px 24px;
    display: flex;
    justify-content: center;
    gap: 12px;
}

.btn-modal {
    padding: 10px 28px;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: inherit;
}

.btn-modal-danger {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    box-shadow: 0 4px 12px rgba(220,38,38,0.3);
}

.btn-modal-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(220,38,38,0.4);
}

.btn-modal-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}

.btn-modal-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16,185,129,0.4);
}

.btn-modal-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
}

.btn-modal-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245,158,11,0.4);
}

.btn-login.loading {
    pointer-events: none;
    opacity: 0.9;
    transform: scale(0.98);
}

.btn-login.loading .btn-text {
    opacity: 0;
}

.btn-login.loading .btn-icon {
    opacity: 0;
}

.btn-login.loading::after {
    content: '';
    position: absolute;
    width: 24px;
    height: 24px;
    top: 50%;
    left: 50%;
    margin-left: -12px;
    margin-top: -12px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spinner 0.8s linear infinite;
    z-index: 2;
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

.btn-login.success {
    background: linear-gradient(135deg, #059669, #047857);
    animation: successPulse 0.5s ease;
}

@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); box-shadow: 0 0 20px rgba(16,185,129,0.8); }
    100% { transform: scale(1); }
}

@media (max-width: 480px) {
    .login-card {
        padding: 35px 25px;
    }
    
    .login-title {
        font-size: 24px;
    }
    
    .logo-login {
        max-width: 140px;
    }
    
    .input-group input {
        font-size: 14px;
        padding: 13px 18px 13px 60px;
    }
    
    .input-icon {
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
    
    .btn-login {
        font-size: 16px;
        padding: 14px;
    }
    
    .modal-title {
        font-size: 20px;
    }
    
    .modal-icon i {
        font-size: 56px;
    }
    
    .modal-body-custom {
    }
}
<?php echo getCSSVariables($temaLogin); ?>
</style>
<?php echo getFundoPersonalizadoCSS($conn, $temaLogin); ?>
</head>
<body class="<?php echo htmlspecialchars($temaLogin['classe'] ?? 'theme-dark'); ?>">

<div class="login-container">
    <div class="login-card">
        <img src="<?php echo $logo; ?>" alt="<?php echo $nomepainel; ?>" class="logo-login">
        
        <h2 class="login-title">Bem-vindo!</h2>
        <p class="login-subtitle">Entre com suas credenciais</p>
        
        <form action="index.php" method="POST" id="loginForm">
            <div class="input-group">
                <div class="input-icon icon-user">
                    <i class='bx bx-user'></i>
                </div>
                <input 
                    type="text" 
                    name="login" 
                    placeholder="Digite seu usuário" 
                    required
                    value="<?php echo htmlspecialchars($saved_login); ?>"
                    autocomplete="username"
                >
            </div>
            
            <div class="input-group">
                <div class="input-icon icon-password">
                    <i class='bx bx-lock-alt'></i>
                </div>
                <input 
                    type="password" 
                    name="senha" 
                    placeholder="Digite sua senha" 
                    required
                    value="<?php echo htmlspecialchars($saved_senha); ?>"
                    autocomplete="current-password"
                >
            </div>
            
            <div class="remember-me">
                <input 
                    type="checkbox" 
                    name="remember" 
                    id="remember"
                    <?php echo (isset($_COOKIE['remember_login']) ? 'checked' : ''); ?>
                >
                <label for="remember">Lembrar-me</label>
            </div>
            
            <button type="submit" name="submit" class="btn-login" id="btnLogin">
                <i class='bx bx-log-in-circle btn-icon'></i>
                <span class="btn-text">Entrar no Painel</span>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <button type="button" class="theme-btn-float" onclick="openThemeModal()">
                <i class='bx bx-palette'></i> Trocar Tema
            </button>
        </div>
    </div>
</div>

<style>
.theme-btn-float {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.7);
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: inherit;
}
.theme-btn-float:hover {
    background: rgba(255,255,255,0.15);
    color: white;
    transform: translateY(-2px);
}
</style>

<!-- Modal de Erro (Login incorreto) -->
<div id="errorModal" class="modal-overlay <?php echo ($show_modal && $alert_type == 'error') ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5>
                    <i class='bx bx-error-circle'></i>
                    Erro de Autenticação
                </h5>
                <button class="modal-close" onclick="fecharModal('errorModal')">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon">
                    <i class='bx bx-error-circle'></i>
                </div>
                <h3 class="modal-title">Falha no Login!</h3>
                <p class="modal-message"><?php echo $alert_message; ?></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-danger" onclick="fecharModal('errorModal')">
                    <i class='bx bx-check'></i> Tentar Novamente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Sessão Expirada -->
<div id="expiredModal" class="modal-overlay <?php echo ($show_modal && $alert_type == 'expired') ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5>
                    <i class='bx bx-time-five'></i>
                    Sessão Expirada
                </h5>
                <button class="modal-close" onclick="fecharModal('expiredModal')">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon">
                    <i class='bx bx-time-five'></i>
                </div>
                <h3 class="modal-title">Sessão Expirada!</h3>
                <p class="modal-message"><?php echo $alert_message; ?></p>
                <p class="modal-message" style="font-size: 12px; margin-top: 10px;">
                    <i class='bx bx-info-circle'></i> Por segurança, sua sessão expirou após 20 minutos de inatividade.
                </p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-warning" onclick="fecharModal('expiredModal')">
                    <i class='bx bx-log-in'></i> Fazer Login Novamente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function createRipple(event, element) {
    const ripple = document.createElement('span');
    ripple.classList.add('ripple');
    
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    let x, y;
    
    if (event.type === 'click') {
        x = event.clientX - rect.left - size / 2;
        y = event.clientY - rect.top - size / 2;
    } else {
        x = rect.width / 2 - size / 2;
        y = rect.height / 2 - size / 2;
    }
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

function addClickEffect(button) {
    button.classList.add('click-effect');
    setTimeout(() => {
        button.classList.remove('click-effect');
    }, 300);
}

function fecharModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    
    if (modalId === 'expiredModal') {
        if (window.history && window.history.replaceState) {
            var url = window.location.href.split('?')[0];
            window.history.replaceState({}, document.title, url);
        }
        document.querySelector('input[name="login"]').focus();
    }
    
    if (modalId === 'errorModal' && window.history && window.history.replaceState) {
        var url = window.location.href.split('?')[0];
        window.history.replaceState({}, document.title, url);
    }
}

const btnLogin = document.getElementById('btnLogin');

btnLogin.addEventListener('click', function(e) {
    createRipple(e, this);
    addClickEffect(this);
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnLogin');
    const btnText = btn.querySelector('.btn-text');
    const btnIcon = btn.querySelector('.btn-icon');
    
    const rect = btn.getBoundingClientRect();
    const centerEvent = {
        type: 'click',
        clientX: rect.left + rect.width / 2,
        clientY: rect.top + rect.height / 2
    };
    createRipple(centerEvent, btn);
    addClickEffect(btn);
    
    btn.classList.add('loading');
    btnText.style.opacity = '0';
    btnIcon.style.opacity = '0';
    
    setTimeout(() => {
        if (!btn.classList.contains('loading')) return;
        btn.classList.add('success');
        btn.classList.remove('loading');
        btnText.style.opacity = '1';
        btnIcon.style.opacity = '1';
        btnText.textContent = '✓ Entrando...';
        btnIcon.className = 'bx bx-loader-circle btn-icon';
        btnIcon.style.animation = 'spin 1s linear infinite';
    }, 300);
});

const inputs = document.querySelectorAll('.input-group input');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.querySelector('.input-icon').style.transform = 'translateY(-50%) scale(1.1)';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.querySelector('.input-icon').style.transform = 'translateY(-50%) scale(1)';
    });
});

document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        const modalId = event.target.id;
        fecharModal(modalId);
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = ['errorModal', 'expiredModal', 'successModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && modal.classList.contains('show')) {
                fecharModal(modalId);
            }
        });
    }
});

const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

<?php if ($session_expired): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('expiredModal');
    if (modal) {
        modal.classList.add('show');
        const closeBtn = modal.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                document.querySelector('input[name="login"]').focus();
            });
        }
    }
});
<?php endif; ?>

let isSubmitting = false;
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (isSubmitting) {
        e.preventDefault();
        return false;
    }
    isSubmitting = true;
});

fetch('admin/notific.php', {
    method: 'POST', 
})
.then(response => {})
.catch(error => {});
</script>




</body>
</html>