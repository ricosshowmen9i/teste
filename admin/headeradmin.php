<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php
    error_reporting(0);
    session_start();
    if(!isset($_SESSION['login'])){
        header('Location: ../index.php');
        exit();
    }
    include_once("../AegisCore/conexao.php");
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    if ($_SESSION['login'] == 'admin') {
    } else {
        $token_invalido_html = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("invalido");});<\/script>';
    }

    $sql = "SELECT * FROM configs WHERE id = '1'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $nomepainel   = $row["nomepainel"];
            $logo         = $row["logo"];
            $icon         = $row["icon"];
            $csspersonali = $row["corfundologo"];
        }
    }

    mysqli_query($conn, "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS tokenvenda TEXT NOT NULL DEFAULT '0'");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS perfil_avatar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        imagem TEXT NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_id)
    )");

    $avatar_upload_msg = '';
    if (isset($_FILES['avatar_upload']) && $_FILES['avatar_upload']['error'] === 0) {
        $arquivo  = $_FILES['avatar_upload'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif','webp'];
        if (in_array($extensao, $permitidas) && $arquivo['size'] <= 5242880) {
            $pasta = '../uploads/avatars/';
            if (!is_dir($pasta)) mkdir($pasta, 0755, true);
            $nomeArquivo     = 'avatar_' . $_SESSION['iduser'] . '_' . time() . '.' . $extensao;
            $caminhoCompleto = $pasta . $nomeArquivo;
            if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                $checkAvatar  = "SELECT * FROM perfil_avatar WHERE user_id = '" . $_SESSION['iduser'] . "'";
                $resultAvatar = mysqli_query($conn, $checkAvatar);
                if (mysqli_num_rows($resultAvatar) > 0) {
                    $avatarAntigo = mysqli_fetch_assoc($resultAvatar);
                    if (file_exists($avatarAntigo['imagem'])) unlink($avatarAntigo['imagem']);
                    mysqli_query($conn, "UPDATE perfil_avatar SET imagem='$caminhoCompleto' WHERE user_id='" . $_SESSION['iduser'] . "'");
                } else {
                    mysqli_query($conn, "INSERT INTO perfil_avatar (user_id, imagem) VALUES ('" . $_SESSION['iduser'] . "','$caminhoCompleto')");
                }
                $avatar_upload_msg = 'success';
            }
        } else {
            $avatar_upload_msg = 'error';
        }
    }

    $sqlGetAvatar    = "SELECT imagem FROM perfil_avatar WHERE user_id='" . $_SESSION['iduser'] . "'";
    $resultGetAvatar = mysqli_query($conn, $sqlGetAvatar);
    $avatarAtual     = '';
    if ($resultGetAvatar && mysqli_num_rows($resultGetAvatar) > 0) {
        $rowAvatar   = mysqli_fetch_assoc($resultGetAvatar);
        $avatarAtual = $rowAvatar['imagem'];
    }

    mysqli_query($conn, "DROP TABLE IF EXISTS limiter");

    $sql452    = "SELECT * FROM accounts WHERE id='" . $_SESSION['iduser'] . "'";
    $result452 = mysqli_query($conn, $sql452);
    $row452    = mysqli_fetch_assoc($result452);
    $tokenvenda         = $row452['tokenvenda'];
    $idcategoriacompra  = $row452['tempo'];
    $acesstoken         = $row452['accesstoken'];
    $acesstokenpaghiper = $row452['acesstokenpaghiper'];

    if ($idcategoriacompra == null || $idcategoriacompra == '')
        mysqli_query($conn, "UPDATE accounts SET tempo='1' WHERE id='" . $_SESSION['iduser'] . "'");

    mysqli_query($conn, "ALTER TABLE pagamentos ADD COLUMN IF NOT EXISTS formadepag TEXT DEFAULT '1', ADD COLUMN IF NOT EXISTS tokenpaghiper TEXT DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE accounts ADD COLUMN IF NOT EXISTS acesstokenpaghiper TEXT DEFAULT NULL, ADD COLUMN IF NOT EXISTS formadepag TEXT DEFAULT NULL, ADD COLUMN IF NOT EXISTS tokenpaghiper TEXT DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE atribuidos ADD COLUMN IF NOT EXISTS valormensal TEXT DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE ssh_accounts ADD COLUMN IF NOT EXISTS valormensal TEXT DEFAULT NULL");

    $totalrevenda        = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM atribuidos WHERE byid='" . $_SESSION['iduser'] . "'"));
    $totalusuarios       = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM ssh_accounts WHERE byid='" . $_SESSION['iduser'] . "'"));
    $totalusuariosglobal = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM ssh_accounts"));
    $totalrevendedores   = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM atribuidos"));
    $totalservidores     = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM servidores"));
    $totallogs           = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM logs"));

    // Total vendido — pagamentos_unificado + pagamentos
    $rv1 = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(valor),0) AS valor FROM pagamentos_unificado
         WHERE revendedor_id='" . $_SESSION['iduser'] . "' AND status='approved'"));
    $rv2 = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(valor),0) AS valor FROM pagamentos
         WHERE byid='" . $_SESSION['iduser'] . "' AND status='Aprovado'"));
    $totalvendido = number_format(($rv1['valor'] ?? 0) + ($rv2['valor'] ?? 0), 2, ',', '.');

    // Vendas hoje — pagamentos_unificado + pagamentos
    date_default_timezone_set('America/Sao_Paulo');
    $dataHoje = date('Y-m-d');
    $rh1 = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(valor),0) AS valor FROM pagamentos_unificado
         WHERE revendedor_id='" . $_SESSION['iduser'] . "' AND status='approved'
         AND DATE(created_at)='$dataHoje'"));
    $rh2 = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(valor),0) AS valor FROM pagamentos
         WHERE byid='" . $_SESSION['iduser'] . "' AND status='Aprovado'
         AND DATE(data)='$dataHoje'"));
    $totalvendidohoje = number_format(($rh1['valor'] ?? 0) + ($rh2['valor'] ?? 0), 2, ',', '.');

    $dataAgora     = date('Y-m-d H:i:s');
    $totalvencidos = mysqli_num_rows(mysqli_query($conn,
        "SELECT * FROM ssh_accounts WHERE byid='" . $_SESSION['iduser'] . "' AND expira<'$dataAgora'"));

    // Onlines separados
    $meusonlines = mysqli_num_rows(mysqli_query($conn,
        "SELECT * FROM ssh_accounts WHERE status='Online' AND byid='" . $_SESSION['iduser'] . "'"));

    $revendedoresIDs = [];
    $rrq = mysqli_query($conn, "SELECT id FROM accounts WHERE byid='" . $_SESSION['iduser'] . "'");
    while ($rrr = mysqli_fetch_assoc($rrq)) $revendedoresIDs[] = $rrr['id'];
    $onlinesRevendedores = 0;
    if (!empty($revendedoresIDs))
        $onlinesRevendedores = mysqli_num_rows(mysqli_query($conn,
            "SELECT * FROM ssh_accounts WHERE status='Online' AND byid IN (" . implode(',', $revendedoresIDs) . ")"));

    $totalonline = $meusonlines + $onlinesRevendedores;

    // Limpar pagamentos expirados
    $dataLimpar = date('d-m-Y H:i:s', strtotime('-1 hour'));
    mysqli_query($conn, "DELETE FROM pagamentos WHERE status='Aguardando Pagamento' AND data<'$dataLimpar'");

    if (isset($_POST['gerarlink'])) {
        $codigo = rand(100000000000, 999999999999);
        mysqli_query($conn, "UPDATE accounts SET tokenvenda='$codigo' WHERE id='" . $_SESSION['iduser'] . "'");
        $tokenvenda = $codigo;
    }
    if (isset($_POST['salvarcate']) && isset($_POST['categoriacompra'])) {
        $cat = mysqli_real_escape_string($conn, $_POST['categoriacompra']);
        mysqli_query($conn, "UPDATE accounts SET tempo='$cat' WHERE id='" . $_SESSION['iduser'] . "'");
        $idcategoriacompra = $cat;
    }

    // Telegram + Token
    $telegram = null;
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $telegram = new \Telegram\Bot\Api('6163337935:AAE8uxSRfSkXHthlZtRr-tjpUPxzzxaiUcQ');
    } elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $telegram = new \Telegram\Bot\Api('6163337935:AAE8uxSRfSkXHthlZtRr-tjpUPxzzxaiUcQ');
    }

    $dominio = $_SERVER['HTTP_HOST'];
    $token   = $_SESSION['token'] ?? '';
    $senhatokenacessoss = "123gdsfgbhgdyegryr56y4w5t7Cv3rwrfcrwa3bgs9ume09v58dasdasdadfsdfgm3nut09083r4y289Y45";
    $url = 'https://gerenciador.painelcontrole.xyz/vencimento.php?senha=' . $senhatokenacessoss . '&token=' . $token . '&dominio=' . $dominio;
    $ctxOpts = [
        'http' => ['method'=>'GET','header'=>"User-Agent: Mozilla/5.0\r\n",'timeout'=>10,'ignore_errors'=>true,'follow_location'=>1],
        'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false]
    ];
    $dataVenc = @file_get_contents($url, false, stream_context_create($ctxOpts));
    $_SESSION['datavencimentotoken'] = $dataVenc;

    if (!isset($token_invalido_html)) $token_invalido_html = '';

    if (!file_exists('suspenderrev.php')) {
        $token_invalido_html = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("ausente");});<\/script>';
    } else {
        include_once 'suspenderrev.php';
    }

    $secret_salt   = "AtlasSecurity_2024_#@!";
    $dominio_atual = $_SERVER['HTTP_HOST'];
    $hash_esperado = hash('sha256', $token . $secret_salt . $dominio_atual);

    if (
        !isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) ||
        $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado ||
        $_SESSION['tokenatual'] != $token ||
        (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)
    ) {
        if (function_exists('security')) {
            security();
            if ($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado)
                $token_invalido_html = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("integridade");});<\/script>';
        } else {
            if ($telegram)
                $telegram->sendMessage(['chat_id'=>'2017803306','text'=>"⚠️ BYPASS DETECTADO: O domínio ".$dominio_atual." tentou burlar a segurança do token!"]);
            $_SESSION['token_invalido_'] = true;
            $token_invalido_html = '<script>document.addEventListener("DOMContentLoaded",function(){showTokenModal("bypass");});<\/script>';
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

    // Inicializar sistema de temas v9 - Global (admin controla)
    include_once("../AegisCore/temas.php");
    processarTemaPOST($conn);
    $temaAtual = initTemas($conn);

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
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

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

        /* ===== PERFIL ===== */
        .profile-card{
            background:linear-gradient(135deg,#1e293b,#0f172a);
            border-radius:18px;
            padding:24px 20px 20px;
            border:1px solid rgba(255,255,255,.07);
            max-width:480px;
            margin:0 auto 16px;
            position:relative;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            align-items:center;
            text-align:center;
        }
        .profile-card-lines{position:absolute;inset:0;pointer-events:none;overflow:hidden;border-radius:18px;}

        .profile-avatar-box{
            width:110px;height:110px;border-radius:16px;
            background:linear-gradient(135deg,#4158D0,#C850C0);
            display:flex;align-items:center;justify-content:center;
            cursor:pointer;transition:all .3s;
            border:3px solid rgba(255,255,255,.12);
            box-shadow:0 10px 30px rgba(65,88,208,.3);
            overflow:hidden;position:relative;
            margin-bottom:10px;
        }
        .profile-avatar-box img{width:100%;height:100%;object-fit:cover;border-radius:13px;}
        .profile-avatar-box .avatar-letra{font-size:30px;color:white;font-weight:700;text-transform:uppercase;}
        .profile-avatar-box:hover{transform:scale(1.05);}
        .avatar-cam-overlay{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);color:white;padding:4px;font-size:10px;text-align:center;transform:translateY(100%);transition:all .3s;}
        .profile-avatar-box:hover .avatar-cam-overlay{transform:translateY(0);}

        .profile-role-lbl{font-size:10px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:3px;}
        .profile-name{font-size:17px;font-weight:700;color:white;margin:0 0 7px;}
        .profile-badge{background:rgba(65,88,208,.2);color:#818cf8;padding:3px 12px;border-radius:40px;font-weight:600;font-size:11px;display:inline-flex;align-items:center;gap:4px;border:1px solid rgba(65,88,208,.3);margin-bottom:14px;}

        /* grid 3 colunas */
        .profile-stats-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:10px;
            width:100%;
            margin-bottom:10px;
        }
        .psg-item{
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.07);
            border-radius:12px;
            padding:10px 8px;
            display:flex;flex-direction:column;align-items:center;gap:5px;
            transition:all .2s;
        }
        .psg-item:hover{background:rgba(255,255,255,.07);border-color:rgba(65,88,208,.3);transform:translateY(-2px);}
        .psg-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;}
        .psg-value{font-size:20px;font-weight:700;background:linear-gradient(135deg,#4158D0,#C850C0);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
        .psg-label{font-size:10px;color:rgba(255,255,255,.4);font-weight:600;text-transform:uppercase;letter-spacing:.5px;}

        /* validade full width */
        .profile-validade{
            width:100%;
            background:rgba(245,158,11,.08);
            border:1px solid rgba(245,158,11,.2);
            border-radius:12px;
            padding:10px 16px;
            display:flex;align-items:center;justify-content:space-between;
        }
        .pv-label{font-size:12px;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:5px;font-weight:500;}
        .pv-value{font-size:14px;font-weight:700;color:#fbbf24;}

        /* ===== CARDS GRID ===== */
        .cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:11px;margin-bottom:16px;}
        .dash-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:15px;padding:13px 15px;display:flex;align-items:center;gap:11px;transition:all .25s;text-decoration:none;color:inherit;cursor:pointer;position:relative;overflow:hidden;}
        .dash-card:hover{background:rgba(255,255,255,.08);transform:translateY(-3px);box-shadow:0 8px 22px rgba(0,0,0,.3);color:inherit;}
        .card-shapes{position:absolute;inset:0;pointer-events:none;z-index:0;overflow:hidden;border-radius:15px;}
        .card-shapes svg{position:absolute;top:0;left:0;width:100%;height:100%;}
        .dash-card .dci,.dash-card>div:last-child{position:relative;z-index:1;}
        .dci{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:21px;color:white;flex-shrink:0;}
        .dci.ic-online-pulse{animation:pulseOnline 2s ease-in-out infinite;}
        .dcl{font-size:9px;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;}
        .dcv{font-size:20px;font-weight:700;color:white;line-height:1.1;}
        .dcv.sm{font-size:13px;font-weight:600;}
        .dcs{font-size:10px;color:rgba(255,255,255,.28);margin-top:1px;}
        .dash-card.online-highlight .dcl{font-size:10px;font-weight:700;color:rgba(255,255,255,.5);}
        .dash-card.online-highlight .dcv{font-size:22px;font-weight:800;}
        .dash-card.online-highlight .dcs{font-size:10px;font-weight:600;color:rgba(255,255,255,.4);}

        .ic-1{background:linear-gradient(135deg,#4158D0,#6366f1);}
        .ic-2{background:linear-gradient(135deg,#C850C0,#e879f9);}
        .ic-3{background:linear-gradient(135deg,#10b981,#34d399);}
        .ic-4{background:linear-gradient(135deg,#f59e0b,#fbbf24);}
        .ic-5{background:linear-gradient(135deg,#3b82f6,#60a5fa);}
        .ic-6{background:linear-gradient(135deg,#8b5cf6,#a78bfa);}
        .ic-7{background:linear-gradient(135deg,#ec4899,#f472b6);}
        .ic-8{background:linear-gradient(135deg,#14b8a6,#2dd4bf);}
        .ic-9{background:linear-gradient(135deg,#ef4444,#f87171);}
        .ic-10{background:linear-gradient(135deg,#f97316,#fb923c);}
        .ic-11{background:linear-gradient(135deg,#06b6d4,#22d3ee);}
        .ic-12{background:linear-gradient(135deg,#84cc16,#a3e635);}

        /* ===== ACTION CARDS ===== */
        .action-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:16px;max-width:480px;margin-left:auto;margin-right:auto;}
        .action-card{background:linear-gradient(135deg,#4158D0,#C850C0);border-radius:18px;padding:26px 18px;color:white;text-align:center;cursor:pointer;transition:all .3s;border:none;box-shadow:0 15px 35px rgba(65,88,208,.25);position:relative;overflow:hidden;min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .action-card:nth-child(2){background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 15px 35px rgba(16,185,129,.25);}
        .action-card::before{content:'';position:absolute;top:-50%;right:-50%;width:200%;height:200%;background:rgba(255,255,255,.08);transform:rotate(45deg);transition:all .5s;}
        .action-card:hover::before{transform:rotate(45deg) translate(20px,20px);}
        .action-card:hover{transform:translateY(-8px) scale(1.02);}
        .action-card i{font-size:44px;margin-bottom:10px;position:relative;z-index:1;}
        .action-card h3{font-size:17px;font-weight:700;margin-bottom:4px;position:relative;z-index:1;}
        .action-card p{font-size:12px;opacity:.85;margin:0;position:relative;z-index:1;}

        /* ===== GENERIC CARD ===== */
        .g-card{background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:18px;border:1px solid rgba(255,255,255,.07);box-shadow:0 10px 30px rgba(0,0,0,.2);margin-bottom:16px;}
        .g-card-header{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:10px;}
        .g-card-header h4{color:white;font-size:15px;font-weight:700;margin:0;}
        .g-card-body{padding:20px;}

        /* ===== SEARCH ===== */
        .search-wrapper{display:flex;align-items:center;background:rgba(255,255,255,.04);border-radius:50px;padding:4px;border:1px solid rgba(255,255,255,.08);transition:all .3s;margin-bottom:16px;}
        .search-wrapper:focus-within{border-color:rgba(65,88,208,.5);background:rgba(65,88,208,.06);}
        .search-icon{width:42px;height:42px;background:linear-gradient(135deg,#4158D0,#C850C0);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:17px;margin-right:8px;flex-shrink:0;}
        .search-input{flex:1;border:none;padding:10px 10px 10px 0;font-size:14px;background:transparent;outline:none;color:white;}
        .search-input::placeholder{color:rgba(255,255,255,.3);font-style:italic;}
        .search-clear{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:rgba(255,255,255,.3);transition:all .3s;margin-right:4px;}
        .search-clear:hover{background:rgba(239,68,68,.15);color:#f87171;transform:rotate(90deg);}

        /* ===== SERVERS ===== */
        .servers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}
        .server-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:16px;overflow:hidden;transition:all .3s;}
        .server-card:hover{transform:translateY(-5px);border-color:rgba(65,88,208,.4);box-shadow:0 16px 40px rgba(65,88,208,.15);}
        .server-header{background:linear-gradient(135deg,#4158D0,#C850C0);padding:16px 18px;display:flex;align-items:center;gap:12px;position:relative;overflow:hidden;}
        .server-header::after{content:'';position:absolute;top:-50%;right:-50%;width:200%;height:200%;background:rgba(255,255,255,.08);transform:rotate(45deg);transition:.5s;}
        .server-card:hover .server-header::after{transform:rotate(45deg) translate(30px,30px);}
        .server-ico{width:50px;height:50px;background:rgba(255,255,255,.2);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:28px;z-index:1;}
        .server-info-h{flex:1;z-index:1;}
        .server-name{font-size:16px;font-weight:700;color:white;margin:0 0 3px;}
        .server-ip-txt{font-size:12px;color:rgba(255,255,255,.8);display:flex;align-items:center;gap:4px;}
        .server-body{padding:16px;}
        .server-stats-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
        .sstat-box{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px;text-align:center;transition:.2s;}
        .sstat-box:hover{border-color:rgba(65,88,208,.35);background:rgba(65,88,208,.08);}
        .sstat-lbl{font-size:10px;color:rgba(255,255,255,.4);font-weight:600;text-transform:uppercase;margin-bottom:3px;}
        .sstat-val{font-size:14px;font-weight:700;color:white;display:flex;align-items:center;justify-content:center;gap:4px;}
        .ram-bar{height:5px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;margin:8px 0;}
        .ram-fill{height:100%;background:linear-gradient(90deg,#10b981,#f59e0b);border-radius:4px;transition:width .3s;}
        .online-badge{background:rgba(16,185,129,.15);color:#34d399;padding:4px 12px;border-radius:40px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;border:1px solid rgba(16,185,129,.25);}

        /* ===== FORM ===== */
        .f-input{width:100%;padding:9px 14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;color:white;font-size:13px;outline:none;font-family:inherit;transition:.2s;}
        .f-input:focus{border-color:rgba(65,88,208,.5);background:rgba(65,88,208,.06);}
        .f-group{margin-bottom:14px;}
        .btn-grad{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit;color:white;}
        .btn-warn{background:linear-gradient(135deg,#f59e0b,#f97316);}
        .btn-pri{background:linear-gradient(135deg,#4158D0,#6366f1);}
        .btn-grad:hover{transform:translateY(-2px);filter:brightness(1.1);}

        /* ===== LINKS CARD ===== */
        .links-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:18px;padding:18px 20px;margin-bottom:16px;}
        .links-card h4{color:white;font-size:14px;font-weight:600;margin-bottom:4px;}
        .links-card p{color:rgba(255,255,255,.3);font-size:12px;margin-bottom:14px;}
        .link-sep{font-size:9px;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.5px;margin:10px 0 4px;}

        /* ===== OVERLAY MOBILE ===== */
        .menu-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;}
        .menu-overlay.active{display:block;}

        /* ===== RESPONSIVE ===== */
        @media(max-width:1024px){
            .side-menu{transform:translateX(-100%);}
            .side-menu.open{transform:translateX(0);}
            .main-wrap{margin-left:0!important;}
            .btn-menu-mobile{display:inline-flex!important;}
        }
        @media(max-width:768px){
            .cards-grid{grid-template-columns:1fr 1fr!important;}
            .action-cards{grid-template-columns:1fr;max-width:100%;}
            .servers-grid{grid-template-columns:1fr;}
            .header-center{display:none;}
            .profile-card{max-width:100%;}
        }
        @media(max-width:480px){
            .profile-stats-grid{grid-template-columns:repeat(3,1fr);gap:6px;}
            .psg-value{font-size:17px;}
            .psg-label{font-size:9px;}
            .psg-icon{width:28px;height:28px;font-size:13px;}
            .profile-validade{flex-direction:column;gap:4px;text-align:center;}
            .pv-label{justify-content:center;}
        }
    </style>
</head>
<body id="inicialeditor" class="<?php echo htmlspecialchars(getBodyClass($temaAtual)); ?>">

<?php echo $token_invalido_html; ?>

<?php if ($avatar_upload_msg): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    <?php if($avatar_upload_msg==='success'): ?>
    showModal({title:'Sucesso!',text:'Foto de perfil atualizada com sucesso!',icon:'success',timer:1800,buttons:false});
    <?php else: ?>
    showModal({title:'Erro!',text:'Arquivo inválido. Use JPG, PNG, GIF ou WEBP (máx 5MB).',icon:'error',buttons:true});
    <?php endif; ?>
});
</script>
<?php endif; ?>

<div class="menu-overlay" id="menuOverlay"></div>

<!-- ===== MENU LATERAL ===== -->
<aside class="side-menu" id="sideMenu">
    <div class="side-menu-logo"><img src="<?php echo $logo; ?>" alt="logo"></div>
    <nav class="side-nav">
        <div class="nav-sec-title">Principal</div>
        <a href="home.php" class="nav-link-main active">
            <span class="nav-icon ni-home"><i class='bx bx-home-alt'></i></span>
            <span class="nav-text">Página Inicial</span>
        </a>

        <div class="nav-sec-title">Usuários</div>
        <button class="nav-link-main" onclick="toggleSub('sUsers',this)">
            <span class="nav-icon ni-user"><i class='bx bx-user-circle'></i></span>
            <span class="nav-text">Gerenciar Usuários</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sUsers">
            <a href="criarusuario.php"><span class="sub-icon si-1"><i class='bx bx-user-plus'></i></span>Criar Usuário</a>
            <a href="criarteste.php"><span class="sub-icon si-2"><i class='bx bx-test-tube'></i></span>Criar Teste</a>
            <a href="listarusuarios.php"><span class="sub-icon si-3"><i class='bx bx-list-ul'></i></span>Lista de Usuários</a>
            <a href="listaglobaluser.php"><span class="sub-icon si-5"><i class='bx bx-globe'></i></span>Todos Usuários</a>
            <a href="listaexpirados.php"><span class="sub-icon si-4"><i class='bx bx-time'></i></span>Expirados</a>
            <a href="limiter.php"><span class="sub-icon si-6"><i class='bx bx-shield-quarter'></i></span>Limiter</a>
        </div>

        <button class="nav-link-main" onclick="toggleSub('sRevenda',this)">
            <span class="nav-icon ni-store"><i class='bx bx-store-alt'></i></span>
            <span class="nav-text">Revendedores</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sRevenda">
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
        <button class="nav-link-main" onclick="toggleSub('sPag',this)">
            <span class="nav-icon ni-pay"><i class='bx bx-wallet'></i></span>
            <span class="nav-text">Pagamentos</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sPag">
            <a href="formaspag.php"><span class="sub-icon si-8"><i class='bx bx-credit-card'></i></span>Configurar</a>
            <a href="listadepag.php"><span class="sub-icon si-9"><i class='bx bx-receipt'></i></span>Listar Pagamentos</a>
            <a href="listadetodospag.php"><span class="sub-icon si-3"><i class='bx bx-spreadsheet'></i></span>Todos Pagamentos</a>
            <a href="cupons.php"><span class="sub-icon si-10"><i class='bx bx-purchase-tag-alt'></i></span>Cupons</a>
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
<div class="main-wrap">
    <div class="top-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <button class="btn-menu-mobile" id="mobileMenuBtn"><i class='bx bx-menu'></i> MENU</button>
        </div>
        <div class="header-center"><i class='bx bx-crown'></i>Bem-vindo ao <?php echo $nomepainel; ?></div>
        <div></div>
    </div>

    <div class="page-body">

        <!-- ===== PERFIL ===== -->
        <div class="profile-card">
            <!-- linhas decorativas SVG -->
            <div class="profile-card-lines">
                <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                    <circle cx="92%" cy="18%" r="55" fill="none" stroke="rgba(65,88,208,0.12)" stroke-width="1"/>
                    <circle cx="92%" cy="18%" r="88" fill="none" stroke="rgba(65,88,208,0.07)" stroke-width="1"/>
                    <circle cx="8%"  cy="85%" r="45" fill="none" stroke="rgba(200,80,192,0.10)" stroke-width="1"/>
                    <circle cx="8%"  cy="85%" r="72" fill="none" stroke="rgba(200,80,192,0.06)" stroke-width="1"/>
                    <line x1="0" y1="100%" x2="100%" y2="0" stroke="rgba(255,255,255,0.02)" stroke-width="1"/>
                    <circle cx="50%" cy="-5%" r="40" fill="none" stroke="rgba(99,102,241,0.06)" stroke-width="1"/>
                </svg>
            </div>

            <form id="avatarForm" action="" method="post" enctype="multipart/form-data" style="display:none;">
                <input type="file" name="avatar_upload" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp">
            </form>

            <!-- avatar -->
            <div class="profile-avatar-box" onclick="document.getElementById('avatarInput').click()" style="position:relative;z-index:1;">
                <?php if (!empty($avatarAtual) && file_exists($avatarAtual)): ?>
                    <img src="<?php echo $avatarAtual; ?>" alt="Avatar">
                <?php else: ?>
                    <span class="avatar-letra"><?php echo strtoupper($_SESSION['login'][0]); ?></span>
                <?php endif; ?>
                <div class="avatar-cam-overlay"><i class='bx bx-camera'></i></div>
            </div>

         
   <!-- nome + badge -->
            <div style="position:relative;z-index:1;width:100%;">
                <div class="profile-role-lbl">Administrador</div>
               
                <div style="display:flex;justify-content:center;margin-bottom:14px;">
                    <span class="profile-badge"><i class='bx bx-crown'></i> Admin</span>
               
                </div>
                <!-- validade abaixo full width -->
                <div class="profile-validade">
                    <div class="pv-label">
                        <i class='bx bx-calendar' style="color:#fbbf24;font-size:14px;"></i>
                        Validade do Token
                    </div>
                    <div class="pv-value"><?php echo $_SESSION['datavencimentotoken']; ?></div>
                </div>
            </div>
        </div>

      

        <!-- ===== DASH CARDS ===== -->
        <?php
        $shapes = [
            '<circle cx="88%" cy="16%" r="22" fill="rgba(99,102,241,.20)"/><rect x="76%" y="58%" width="18" height="18" rx="4" fill="rgba(129,140,248,.15)" transform="rotate(20,85,67)"/><circle cx="95%" cy="74%" r="12" fill="rgba(165,180,252,.12)"/>',
            '<circle cx="90%" cy="13%" r="20" fill="rgba(232,121,249,.20)"/><rect x="78%" y="62%" width="16" height="16" rx="3" fill="rgba(244,114,182,.15)" transform="rotate(-15,86,70)"/><circle cx="94%" cy="72%" r="10" fill="rgba(251,182,206,.12)"/>',
            '<circle cx="87%" cy="15%" r="24" fill="rgba(52,211,153,.18)"/><rect x="80%" y="60%" width="17" height="17" rx="4" fill="rgba(16,185,129,.14)" transform="rotate(25,88,68)"/><circle cx="93%" cy="78%" r="11" fill="rgba(110,231,183,.12)"/>',
            '<circle cx="91%" cy="14%" r="21" fill="rgba(251,191,36,.20)"/><rect x="77%" y="63%" width="15" height="15" rx="3" fill="rgba(245,158,11,.15)" transform="rotate(10,84,70)"/><circle cx="95%" cy="70%" r="13" fill="rgba(253,224,71,.12)"/>',
            '<circle cx="89%" cy="16%" r="23" fill="rgba(96,165,250,.19)"/><rect x="79%" y="61%" width="16" height="16" rx="4" fill="rgba(59,130,246,.14)" transform="rotate(-20,87,69)"/><circle cx="94%" cy="76%" r="10" fill="rgba(147,197,253,.12)"/>',
            '<circle cx="86%" cy="14%" r="22" fill="rgba(167,139,250,.20)"/><rect x="78%" y="64%" width="17" height="17" rx="3" fill="rgba(139,92,246,.14)" transform="rotate(15,86,72)"/><circle cx="93%" cy="75%" r="12" fill="rgba(196,181,253,.12)"/>',
            '<circle cx="90%" cy="12%" r="21" fill="rgba(244,114,182,.20)"/><rect x="80%" y="62%" width="15" height="15" rx="4" fill="rgba(236,72,153,.14)" transform="rotate(-10,87,69)"/><circle cx="95%" cy="73%" r="11" fill="rgba(251,207,232,.12)"/>',
            '<circle cx="88%" cy="15%" r="23" fill="rgba(45,212,191,.19)"/><rect x="79%" y="60%" width="16" height="16" rx="3" fill="rgba(20,184,166,.15)" transform="rotate(20,87,68)"/><circle cx="94%" cy="77%" r="10" fill="rgba(153,246,228,.12)"/>',
            '<circle cx="91%" cy="13%" r="22" fill="rgba(248,113,113,.20)"/><rect x="78%" y="63%" width="17" height="17" rx="4" fill="rgba(239,68,68,.14)" transform="rotate(-15,86,71)"/><circle cx="95%" cy="71%" r="12" fill="rgba(254,202,202,.12)"/>',
            '<circle cx="89%" cy="15%" r="21" fill="rgba(251,146,60,.20)"/><rect x="80%" y="61%" width="15" height="15" rx="3" fill="rgba(249,115,22,.15)" transform="rotate(12,87,68)"/><circle cx="94%" cy="76%" r="11" fill="rgba(254,215,170,.12)"/>',
            '<circle cx="87%" cy="14%" r="20" fill="rgba(6,182,212,.20)"/><rect x="79%" y="62%" width="16" height="16" rx="4" fill="rgba(34,211,238,.14)" transform="rotate(18,87,70)"/><circle cx="93%" cy="74%" r="10" fill="rgba(103,232,249,.12)"/>',
            '<circle cx="90%" cy="16%" r="22" fill="rgba(163,230,53,.18)"/><rect x="78%" y="60%" width="15" height="15" rx="3" fill="rgba(132,204,22,.14)" transform="rotate(-12,86,68)"/><circle cx="94%" cy="75%" r="11" fill="rgba(190,242,100,.12)"/>',
            '<circle cx="88%" cy="14%" r="21" fill="rgba(99,102,241,.18)"/><rect x="80%" y="63%" width="16" height="16" rx="4" fill="rgba(129,140,248,.13)" transform="rotate(22,88,71)"/><circle cx="93%" cy="76%" r="10" fill="rgba(165,180,252,.11)"/>',
        ];

        $dcards = [
            ['href'=>'criarteste.php',             'ic'=>'ic-6',               'icon'=>'bx-test-tube',    'lbl'=>'Criar Teste',     'val'=>'Teste',              'sub'=>'Acesso rápido',         'sm'=>true, 'online'=>false],
            ['href'=>'criarusuario.php',           'ic'=>'ic-3',               'icon'=>'bx-user-plus',    'lbl'=>'Criar Usuário',   'val'=>'Usuário',            'sub'=>'Acesso rápido',         'sm'=>true, 'online'=>false],
            ['href'=>'onlines.php',               'ic'=>'ic-3 ic-online-pulse','icon'=>'bx-wifi',         'lbl'=>'Meus Onlines',    'val'=>$meusonlines,         'sub'=>'Seus usuários online',  'sm'=>false,'online'=>true ],
            ['href'=>'onlines.php',               'ic'=>'ic-7 ic-online-pulse','icon'=>'bx-wifi',         'lbl'=>'Onlines Revendas','val'=>$onlinesRevendedores, 'sub'=>'De seus revendedores',  'sm'=>false,'online'=>true ],
            ['href'=>'listarusuarios.php',         'ic'=>'ic-1',               'icon'=>'bx-user',         'lbl'=>'Usuários',        'val'=>$totalusuarios,       'sub'=>'Seus cadastrados',      'sm'=>false,'online'=>false],
            ['href'=>'listarrevendedores.php',     'ic'=>'ic-2',               'icon'=>'bx-store-alt',    'lbl'=>'Revendedores',    'val'=>$totalrevenda,        'sub'=>'Total ativos',          'sm'=>false,'online'=>false],
            ['href'=>'servidores.php',             'ic'=>'ic-5',               'icon'=>'bx-server',       'lbl'=>'Servidores',      'val'=>$totalservidores,     'sub'=>'Servidores ativos',     'sm'=>false,'online'=>false],
            ['href'=>'listaexpirados.php',         'ic'=>'ic-9',               'icon'=>'bx-calendar-x',   'lbl'=>'Expirados',       'val'=>$totalvencidos,       'sub'=>'Usuários vencidos',     'sm'=>false,'online'=>false],
            ['href'=>'',                           'ic'=>'ic-4',               'icon'=>'bx-dollar-circle','lbl'=>'Total Vendido',   'val'=>'R$ '.$totalvendido,  'sub'=>'Aprovado acumulado',    'sm'=>true, 'online'=>false],
            ['href'=>'',                           'ic'=>'ic-12',              'icon'=>'bx-money',        'lbl'=>'Vendas Hoje',     'val'=>'R$ '.$totalvendidohoje,'sub'=>'Aprovado hoje',       'sm'=>true, 'online'=>false],
            ['href'=>'listartodosrevendedores.php','ic'=>'ic-8',               'icon'=>'bx-network-chart','lbl'=>'Total Revendas',  'val'=>$totalrevendedores,   'sub'=>'Global do sistema',     'sm'=>false,'online'=>false],
            ['href'=>'listaglobaluser.php',        'ic'=>'ic-11',              'icon'=>'bx-globe',        'lbl'=>'Usuários Global', 'val'=>$totalusuariosglobal, 'sub'=>'Todos no sistema',      'sm'=>false,'online'=>false],
            ['href'=>'logs.php',                   'ic'=>'ic-10',              'icon'=>'bx-history',      'lbl'=>'Logs',            'val'=>$totallogs,           'sub'=>'Total de registros',    'sm'=>false,'online'=>false],
            
        ];

        echo '<div class="cards-grid">';
        foreach ($dcards as $i => $c):
            $tag  = !empty($c['href']) ? 'a' : 'div';
            $href = !empty($c['href']) ? ' href="'.$c['href'].'"' : '';
            $sh   = $shapes[$i] ?? $shapes[0];
            $oc   = $c['online'] ? ' online-highlight' : '';
        ?>
        <<?php echo $tag.$href; ?> class="dash-card<?php echo $oc; ?>">
            <div class="card-shapes"><svg xmlns="http://www.w3.org/2000/svg"><?php echo $sh; ?></svg></div>
            <div class="dci <?php echo $c['ic']; ?>"><i class='bx <?php echo $c['icon']; ?>'></i></div>
            <div>
                <div class="dcl"><?php echo $c['lbl']; ?></div>
                <div class="dcv<?php echo $c['sm']?' sm':''; ?>"><?php echo $c['val']; ?></div>
                <div class="dcs"><?php echo $c['sub']; ?></div>
            </div>
        </<?php echo $tag; ?>>
        <?php endforeach;
        echo '</div>'; ?>

        <!-- ===== LINKS DE VENDA ===== -->
        <?php if ($acesstoken != '' || $acesstokenpaghiper != ''): ?>
        <div class="links-card">
            <h4>Links de Venda</h4>
            <p>Use esses links para seus clientes comprarem seus produtos.</p>
            <div class="f-group"><div class="link-sep">Para Novos Revendedores</div><input class="f-input" type="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/revenda.php?token=<?php echo $tokenvenda; ?>" readonly></div>
            <div class="f-group"><div class="link-sep">Link Bot Vendas</div><input class="f-input" type="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/comprar.php?token=<?php echo $tokenvenda; ?>" readonly></div>
            <div class="f-group"><div class="link-sep">Link Teste Automático</div><input class="f-input" type="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/criarteste.php?token=<?php echo $tokenvenda; ?>" readonly></div>
            <form action="headeradmin.php" method="post" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
                <button class="btn-grad btn-warn" type="submit" name="gerarlink"><i class='bx bx-refresh'></i> Gerar Novo Link</button>
                <div style="flex:1;min-width:200px;">
                    <div class="link-sep">ID da Categoria para Compra Automática</div>
                    <div style="display:flex;gap:8px;margin-top:4px;">
                        <input class="f-input" type="text" name="categoriacompra" value="<?php echo $idcategoriacompra; ?>" style="flex:1;">
                        <button class="btn-grad btn-pri" type="submit" name="salvarcate"><i class='bx bx-save'></i> Salvar</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ===== SERVIDORES ===== -->
        <div class="g-card">
            <div class="g-card-header">
                <div class="dci ic-5" style="width:36px;height:36px;font-size:17px;border-radius:10px;"><i class='bx bx-server'></i></div>
                <h4>Servidores</h4>
            </div>
            <div class="g-card-body">
                <div class="search-wrapper">
                    <div class="search-icon"><i class='bx bx-search'></i></div>
                    <input type="text" class="search-input" id="pesquisar" placeholder="Pesquisar por nome ou IP..." onkeyup="pesquisar()">
                    <div class="search-clear" onclick="limparPesquisa()"><i class='bx bx-x'></i></div>
                </div>
                <div class="servers-grid" id="serversGrid">
                <?php
                $sqlsrv    = "SELECT * FROM servidores ORDER BY id DESC";
                $resultsrv = $conn->query($sqlsrv);
                while ($srv = mysqli_fetch_assoc($resultsrv)):
                    $ram_total = 8;
                    $ram_atual = floatval($srv['serverram']);
                    $ram_pct   = min(100, ($ram_atual / $ram_total) * 100);
                ?>
                <div class="server-card" data-nome="<?php echo strtolower($srv['nome']); ?>" data-ip="<?php echo strtolower($srv['ip']); ?>">
                    <div class="server-header">
                        <div class="server-ico"><i class='bx bx-server'></i></div>
                        <div class="server-info-h">
                            <div class="server-name"><?php echo $srv['nome']; ?></div>
                            <div class="server-ip-txt"><i class='bx bx-network-chart'></i><?php echo $srv['ip']; ?>:<?php echo $srv['porta']; ?></div>
                        </div>
                    </div>
                    <div class="server-body">
                        <div class="server-stats-row">
                            <div class="sstat-box"><div class="sstat-lbl">CPU</div><div class="sstat-val"><i class='bx bx-cpu' style="color:#f87171;"></i><?php echo $srv['servercpu']; ?> Cores</div></div>
                            <div class="sstat-box"><div class="sstat-lbl">RAM</div><div class="sstat-val"><i class='bx bx-memory-card' style="color:#818cf8;"></i><?php echo $srv['serverram']; ?></div></div>
                        </div>
                        <div class="ram-bar"><div class="ram-fill" style="width:<?php echo $ram_pct; ?>%"></div></div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
                            <span class="online-badge"><i class='bx bx-wifi'></i><?php echo $srv['onlines']; ?> online</span>
                            <span style="color:rgba(255,255,255,.3);font-size:11px;"><i class='bx bx-time'></i><?php echo date('d/m H:i', strtotime($srv['lastview'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
        </div>

    </div><!-- /page-body -->
</div><!-- /main-wrap -->

<script>
// ============================================================
// MODAL UNIFICADO
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
function toggleSub(id,btn){
    var open=document.getElementById(id).classList.contains('open');
    document.querySelectorAll('.nav-sub.open').forEach(function(s){s.classList.remove('open');});
    document.querySelectorAll('.nav-link-main.open').forEach(function(b){b.classList.remove('open');});
    if(!open){document.getElementById(id).classList.add('open');btn.classList.add('open');}
}
var ov2=document.getElementById('menuOverlay'),sm=document.getElementById('sideMenu');
document.getElementById('mobileMenuBtn')?.addEventListener('click',function(){sm.classList.add('open');ov2.classList.add('active');});
ov2?.addEventListener('click',function(){sm.classList.remove('open');ov2.classList.remove('active');});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){sm.classList.remove('open');ov2.classList.remove('active');}});

// AVATAR
document.getElementById('avatarInput').addEventListener('change',function(e){
    var file=e.target.files[0];if(!file)return;
    if(file.size>5242880){showModal({title:'Erro!',text:'Máximo 5MB.',icon:'error',buttons:true});return;}
    var ok=['image/jpeg','image/png','image/gif','image/webp'];
    if(ok.indexOf(file.type)===-1){showModal({title:'Erro!',text:'Use JPG, PNG, GIF ou WEBP.',icon:'error',buttons:true});return;}
    var r=new FileReader();
    r.onload=function(ev){
        var av=document.querySelector('.profile-avatar-box');
        var img=av.querySelector('img'),lt=av.querySelector('.avatar-letra');
        if(img){img.src=ev.target.result;}
        else{if(lt)lt.style.display='none';var ni=document.createElement('img');ni.src=ev.target.result;ni.alt='Avatar';av.insertBefore(ni,av.firstChild);}
    };
    r.readAsDataURL(file);
    document.getElementById('avatarForm').submit();
});

// PESQUISA SERVIDORES
function pesquisar(){
    var s=document.getElementById('pesquisar').value.toLowerCase();
    document.querySelectorAll('.server-card').forEach(function(c){
        c.style.display=(c.dataset.nome.includes(s)||c.dataset.ip.includes(s))?'block':'none';
    });
}
function limparPesquisa(){document.getElementById('pesquisar').value='';pesquisar();}

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
    p(document.getElementById('inicialeditor'));
});
</script>

<?php
echo '<script>if(typeof window.openThemeModal==="undefined"){window.openThemeModal=function(){alert("Modal de temas não carregou. Verifique erros no console.");};window.fecharThemeModal=function(){};}</script>';
try { echo getModalTemasHTML($conn); } catch (\Throwable $e) { echo '<div style="position:fixed;bottom:10px;left:10px;background:#dc2626;color:#fff;padding:10px 16px;border-radius:10px;z-index:99999;font-size:12px;font-family:monospace;max-width:500px;">ERRO Modal: '.htmlspecialchars($e->getMessage()).'</div>'; }
?>

</body>
</html>