<?php
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Cookies lembrar-me ────────────────────────────────────────────────────
$saved_login = isset($_COOKIE['remember_login']) ? base64_decode($_COOKIE['remember_login']) : '';
$saved_senha = isset($_COOKIE['remember_senha']) ? base64_decode($_COOKIE['remember_senha']) : '';

// ── Conexão ───────────────────────────────────────────────────────────────
if (!file_exists('AegisCore/conexao.php')) { header('Location: install.php'); exit; }
include 'AegisCore/conexao.php';
$conn = @mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) { header('Location: install.php'); exit; }

// ── Sessão expirada ───────────────────��───────────────────────────────────
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    header('Location: index.php?expired=1'); exit;
}
$_SESSION['last_activity'] = time();

// ── Telegram (opcional, não quebra se falhar) ─────────────────────────────
if (file_exists('vendor/autoload.php')) {
    try {
        require_once 'vendor/autoload.php';
        $dominio = $_SERVER['HTTP_HOST'] ?? '';
        $path    = $_SERVER['PHP_SELF']  ?? '';
        if ($path !== '/index.php') {
            $tg = new \Telegram\Bot\Api('5997467208:AAHFCOmoL1tWoTpPwHZfxTv4DUWL3nJvdOk');
            $tg->sendMessage(['chat_id'=>'2017803306','text'=>"O dominio $dominio Esta Usando Outra Pasta $path"]);
        }
    } catch (\Throwable $e) {}
}

// ── Dados do painel ───────────────────────────────────────────────────────
$nomepainel = 'Painel'; $logo = ''; $icon = '';
$rCfg = $conn->query("SELECT nomepainel,logo,icon FROM configs LIMIT 1");
if ($rCfg && $rCfg->num_rows > 0) {
    $cfg = $rCfg->fetch_assoc();
    $nomepainel = $cfg['nomepainel'] ?? 'Painel';
    $logo       = $cfg['logo']       ?? '';
    $icon       = $cfg['icon']       ?? '';
}

// ── TEMAS — Carrega do banco (tema definido pelo admin) ──────────────────
include_once 'AegisCore/temas.php';
$_temaGlobal = initTemas($conn);
// Usar tema global ativo (mesmo das outras páginas) para manter consistência
if (is_array($_temaGlobal) && isset($_temaGlobal['classe'])) {
    $temaLogin = $_temaGlobal;
} else {
    $temaLogin = getTemaLogin($conn);
}
$tClasse   = $temaLogin['classe'] ?? 'theme-dark';
$tPreview  = $temaLogin['preview'] ?? '#6366f1';
$tDesativado = empty($tClasse); // Modo sem tema

// Mapa de cores por tema (para CSS inline do login)
$temasMap = [
  'theme-dark'        => ['#0d0d1a','#1a1a2e','#6366f1','#818cf8','linear-gradient(135deg,#6366f1,#4f46e5)','rgba(99,102,241,.25)','rgba(129,140,248,.7)','border-radius:22px'],
  'theme-neon-roxo'   => ['#0d0015','#1a0030','#a855f7','#06b6d4','linear-gradient(135deg,#a855f7,#06b6d4)','rgba(168,85,247,.25)','rgba(6,182,212,.7)','border-radius:22px'],
  'theme-cyber'       => ['#0a0800','#14100a','#facc15','#fde047','linear-gradient(135deg,#facc15,#eab308)','rgba(250,204,21,.3)','rgba(234,179,8,.8)','border-radius:4px'],
  'theme-arctic'      => ['#020d1a','#041525','#38bdf8','#7dd3fc','linear-gradient(135deg,#38bdf8,#0284c7)','rgba(56,189,248,.25)','rgba(56,189,248,.7)','border-radius:20px'],
  'theme-ocean'       => ['#020d1a','#041525','#0ea5e9','#38bdf8','linear-gradient(135deg,#0ea5e9,#0284c7)','rgba(14,165,233,.25)','rgba(56,189,248,.7)','border-radius:28px 8px 28px 8px'],
  'theme-sunset'      => ['#0f0800','#1a0f00','#f97316','#fb923c','linear-gradient(135deg,#f97316,#ea580c)','rgba(249,115,22,.25)','rgba(234,88,12,.7)','border-radius:4px 24px 4px 24px'],
  'theme-emerald'     => ['#020f0a','#04150e','#10b981','#34d399','linear-gradient(135deg,#10b981,#059669)','rgba(16,185,129,.25)','rgba(5,150,105,.7)','border-radius:16px'],
  'theme-sakura'      => ['#0f0015','#1a0020','#ec4899','#f472b6','linear-gradient(135deg,#ec4899,#db2777)','rgba(236,72,153,.25)','rgba(219,39,119,.7)','border-radius:32px'],
  'theme-galaxy'      => ['#04000f','#0a0019','#8b5cf6','#c084fc','linear-gradient(135deg,#8b5cf6,#a855f7)','rgba(139,92,246,.3)','rgba(168,85,247,.8)','border-radius:40px 12px 40px 12px'],
  'theme-rose'        => ['#1a0010','#200015','#f43f5e','#fb7185','linear-gradient(135deg,#f43f5e,#e11d48)','rgba(244,63,94,.25)','rgba(225,29,72,.7)','border-radius:0 28px 0 28px'],
  'theme-violet'      => ['#0d0020','#150030','#7c3aed','#a78bfa','linear-gradient(135deg,#7c3aed,#6d28d9)','rgba(124,58,237,.25)','rgba(109,40,217,.7)','border-radius:16px'],
  'theme-mint'        => ['#001a18','#00251f','#14b8a6','#2dd4bf','linear-gradient(135deg,#14b8a6,#0d9488)','rgba(20,184,166,.25)','rgba(13,148,136,.7)','border-radius:50px 12px 50px 12px'],
  'theme-lavender'    => ['#150020','#200030','#9333ea','#e879f9','linear-gradient(135deg,#9333ea,#c084fc)','rgba(147,51,234,.28)','rgba(200,80,192,.8)','border-radius:36px 36px 20px 20px'],
  'theme-aqua'        => ['#001820','#002530','#0891b2','#67e8f9','linear-gradient(135deg,#0891b2,#06b6d4)','rgba(8,145,178,.25)','rgba(6,182,212,.7)','border-radius:8px 40px 8px 40px'],
  'theme-gold'        => ['#0f0900','#1a1000','#d97706','#fde68a','linear-gradient(135deg,#d97706,#f59e0b)','rgba(217,119,6,.28)','rgba(245,158,11,.8)','border-radius:12px'],
  'theme-copper'      => ['#0f0800','#1a1000','#b45309','#fb923c','linear-gradient(135deg,#b45309,#ea580c)','rgba(180,83,9,.28)','rgba(234,88,12,.8)','border-radius:16px'],
  'theme-inferno'     => ['#1a0a0a','#0f0505','#dc2626','#f97316','linear-gradient(135deg,#dc2626,#f97316)','rgba(220,38,38,.3)','rgba(249,115,22,.8)','border-radius:16px'],
  'theme-caramel'     => ['#0f0800','#1a0f00','#d97706','#fbbf24','linear-gradient(135deg,#d97706,#f59e0b)','rgba(217,119,6,.25)','rgba(251,191,36,.7)','border-radius:16px'],
  'theme-matrix'      => ['#060a06','#0a100a','#00ff41','#00cc33','linear-gradient(135deg,#00ff41,#00cc33)','rgba(0,255,65,.25)','rgba(0,255,65,.8)','border-radius:2px'],
  'theme-naruto'      => ['#0f0a05','#1a1208','#f97316','#fbbf24','linear-gradient(135deg,#f97316,#fbbf24)','rgba(249,115,22,.25)','rgba(251,191,36,.7)','border-radius:8px'],
  'theme-dbz'         => ['#0a0502','#150a05','#f97316','#fbbf24','linear-gradient(135deg,#f97316,#3b82f6)','rgba(249,115,22,.25)','rgba(59,130,246,.7)','border-radius:16px'],
  'theme-onepiece'    => ['#0f0a0a','#1a1010','#dc2626','#fbbf24','linear-gradient(135deg,#dc2626,#fbbf24)','rgba(220,38,38,.25)','rgba(251,191,36,.7)','border-radius:12px'],
  'theme-cyberpunk'   => ['#0a0a0f','#11111a','#ff00ff','#00ffff','linear-gradient(135deg,#ff00ff,#00ffff)','rgba(255,0,255,.3)','rgba(0,255,255,.8)','border-radius:4px'],
  'theme-retrogames'  => ['#0f0f23','#16213e','#e94560','#fbbf24','linear-gradient(135deg,#e94560,#0f0f23)','rgba(233,69,96,.3)','rgba(233,69,96,.9)','border-radius:0'],
  'theme-steampunk'   => ['#0f0800','#1a1000','#d97706','#b45309','linear-gradient(135deg,#d97706,#b45309)','rgba(217,119,6,.28)','rgba(180,83,9,.8)','border-radius:12px'],
  'theme-pokemon'     => ['#1a0a0a','#1f1010','#dc2626','#fbbf24','linear-gradient(135deg,#dc2626,#fbbf24)','rgba(220,38,38,.25)','rgba(251,191,36,.7)','border-radius:20px'],
  'theme-primavera'   => ['#1a1520','#1f1a25','#f472b6','#34d399','linear-gradient(135deg,#f472b6,#34d399)','rgba(244,114,182,.25)','rgba(52,211,153,.7)','border-radius:20px'],
  'theme-vampire'     => ['#0f0505','#1a0a0a','#991b1b','#dc2626','linear-gradient(135deg,#991b1b,#7f1d1d)','rgba(153,27,27,.3)','rgba(220,38,38,.7)','border-radius:16px'],
  'theme-halloween'   => ['#1a1010','#1f1515','#f97316','#8b5cf6','linear-gradient(135deg,#f97316,#8b5cf6)','rgba(249,115,22,.25)','rgba(139,92,246,.7)','border-radius:20px'],
  'theme-natal'       => ['#04150e','#020f08','#22c55e','#dc2626','linear-gradient(135deg,#22c55e,#dc2626)','rgba(34,197,94,.25)','rgba(220,38,38,.7)','border-radius:20px'],
  'theme-natalneve'   => ['#0f1a25','#162030','#dc2626','#f8fafc','linear-gradient(135deg,#dc2626,#22c55e)','rgba(220,38,38,.25)','rgba(34,197,94,.7)','border-radius:20px'],
  'theme-anonovo'     => ['#18150a','#0a0800','#f59e0b','#3b82f6','linear-gradient(135deg,#f59e0b,#3b82f6)','rgba(245,158,11,.25)','rgba(59,130,246,.7)','border-radius:20px'],
  'theme-anonovo-fogo'=> ['#1a1a2e','#1f1f38','#fbbf24','#ef4444','linear-gradient(135deg,#fbbf24,#ef4444)','rgba(251,191,36,.25)','rgba(239,68,68,.7)','border-radius:20px'],
  'theme-valentine'   => ['#1a0f1a','#100a10','#ef4444','#ec4899','linear-gradient(135deg,#ef4444,#ec4899)','rgba(239,68,68,.25)','rgba(236,72,153,.7)','border-radius:24px'],
  'theme-carnaval'    => ['#1a1010','#1f1515','#fbbf24','#ec4899','linear-gradient(135deg,#fbbf24,#ec4899)','rgba(251,191,36,.25)','rgba(236,72,153,.7)','border-radius:20px'],
  'theme-pascoa'      => ['#1a1520','#0f0a15','#f472b6','#60a5fa','linear-gradient(135deg,#f472b6,#60a5fa)','rgba(244,114,182,.25)','rgba(96,165,250,.7)','border-radius:20px'],
  'theme-festajunina' => ['#1a0a0a','#1f1010','#dc2626','#fbbf24','linear-gradient(135deg,#dc2626,#fbbf24)','rgba(220,38,38,.25)','rgba(251,191,36,.7)','border-radius:20px'],
  'theme-dcriancas'   => ['#1a1525','#1f1a2a','#fbbf24','#60a5fa','linear-gradient(135deg,#fbbf24,#60a5fa)','rgba(251,191,36,.25)','rgba(96,165,250,.7)','border-radius:20px'],
  'theme-dentista'    => ['#0a0f1a','#111827','#3b82f6','#22d3ee','linear-gradient(135deg,#3b82f6,#22d3ee)','rgba(59,130,246,.25)','rgba(34,211,238,.7)','border-radius:20px'],
  'theme-trabalhador' => ['#1a1a1a','#1f1f1f','#dc2626','#fbbf24','linear-gradient(135deg,#dc2626,#fbbf24)','rgba(220,38,38,.25)','rgba(251,191,36,.7)','border-radius:20px'],
  'theme-mulheres'    => ['#1a0a1a','#1f1020','#ec4899','#a855f7','linear-gradient(135deg,#ec4899,#a855f7)','rgba(236,72,153,.25)','rgba(168,85,247,.7)','border-radius:20px'],
  'theme-maes'        => ['#1a1520','#1f1a25','#f472b6','#ef4444','linear-gradient(135deg,#f472b6,#ef4444)','rgba(244,114,182,.25)','rgba(239,68,68,.7)','border-radius:20px'],
  'theme-pais'        => ['#1a1a2e','#1f1f38','#3b82f6','#60a5fa','linear-gradient(135deg,#3b82f6,#1d4ed8)','rgba(59,130,246,.25)','rgba(96,165,250,.7)','border-radius:20px'],
];

// Obter cores do tema ativo pelo nome da classe
$tCores = $temasMap[$tClasse] ?? $temasMap['theme-dark'];
[$tBg1,$tBg2,$tAcc1,$tAcc2,$tGrad,$tBdr,$tBdrH,$tShape] = $tCores;

// ── Limpar zips ───────────────────────────────────────────────────────────
foreach (glob(__DIR__ . '/*.zip') ?: [] as $zip) { @unlink($zip); }

// ── Processamento do login ────────────────────────────────────────────────
$alert_message = ''; $alert_type = ''; $show_modal = false;

if (isset($_POST['submit'])) {
    $login = mysqli_real_escape_string($conn, trim($_POST['login'] ?? ''));
    $senha = mysqli_real_escape_string($conn, trim($_POST['senha'] ?? ''));

    // accounts (admin / revendedor)
    $st = mysqli_prepare($conn, "SELECT * FROM accounts WHERE login=? AND senha=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'ss', $login, $senha);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);

    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $_SESSION['iduser'] = $row['id'];
        $_SESSION['login']  = $row['login'];
        $_SESSION['senha']  = $row['senha'];
        if (!empty($_POST['remember'])) {
            setcookie('remember_login', base64_encode($login), time()+86400*30, '/');
            setcookie('remember_senha', base64_encode($senha), time()+86400*30, '/');
        } else {
            setcookie('remember_login','',time()-3600,'/');
            setcookie('remember_senha','',time()-3600,'/');
        }
        $dest = ($row['id']==1) ? 'admin/home.php' : 'home.php';
        echo "<script>window.location.href='$dest';</script>"; exit;
    }

    // ssh_accounts (usuário comum)
    $st2 = mysqli_prepare($conn, "SELECT * FROM ssh_accounts WHERE login=? AND senha=? LIMIT 1");
    mysqli_stmt_bind_param($st2, 'ss', $login, $senha);
    mysqli_stmt_execute($st2);
    $res2 = mysqli_stmt_get_result($st2);

    if ($res2 && mysqli_num_rows($res2) > 0) {
        $ru = mysqli_fetch_assoc($res2);
        $_SESSION['usuario_id']           = $ru['id'];
        $_SESSION['usuario_login']         = $ru['login'];
        $_SESSION['usuario_senha']         = $ru['senha'];
        $_SESSION['usuario_limite']        = $ru['limite'];
        $_SESSION['usuario_expira']        = $ru['expira'];
        $_SESSION['usuario_byid']          = $ru['byid'];
        $_SESSION['usuario_valor_proprio'] = floatval($ru['valormensal'] ?? 0);
        $_SESSION['usuario_suspenso']      = $ru['mainid'] ?? '';
        $_SESSION['usuario_renovacao']     = true;
        $rRev = $conn->query("SELECT * FROM accounts WHERE id=".intval($ru['byid'])." LIMIT 1");
        if ($rRev && $rRev->num_rows > 0) {
            $rev = $rRev->fetch_assoc();
            $_SESSION['revendedor_id']           = $rev['id'];
            $_SESSION['revendedor_nome']          = $rev['nome'];
            $_SESSION['revendedor_email']         = $rev['contato'];
            $_SESSION['revendedor_mp_token']      = $rev['mp_access_token'] ?? '';
            $_SESSION['revendedor_mp_public_key'] = $rev['mp_public_key'] ?? '';
            $_SESSION['valor_padrao']             = floatval($rev['valorusuario'] ?? 0);
        }
        $_SESSION['valor_renovacao'] = ($_SESSION['usuario_valor_proprio'] > 0)
            ? $_SESSION['usuario_valor_proprio'] : ($_SESSION['valor_padrao'] ?? 0);
        if (!empty($_POST['remember'])) {
            setcookie('remember_login', base64_encode($login), time()+86400*30, '/');
            setcookie('remember_senha', base64_encode($senha), time()+86400*30, '/');
        } else {
            setcookie('remember_login','',time()-3600,'/');
            setcookie('remember_senha','',time()-3600,'/');
        }
        echo "<script>window.location.href='usuario/index.php';</script>"; exit;
    }

    $alert_message = 'Login ou Senha Incorretos!';
    $alert_type    = 'error';
    $show_modal    = true;
}

$session_expired = !empty($_GET['expired']);
if ($session_expired) {
    $alert_message = 'Sua sessão expirou por inatividade. Faça login novamente.';
    $alert_type    = 'expired';
    $show_modal    = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
<title><?= htmlspecialchars($nomepainel) ?> - Login</title>
<link rel="shortcut icon" href="<?= htmlspecialchars($icon) ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars($icon) ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="AegisCore/temas_visual.css">
    <?php echo getFundoPersonalizadoCSS($conn, $temaLogin); ?>
<style>
:root {
    --bg1  : <?= $tBg1  ?>;
    --bg2  : <?= $tBg2  ?>;
    --acc1 : <?= $tAcc1 ?>;
    --acc2 : <?= $tAcc2 ?>;
    --grad : <?= $tGrad ?>;
    --bdr  : <?= $tBdr  ?>;
    --bdrh : <?= $tBdrH ?>;
    --bdr-h: <?= $tBdrH ?>;
    --shape: <?= preg_replace('/^border-radius:\s*/', '', $tShape) ?>;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

body {
    font-family:'Poppins',sans-serif;
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    padding:20px;
    background: radial-gradient(ellipse at 50% 0%, rgba(99,102,241,0.15) 0%, #0f172a 70%);
    /* temas_visual.css sobrescreve com body.theme-* { background: ... !important } */
}

.login-container{width:100%;max-width:440px;animation:fadeUp .55s ease both;position:relative;z-index:1}
@keyframes fadeUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}

/* ── CARD ─────────────────────────────────────────────────────────────── */
.login-card {
    padding:48px 38px 40px;
    position:relative;overflow:hidden;
}
/* Fundo SÓLIDO — especificidade body[class] .login-card = (0,2,1)
   igual a body.theme-* .login-card do temas_visual.css.
   Como este <style> vem DEPOIS do <link> temas_visual.css,
   ganha pelo cascade order quando ambos usam !important. */
body[class] .login-card {
    background: var(--bg2, #1a1a2e) !important;
    border: 1px solid var(--bdr, rgba(99,102,241,.25)) !important;
    border-radius: var(--shape, 22px) !important;
    box-shadow: 0 8px 32px rgba(0,0,0,.4) !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
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

/* ── LOGO ─────────────────────────────────────────────────────────────── */
.logo-login{
    display:block;max-width:155px;height:auto;
    margin:0 auto 24px;position:relative;z-index:1;
    animation:floatL 3s ease-in-out infinite;
    filter:drop-shadow(0 4px 12px rgba(0,0,0,.4));
}
@keyframes floatL{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}

/* ── TÍTULOS ──────────────────────────────────────────────────────────── */
.login-title{
    text-align:center;font-size:26px;font-weight:700;margin-bottom:6px;
    background:var(--grad);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    position:relative;z-index:1;
}
.login-subtitle{
    text-align:center;color:rgba(255,255,255,.4);
    font-size:13px;margin-bottom:28px;position:relative;z-index:1;
}

/* ── INPUTS ───────────────────────────────────────────────────────────── */
.input-group{position:relative;margin-bottom:20px;z-index:1}
.input-icon{
    position:absolute;left:0;top:50%;transform:translateY(-50%);
    width:46px;height:46px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:19px;z-index:2;transition:transform .25s;
    box-shadow:0 4px 10px rgba(0,0,0,.25);
}
.icon-user{background:var(--grad);color:#fff}
.icon-password{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.input-group:focus-within .input-icon{transform:translateY(-50%) scale(1.1)}

.input-group input{
    width:100%;padding:14px 16px 14px 60px;
    background:rgba(255,255,255,.05);
    border:1.5px solid var(--bdr);border-radius:50px;
    font-size:15px;font-family:'Poppins',sans-serif;color:#fff;
    transition:all .25s;
}
.input-group input::placeholder{color:rgba(255,255,255,.28)}
.input-group input:focus{
    outline:none;border-color:var(--bdr-h, var(--bdrh));
    background:rgba(255,255,255,.08);
    box-shadow:0 0 0 3px var(--bdr);
}

/* ── LEMBRAR ──────────────────────────────────────────────────────────── */
.remember-me{
    display:flex;align-items:center;gap:10px;
    margin-bottom:22px;padding-left:4px;position:relative;z-index:1;
}
.remember-me input[type=checkbox]{width:17px;height:17px;cursor:pointer;accent-color:var(--acc1)}
.remember-me label{color:rgba(255,255,255,.55);font-size:13px;cursor:pointer;transition:color .2s}
.remember-me label:hover{color:rgba(255,255,255,.9)}

/* ── BOTÃO LOGIN ──────────────────────────────────────────────────────── */
.btn-login{
    width:100%;padding:14px;background:var(--grad);
    border:none;border-radius:50px;
    color:#fff;font-size:16px;font-weight:600;font-family:'Poppins',sans-serif;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;
    position:relative;overflow:hidden;z-index:1;
    transition:transform .25s,box-shadow .25s,filter .25s;
    box-shadow:0 8px 22px rgba(0,0,0,.35);
}
.btn-login::before{
    content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent);
    transition:left .5s;z-index:0;
}
.btn-login:hover::before{left:100%}
.btn-login:hover{transform:translateY(-3px);filter:brightness(1.1);box-shadow:0 14px 30px rgba(0,0,0,.45)}
.btn-login:active{transform:translateY(1px) scale(.98)}
.btn-login .btn-text,.btn-login .btn-icon{position:relative;z-index:1}
.btn-login .btn-icon{font-size:19px;transition:transform .25s}
.btn-login:hover .btn-icon{transform:translateX(4px)}

.ripple{
    position:absolute;border-radius:50%;
    background:rgba(255,255,255,.55);
    transform:scale(0);animation:ripA .6s linear;
    pointer-events:none;z-index:2;
}
@keyframes ripA{to{transform:scale(4);opacity:0}}

.btn-login.loading{pointer-events:none}
.btn-login.loading .btn-text,.btn-login.loading .btn-icon{opacity:0}
.btn-login.loading::after{
    content:'';position:absolute;
    width:22px;height:22px;top:50%;left:50%;margin:-11px 0 0 -11px;
    border:3px solid rgba(255,255,255,.3);border-top-color:#fff;
    border-radius:50%;animation:spin .8s linear infinite;z-index:3;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── MODAIS ───────────────────────────────────────────────────────────── */
.modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.82);
    display:none;align-items:center;justify-content:center;
    z-index:9999;backdrop-filter:blur(8px);
}
.modal-overlay.show{display:flex}
.modal-box{
    max-width:400px;width:90%;
    background:linear-gradient(145deg,#1e293b,#0f172a);
    border-radius:20px;overflow:hidden;
    border:1px solid rgba(255,255,255,.1);
    box-shadow:0 25px 55px rgba(0,0,0,.55);
    animation:mIn .3s ease;
}
@keyframes mIn{from{opacity:0;transform:translateY(-18px) scale(.96)}to{opacity:1;transform:none}}
.mhdr{padding:16px 22px;display:flex;align-items:center;justify-content:space-between}
.mhdr.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.mhdr.warn{background:linear-gradient(135deg,#f59e0b,#d97706)}
.mhdr h5{margin:0;color:#fff;font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px}
.mx{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;opacity:.75;
    width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:.2s}
.mx:hover{opacity:1;background:rgba(255,255,255,.15)}
.mbdy{padding:26px 22px;color:#fff;text-align:center}
.mico i{font-size:62px;animation:icp .4s ease;margin-bottom:12px;display:block}
@keyframes icp{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}
.mico .bx-error-circle{color:#dc2626}
.mico .bx-time-five{color:#f59e0b}
.mttl{
    font-size:20px;font-weight:700;margin-bottom:8px;
    background:var(--grad);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.mmsg{color:rgba(255,255,255,.6);font-size:13px;line-height:1.6}
.mftr{border-top:1px solid rgba(255,255,255,.08);padding:12px 22px;display:flex;justify-content:center}
.btnm{
    padding:9px 24px;border:none;border-radius:50px;
    font-weight:600;font-size:13px;cursor:pointer;
    display:inline-flex;align-items:center;gap:7px;
    font-family:inherit;transition:all .2s;
}
.btnm.red{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff}
.btnm.red:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(220,38,38,.35)}
.btnm.yel{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.btnm.yel:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(245,158,11,.35)}

@media(max-width:480px){
    .login-card{padding:30px 20px}
    .login-title{font-size:22px}
    .logo-login{max-width:120px}
    .input-group input{font-size:14px;padding:12px 15px 12px 56px}
    .input-icon{width:42px;height:42px;font-size:17px}
    .btn-login{font-size:15px;padding:13px}
}
</style>
</head>
<body class="<?= htmlspecialchars($tClasse) ?>">

<div class="login-container">
  <div class="login-card">
    <?php if(!empty($logo)): ?>
    <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($nomepainel) ?>" class="logo-login">
    <?php endif; ?>
    <h2 class="login-title">Bem-vindo!</h2>
    <p class="login-subtitle">Entre com suas credenciais para acessar</p>

    <form method="POST" action="index.php" id="loginForm">
      <div class="input-group">
        <div class="input-icon icon-user"><i class='bx bx-user'></i></div>
        <input type="text" name="login" placeholder="Usuário" required
               value="<?= htmlspecialchars($saved_login) ?>" autocomplete="username">
      </div>
      <div class="input-group">
        <div class="input-icon icon-password"><i class='bx bx-lock-alt'></i></div>
        <input type="password" name="senha" placeholder="Senha" required
               value="<?= htmlspecialchars($saved_senha) ?>" autocomplete="current-password">
      </div>
      <div class="remember-me">
        <input type="checkbox" name="remember" id="remember"
               <?= !empty($_COOKIE['remember_login']) ? 'checked':'' ?>>
        <label for="remember">Lembrar-me neste dispositivo</label>
      </div>
      <button type="submit" name="submit" class="btn-login" id="btnLogin">
        <i class='bx bx-log-in-circle btn-icon'></i>
        <span class="btn-text">Entrar no Painel</span>
      </button>
    </form>
  </div>
</div>

<!-- MODAL ERRO -->
<div id="mErro" class="modal-overlay <?= ($show_modal&&$alert_type==='error')?'show':'' ?>">
  <div class="modal-box">
    <div class="mhdr err">
      <h5><i class='bx bx-error-circle'></i> Autenticação</h5>
      <button class="mx" onclick="closeM('mErro')"><i class='bx bx-x'></i></button>
    </div>
    <div class="mbdy">
      <div class="mico"><i class='bx bx-error-circle'></i></div>
      <div class="mttl">Falha no Login!</div>
      <p class="mmsg"><?= htmlspecialchars($alert_message) ?></p>
    </div>
    <div class="mftr">
      <button class="btnm red" onclick="closeM('mErro')"><i class='bx bx-refresh'></i> Tentar novamente</button>
    </div>
  </div>
</div>

<!-- MODAL EXPIRADO -->
<div id="mExp" class="modal-overlay <?= ($show_modal&&$alert_type==='expired')?'show':'' ?>">
  <div class="modal-box">
    <div class="mhdr warn">
      <h5><i class='bx bx-time-five'></i> Sessão Expirada</h5>
      <button class="mx" onclick="closeM('mExp')"><i class='bx bx-x'></i></button>
    </div>
    <div class="mbdy">
      <div class="mico"><i class='bx bx-time-five'></i></div>
      <div class="mttl">Sessão Expirada!</div>
      <p class="mmsg"><?= htmlspecialchars($alert_message) ?></p>
      <p class="mmsg" style="font-size:11px;margin-top:6px;opacity:.55">Encerrada após 20 min de inatividade.</p>
    </div>
    <div class="mftr">
      <button class="btnm yel" onclick="closeM('mExp')"><i class='bx bx-log-in'></i> Entrar</button>
    </div>
  </div>
</div>


<script>
function closeM(id){
    document.getElementById(id).classList.remove('show');
    if(window.history&&window.history.replaceState)
        window.history.replaceState({},'',location.pathname);
}
document.querySelectorAll('.modal-overlay').forEach(function(m){
    m.addEventListener('click',function(e){if(e.target===this)closeM(this.id);});
});
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        ['mErro','mExp'].forEach(function(id){
            var el=document.getElementById(id);
            if(el&&el.classList.contains('show'))closeM(id);
        });
    }
});

var btnL=document.getElementById('btnLogin');
btnL.addEventListener('click',function(e){
    var r=document.createElement('span');
    r.className='ripple';
    var rect=this.getBoundingClientRect();
    var s=Math.max(rect.width,rect.height);
    r.style.cssText='width:'+s+'px;height:'+s+'px;left:'+(e.clientX-rect.left-s/2)+'px;top:'+(e.clientY-rect.top-s/2)+'px';
    this.appendChild(r);
    setTimeout(function(){r.remove();},650);
});

var sub=false;
document.getElementById('loginForm').addEventListener('submit',function(e){
    if(sub){e.preventDefault();return;}
    sub=true;
    btnL.classList.add('loading');
});

<?php if($session_expired): ?>
document.addEventListener('DOMContentLoaded',function(){
    document.getElementById('mExp').classList.add('show');
});
<?php endif; ?>

fetch('admin/notific.php',{method:'POST'}).catch(function(){});
</script>
</body>
</html>