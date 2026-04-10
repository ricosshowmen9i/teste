<?php
error_reporting(0);
session_start();

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    echo "<script>alert('Sessão expirada por inatividade!');</script>";
    session_unset();
    session_destroy();
    echo "<script>setTimeout(function(){ window.location.href='../index.php'; }, 500);</script>";
    exit();
}
$_SESSION['last_activity'] = time();
date_default_timezone_set('America/Sao_Paulo');

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

if (!isset($_SESSION['login'])) {
    header('Location: ../index.php');
    exit();
}

include_once("../AegisCore/conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// ── TEMAS: carregar tema global (somente leitura para revendedores) ──
include_once("../AegisCore/temas.php");
$_h2_tema = initTemas($conn);

$sql = "SELECT * FROM configs";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $nomepainel   = $row['nomepainel'];
    $logo         = $row['logo'];
    $icon         = $row['icon'];
    $csspersonali = $row['corfundologo'];
}

if ($_SESSION['login'] == 'admin') {
    header('Location: ../admin/home.php');
    exit();
}

if (!isset($_SESSION['login']) && !isset($_SESSION['senha'])) {
    session_destroy();
    header('location: ../index.php');
    exit();
}

if (isset($_POST['voltaradmin']) && isset($_SESSION['admin564154156'])) {
    $sqladmin    = "SELECT * FROM accounts WHERE id='1'";
    $resultadmin = $conn->query($sqladmin);
    $rowadmin    = $resultadmin->fetch_assoc();
    $_SESSION['login']  = $rowadmin['login'];
    $_SESSION['senha']  = $rowadmin['senha'];
    $_SESSION['iduser'] = $rowadmin['id'];
    echo "<script>window.location.href='../admin/home.php';</script>";
    exit();
}

// Substituições
$sql_cfg = "SELECT * FROM configs";
$result_cfg = $conn->query($sql_cfg);
$textopersonali = '';
if ($result_cfg && $result_cfg->num_rows > 0) {
    $row_cfg = $result_cfg->fetch_assoc();
    $csspersonali    = $row_cfg['corfundologo'] ?? '';
    $textopersonali  = $row_cfg['textoedit']    ?? '';
}
$linhas = explode("\n", $textopersonali);
$substituicoes = [];
foreach ($linhas as $linha) {
    $par = explode("=", $linha);
    if (count($par) === 2) {
        $substituicoes[] = ['original' => trim($par[0]), 'substituto' => trim($par[1])];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?php echo $nomepainel; ?> - Painel Revendedor</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="../app-assets/sweetalert.min.js"></script>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <?php echo getFundoPersonalizadoCSS($conn, $_h2_tema); ?>
    <style>
        /* Base sem filtro de cor */

        /* ── RESET GLOBAL ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            font-family: 'Inter', sans-serif !important;
            background: #0f172a !important;
            color: white !important;
            min-height: 100vh;
        }
/* Barra de rolagem invisível — scroll funciona normalmente */
.side-menu {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.side-menu::-webkit-scrollbar {
    width: 0;
    height: 0;
    background: transparent;
}
        /* ── MENU LATERAL (220px fixo) ── */
        .side-menu {
            position: fixed; top: 0; left: 0;
            width: 220px; height: 100vh;
            background: linear-gradient(180deg, #1a1f3a 0%, #0f1429 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
            z-index: 1000;
            display: flex; flex-direction: column;
            transition: transform 0.3s ease;
            overflow-y: auto;
            border-radius: 0 20px 20px 0;
        }
        .side-menu-logo { padding: 16px 16px 12px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .side-menu-logo img { max-width: 160px; max-height: 88px; object-fit: contain; }
        .side-menu-user { padding: 10px 14px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.03); }
        .user-av { width: 32px; height: 32px; background: linear-gradient(135deg,#4158D0,#C850C0); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: white; flex-shrink: 0; }
        .user-nm { font-size: 12px; font-weight: 600; color: white; }
        .user-rl { font-size: 10px; color: rgba(255,255,255,0.3); }

        .side-nav { flex: 1; padding: 6px 8px; }
        .nav-sec-title { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.2); text-transform: uppercase; letter-spacing: 1px; padding: 8px 8px 3px; }
        .nav-link-main { display: flex; align-items: center; gap: 8px; padding: 7px 10px; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 12px; font-weight: 500; border-radius: 10px; transition: all 0.2s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; margin-bottom: 1px; }
        .nav-link-main:hover { background: rgba(255,255,255,0.07); color: white; }
        .nav-link-main.active { background: rgba(65,88,208,0.25); color: white; }
        .nav-icon { width: 26px; height: 26px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .nav-text { flex: 1; }
        .nav-arrow { font-size: 13px; color: rgba(255,255,255,0.25); transition: transform 0.25s; }
        .nav-link-main.open .nav-arrow { transform: rotate(90deg); }
        .nav-sub { display: none; padding: 1px 0 4px 0; }
        .nav-sub.open { display: block; }
        .nav-sub a { display: flex; align-items: center; gap: 7px; padding: 6px 10px 6px 14px; color: rgba(255,255,255,0.45); text-decoration: none; font-size: 11px; font-weight: 500; border-radius: 8px; transition: all 0.2s; margin-bottom: 1px; }
        .nav-sub a:hover { color: white; background: rgba(255,255,255,0.05); }
        .sub-icon { width: 22px; height: 22px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }

        /* Cores ícones nav */
        .ni-home  { background:rgba(65,88,208,0.25);  color:#818cf8; }
        .ni-user  { background:rgba(200,80,192,0.25); color:#e879f9; }
        .ni-store { background:rgba(16,185,129,0.25); color:#34d399; }
        .ni-pay   { background:rgba(245,158,11,0.25); color:#fbbf24; }
        .ni-log   { background:rgba(249,115,22,0.25); color:#fb923c; }
        .ni-whats { background:rgba(59,130,246,0.25); color:#60a5fa; }
        .ni-cfg   { background:rgba(139,92,246,0.25); color:#a78bfa; }
        .ni-exit  { background:rgba(239,68,68,0.25);  color:#f87171; }

        /* Cores ícones sub */
        .si-1  { background:rgba(65,88,208,0.2);  color:#818cf8; }
        .si-2  { background:rgba(16,185,129,0.2); color:#34d399; }
        .si-3  { background:rgba(245,158,11,0.2); color:#fbbf24; }
        .si-4  { background:rgba(239,68,68,0.2);  color:#f87171; }
        .si-5  { background:rgba(59,130,246,0.2); color:#60a5fa; }
        .si-6  { background:rgba(139,92,246,0.2); color:#a78bfa; }
        .si-7  { background:rgba(236,72,153,0.2); color:#f472b6; }
        .si-8  { background:rgba(20,184,166,0.2); color:#2dd4bf; }
        .si-9  { background:rgba(249,115,22,0.2); color:#fb923c; }
        .si-10 { background:rgba(6,182,212,0.2);  color:#22d3ee; }

        /* ── TOPO ── */
        .top-header {
            position: fixed; top: 0;
            left: 220px;                        /* começa após o menu */
            right: 0;
            z-index: 900;
            background: rgba(26,31,58,0.97);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            border-radius: 0 0 18px 18px;
            margin: 0 12px;
            height: 54px;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 18px;
        }
        .header-left { display: flex; align-items: center; gap: 8px; }
        .header-center { position: absolute; left: 50%; transform: translateX(-50%); font-size: 11px; color: rgba(255,255,255,0.35); display: flex; align-items: center; gap: 5px; white-space: nowrap; pointer-events: none; }
        .header-center i { color: #a78bfa; }
        .header-right { display: flex; align-items: center; gap: 8px; }
        .header-user { display: flex; align-items: center; gap: 6px; font-size: 12px; color: rgba(255,255,255,0.6); font-weight: 600; }
        .header-av { width: 28px; height: 28px; background: linear-gradient(135deg,#4158D0,#C850C0); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: white; }
        .btn-hd { padding: 5px 12px; border-radius: 16px; font-size: 11px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-rv-h { background: linear-gradient(135deg,#f59e0b,#d97706); color: white; }
        .btn-cp-h { background: linear-gradient(135deg,#10b981,#059669); color: white; }
        .btn-rv-h:hover, .btn-cp-h:hover { transform: translateY(-1px); color: white; }
        .btn-menu-mobile { display: none; background: rgba(65,88,208,0.25); border: 1px solid rgba(65,88,208,0.4); color: #818cf8; border-radius: 10px; padding: 5px 12px; font-size: 12px; font-weight: 700; cursor: pointer; letter-spacing: 0.5px; align-items: center; gap: 5px; }

        /* ── WRAPPER DO CONTEÚDO ──
           Substitui o .content-wrapper do Bootstrap original.
           margin-left = largura do menu (220px)
           padding-top = altura do topo fixo (54px) + folga (12px)
        ── */
        .content-wrapper-new {
            margin-left: 0px;
            padding-top: 66px;      /* 54px topo + 12px folga */
            padding-left: 0;
            padding-right: 0;
            padding-bottom: 40px;
            min-height: 100vh;
            background: #0f172a;
        }

        /* ── BOTÃO VOLTAR ADMIN ── */
        .back-button { position: fixed; bottom: 18px; right: 18px; background: #4158D0; color: white; border-radius: 50%; width: 44px; height: 44px; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.4); z-index: 9999; border: none; cursor: pointer; font-size: 19px; text-decoration: none; }

        /* ── MENU OVERLAY MOBILE ── */
        .menu-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; }
        .menu-overlay.active { display: block; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .side-menu { transform: translateX(-100%); }
            .side-menu.open { transform: translateX(0); }
            .top-header { left: 0; margin: 0 8px; border-radius: 0 0 14px 14px; }
            .content-wrapper-new { margin-left: 0 !important; }
            .btn-menu-mobile { display: inline-flex !important; }
            .btn-hd-hide { display: none !important; }
        }
        @media (max-width: 768px) {
            .header-center { display: none; }
            .content-wrapper-new { padding-left: 0; padding-right: 0; }
        }
    </style>
</head>
<body id="inicialeditor" class="<?php echo htmlspecialchars(getBodyClass($_h2_tema)); ?>">

<div class="menu-overlay" id="menuOverlay"></div>

<?php if (isset($_SESSION['admin564154156'])): ?>
<form method="post" action="header2.php">
    <button type="submit" name="voltaradmin" class="back-button">
        <i class='bx bx-arrow-back'></i>
    </button>
</form>
<?php endif; ?>

<!-- ═══════════ MENU LATERAL ═══════════ -->
<aside class="side-menu" id="sideMenu">
    <div class="side-menu-logo">
        <img src="<?php echo $logo; ?>" alt="logo">
    </div>
   
        </div>
    </div>
    <nav class="side-nav">
        <div class="nav-sec-title">Principal</div>
        <a href="../home.php" class="nav-link-main">
            <span class="nav-icon ni-home"><i class='bx bx-home-alt'></i></span>
            <span class="nav-text">Início</span>
        </a>

        <div class="nav-sec-title">Gerenciar</div>

        <button class="nav-link-main" onclick="toggleSub('sUsers', this)">
            <span class="nav-icon ni-user"><i class='bx bx-user'></i></span>
            <span class="nav-text">Usuários</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sUsers">
            <a href="criarusuario.php"><span class="sub-icon si-1"><i class='bx bx-user-plus'></i></span>Criar Usuário</a>
            <a href="criarteste.php"><span class="sub-icon si-2"><i class='bx bx-test-tube'></i></span>Criar Teste</a>
            <a href="listarusuarios.php"><span class="sub-icon si-3"><i class='bx bx-list-ul'></i></span>Listar Usuários</a>
            <a href="listaexpirados.php"><span class="sub-icon si-4"><i class='bx bx-time'></i></span>Expirados</a>
            
            <a href="deviceid.php"><span class="sub-icon si-10"><i class='bx bx-devices'></i></span>Device ID</a>
        </div>

        <button class="nav-link-main" onclick="toggleSub('sRevenda', this)">
            <span class="nav-icon ni-store"><i class='bx bx-store-alt'></i></span>
            <span class="nav-text">Revendedores</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sRevenda">
            <a href="criarrevenda.php"><span class="sub-icon si-6"><i class='bx bx-user-check'></i></span>Criar Revenda</a>
            <a href="listarrevendedores.php"><span class="sub-icon si-7"><i class='bx bx-group'></i></span>Listar Revendedores</a>
            
        </div>
        <div class="nav-sec-title">Loja</div>
<!-- Loja de Aplicativos -->
<a href="../aplicativos.php" class="nav-link-main">
    <span class="nav-icon ni-store"><i class='bx bxl-play-store' style="color: #10b981;"></i></span>
    <span class="nav-text">Loja de Apps</span>

</a>
<div class="nav-sec-title">Onlines</div>
<a href="onlines.php" class="nav-link-main">
    <span class="nav-icon" style="background: rgba(16,185,129,0.25); color: #10b981;"><i class='bx bx-wifi'></i></span>
    <span class="nav-text">Onlines</span>
</a>
<a href="onlines_revendas.php" class="nav-link-main">
    <span class="nav-icon" style="background: rgba(59,130,246,0.25); color: #3b82f6;"><i class='bx bx-wifi'></i></span>
    <span class="nav-text">Onlines Revendas</span>
</a>
<a href="suspensoes_limite.php" class="nav-link-main">
    <span class="nav-icon" style="background: rgba(239,68,68,0.25); color: #ef4444;"><i class='bx bx-wifi'></i></span>
    <span class="nav-text">Onlines Suspensos</span>
</a>
        <div class="nav-sec-title">Financeiro</div>

        <button class="nav-link-main" onclick="toggleSub('sPag', this)">
            <span class="nav-icon ni-pay"><i class='bx bx-credit-card'></i></span>
            <span class="nav-text">Pagamentos</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sPag">
            <a href="formaspag.php"><span class="sub-icon si-8"><i class='bx bx-cog'></i></span>Configurar</a>
            <a href="listadepag.php"><span class="sub-icon si-9"><i class='bx bx-receipt'></i></span>Listar Pagamentos</a>
            <a href="cupons.php"><span class="sub-icon si-10"><i class='bx bx-purchase-tag'></i></span>Cupons</a>
            <a href="planos_pagamento.php"><span class="sub-icon si-1"><i class='bx bx-dollar-circle'></i></span>Planos</a>
        </div>

        <div class="nav-sec-title">Sistema</div>
        <a href="logs.php" class="nav-link-main">
            <span class="nav-icon ni-log"><i class='bx bx-history'></i></span>
            <span class="nav-text">Logs</span>
        </a>
        <a href="whatsconect.php" class="nav-link-main">
            <span class="nav-icon ni-whats"><i class='bx bxl-whatsapp'></i></span>
            <span class="nav-text">WhatsApp</span>
        </a>
        <a href="editconta.php" class="nav-link-main">
            <span class="nav-icon ni-cfg"><i class='bx bx-cog'></i></span>
            <span class="nav-text">Conta</span>
        </a>
        <a href="../logout.php" class="nav-link-main">
            <span class="nav-icon ni-exit"><i class='bx bx-power-off'></i></span>
            <span class="nav-text">Sair</span>
        </a>
    </nav>
</aside>

<!-- ═══════════ TOPO FIXO ═══════════ -->
<div class="top-header" style="position:fixed;">
    <div class="header-left">
        <button class="btn-menu-mobile" id="mobileMenuBtn">
            <i class='bx bx-menu'></i> MENU
        </button>
    </div>
    <div class="header-center">
        <i class='bx bx-calendar-check'></i>
        Membro desde: <?php echo date('d/m/Y'); ?>
    </div>
    <div class="header-right">
     
        </div>
    </div>
</div>

<!-- ═══════════ CONTEÚDO ═══════════
     Tudo que as páginas colocam após include('header2.php')
     cai automaticamente aqui dentro.
═══════════════════════════════════ -->
<div class="content-wrapper-new">

<script>
// Accordion
function toggleSub(id, btn) {
    var isOpen = document.getElementById(id).classList.contains('open');
    document.querySelectorAll('.nav-sub.open').forEach(function(s){ s.classList.remove('open'); });
    document.querySelectorAll('.nav-link-main.open').forEach(function(b){ b.classList.remove('open'); });
    if (!isOpen) { document.getElementById(id).classList.add('open'); btn.classList.add('open'); }
}
// Mobile menu
var _ov = document.getElementById('menuOverlay');
var _sm = document.getElementById('sideMenu');
document.getElementById('mobileMenuBtn').addEventListener('click', function(){
    _sm.classList.add('open'); _ov.classList.add('active');
});
_ov.addEventListener('click', function(){
    _sm.classList.remove('open'); _ov.classList.remove('active');
});
document.addEventListener('keydown', function(e){
    if (e.key==='Escape'){ _sm.classList.remove('open'); _ov.classList.remove('active'); }
});
document.querySelectorAll('.side-menu a').forEach(function(a){
    a.addEventListener('click', function(){
        if(window.innerWidth<=1024){ _sm.classList.remove('open'); _ov.classList.remove('active'); }
    });
});
// Substituições de texto
window.addEventListener('DOMContentLoaded', function(){
    var s = <?php echo json_encode($substituicoes); ?>;
    function p(el){
        if(el.nodeType===3){ s.forEach(function(x){ el.textContent=el.textContent.replace(x.original,x.substituto); }); }
        else { for(var i=0;i<el.childNodes.length;i++) p(el.childNodes[i]); }
    }
    p(document.getElementById('inicialeditor'));
});
</script>
<!-- Temas: revendedores usam tema global definido pelo admin -->