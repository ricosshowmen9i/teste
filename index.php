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
$temaLogin = getTemaLogin($conn);

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

            // Verificar suspensão/vencimento do revendedor (não aplica ao admin)
            $blocked = false;
            if ($row['id'] != 1) {
                $atrib_stmt = $conn->prepare("SELECT suspenso, expira, tipo FROM atribuidos WHERE userid=? LIMIT 1");
                if ($atrib_stmt) {
                    $atrib_stmt->bind_param('i', $row['id']);
                    $atrib_stmt->execute();
                    $atrib_r = $atrib_stmt->get_result();
                    if ($atrib_r && $atrib_r->num_rows > 0) {
                        $ar = $atrib_r->fetch_assoc();
                        if ($ar['suspenso'] >= 1) {
                            $alert_message = 'Sua conta está suspensa. Entre em contato com o suporte para regularizar sua situação.';
                            $alert_type    = 'suspended';
                            $show_modal    = true;
                            $blocked       = true;
                        } elseif ($ar['tipo'] != 'Credito' && !empty($ar['expira']) && $ar['expira'] < date('Y-m-d H:i:s')) {
                            $alert_message = 'Sua conta está vencida. Realize o pagamento para reativar o acesso.';
                            $alert_type    = 'vencido';
                            $show_modal    = true;
                            $blocked       = true;
                        }
                    }
                }
            }

            // Sempre setar sessão quando login/senha válidos (mesmo se suspenso/vencido)
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

            if (!$blocked) {
                if ($row['id'] == 1) {
                    // ADMINISTRADOR vai para pasta admin
                    echo "<script>window.location.href='admin/home.php';</script>";
                } else {
                    // REVENDEDOR - vai para pasta revendedor (home.php)
                    echo "<script>window.location.href='home.php';</script>";
                }
                exit;
            }
        }
        
        if (!$show_modal) {
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
    <link rel="stylesheet" href="AegisCore/temas_visual.css?v=<?php echo time(); ?>">
    <?php echo getFundoPersonalizadoCSS($conn, $temaLogin); ?>
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
    position: relative;
    overflow-x: hidden;
    background: linear-gradient(135deg, #0f0c29, #1e1b4b, #0f172a);
    color: #fff;
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
    padding:48px 38px 40px;
    position:relative;overflow:hidden;
}
.login-card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:4px;
    background:var(--grad);z-index:3;
}
.login-card::after{
    content:'';position:absolute;
    top:-60px;right:-60px;
    width:180px;height:180px;
    background:radial-gradient(circle,var(--bdr) 0%,transparent 70%);
    pointer-events:none;z-index:0;
}

.login-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(16,185,129,0.03) 0%, transparent 70%);
    pointer-events: none;
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
    background: linear-gradient(135deg, #fff, #34d399);
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
.modal-overlay.show{display:flex}

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
    border-color: rgba(16,185,129,0.6);
    background: rgba(255,255,255,0.09);
    box-shadow: 0 0 0 4px rgba(16,185,129,0.1);
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
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    border-radius: 50px;
    color: white;
    font-size: 18px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 10px 25px rgba(16,185,129,0.3);
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
    box-shadow: 0 15px 35px rgba(16,185,129,0.5);
    background: linear-gradient(135deg, #34d399, #10b981);
}

.btn-login:hover .btn-icon {
    transform: translateX(5px);
}

.btn-login:active {
    transform: translateY(2px) scale(0.97);
    box-shadow: 0 5px 15px rgba(16,185,129,0.4);
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
/* Fallback para modal-box quando tema não aplicado */
.modal-box {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border: 1px solid rgba(255,255,255,.1);
}
<?php echo getCSSVariables($temaLogin); ?>
</style>

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
    </div>
</div>


<script>
// ============================================================
// MODAL UNIFICADO — mesmo visual dark/themed do painel
// ============================================================
function showModal(opts){
    var icon=opts.icon||'info',title=opts.title||'',text=opts.text||'',timer=opts.timer||0,
        buttons=(opts.buttons!==false)?opts.buttons:false,onConfirm=opts.onConfirm||null,isDanger=opts.dangerMode||false;
    var iconMap={
        success:{bg:'rgba(16,185,129,.15)',html:'<i class="bx bx-check-circle" style="font-size:54px;color:#10b981;"></i>'},
        error:  {bg:'rgba(239,68,68,.15)', html:'<i class="bx bx-x-circle" style="font-size:54px;color:#ef4444;"></i>'},
        warning:{bg:'rgba(245,158,11,.15)',html:'<i class="bx bx-error" style="font-size:54px;color:#f59e0b;"></i>'},
        info:   {bg:'rgba(59,130,246,.15)',html:'<i class="bx bx-info-circle" style="font-size:54px;color:#3b82f6;"></i>'},
        token:  {bg:'rgba(239,68,68,.15)', html:'<i class="bx bx-lock-alt" style="font-size:54px;color:#ef4444;"></i>'},
    };
    var ic=iconMap[icon]||iconMap.info;
    var ov=document.createElement('div');
    ov.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:99999;display:flex;align-items:center;justify-content:center;animation:fadeInOv .2s ease;';
    var bx=document.createElement('div');
    bx.className='modal-box';
    bx.style.cssText='border-radius:28px;padding:36px 32px;max-width:440px;width:90%;box-shadow:0 25px 60px rgba(0,0,0,.6);text-align:center;animation:slideUpM .25s ease;font-family:Poppins,sans-serif;';
    var id=document.createElement('div');
    id.style.cssText='width:80px;height:80px;border-radius:50%;background:'+ic.bg+';display:flex;align-items:center;justify-content:center;margin:0 auto 18px;';
    id.innerHTML=ic.html;
    var te=document.createElement('h3');te.style.cssText='color:#fff;font-size:20px;font-weight:700;margin:0 0 10px;';te.textContent=title;
    var tx=document.createElement('p');tx.style.cssText='color:rgba(255,255,255,.6);font-size:14px;margin:0 0 24px;line-height:1.6;';tx.innerHTML=text;
    bx.appendChild(id);bx.appendChild(te);bx.appendChild(tx);
    var cb=null,kb=null;
    if(buttons!==false){
        var br=document.createElement('div');br.style.cssText='display:flex;gap:10px;justify-content:center;flex-wrap:wrap;';
        cb=document.createElement('button');
        var cl=(Array.isArray(buttons)&&buttons[1])?buttons[1]:'OK';
        cb.textContent=cl;
        var bg=isDanger?'linear-gradient(135deg,#dc2626,#b91c1c)':'linear-gradient(135deg,#4158D0,#6366f1)';
        cb.style.cssText='padding:11px 28px;border:none;border-radius:14px;background:'+bg+';color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;';
        cb.onmouseover=function(){this.style.filter='brightness(1.1)';this.style.transform='translateY(-1px)';};
        cb.onmouseout=function(){this.style.filter='';this.style.transform='';};
        br.appendChild(cb);
        if(Array.isArray(buttons)&&buttons[0]){
            kb=document.createElement('button');kb.textContent=buttons[0];
            kb.style.cssText='padding:11px 28px;border:1px solid rgba(255,255,255,.15);border-radius:14px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.7);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s;';
            kb.onmouseover=function(){this.style.background='rgba(255,255,255,.12)';};
            kb.onmouseout=function(){this.style.background='rgba(255,255,255,.06)';};
            br.appendChild(kb);
        }
        bx.appendChild(br);
    }
    ov.appendChild(bx);document.body.appendChild(ov);
    var res=[],result={then:function(fn){res.push(fn);return this;}};
    function resolve(v){if(document.body.contains(ov))document.body.removeChild(ov);res.forEach(function(fn){fn(v);});if(onConfirm)onConfirm(v);}
    if(cb)cb.onclick=function(){resolve(true);};
    if(kb)kb.onclick=function(){resolve(false);};
    if(timer>0)setTimeout(function(){resolve(true);},timer);
    ov.addEventListener('click',function(e){if(e.target===ov)resolve(false);});
    return result;
}
window.swal=function(o,t,i){
    if(typeof o==='string')return showModal({title:o,text:t||'',icon:i||'info',buttons:true});
    return showModal(o);
};
(function(){
    if(!document.getElementById('modal-anim-style')){
        var s=document.createElement('style');
        s.id='modal-anim-style';
        s.textContent='@keyframes fadeInOv{from{opacity:0}to{opacity:1}}@keyframes slideUpM{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(s);
    }
})();

function createRipple(event, element) {
    const ripple = document.createElement('span');
    ripple.classList.add('ripple');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    let x = event.clientX - rect.left - size / 2;
    let y = event.clientY - rect.top - size / 2;
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    element.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
}

function addClickEffect(button) {
    button.classList.add('click-effect');
    setTimeout(() => button.classList.remove('click-effect'), 300);
}

const btnLogin = document.getElementById('btnLogin');
btnLogin.addEventListener('click', function(e) { createRipple(e, this); addClickEffect(this); });

document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnLogin');
    const btnText = btn.querySelector('.btn-text');
    const btnIcon = btn.querySelector('.btn-icon');
    const rect = btn.getBoundingClientRect();
    createRipple({type:'click',clientX:rect.left+rect.width/2,clientY:rect.top+rect.height/2}, btn);
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

const style = document.createElement('style');
style.textContent = `@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}`;
document.head.appendChild(style);

let isSubmitting = false;
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (isSubmitting) { e.preventDefault(); return false; }
    isSubmitting = true;
});

<?php if ($show_modal && $alert_type == 'error'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showModal({title:'Falha no Login!', text:<?php echo json_encode($alert_message); ?>, icon:'error', buttons:true});
});
<?php elseif ($show_modal && $alert_type == 'expired'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showModal({
        title: 'Sessão Expirada!',
        text: <?php echo json_encode($alert_message); ?> + '<br><br><small style="color:rgba(255,255,255,.4);">Por segurança, sua sessão expirou após 20 minutos de inatividade.</small>',
        icon: 'warning',
        buttons: true
    }).then(function() {
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.href.split('?')[0]);
        }
        document.querySelector('input[name="login"]').focus();
    });
});
<?php elseif ($show_modal && $alert_type == 'suspended'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showModal({
        title:'Conta Suspensa!',
        text:<?php echo json_encode($alert_message); ?>,
        icon:'token',
        buttons:['OK', '💳 Pagamento'],
        dangerMode:true
    }).then(function(v){
        if(v) window.location.href='home.php';
    });
});
<?php elseif ($show_modal && $alert_type == 'vencido'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showModal({
        title:'Conta Vencida!',
        text:<?php echo json_encode($alert_message); ?>,
        icon:'warning',
        buttons:['OK', '💳 Pagamento'],
        dangerMode:false
    }).then(function(v){
        if(v) window.location.href='home.php';
    });
});
<?php endif; ?>

<?php if ($session_expired): ?>
document.addEventListener('DOMContentLoaded', function() {
    showModal({
        title: 'Sessão Expirada!',
        text: 'Sua sessão expirou. Faça login novamente.<br><br><small style="color:rgba(255,255,255,.4);">Por segurança, sessões expiram após 20 minutos de inatividade.</small>',
        icon: 'warning',
        buttons: true
    }).then(function() {
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.href.split('?')[0]);
        }
        document.querySelector('input[name="login"]').focus();
    });
});
<?php endif; ?>

fetch('admin/notific.php', { method: 'POST' }).then(function(){}).catch(function(){});
</script>

</body>
</html>
