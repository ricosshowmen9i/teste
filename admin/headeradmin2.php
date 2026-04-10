<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php
    error_reporting(0);
    session_start();

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
        $token_invalido_html_sess = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("sessao");});<\/script>';
    }
    $_SESSION['last_activity'] = time();

    if (!isset($token_invalido_html_sess)) $token_invalido_html_sess = '';

    if(!isset($_SESSION['login'])){
        header('Location: ../index.php');
        exit();
    }

    include_once("../AegisCore/conexao.php");
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    if ($_SESSION['login'] == 'admin') {
    } else {
        $token_invalido_html_sess = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("invalido");});<\/script>';
    }

    $sql = "SELECT * FROM configs WHERE id='1'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $nomepainel   = $row["nomepainel"];
            $logo         = $row["logo"];
            $icon         = $row["icon"];
            $csspersonali = $row["corfundologo"];
        }
    }

    // Token validation
    $telegram = null;
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $telegram = new \Telegram\Bot\Api('6163337935:AAE8uxSRfSkXHthlZtRr-tjpUPxzzxaiUcQ');
    } elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $telegram = new \Telegram\Bot\Api('6163337935:AAE8uxSRfSkXHthlZtRr-tjpUPxzzxaiUcQ');
    }

    if (!file_exists('suspenderrev.php')) {
        $token_invalido_html_sess = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("ausente");});<\/script>';
    } else {
        include_once 'suspenderrev.php';
    }

    $secret_salt   = "AtlasSecurity_2024_#@!";
    $dominio_atual = $_SERVER['HTTP_HOST'];
    $token_sessao  = isset($_SESSION['token']) ? $_SESSION['token'] : '';
    $hash_esperado = hash('sha256', $token_sessao . $secret_salt . $dominio_atual);

    if (
        !isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) ||
        $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado ||
        $_SESSION['tokenatual'] != $token_sessao ||
        (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)
    ) {
        if (function_exists('security')) {
            security();
            if ($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado)
                $token_invalido_html_sess = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("integridade");});<\/script>';
        } else {
            if ($telegram)
                $telegram->sendMessage(['chat_id'=>'2017803306','text'=>"⚠️ BYPASS DETECTADO: O domínio ".$dominio_atual." tentou burlar a segurança do token!"]);
            $_SESSION['token_invalido_'] = true;
            $token_invalido_html_sess = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("bypass");});<\/script>';
        }
    }

    $sqlcfg = "SELECT * FROM configs";
    $rescfg  = $conn->query($sqlcfg);
    $textopersonali = '';
    if ($rescfg->num_rows > 0) {
        $rowcfg = $rescfg->fetch_assoc();
        $textopersonali = $rowcfg["textoedit"] ?? '';
    }
    $linhas = explode("\n", $textopersonali);
    $substituicoes = [];
    foreach ($linhas as $linha) {
        $par = explode("=", $linha);
        if (count($par) === 2)
            $substituicoes[] = ['original'=>trim($par[0]),'substituto'=>trim($par[1])];
    }
    // Inicializar sistema de temas v9 — Global (admin controla)
    include_once("../AegisCore/temas.php");
    try {
        $temaAtual = initTemas($conn);
        processarTemaPOST($conn);
    } catch (\Throwable $e) {
        // Fallback seguro se qualquer função de temas falhar (ex: servidor sem ext-calendar)
        $temaAtual = ['id'=>1,'nome'=>'Dark','classe'=>'theme-dark','preview'=>'#6366f1','origem'=>'fallback'];
        $_SESSION['tema_atual'] = $temaAtual;
    }
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomepainel; ?> - Painel Administrativo</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>body{opacity:0;transition:opacity .15s ease;}</style>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css?v=<?php echo time(); ?>" onload="document.body?document.body.style.opacity='1':document.addEventListener('DOMContentLoaded',function(){document.body.style.opacity='1';});">
    <?php echo getFundoPersonalizadoCSS($conn, $temaAtual); ?>
    <style>

        @keyframes fadeInOv { from{opacity:0} to{opacity:1} }
        @keyframes slideUpM { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
        @keyframes pulseOnline {
            0%  {box-shadow:0 0 0 0 rgba(16,185,129,.4);}
            50% {box-shadow:0 0 0 8px rgba(59,130,246,.15);}
            100%{box-shadow:0 0 0 0 rgba(16,185,129,0);}
        }

        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;color:white;}
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
        /* ===== MENU LATERAL ===== */
        .side-menu{position:fixed;top:0;left:0;width:220px;height:100vh;background:linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%);border-right:1px solid rgba(255,255,255,.06);z-index:1000;display:flex;flex-direction:column;transition:transform .3s ease;overflow-y:auto;border-radius:0 20px 20px 0;}
        .side-menu-logo{padding:16px 16px 12px;border-bottom:1px solid rgba(255,255,255,.06);text-align:center;}
        .side-menu-logo img{max-width:160px;max-height:88px;object-fit:contain;}
        .side-nav{flex:1;padding:6px 8px;}
        .nav-sec-title{font-size:9px;font-weight:700;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:1px;padding:8px 8px 3px;}
        .nav-link-main{display:flex;align-items:center;gap:8px;padding:7px 10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:12px;font-weight:500;border-radius:10px;transition:all .2s;cursor:pointer;border:none;background:none;width:100%;text-align:left;margin-bottom:1px;}
        .nav-link-main:hover{background:rgba(255,255,255,.07);color:white;}
        .nav-link-main.active{background:rgba(65,88,208,.25);color:white;}
        .nav-icon{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
        .nav-text{flex:1;}
        .nav-arrow{font-size:13px;color:rgba(255,255,255,.25);transition:transform .25s;}
        .nav-link-main.open .nav-arrow{transform:rotate(90deg);}
        .nav-sub{display:none;padding:1px 0 4px 0;}
        .nav-sub.open{display:block;}
        .nav-sub a{display:flex;align-items:center;gap:7px;padding:6px 10px 6px 14px;color:rgba(255,255,255,.45);text-decoration:none;font-size:11px;font-weight:500;border-radius:8px;transition:all .2s;margin-bottom:1px;}
        .nav-sub a:hover{color:white;background:rgba(255,255,255,.05);}
        .sub-icon{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}

        .ni-home{background:rgba(65,88,208,.25);color:#818cf8;}
        .ni-user{background:rgba(200,80,192,.25);color:#e879f9;}
        .ni-store{background:rgba(16,185,129,.25);color:#34d399;}
        .ni-pay{background:rgba(245,158,11,.25);color:#fbbf24;}
        .ni-srv{background:rgba(59,130,246,.25);color:#60a5fa;}
        .ni-log{background:rgba(249,115,22,.25);color:#fb923c;}
        .ni-cfg{background:rgba(139,92,246,.25);color:#a78bfa;}
        .ni-wp{background:rgba(34,197,94,.25);color:#4ade80;}
        .ni-exit{background:rgba(239,68,68,.25);color:#f87171;}
        .ni-grp{background:rgba(16,185,129,.25);color:#34d399;}

        .si-1{background:rgba(65,88,208,.2);color:#818cf8;}
        .si-2{background:rgba(16,185,129,.2);color:#34d399;}
        .si-3{background:rgba(245,158,11,.2);color:#fbbf24;}
        .si-4{background:rgba(239,68,68,.2);color:#f87171;}
        .si-5{background:rgba(59,130,246,.2);color:#60a5fa;}
        .si-6{background:rgba(139,92,246,.2);color:#a78bfa;}
        .si-7{background:rgba(236,72,153,.2);color:#f472b6;}
        .si-8{background:rgba(20,184,166,.2);color:#2dd4bf;}
        .si-9{background:rgba(249,115,22,.2);color:#fb923c;}
        .si-10{background:rgba(6,182,212,.2);color:#22d3ee;}

        /* ===== WRAP ===== */
        .main-wrap{margin-left:220px;min-height:100vh;}
        .top-header{position:sticky;top:0;z-index:900;background:rgba(26,31,58,.97);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.07);border-radius:0 0 18px 18px;margin:0 12px;padding:0 18px;height:54px;display:flex;align-items:center;justify-content:space-between;}
        .header-center{position:absolute;left:50%;transform:translateX(-50%);font-size:11px;color:rgba(255,255,255,.35);display:flex;align-items:center;gap:5px;white-space:nowrap;}
        .header-center i{color:#a78bfa;}
        .btn-menu-mobile{display:none;background:rgba(65,88,208,.25);border:1px solid rgba(65,88,208,.4);color:#818cf8;border-radius:10px;padding:5px 12px;font-size:12px;font-weight:700;cursor:pointer;align-items:center;gap:5px;}
        .page-body{padding:16px 16px 40px;}

        /* ===== OVERLAY MOBILE ===== */
        .menu-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;}
        .menu-overlay.active{display:block;}

        @media(max-width:1024px){
            .side-menu{transform:translateX(-100%);}
            .side-menu.open{transform:translateX(0);}
            .main-wrap{margin-left:0!important;}
            .btn-menu-mobile{display:inline-flex!important;}
        }
        @media(max-width:768px){
            .header-center{display:none;}
        }
    </style>
</head>
<body id="inicialeditor2" class="<?php echo htmlspecialchars(getBodyClass($temaAtual)); ?>">

<?php echo $token_invalido_html_sess; ?>

<div class="menu-overlay" id="menuOverlay2"></div>

<!-- ===== MENU LATERAL ===== -->
<aside class="side-menu" id="sideMenu2">
    <div class="side-menu-logo"><img src="<?php echo $logo; ?>" alt="logo"></div>
    <nav class="side-nav">
        <div class="nav-sec-title">Principal</div>
        <a href="home.php" class="nav-link-main active">
            <span class="nav-icon ni-home"><i class='bx bx-home-alt'></i></span>
            <span class="nav-text">Página Inicial</span>
        </a>

        <div class="nav-sec-title">Usuários</div>
        <button class="nav-link-main" onclick="toggleSub2('sUsers2',this)">
            <span class="nav-icon ni-user"><i class='bx bx-user-circle'></i></span>
            <span class="nav-text">Gerenciar Usuários</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sUsers2">
            <a href="criarusuario.php"><span class="sub-icon si-1"><i class='bx bx-user-plus'></i></span>Criar Usuário</a>
            <a href="criarteste.php"><span class="sub-icon si-2"><i class='bx bx-test-tube'></i></span>Criar Teste</a>
            <a href="listarusuarios.php"><span class="sub-icon si-3"><i class='bx bx-list-ul'></i></span>Lista de Usuários</a>
            <a href="listaglobaluser.php"><span class="sub-icon si-5"><i class='bx bx-globe'></i></span>Todos Usuários</a>
            <a href="listaexpirados.php"><span class="sub-icon si-4"><i class='bx bx-time'></i></span>Expirados</a>
            <a href="limiter.php"><span class="sub-icon si-6"><i class='bx bx-shield-quarter'></i></span>Limiter</a>
        </div>

        <button class="nav-link-main" onclick="toggleSub2('sRevenda2',this)">
            <span class="nav-icon ni-store"><i class='bx bx-store-alt'></i></span>
            <span class="nav-text">Revendedores</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sRevenda2">
            <a href="criarrevenda.php"><span class="sub-icon si-7"><i class='bx bx-cart-add'></i></span>Criar Revenda</a>
            <a href="listarrevendedores.php"><span class="sub-icon si-6"><i class='bx bx-group'></i></span>Listar Revendedores</a>
            <a href="listartodosrevendedores.php"><span class="sub-icon si-10"><i class='bx bx-network-chart'></i></span>Todos Revendedores</a>
        </div>

<div class="nav-sec-title">Loja</div>
    <a href="gerenciar_aplicativos.php" class="nav-link-main">
            <span class="nav-icon ni-store"><i class='bx bxl-play-store' style="color: #10b981;"></i></span>
            <span class="nav-text">Gerenciar Loja</span>
        </a>
        <a href="/aplicativos.php" class="nav-link-main">
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
            <span class="nav-text">Limite Suspensos</span>
        </a>
        <div class="nav-sec-title">Financeiro</div>
        <button class="nav-link-main" onclick="toggleSub2('sPag2',this)">
            <span class="nav-icon ni-pay"><i class='bx bx-wallet'></i></span>
            <span class="nav-text">Pagamentos</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sPag2">
            <a href="formaspag.php"><span class="sub-icon si-8"><i class='bx bx-credit-card'></i></span>Configurar</a>
            <a href="listadepag.php"><span class="sub-icon si-9"><i class='bx bx-receipt'></i></span>Listar Pagamentos</a>
            <a href="listadetodospag.php"><span class="sub-icon si-3"><i class='bx bx-spreadsheet'></i></span>Todos Pagamentos</a>
            <a href="links_venda.php"><span class="sub-icon si-10"><i class='bx bx-link'></i></span>Link de Vendas</a>
            <a href="planos.php"><span class="sub-icon si-1"><i class='bx bx-package'></i></span>Planos</a>
            <a href="cupons.php"><span class="sub-icon si-7"><i class='bx bx-purchase-tag-alt'></i></span>Cupons</a>
        </div>

        <div class="nav-sec-title">Sistema</div>
        <a href="servidores.php" class="nav-link-main">
            <span class="nav-icon ni-srv"><i class='bx bx-server'></i></span>
            <span class="nav-text">Servidores</span>
        </a>
        <a href="logs.php" class="nav-link-main">
            <span class="nav-icon ni-log"><i class='bx bx-detail'></i></span>
            <span class="nav-text">Logs</span>
        </a>
        <div class="nav-sec-title">Configurações</div>
        <a href="editconta.php" class="nav-link-main">
            <span class="nav-icon ni-cfg"><i class='bx bx-id-card'></i></span>
            <span class="nav-text">Conta</span>
        </a>
        <a href="configpainel.php" class="nav-link-main">
            <span class="nav-icon" style="background:rgba(200,80,192,.25);color:#e879f9"><i class='bx bx-paint'></i></span>
            <span class="nav-text">Editar Painel</span>
        </a>
        <a href="editorpainel.php" class="nav-link-main">
            <span class="nav-icon" style="background:rgba(245,158,11,.25);color:#fbbf24"><i class='bx bx-brush'></i></span>
            <span class="nav-text">Editor CSS</span>
        </a>
        <a href="whatsconect.php" class="nav-link-main">
            <span class="nav-icon ni-wp"><i class='bx bxl-whatsapp'></i></span>
            <span class="nav-text">WhatsApp</span>
        </a>
        <a href="checkuserconf.php" class="nav-link-main">
            <span class="nav-icon" style="background:rgba(6,182,212,.25);color:#22d3ee"><i class='bx bx-search-alt'></i></span>
            <span class="nav-text">CheckUser</span>
        </a>
        <a href="#" class="nav-link-main" onclick="openThemeModal();return false;">
            <span class="nav-icon" style="background:linear-gradient(135deg,#4158D0,#C850C0);color:#fff"><i class='bx bx-palette'></i></span>
            <span class="nav-text">Temas</span>
        </a>
        <a href="../logout.php" class="nav-link-main">
            <span class="nav-icon ni-exit"><i class='bx bx-power-off'></i></span>
            <span class="nav-text">Sair</span>
        </a>
    </nav>
</aside>

<!-- ===== CONTEÚDO ===== -->
<div class="main-wrap" id="mainWrap2">
    <div class="top-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <button class="btn-menu-mobile" id="mobileMenuBtn2"><i class='bx bx-menu'></i> MENU</button>
        </div>
        <div class="header-center" id="inicialeditor"><i class='bx bx-crown'></i>Bem-vindo ao <?php echo $nomepainel; ?></div>
        <div></div>
    </div>
    <div class="page-body">
<!-- conteúdo das páginas é injetado após este include -->

<script>
// ============================================================
// MODAL UNIFICADO — idêntico ao headeradmin.php
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
    bx.style.cssText='background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:28px;padding:36px 32px;max-width:440px;width:90%;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.6);text-align:center;animation:slideUpM .25s ease;font-family:Inter,sans-serif;';
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
    return result;
}
window.swal=function(o,t,i){
    if(typeof o==='string')return showModal({title:o,text:t||'',icon:i||'info',buttons:true});
    return showModal(o);
};

function showTokenModal(motivo){
    var msgs={
        sessao:     {title:'Sessão Expirada!',               text:'Sua sessão expirou por inatividade.<br><br><b style="color:#fbbf24;">Faça login novamente</b> para continuar.'},
        bypass:     {title:'Bypass Detectado!',              text:'Tentativa de burlar a segurança foi identificada.<br><br><b style="color:#f87171;">Entre em contato com o suporte</b> para regularizar sua situação.'},
        ausente:    {title:'Arquivo de Segurança Ausente!',  text:'O arquivo de validação não foi encontrado.<br><br><b style="color:#f87171;">Entre em contato com o suporte</b> para corrigir.'},
        invalido:   {title:'Token Inválido!',                text:'Seu token é inválido ou expirou.<br><br><b style="color:#fbbf24;">Entre em contato com o administrador</b> para renovar.'},
        integridade:{title:'Erro de Integridade!',           text:'Falha na verificação de integridade de segurança.<br><br><b style="color:#f87171;">Entre em contato com o suporte</b> imediatamente.'},
    };
    var m=msgs[motivo]||msgs.invalido;
    showModal({
        title:m.title,
        text:m.text+'<br><br><small style="color:rgba(255,255,255,.3);">Domínio: '+window.location.hostname+'</small>',
        icon:'token',buttons:['Fechar','Ir para Login'],
        onConfirm:function(c){if(c)window.location.href='../index.php';}
    });
}

// MENU
function toggleSub2(id,btn){
    var open=document.getElementById(id).classList.contains('open');
    document.querySelectorAll('.nav-sub.open').forEach(function(s){s.classList.remove('open');});
    document.querySelectorAll('.nav-link-main.open').forEach(function(b){b.classList.remove('open');});
    if(!open){document.getElementById(id).classList.add('open');btn.classList.add('open');}
}
var ov2=document.getElementById('menuOverlay2'),sm2=document.getElementById('sideMenu2');
document.getElementById('mobileMenuBtn2')?.addEventListener('click',function(){sm2.classList.add('open');ov2.classList.add('active');});
ov2?.addEventListener('click',function(){sm2.classList.remove('open');ov2.classList.remove('active');});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){sm2.classList.remove('open');ov2.classList.remove('active');}});
window.addEventListener('resize',function(){if(window.innerWidth>=1024){sm2.classList.remove('open');ov2.classList.remove('active');}});
var links2=sm2?sm2.querySelectorAll('a[href]:not([href="#"])'):[];
for(var i=0;i<links2.length;i++){
    links2[i].addEventListener('click',function(){
        if(window.innerWidth<1024){setTimeout(function(){sm2.classList.remove('open');ov2.classList.remove('active');},100);}
    });
}

// AUTO-PING
setInterval(function(){fetch('suspenderauto.php',{method:'POST'}).catch(function(){});},10000);
</script>

<script>
window.addEventListener('DOMContentLoaded',function(){
    var s=<?php echo json_encode($substituicoes); ?>;
    function p(el){
        if(el.nodeType===3){s.forEach(function(x){el.textContent=el.textContent.replace(x.original,x.substituto);});}
        else{for(var i=0;i<el.childNodes.length;i++)p(el.childNodes[i]);}
    }
    var el=document.getElementById('inicialeditor');
    if(el)p(el.parentNode);
});
</script>
<?php
// Fallback: garantir que openThemeModal sempre existe (mesmo se o modal falhar)
echo '<script>if(typeof window.openThemeModal==="undefined"){window.openThemeModal=function(){alert("Modal de temas não carregou. Verifique erros no console.");};window.fecharThemeModal=function(){};}</script>';
try { echo getModalTemasHTML($conn); } catch (\Throwable $e) { echo '<div style="position:fixed;bottom:10px;left:10px;background:#dc2626;color:#fff;padding:10px 16px;border-radius:10px;z-index:99999;font-size:12px;font-family:monospace;max-width:500px;">ERRO Modal: '.htmlspecialchars($e->getMessage()).'</div>'; }
?>
<script>
// Aplicar classe do tema em TODOS os bodies da página (incluindo o body das sub-páginas)
(function(){
    var temaClasse = '<?php echo htmlspecialchars(getBodyClass($temaAtual)); ?>';
    if (!temaClasse) return;
    document.querySelectorAll('body').forEach(function(b){
        b.classList.forEach(function(c){ if(c.startsWith('theme-')) b.classList.remove(c); });
        b.classList.add(temaClasse);
    });
    if (!document.querySelector('link[href*="temas_visual"]')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '../AegisCore/temas_visual.css';
        document.head.appendChild(link);
    }
    // Garantir que body fica visível (fallback anti-piscada)
    document.body.style.opacity = '1';
})();
</script>