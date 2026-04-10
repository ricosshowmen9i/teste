<?php
// @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
$kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
function aleatorio653751($input) {
    session_start();
    error_reporting(0);
    include_once("AegisCore/conexao.php");
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) die("Connection failed: " . mysqli_connect_error());

    $sql = "SELECT * FROM configs";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $nomepainel   = $row['nomepainel'];
        $logo         = $row['logo'];
        $icon         = $row['icon'];
        $csspersonali = $row['corfundologo'];
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset(); 
        session_destroy();
        header('Location: index.php?timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();

    if (!isset($_SESSION['login'])) { 
        header('Location: index.php'); 
        exit(); 
    }
    
    if ($_SESSION['login'] == 'admin') { 
        header('Location: admin/home.php'); 
        exit(); 
    }

    if (isset($_POST['voltaradmin']) && isset($_SESSION['admin564154156'])) {
        $sqladmin    = "SELECT * FROM accounts WHERE id='1'";
        $resultadmin = $conn->query($sqladmin);
        $rowadmin    = $resultadmin->fetch_assoc();
        $t = $_SESSION['token']; 
        $ta = $_SESSION['tokenatual']; 
        $sg = $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'];
        session_unset();
        $_SESSION['login']  = $rowadmin['login']; 
        $_SESSION['senha']  = $rowadmin['senha'];
        $_SESSION['iduser'] = $rowadmin['id'];    
        $_SESSION['last_activity'] = time();
        $_SESSION['token']  = $t; 
        $_SESSION['tokenatual'] = $ta; 
        $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] = $sg;
        echo "<script>window.location.href='admin/home.php';</script>"; 
        exit();
    }

    // ── Trocar tema: antes de qualquer HTML ──
    if (isset($_POST['__setMeuTema']) || isset($_POST['__setLoginTema']) || isset($_POST['__resetTema'])) {
        include_once('AegisCore/temas.php');
        if (isset($_POST['__setMeuTema'])) {
            setarTemaUsuario($conn, intval($_SESSION['iduser']??0), intval($_POST['__setMeuTema']));
        } elseif (isset($_POST['__setLoginTema'])) {
            setarTemaLogin($conn, intval($_POST['__setLoginTema']));
        } elseif (isset($_POST['__resetTema'])) {
            setarTemaUsuario($conn, intval($_SESSION['iduser']??0), 1);
        }
        header('Location: home.php');
        exit();
    }

    $dominio = $_SERVER['HTTP_HOST'];

    $sql1    = "SELECT * FROM atribuidos WHERE userid='" . $_SESSION['iduser'] . "'";
    $result1 = mysqli_query($conn, $sql1);
    $suspenso = 0;
    $validade_conta = '';
    $limite_total = 0;
    $bloquear_acesso = false;
    $motivo_bloqueio = '';
    $dias_vencidos = 0;
    $valor_plano_revenda = '0.00';
    $nome_plano_revenda = '';
    $id_plano_atual = null;
    
    while ($row1 = mysqli_fetch_assoc($result1)) {
        $_SESSION['expira']  = date('d/m/Y', strtotime($row1['expira']));
        $_SESSION['expira_raw'] = $row1['expira'];
        $_SESSION['limite']  = $row1['limite'];
        $_SESSION['tipo']    = $row1['tipo'];
        $suspenso            = $row1['suspenso'];
        $_SESSION['byid']    = $row1['byid'];
        $validade_conta      = $row1['expira'];
        $limite_total        = $row1['limite'];
        $valor_plano_revenda = $row1['valormensal'] ?? '0.00';
        $id_plano_atual      = $row1['id_plano'] ?? null;
    }

    if ($id_plano_atual) {
        $sql_plano = "SELECT * FROM planos_pagamento WHERE id='" . intval($id_plano_atual) . "'";
        $result_plano = mysqli_query($conn, $sql_plano);
        if ($result_plano && mysqli_num_rows($result_plano) > 0) {
            $row_plano = mysqli_fetch_assoc($result_plano);
            $nome_plano_revenda = $row_plano['nome'];
            $valor_plano_revenda = $row_plano['valor'];
        }
    }

    if ($_SESSION['tipo'] == 'Credito') {
        $_SESSION['tipo'] = 'Seus Créditos'; 
        $_SESSION['expira'] = 'Nunca';
    } else {
        $_SESSION['tipo'] = 'Seu Limite';
    }

    $conta_vencida = false;
    if ($_SESSION['tipo'] != 'Seus Créditos' && !empty($validade_conta) && $validade_conta < date('Y-m-d H:i:s')) {
        $conta_vencida = true;
        $data_vencimento = strtotime($validade_conta);
        $data_atual = time();
        $dias_vencidos = floor(($data_atual - $data_vencimento) / (60 * 60 * 24));
    }

    $revendedor_vencido = false;
    $revendedor_id = '';
    if ($_SESSION['byid'] != '1') {
        $rs = mysqli_query($conn, "SELECT * FROM atribuidos WHERE userid='" . $_SESSION['byid'] . "'");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $rr = mysqli_fetch_assoc($rs);
            $dataadmin = $rr['expira'];
            $revendedor_id = $_SESSION['byid'];
            if (!empty($dataadmin) && $dataadmin < date('Y-m-d H:i:s')) {
                $revendedor_vencido = true;
            }
        }
    }

    if ($suspenso == '1') {
        $bloquear_acesso = true;
        $motivo_bloqueio = 'suspenso_admin';
    } elseif ($revendedor_vencido) {
        $bloquear_acesso = true;
        $motivo_bloqueio = 'revendedor_vencido';
    } elseif ($conta_vencida) {
        $bloquear_acesso = true;
        $motivo_bloqueio = 'vencido';
    }

    if ($bloquear_acesso) {
        $tokenvb  = "SELECT * FROM accounts WHERE id='" . $_SESSION['iduser'] . "'";
        $resultvb = mysqli_query($conn, $tokenvb);
        $rowvb    = mysqli_fetch_assoc($resultvb);
        $nome_usuario = $rowvb['login'] ?? $_SESSION['login'];
        $profile_image = $rowvb['profile_image'] ?? '';
        $avatar_url = !empty($profile_image)
            ? 'uploads/profiles/' . $profile_image
            : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['login']) . '&size=100&background=4158D0&color=fff&bold=true';
        
        $sql_data = "SELECT expira FROM atribuidos WHERE userid='" . $_SESSION['iduser'] . "'";
        $result_data = mysqli_query($conn, $sql_data);
        $row_data = mysqli_fetch_assoc($result_data);
        $data_vencimento_exibicao = $row_data['expira'] ?? $validade_conta;
        
        
        

    // ============================================================
    // VALIDAÇÃO DE TOKEN — idêntica ao headeradmin
    // ============================================================
    $telegram = null;
    if (file_exists('vendor/autoload.php')) {
        try {
            require_once 'vendor/autoload.php';
            $telegram = new \Telegram\Bot\Api('6163337935:AAE8uxSRfSkXHthlZtRr-tjpUPxzzxaiUcQ');
        } catch (\Throwable $e) {
            $telegram = null;
        }
    }
$dominio = $_SERVER['HTTP_HOST'];

$token = $_SESSION['token'] ?? '';
$senhatokenacessoss = "123gdsfgbhgdyegryr56y4w5t7Cv3rwrfcrwa3bgs9ume09v58dasdasdadfsdfgm3nut09083r4y289Y45";
$url = 'https://gerenciador.painelcontrole.xyz/vencimento.php?senha=' . $senhatokenacessoss . '&token=' . $token . '&dominio=' . $dominio;

$contextOptions = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        'timeout' => 10,
        'max_redirects' => 1,
        'follow_location' => 1,
        'ignore_errors' => true,
        'protocol_version' => '1.1',
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
];
$context = stream_context_create($contextOptions);
$dataVenc = @file_get_contents($url, false, $context);
$_SESSION['datavencimentotoken'] = $dataVenc;

if (!file_exists('suspenderrev.php')) {
    echo "<script>
    document.addEventListener('DOMContentLoaded',function(){
        showTokenModal('ausente');
    });
    </script>";
    // Não usa exit() para não quebrar o HTML — o modal avisa o usuário
} else {
    include_once 'suspenderrev.php';
}

// Validação de segurança reforçada
$secret_salt = "AtlasSecurity_2024_#@!";
$dominio_atual = $_SERVER['HTTP_HOST'];
$token_sessao = $token;
$hash_esperado = hash('sha256', $token_sessao . $secret_salt . $dominio_atual);

if (
    !isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) ||
    $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado ||
    $_SESSION['tokenatual'] != $token_sessao ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)
) {
    if (function_exists('security')) {
        security();
        if ($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] !== $hash_esperado) {
            echo "<script>document.addEventListener('DOMContentLoaded',function(){ showTokenModal('integridade'); });</script>";
        }
    } else {
        if ($telegram) {
            $telegram->sendMessage([
                'chat_id' => '2017803306',
                'text' => "⚠️ BYPASS DETECTADO: O domínio " . $dominio_atual . " tentou burlar a segurança do token!"
            ]);
        }
        $_SESSION['token_invalido_'] = true;
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ showTokenModal('bypass'); });</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomepainel; ?> - Acesso Bloqueado</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        /* Barra de rolagem invisível — scroll funciona normalmente */

        
        .block-container { max-width: 550px; width: 100%; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .block-card { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 28px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .block-header { padding: 28px 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .block-header.vencido { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .block-header.suspenso { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .block-header.revendedor { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
        .block-icon { font-size: 70px; margin-bottom: 16px; }
        .block-header h2 { color: white; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .block-header p { color: rgba(255,255,255,0.9); font-size: 14px; }
        .block-body { padding: 28px 24px; }
        .user-info { background: rgba(255,255,255,0.05); border-radius: 20px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px; border: 1px solid rgba(255,255,255,0.08); }
        .user-avatar { width: 64px; height: 64px; border-radius: 16px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2); }
        .user-details h3 { color: white; font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .user-details p { color: rgba(255,255,255,0.5); font-size: 12px; }
        .info-card { background: rgba(255,255,255,0.03); border-radius: 20px; padding: 20px; margin-bottom: 28px; border: 1px solid rgba(255,255,255,0.08); }
        .info-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; }
        .info-row:not(:last-child) { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-label { font-size: 13px; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 10px; }
        .info-label i { font-size: 18px; }
        .info-value { font-size: 14px; font-weight: 600; color: white; }
        .info-value.danger { color: #f87171; }
        .info-value.warning { color: #fbbf24; }
        .btn-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { flex: 1; padding: 14px 20px; border: none; border-radius: 14px; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-family: inherit; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .logo-area { text-align: center; margin-bottom: 24px; }
        .logo-area img { max-height: 85px; }
        .contact-info { background: rgba(59,130,246,0.1); border-radius: 12px; padding: 12px; text-align: center; border: 1px solid rgba(59,130,246,0.3); }
        @media (max-width: 640px) {
            .btn-group { flex-direction: column; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
        .side-menu {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.side-menu::-webkit-scrollbar {
    width: 0;
    height: 0;
    background: transparent;
}
    </style>
</head>
<body>
    <div class="block-container">
        <div class="logo-area"><img src="<?php echo $logo; ?>" alt="Logo"></div>
        <div class="block-card">
            <div class="block-header <?php 
                if ($motivo_bloqueio == 'vencido') echo 'vencido';
                elseif ($motivo_bloqueio == 'revendedor_vencido') echo 'revendedor';
                else echo 'suspenso';
            ?>">
                <div class="block-icon">
                    <?php if ($motivo_bloqueio == 'vencido'): ?>
                        <i class='bx bx-calendar-x'></i>
                    <?php elseif ($motivo_bloqueio == 'revendedor_vencido'): ?>
                        <i class='bx bx-store-alt'></i>
                    <?php else: ?>
                        <i class='bx bx-lock-alt'></i>
                    <?php endif; ?>
                </div>
                <h2>
                    <?php if ($motivo_bloqueio == 'vencido'): ?>
                        Conta Vencida!
                    <?php elseif ($motivo_bloqueio == 'revendedor_vencido'): ?>
                        Revendedor com Conta Vencida!
                    <?php else: ?>
                        Conta Suspensa!
                    <?php endif; ?>
                </h2>
                <p>
                    <?php if ($motivo_bloqueio == 'vencido'): ?>
                        Seu acesso foi bloqueado devido à falta de pagamento
                    <?php elseif ($motivo_bloqueio == 'revendedor_vencido'): ?>
                        Seu revendedor não renovou sua assinatura
                    <?php else: ?>
                        Sua conta foi suspensa pelo administrador
                    <?php endif; ?>
                </p>
            </div>
            <div class="block-body">
                <div class="user-info">
                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="user-avatar">
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($nome_usuario); ?></h3>
                        <p><i class='bx bx-user'></i> ID: <?php echo $_SESSION['iduser']; ?></p>
                    </div>
                </div>
                <div class="info-card">
                    <?php if ($motivo_bloqueio == 'vencido'): ?>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-calendar' style="color: #fbbf24;"></i> Data de Vencimento</div>
                            <div class="info-value warning"><?php echo date('d/m/Y', strtotime($data_vencimento_exibicao)); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-time' style="color: #f87171;"></i> Dias Vencidos</div>
                            <div class="info-value danger"><?php echo $dias_vencidos; ?> dias</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-info-circle' style="color: #60a5fa;"></i> Status</div>
                            <div class="info-value warning">Aguardando renovação</div>
                        </div>
                    <?php elseif ($motivo_bloqueio == 'revendedor_vencido'): ?>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-store' style="color: #a78bfa;"></i> ID do Revendedor</div>
                            <div class="info-value"><?php echo $revendedor_id; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-calendar' style="color: #fbbf24;"></i> Status da Revenda</div>
                            <div class="info-value danger">Conta do revendedor vencida</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-info-circle' style="color: #60a5fa;"></i> Solução</div>
                            <div class="info-value warning">Entre em contato com seu revendedor</div>
                        </div>
                    <?php else: ?>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-lock' style="color: #f87171;"></i> Status</div>
                            <div class="info-value danger">Conta suspensa pelo administrador</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class='bx bx-calendar' style="color: #fbbf24;"></i> Data de Vencimento Original</div>
                            <div class="info-value"><?php echo date('d/m/Y', strtotime($data_vencimento_exibicao)); ?></div>
                        </div>
                        <div class="contact-info">
                            <i class='bx bx-support' style="font-size: 20px;"></i>
                            <div class="info-value warning">Entre em contato com o suporte para regularizar sua situação</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="btn-group">
                    <?php if ($motivo_bloqueio == 'vencido'): ?>
                        <a href="AegisCore/renovar_plano.php" class="btn btn-warning"><i class='bx bx-refresh'></i> Renovar Plano</a>
                    <?php elseif ($motivo_bloqueio == 'revendedor_vencido'): ?>
                        <a href="AegisCore/renovacao.php?tipo=revendedor&id=<?php echo $revendedor_id; ?>" class="btn btn-warning"><i class='bx bx-refresh'></i> Renovar Revenda</a>
                        <a href="AegisCore/pagamento.php" class="btn btn-primary"><i class='bx bx-help-circle'></i> Ajuda</a>
                    <?php elseif ($motivo_bloqueio == 'suspenso_admin'): ?>
                        <a href="AegisCore/renovar_plano.php" class="btn btn-warning"><i class='bx bx-credit-card'></i> Pagamento</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-danger"><i class='bx bx-log-out'></i> Sair do Sistema</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
        exit();
    }

    // ========== ACESSO LIBERADO ==========
    $tokenvb  = "SELECT * FROM accounts WHERE id='" . $_SESSION['iduser'] . "'";
    $resultvb = mysqli_query($conn, $tokenvb);
    $rowvb    = mysqli_fetch_assoc($resultvb);
    $tokenvenda         = $rowvb['tokenvenda'];
    $accesstoken        = $rowvb['accesstoken'];
    $acesstokenpaghiper = $rowvb['acesstokenpaghiper'];
    $profile_image = $rowvb['profile_image'] ?? '';
    $nome_usuario = $rowvb['login'] ?? $_SESSION['login'];

    $totalrevenda  = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM atribuidos WHERE byid='" . $_SESSION['iduser'] . "'"));
    $totalusuarios = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM ssh_accounts WHERE byid='" . $_SESSION['iduser'] . "'"));

    // ========== TOTAL VENDIDO - pagamentos_unificado + pagamentos ==========
    $r3 = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COALESCE(SUM(valor), 0) AS valor 
        FROM pagamentos_unificado 
        WHERE revendedor_id='" . $_SESSION['iduser'] . "' 
        AND status='approved'
    "));
    $totalvendido_unificado = $r3['valor'] ?? 0;

    $r3b = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COALESCE(SUM(valor), 0) AS valor 
        FROM pagamentos 
        WHERE byid='" . $_SESSION['iduser'] . "' 
        AND status='Aprovado'
    "));
    $totalvendido_antigo = $r3b['valor'] ?? 0;

    $totalvendido = number_format($totalvendido_unificado + $totalvendido_antigo, 2, ',', '.');

    date_default_timezone_set('America/Sao_Paulo');

    $revendedoresIDs = [];
    $rr2 = mysqli_query($conn, "SELECT * FROM accounts WHERE byid='" . $_SESSION['iduser'] . "'");
    while ($rrr = mysqli_fetch_assoc($rr2)) { 
        $revendedoresIDs[] = $rrr['id']; 
    }

    $totalOnlineRevendedores = 0;
    if (!empty($revendedoresIDs)) {
        $totalOnlineRevendedores = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM ssh_accounts WHERE status='Online' AND byid IN (" . implode(",", $revendedoresIDs) . ")"));
    }

    $totalonline = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM ssh_accounts WHERE status='Online' AND byid='" . $_SESSION['iduser'] . "'"));

    $res = $conn->prepare("SELECT sum(limite) AS v FROM ssh_accounts WHERE byid=?");
    $res->bind_param("s", $_SESSION['iduser']); 
    $res->execute(); 
    $res->bind_result($numusuarios); 
    $res->fetch(); 
    $res->close();

    $res = $conn->prepare("SELECT sum(limite) AS v FROM atribuidos WHERE byid=?");
    $res->bind_param("s", $_SESSION['iduser']); 
    $res->execute(); 
    $res->bind_result($limiteusado); 
    $res->fetch(); 
    $res->close();

    $somalimite = ($numusuarios ?? 0) + ($limiteusado ?? 0);
    $restante   = $_SESSION['limite'] - $somalimite;

    $rv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM ssh_accounts WHERE byid='" . $_SESSION['iduser'] . "' AND expira < NOW()"));
    $totalvencidos = $rv['total'] ?? 0;

    $rl = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM logs WHERE byid='" . $_SESSION['iduser'] . "'"));
    $total_logs = $rl['total'] ?? 0;

    // Upload de foto
    $upload_success = false;
    if (isset($_POST['upload_image'])) {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = 'uploads/profiles/';
                if (!file_exists($dir)) mkdir($dir, 0777, true);
                $fname = 'profile_' . $_SESSION['iduser'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                    if (!empty($rowvb['profile_image']) && file_exists($dir . $rowvb['profile_image'])) {
                        unlink($dir . $rowvb['profile_image']);
                    }
                    $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='" . $_SESSION['iduser'] . "'");
                    $profile_image = $fname;
                    $upload_success = true;
                }
            }
        }
    }

    $mostrar_modal_limite  = isset($_GET['limite'])  && $_GET['limite']  == 1;
    $mostrar_modal_vencido = isset($_GET['vencido']) && $_GET['vencido'] == 1;

    if (isset($_POST['gerarlink'])) {
        $codigo = rand(100000000000, 999999999999);
        $conn->query("UPDATE accounts SET tokenvenda='$codigo' WHERE id='" . $_SESSION['iduser'] . "'");
        echo "<meta http-equiv='refresh' content='0'>";
    }

    $avatar_url = !empty($profile_image)
        ? 'uploads/profiles/' . $profile_image
        : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['login']) . '&size=100&background=4158D0&color=fff&bold=true';

    $pct = $_SESSION['limite'] > 0 ? round(($somalimite / $_SESSION['limite']) * 100) : 0;
    
    
    
  
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomepainel; ?> - Painel</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="AegisCore/temas_visual.css?v=<?php echo time(); ?>">
    <?php
    include_once("AegisCore/temas.php");
    $temaHome = initTemas($conn);
    ?>
    <style>
        <?php echo $csspersonali; ?>

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #0f0c29, #1e1b4b, #0f172a); min-height: 100vh; color: #fff; font-family: 'Inter', sans-serif; }

        
        .side-menu {
    position: fixed; top: 0; left: 0;
    width: 220px; height: 100vh;
    background: linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%);
    border-right: 1px solid rgba(255,255,255,0.06);
    z-index: 1000;
    display: flex; flex-direction: column;
    transition: transform 0.3s ease;
    overflow-y: auto;
    border-radius: 0 20px 20px 0;
    /* ↓ invisível */
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.side-menu::-webkit-scrollbar {
    width: 0;
    height: 0;
    background: transparent;
}
        
        /* ========== MENU LATERAL ========== */
        .side-menu {
            position: fixed; top: 0; left: 0;
            width: 220px; height: 100vh;
            background: linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
            z-index: 1000;
            display: flex; flex-direction: column;
            transition: transform 0.3s ease;
            overflow-y: auto;
            border-radius: 0 20px 20px 0;
        }
        .side-menu-logo { padding: 16px 16px 12px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .side-menu-logo img { max-width: 160px; max-height: 88px; object-fit: contain; }
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

        .ni-home  { background:rgba(65,88,208,0.25);  color:#818cf8; }
        .ni-user  { background:rgba(200,80,192,0.25); color:#e879f9; }
        .ni-store { background:rgba(16,185,129,0.25); color:#34d399; }
        .ni-pay   { background:rgba(245,158,11,0.25); color:#fbbf24; }
        .ni-log   { background:rgba(249,115,22,0.25); color:#fb923c; }
        .ni-whats { background:rgba(59,130,246,0.25); color:#60a5fa; }
        .ni-cfg   { background:rgba(139,92,246,0.25); color:#a78bfa; }
        .ni-exit  { background:rgba(239,68,68,0.25);  color:#f87171; }

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

        /* ========== CONTEÚDO ========== */
        .main-wrap { margin-left: 220px; min-height: 100vh; background: #0f172a; }
        .top-header { position: sticky; top: 0; z-index: 900; background: rgba(26,31,58,0.97); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.07); border-radius: 0 0 18px 18px; margin: 0 12px; padding: 0 18px; height: 54px; display: flex; align-items: center; justify-content: space-between; }
        .header-left { display: flex; align-items: center; gap: 8px; }
        .membro-desde { position: absolute; left: 50%; transform: translateX(-50%); font-size: 11px; color: rgba(255,255,255,0.35); display: flex; align-items: center; gap: 5px; white-space: nowrap; }
        .membro-desde i { color: #a78bfa; }
        .header-right { display: flex; align-items: center; gap: 8px; }
        .btn-menu-mobile { display: none; background: rgba(65,88,208,0.25); border: 1px solid rgba(65,88,208,0.4); color: #818cf8; border-radius: 10px; padding: 5px 12px; font-size: 12px; font-weight: 700; cursor: pointer; letter-spacing: 0.5px; align-items: center; gap: 5px; }
        .page-body { padding: 16px 16px 30px; }

        /* ========== PERFIL CARD ========== */
        .profile-card {
            background: linear-gradient(135deg,#1e293b,#0f172a);
            border-radius: 18px;
            padding: 22px 24px;
            border: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: flex-start;
            gap: 18px;
            margin: 0 auto 16px;
            max-width: 680px;
        }
        .profile-avatar-container { position: relative; flex-shrink: 0; }
        .profile-avatar { width: 140px; height: 140px; border-radius: 16px; object-fit: cover; border: 3px solid rgba(255,255,255,0.12); }
        .avatar-upload { position: absolute; bottom: -3px; right: -3px; background: #4158D0; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #0f172a; }
        .avatar-upload i { color: white; font-size: 11px; }
        .profile-info { flex: 1; min-width: 0; }
        .profile-role { font-size: 10px; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 2px; }
        .profile-info h2 { color: white; font-size: 18px; font-weight: 700; margin-bottom: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .profile-details-row {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }
        .profile-detail-item {
            flex: 1;
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 8px 10px;
            border: 1px solid rgba(255,255,255,0.06);
            text-align: center;
        }
        .pd-label {
            font-size: 9px;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }
        .pd-value {
            font-size: 13px;
            font-weight: 700;
            color: white;
        }
        .pd-value.warning { color: #fbbf24; }
        .pd-value.info { color: #818cf8; }
        .pd-value.success { color: #34d399; }

        .profile-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-venc { padding: 7px 16px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; text-decoration: none; }
        .b-rv { background: linear-gradient(135deg,#f59e0b,#d97706); color: white; }
        .b-cp { background: linear-gradient(135deg,#10b981,#059669); color: white; }
        .btn-venc:hover { transform: translateY(-2px); filter: brightness(1.05); }

        /* ========== CARDS ========== */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(185px,1fr)); gap: 11px; margin-bottom: 16px; }
        .dash-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07); border-radius: 15px; padding: 13px 15px; display: flex; align-items: center; gap: 11px; transition: all 0.25s; text-decoration: none; color: inherit; cursor: pointer; position: relative; overflow: hidden; }
        .dash-card:hover { background: rgba(255,255,255,0.08); transform: translateY(-3px); box-shadow: 0 8px 22px rgba(0,0,0,0.3); color: inherit; }
        .card-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; border-radius: 15px; }
        .card-shapes svg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .dash-card .dci, .dash-card > div:last-child { position: relative; z-index: 1; }
        .dci { width: 46px; height: 46px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 21px; color: white; flex-shrink: 0; }
        .dcl { font-size: 9px; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .dcv { font-size: 20px; font-weight: 700; color: white; line-height: 1.1; }
        .dcv.sm { font-size: 13px; font-weight: 600; }
        .dcs { font-size: 10px; color: rgba(255,255,255,0.28); margin-top: 1px; }

        /* ========== ONLINE CARD - DESTAQUE SEM MUDAR COR ========== */
        .dash-card.online-highlight .dcl {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.5);
        }
        .dash-card.online-highlight .dcv {
            font-size: 26px;
            font-weight: 800;
        }
        .dash-card.online-highlight .dcs {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
        }
        
        /* Pulsing icon - azul e verde */
        @keyframes pulseOnline {
            0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
            50% { box-shadow: 0 0 0 8px rgba(59,130,246,0.15); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        .dci.ic-online-pulse {
            animation: pulseOnline 2s ease-in-out infinite;
        }

        .ic-1  { background:linear-gradient(135deg,#4158D0,#6366f1); }
        .ic-2  { background:linear-gradient(135deg,#C850C0,#e879f9); }
        .ic-3  { background:linear-gradient(135deg,#10b981,#34d399); }
        .ic-4  { background:linear-gradient(135deg,#f59e0b,#fbbf24); }
        .ic-5  { background:linear-gradient(135deg,#3b82f6,#60a5fa); }
        .ic-6  { background:linear-gradient(135deg,#8b5cf6,#a78bfa); }
        .ic-7  { background:linear-gradient(135deg,#ec4899,#f472b6); }
        .ic-8  { background:linear-gradient(135deg,#14b8a6,#2dd4bf); }
        .ic-9  { background:linear-gradient(135deg,#ef4444,#f87171); }
        .ic-10 { background:linear-gradient(135deg,#f97316,#fb923c); }

        /* ========== CARD LIMITE ========== */
        .limite-card { background: linear-gradient(135deg,#1e293b,#0f172a); border-radius: 18px; padding: 20px 22px; border: 1px solid rgba(255,255,255,0.07); margin-bottom: 16px; position: relative; overflow: hidden; }
        .limite-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; position: relative; z-index: 1; }
        .limite-header h4 { color: white; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .limite-header h4 i { font-size: 18px; color: #a78bfa; }
        .limite-pct { font-size: 28px; font-weight: 800; background: linear-gradient(135deg,#4158D0,#C850C0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .limite-body { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; position: relative; z-index: 1; }
        .limite-chart { flex-shrink: 0; }
        .limite-stats { flex: 1; display: flex; flex-direction: column; gap: 10px; min-width: 180px; }
        .stat-row { display: flex; align-items: center; gap: 10px; }
        .stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
        .stat-info { flex: 1; }
        .stat-lbl { font-size: 10px; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-val { font-size: 18px; font-weight: 700; color: white; }
        .stat-bar { height: 5px; background: rgba(255,255,255,0.08); border-radius: 10px; margin-top: 4px; overflow: hidden; }
        .stat-fill { height: 100%; border-radius: 10px; transition: width 0.6s ease; }

        .links-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 18px 20px; margin-bottom: 28px; }
        .links-card h4 { color: white; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        .links-card p  { color: rgba(255,255,255,0.3); font-size: 11px; margin-bottom: 12px; }
        .link-group { margin-bottom: 10px; }
        .link-lbl { font-size: 9px; color: rgba(255,255,255,0.28); text-transform: uppercase; margin-bottom: 3px; }
        .link-group input { width: 100%; padding: 7px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; color: white; font-size: 12px; }

        .back-button { position: fixed; bottom: 18px; right: 18px; background: #4158D0; color: white; border-radius: 50%; width: 44px; height: 44px; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.4); z-index: 9999; text-decoration: none; font-size: 19px; }
        .menu-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; }
        .menu-overlay.active { display: block; }
        
        .btn-warning-sm { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; padding: 6px 14px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; text-decoration: none; border: none; cursor: pointer; font-family: inherit; }
        .btn-warning-sm:hover { transform: translateY(-2px); filter: brightness(1.05); }

        @media (max-width: 1024px) {
            .side-menu { transform: translateX(-100%); }
            .side-menu.open { transform: translateX(0); }
            .main-wrap { margin-left: 0 !important; }
            .btn-menu-mobile { display: inline-flex !important; }
        }
        @media (max-width: 768px) {
            .cards-grid { grid-template-columns: 1fr 1fr !important; }
            /* MOBILE: Foto ao lado, info do lado */
            .profile-card {
                flex-direction: row;
                align-items: flex-start;
                padding: 16px;
                gap: 14px;
                max-width: 100%;
            }
            .profile-avatar {
                width: 100px !important;
                height: 100px !important;
            }
            .profile-avatar-container {
                width: 90px;
                height: 90px;
            }
            .profile-info h2 {
                font-size: 15px;
            }
            .profile-details-row {
                gap: 6px;
            }
            .profile-detail-item {
                padding: 6px 6px;
            }
            .pd-label { font-size: 8px; }
            .pd-value { font-size: 12px; }
            .profile-actions {
                justify-content: flex-start;
            }
            .limite-body { flex-direction: column; }
            .membro-desde { display: none; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaHome)); ?>">

<div class="menu-overlay" id="menuOverlay"></div>

<aside class="side-menu" id="sideMenu">
    <div class="side-menu-logo"><img src="<?php echo $logo; ?>" alt="logo"></div>
    <nav class="side-nav">
        <div class="nav-sec-title">Principal</div>
        <a href="home.php" class="nav-link-main active">
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
            <a href="AegisCore/criarusuario.php"><span class="sub-icon si-1"><i class='bx bx-user-plus'></i></span>Criar Usuário</a>
            <a href="AegisCore/criarteste.php"><span class="sub-icon si-2"><i class='bx bx-test-tube'></i></span>Criar Teste</a>
            <a href="AegisCore/listarusuarios.php"><span class="sub-icon si-3"><i class='bx bx-list-ul'></i></span>Listar Usuários</a>
            <a href="AegisCore/listaexpirados.php"><span class="sub-icon si-4"><i class='bx bx-time'></i></span>Expirados</a>
            <a href="AegisCore/onlines.php"><span class="sub-icon si-5"><i class='bx bx-wifi'></i></span>Onlines</a>
            <a href="AegisCore/deviceid.php"><span class="sub-icon si-10"><i class='bx bx-devices'></i></span>Device ID</a>
        </div>
        <button class="nav-link-main" onclick="toggleSub('sRevenda', this)">
            <span class="nav-icon ni-store"><i class='bx bx-store-alt'></i></span>
            <span class="nav-text">Revendedores</span>
            <i class='bx bx-chevron-right nav-arrow'></i>
        </button>
        <div class="nav-sub" id="sRevenda">
            <a href="AegisCore/criarrevenda.php"><span class="sub-icon si-6"><i class='bx bx-user-check'></i></span>Criar Revenda</a>
            <a href="AegisCore/listarrevendedores.php"><span class="sub-icon si-7"><i class='bx bx-group'></i></span>Listar Revendedores</a>
        </div>
        <div class="nav-sec-title">Loja</div>
        <a href="aplicativos.php" class="nav-link-main">
            <span class="nav-icon ni-store"><i class='bx bxl-play-store' style="color: #10b981;"></i></span>
            <span class="nav-text">Loja de Apps</span>
        </a>
        <div class="nav-sec-title">Onlines</div>
        <a href="AegisCore/onlines.php" class="nav-link-main">
            <span class="nav-icon" style="background: rgba(16,185,129,0.25); color: #10b981;"><i class='bx bx-wifi'></i></span>
            <span class="nav-text">Onlines</span>
        </a>
        <a href="AegisCore/onlines_revendas.php" class="nav-link-main">
            <span class="nav-icon" style="background: rgba(59,130,246,0.25); color: #3b82f6;"><i class='bx bx-wifi'></i></span>
            <span class="nav-text">Onlines Revendas</span>
        </a>
        <a href="AegisCore/suspensoes_limite.php" class="nav-link-main">
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
            <a href="AegisCore/formaspag.php"><span class="sub-icon si-8"><i class='bx bx-cog'></i></span>Configurar</a>
            <a href="AegisCore/listadepag.php"><span class="sub-icon si-9"><i class='bx bx-receipt'></i></span>Listar Pagamentos</a>
            <a href="AegisCore/cupons.php"><span class="sub-icon si-10"><i class='bx bx-purchase-tag'></i></span>Cupons</a>
            <a href="AegisCore/planos_pagamento.php"><span class="sub-icon si-1"><i class='bx bx-dollar-circle'></i></span>Planos</a>
        </div>
        <div class="nav-sec-title">Sistema</div>
        <a href="AegisCore/logs.php" class="nav-link-main">
            <span class="nav-icon ni-log"><i class='bx bx-history'></i></span>
            <span class="nav-text">Logs</span>
        </a>
        <a href="AegisCore/whatsconect.php" class="nav-link-main">
            <span class="nav-icon ni-whats"><i class='bx bxl-whatsapp'></i></span>
            <span class="nav-text">WhatsApp</span>
        </a>
        <a href="AegisCore/editconta.php" class="nav-link-main">
            <span class="nav-icon ni-cfg"><i class='bx bx-cog'></i></span>
            <span class="nav-text">Conta</span>
        </a>
        <a href="#" class="nav-link-main" onclick="openThemeModal(); return false;">
            <span class="nav-icon" style="background:linear-gradient(135deg, var(--primary, #10b981), var(--secondary, #C850C0));color:#fff"><i class='bx bx-palette'></i></span>
            <span class="nav-text">Temas</span>
        </a>
        <a href="logout.php" class="nav-link-main">
            <span class="nav-icon ni-exit"><i class='bx bx-power-off'></i></span>
            <span class="nav-text">Sair</span>
        </a>
    </nav>
</aside>

<div class="main-wrap">
    <div class="top-header">
        <div class="header-left">
            <button class="btn-menu-mobile" id="mobileMenuBtn">
                <i class='bx bx-menu'></i> MENU
            </button>
        </div>
        <div class="membro-desde">
            <i class='bx bx-calendar-check'></i>
            Membro desde: <?php echo date('d/m/Y'); ?>
        </div>
        <div class="header-right"></div>
    </div>

    <div class="page-body" id="inicialeditor">
        <!-- ========== PERFIL ========== -->
        <div class="profile-card">
            <div class="profile-avatar-container">
                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="profile-avatar" id="profile-avatar">
                <form method="post" enctype="multipart/form-data" id="avatar-form">
                    <label for="file-input" class="avatar-upload"><i class='bx bx-camera'></i></label>
                    <input type="file" id="file-input" name="profile_image" accept="image/*" style="display:none;">
                    <input type="submit" name="upload_image" id="upload-submit" style="display:none;">
                </form>
            </div>
            <div class="profile-info">
                <div class="profile-role">Revendedor</div>
                <h2><?php echo htmlspecialchars($_SESSION['login']); ?></h2>
                <div class="profile-details-row">
                    <div class="profile-detail-item">
                        <div class="pd-label"><i class='bx bx-time' style="font-size:10px;color:#fbbf24;"></i> Vencimento</div>
                        <div class="pd-value warning"><?php echo $_SESSION['expira']; ?></div>
                    </div>
                    <div class="profile-detail-item">
                        <div class="pd-label"><i class='bx bx-layer' style="font-size:10px;color:#818cf8;"></i> Limite</div>
                        <div class="pd-value info"><?php echo (int)$_SESSION['limite']; ?></div>
                    </div>
                    <div class="profile-detail-item">
                        <div class="pd-label"><i class='bx bx-dollar' style="font-size:10px;color:#34d399;"></i> Valor Plano</div>
                        <div class="pd-value success">R$ <?php echo number_format((float)$valor_plano_revenda, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="AegisCore/renovar_plano.php" class="btn-venc b-rv"><i class='bx bx-refresh'></i> Renovar Agora</a>
                    <a href="AegisCore/planos_revenda.php?tipo=comprar" class="btn-venc b-cp"><i class='bx bx-plus-circle'></i> Comprar</a>
                </div>
            </div>
        </div>

        <!-- ========== CARDS ========== -->
        <div class="cards-grid">
        <?php
        $shapes_cards = [
            '<circle cx="88%" cy="16%" r="22" fill="rgba(99,102,241,0.20)"/><rect x="76%" y="58%" width="18" height="18" rx="4" fill="rgba(129,140,248,0.15)" transform="rotate(20,85,67)"/><circle cx="95%" cy="74%" r="12" fill="rgba(165,180,252,0.12)"/>',
            '<circle cx="90%" cy="13%" r="20" fill="rgba(232,121,249,0.20)"/><rect x="78%" y="62%" width="16" height="16" rx="3" fill="rgba(244,114,182,0.15)" transform="rotate(-15,86,70)"/><circle cx="94%" cy="72%" r="10" fill="rgba(251,182,206,0.12)"/>',
            '<circle cx="87%" cy="15%" r="24" fill="rgba(52,211,153,0.18)"/><rect x="80%" y="60%" width="17" height="17" rx="4" fill="rgba(16,185,129,0.14)" transform="rotate(25,88,68)"/><circle cx="93%" cy="78%" r="11" fill="rgba(110,231,183,0.12)"/>',
            '<circle cx="91%" cy="14%" r="21" fill="rgba(251,191,36,0.20)"/><rect x="77%" y="63%" width="15" height="15" rx="3" fill="rgba(245,158,11,0.15)" transform="rotate(10,84,70)"/><circle cx="95%" cy="70%" r="13" fill="rgba(253,224,71,0.12)"/>',
            '<circle cx="89%" cy="16%" r="23" fill="rgba(96,165,250,0.19)"/><rect x="79%" y="61%" width="16" height="16" rx="4" fill="rgba(59,130,246,0.14)" transform="rotate(-20,87,69)"/><circle cx="94%" cy="76%" r="10" fill="rgba(147,197,253,0.12)"/>',
            '<circle cx="86%" cy="14%" r="22" fill="rgba(167,139,250,0.20)"/><rect x="78%" y="64%" width="17" height="17" rx="3" fill="rgba(139,92,246,0.14)" transform="rotate(15,86,72)"/><circle cx="93%" cy="75%" r="12" fill="rgba(196,181,253,0.12)"/>',
            '<circle cx="90%" cy="12%" r="21" fill="rgba(244,114,182,0.20)"/><rect x="80%" y="62%" width="15" height="15" rx="4" fill="rgba(236,72,153,0.14)" transform="rotate(-10,87,69)"/><circle cx="95%" cy="73%" r="11" fill="rgba(251,207,232,0.12)"/>',
            '<circle cx="88%" cy="15%" r="23" fill="rgba(45,212,191,0.19)"/><rect x="79%" y="60%" width="16" height="16" rx="3" fill="rgba(20,184,166,0.15)" transform="rotate(20,87,68)"/><circle cx="94%" cy="77%" r="10" fill="rgba(153,246,228,0.12)"/>',
            '<circle cx="91%" cy="13%" r="22" fill="rgba(248,113,113,0.20)"/><rect x="78%" y="63%" width="17" height="17" rx="4" fill="rgba(239,68,68,0.14)" transform="rotate(-15,86,71)"/><circle cx="95%" cy="71%" r="12" fill="rgba(254,202,202,0.12)"/>',
            '<circle cx="89%" cy="15%" r="21" fill="rgba(251,146,60,0.20)"/><rect x="80%" y="61%" width="15" height="15" rx="3" fill="rgba(249,115,22,0.15)" transform="rotate(12,87,68)"/><circle cx="94%" cy="76%" r="11" fill="rgba(254,215,170,0.12)"/>',
        ];

        $cards = [
            ['link'=>'AegisCore/criarusuario.php',      'ic'=>'ic-1',  'icon'=>'bx-user-plus',    'lbl'=>'Ação',        'val'=>'Criar Usuário',      'sub'=>'Adicionar novo',                    'sm'=>true,  'online'=>false],
            ['link'=>'AegisCore/criarteste.php',         'ic'=>'ic-2',  'icon'=>'bx-test-tube',    'lbl'=>'Ação',        'val'=>'Criar Teste',        'sub'=>'Teste p/ clientes',                 'sm'=>true,  'online'=>false],
            ['link'=>'',                             'ic'=>'ic-7',  'icon'=>'bx-wifi',         'lbl'=>'Online',      'val'=>$totalonline,         'sub'=>'Revendedor: '.$totalOnlineRevendedores,'sm'=>false,'online'=>true],
            ['link'=>'AegisCore/listarusuarios.php',     'ic'=>'ic-3',  'icon'=>'bx-user',         'lbl'=>'Usuários',    'val'=>$totalusuarios,       'sub'=>'Total cadastrados',                 'sm'=>false, 'online'=>false],
            ['link'=>'',                             'ic'=>'ic-4',  'icon'=>'bx-dollar',       'lbl'=>'Vendas',      'val'=>'R$ '.$totalvendido,  'sub'=>'Total aprovado',                    'sm'=>true,  'online'=>false],
            
            ['link'=>'AegisCore/listaexpirados.php',     'ic'=>'ic-6',  'icon'=>'bx-error-circle', 'lbl'=>'Vencidos',    'val'=>$totalvencidos,       'sub'=>'Usuários expirados',                'sm'=>false, 'online'=>false],
            ['link'=>'AegisCore/listarrevendedores.php', 'ic'=>'ic-8',  'icon'=>'bx-store-alt',    'lbl'=>'Revendedores','val'=>$totalrevenda,        'sub'=>'Total cadastrados',                 'sm'=>false, 'online'=>false],
            ['link'=>'AegisCore/logs.php',               'ic'=>'ic-9',  'icon'=>'bx-history',      'lbl'=>'Logs',        'val'=>$total_logs,          'sub'=>'Total de logs',                     'sm'=>false, 'online'=>false],
            ['link'=>'',                             'ic'=>'ic-10', 'icon'=>'bx-bar-chart-alt','lbl'=>'Restante',    'val'=>$restante,            'sub'=>'de '.$_SESSION['limite'].' total',  'sm'=>false, 'online'=>false],
        ];

        foreach ($cards as $i => $c):
            if ($i === 9 && $_SESSION['tipo'] !== 'Seu Limite') continue;
            $tag  = !empty($c['link']) ? 'a' : 'div';
            $href = !empty($c['link']) ? ' href="'.$c['link'].'"' : '';
            $sh   = $shapes_cards[$i] ?? '';
            $onlineClass = $c['online'] ? ' online-highlight' : '';
            $iconClass = $c['ic'];
            if ($c['online']) $iconClass .= ' ic-online-pulse';
        ?>
        <<?php echo $tag.$href; ?> class="dash-card<?php echo $onlineClass; ?>">
            <div class="card-shapes"><svg xmlns="http://www.w3.org/2000/svg"><?php echo $sh; ?></svg></div>
            <div class="dci <?php echo $iconClass; ?>"><i class='bx <?php echo $c['icon']; ?>'></i></div>
            <div>
                <div class="dcl"><?php echo $c['lbl']; ?></div>
                <div class="dcv<?php echo $c['sm']?' sm':''; ?>"><?php echo $c['val']; ?></div>
                <div class="dcs"><?php echo $c['sub']; ?></div>
            </div>
        </<?php echo $tag; ?>>
        <?php endforeach; ?>
        </div>

        <!-- Card Limite -->
        <div class="limite-card">
            <div class="card-shapes">
                <svg xmlns="http://www.w3.org/2000/svg">
                    <circle cx="95%" cy="15%" r="50" fill="rgba(65,88,208,0.09)"/>
                    <circle cx="5%"  cy="85%" r="35" fill="rgba(200,80,192,0.09)"/>
                    <rect x="88%" y="55%" width="28" height="28" rx="6" fill="rgba(245,158,11,0.07)" transform="rotate(20,102,69)"/>
                    <circle cx="50%" cy="5%"  r="18" fill="rgba(16,185,129,0.07)"/>
                    <rect x="2%" y="15%" width="22" height="22" rx="5" fill="rgba(59,130,246,0.07)" transform="rotate(-15,13,26)"/>
                </svg>
            </div>
            <div class="limite-header">
                <h4><i class='bx bx-bar-chart-alt-2'></i> Uso do Limite</h4>
                <div class="limite-pct"><?php echo $pct; ?>%</div>
            </div>
            <div class="limite-body">
                <div class="limite-chart"><div id="chartLimite"></div></div>
                <div class="limite-stats">
                    <div class="stat-row">
                        <div class="stat-icon" style="background:rgba(65,88,208,0.2);color:#818cf8"><i class='bx bx-user'></i></div>
                        <div class="stat-info">
                            <div class="stat-lbl">Usuários</div>
                            <div class="stat-val"><?php echo (int)($numusuarios??0); ?></div>
                            <div class="stat-bar"><div class="stat-fill" style="width:<?php echo $_SESSION['limite']>0?round((($numusuarios??0)/$_SESSION['limite'])*100):0; ?>%;background:linear-gradient(90deg,#4158D0,#6366f1)"></div></div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-icon" style="background:rgba(200,80,192,0.2);color:#e879f9"><i class='bx bx-store-alt'></i></div>
                        <div class="stat-info">
                            <div class="stat-lbl">Revendedores</div>
                            <div class="stat-val"><?php echo (int)($limiteusado??0); ?></div>
                            <div class="stat-bar"><div class="stat-fill" style="width:<?php echo $_SESSION['limite']>0?round((($limiteusado??0)/$_SESSION['limite'])*100):0; ?>%;background:linear-gradient(90deg,#C850C0,#e879f9)"></div></div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-icon" style="background:rgba(16,185,129,0.2);color:#34d399"><i class='bx bx-check-circle'></i></div>
                        <div class="stat-info">
                            <div class="stat-lbl">Disponível</div>
                            <div class="stat-val"><?php echo max(0,(int)$restante); ?></div>
                            <div class="stat-bar"><div class="stat-fill" style="width:<?php echo $_SESSION['limite']>0?round((max(0,$restante)/$_SESSION['limite'])*100):0; ?>%;background:linear-gradient(90deg,#10b981,#34d399)"></div></div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-icon" style="background:rgba(255,204,112,0.15);color:#FFCC70"><i class='bx bx-bar-chart'></i></div>
                        <div class="stat-info">
                            <div class="stat-lbl">Total do Plano</div>
                            <div class="stat-val"><?php echo (int)$_SESSION['limite']; ?></div>
                            <div class="stat-bar"><div class="stat-fill" style="width:100%;background:linear-gradient(90deg,#FFCC70,#f59e0b)"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($accesstoken != '' || $acesstokenpaghiper != ''): ?>
        <div class="links-card">
            <h4>Links de Venda</h4>
            <p>Compartilhe com seus clientes.</p>
            <div class="link-group"><div class="link-lbl">Para Novos Revendedores</div><input type="text" class="form-control" value="https://<?php echo $dominio; ?>/revenda.php?token=<?php echo $tokenvenda; ?>" readonly></div>
            <div class="link-group"><div class="link-lbl">Bot de Vendas</div><input type="text" class="form-control" value="https://<?php echo $dominio; ?>/planos_revenda.php?token=<?php echo $tokenvenda; ?>" readonly></div>
            <div class="link-group"><div class="link-lbl">Teste Automático</div><input type="text" class="form-control" value="https://<?php echo $dominio; ?>/criarteste.php?token=<?php echo $tokenvenda; ?>" readonly></div>
            <form action="home.php" method="post" style="margin-top:12px;">
                <button class="btn-warning-sm" type="submit" name="gerarlink"><i class='bx bx-refresh'></i> Gerar Novo Link</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['admin564154156'])): ?>
<form method="post" action="home.php">
    <button type="submit" name="voltaradmin" class="back-button"><i class='bx bx-arrow-back'></i></button>
</form>
<?php endif; ?>

<script>
function toggleSub(id, btn) {
    var isOpen = document.getElementById(id).classList.contains('open');
    document.querySelectorAll('.nav-sub.open').forEach(function(sub) { sub.classList.remove('open'); });
    document.querySelectorAll('.nav-link-main.open').forEach(function(b) { b.classList.remove('open'); });
    if (!isOpen) {
        document.getElementById(id).classList.add('open');
        btn.classList.add('open');
    }
}

var overlay = document.getElementById('menuOverlay');
var sideMenu = document.getElementById('sideMenu');
document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
    sideMenu.classList.add('open'); 
    overlay.classList.add('active');
});
overlay?.addEventListener('click', function() {
    sideMenu.classList.remove('open'); 
    overlay.classList.remove('active');
});
document.addEventListener('keydown', function(e){ 
    if(e.key==='Escape'){ 
        sideMenu.classList.remove('open'); 
        overlay.classList.remove('active'); 
    }
});

document.getElementById('file-input')?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        var r = new FileReader();
        r.onload = function(e){ document.getElementById('profile-avatar').src = e.target.result; };
        r.readAsDataURL(this.files[0]);
    }
    document.getElementById('upload-submit')?.click();
});

new ApexCharts(document.querySelector("#chartLimite"), {
    series: [<?php echo (int)($numusuarios??0); ?>, <?php echo (int)($limiteusado??0); ?>, <?php echo max(0,(int)$restante); ?>],
    chart: { type:'donut', height:180, background:'transparent' },
    labels: ['Usuários','Revendedores','Disponível'],
    colors: ['#4158D0','#C850C0','#10b981'],
    dataLabels: { enabled:false }, legend: { show:false },
    plotOptions: { pie: { donut: { size:'65%', labels: { show:true, total: { show:true, label:'Usado', color:'#fff', formatter:function(){ return '<?php echo $pct; ?>%'; } } } } } },
    stroke: { width:0 }, theme: { mode:'dark' }
}).render();
</script>

<?php
    $sql_cfg = "SELECT * FROM configs"; $result_cfg = $conn->query($sql_cfg);
    if ($result_cfg->num_rows > 0) { $row_cfg = $result_cfg->fetch_assoc(); $textopersonali = $row_cfg["textoedit"]; }
    $tradutor = $textopersonali ?? ''; $linhas = explode("\n", $tradutor); $substituicoes = [];
    foreach ($linhas as $linha) { $par = explode("=", $linha); if (count($par) === 2) { $substituicoes[] = ['original'=>trim($par[0]),'substituto'=>trim($par[1])]; } }
?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    var s = <?php echo json_encode($substituicoes); ?>;
    function p(el) { if(el.nodeType===3){s.forEach(function(x){el.textContent=el.textContent.replace(x.original,x.substituto);});}else{for(var i=0;i<el.childNodes.length;i++)p(el.childNodes[i]);} }
    p(document.getElementById('inicialeditor').parentNode);
});






// ============================================================
// MODAL UNIFICADO — mesmo visual dark do home
// ============================================================
function showModal(opts) {
    var icon = opts.icon || 'info';
    var title = opts.title || '';
    var text = opts.text || '';
    var timer = opts.timer || 0;
    var buttons = opts.buttons !== false;
    var onConfirm = opts.onConfirm || null;
    var isDanger = opts.dangerMode || false;

    var iconMap = {
        success: { bg:'rgba(16,185,129,0.15)', html:'<i class="bx bx-check-circle" style="font-size:54px;color:#10b981;"></i>' },
        error:   { bg:'rgba(239,68,68,0.15)',  html:'<i class="bx bx-x-circle" style="font-size:54px;color:#ef4444;"></i>' },
        warning: { bg:'rgba(245,158,11,0.15)', html:'<i class="bx bx-error" style="font-size:54px;color:#f59e0b;"></i>' },
        info:    { bg:'rgba(59,130,246,0.15)', html:'<i class="bx bx-info-circle" style="font-size:54px;color:#3b82f6;"></i>' },
        token:   { bg:'rgba(239,68,68,0.15)',  html:'<i class="bx bx-lock-alt" style="font-size:54px;color:#ef4444;"></i>' },
    };
    var ic = iconMap[icon] || iconMap['info'];

    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:flex;align-items:center;justify-content:center;animation:fadeInOv .2s ease;';

    var box = document.createElement('div');
    box.style.cssText = 'background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:28px;padding:36px 32px;max-width:440px;width:90%;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 60px rgba(0,0,0,0.6);text-align:center;animation:slideUpM .25s ease;font-family:\'Inter\',sans-serif;';

    var icDiv = document.createElement('div');
    icDiv.style.cssText = 'width:80px;height:80px;border-radius:50%;background:'+ic.bg+';display:flex;align-items:center;justify-content:center;margin:0 auto 18px;';
    icDiv.innerHTML = ic.html;

    var titleEl = document.createElement('h3');
    titleEl.style.cssText = 'color:#fff;font-size:20px;font-weight:700;margin:0 0 10px;';
    titleEl.textContent = title;

    var textEl = document.createElement('p');
    textEl.style.cssText = 'color:rgba(255,255,255,0.6);font-size:14px;margin:0 0 24px;line-height:1.6;';
    textEl.innerHTML = text;

    box.appendChild(icDiv);
    box.appendChild(titleEl);
    box.appendChild(textEl);

    var confirmBtn, cancelBtn;

    if (buttons) {
        var btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:10px;justify-content:center;flex-wrap:wrap;';

        confirmBtn = document.createElement('button');
        var confirmLabel = (Array.isArray(buttons) && buttons[1]) ? buttons[1] : 'OK';
        confirmBtn.textContent = confirmLabel;
        var bg = isDanger ? 'linear-gradient(135deg,#dc2626,#b91c1c)' : 'linear-gradient(135deg,#4158D0,#6366f1)';
        confirmBtn.style.cssText = 'padding:11px 28px;border:none;border-radius:14px;background:'+bg+';color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;';
        confirmBtn.onmouseover = function(){ this.style.filter='brightness(1.1)';this.style.transform='translateY(-1px)'; };
        confirmBtn.onmouseout  = function(){ this.style.filter='';this.style.transform=''; };

        btnRow.appendChild(confirmBtn);

        if (Array.isArray(buttons) && buttons[0]) {
            cancelBtn = document.createElement('button');
            cancelBtn.textContent = buttons[0];
            cancelBtn.style.cssText = 'padding:11px 28px;border:1px solid rgba(255,255,255,0.15);border-radius:14px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.7);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s;';
            cancelBtn.onmouseover = function(){ this.style.background='rgba(255,255,255,0.12)'; };
            cancelBtn.onmouseout  = function(){ this.style.background='rgba(255,255,255,0.06)'; };
            btnRow.appendChild(cancelBtn);
        }

        box.appendChild(btnRow);
    }

    overlay.appendChild(box);
    document.body.appendChild(overlay);

    var result = {
        _resolvers: [],
        then: function(fn){ this._resolvers.push(fn); return this; }
    };

    function resolve(val) {
        if (document.body.contains(overlay)) document.body.removeChild(overlay);
        result._resolvers.forEach(function(fn){ fn(val); });
        if (onConfirm) onConfirm(val);
    }

    if (confirmBtn) confirmBtn.onclick = function(){ resolve(true); };
    if (cancelBtn)  cancelBtn.onclick  = function(){ resolve(false); };

    if (timer > 0) {
        setTimeout(function(){ resolve(true); }, timer);
    }

    return result;
}

window.swal = function(opts, text, icon) {
    if (typeof opts === 'string') return showModal({ title: opts, text: text||'', icon: icon||'info' });
    return showModal(opts);
};

function showTokenModal(motivo) {
    var msgs = {
        bypass:     { title:'Bypass Detectado!',               text:'Tentativa de burlar a segurança foi identificada.<br><br><b style="color:#f87171;">Entre em contato com o suporte</b> para regularizar sua situação.' },
        ausente:    { title:'Arquivo de Segurança Ausente!',   text:'O arquivo de validação não foi encontrado no servidor.<br><br><b style="color:#f87171;">Entre em contato com o suporte</b> para corrigir.' },
        invalido:   { title:'Token Inválido!',                 text:'Seu token de acesso é inválido ou expirou.<br><br><b style="color:#fbbf24;">Entre em contato com o administrador</b> para renovar seu acesso.' },
        integridade:{ title:'Erro de Integridade de Segurança!',text:'A verificação de integridade falhou.<br><br><b style="color:#f87171;">Entre em contato com o suporte</b> imediatamente.' },
    };
    var m = msgs[motivo] || msgs.invalido;
    showModal({
        title: m.title,
        text: m.text + '<br><br><small style="color:rgba(255,255,255,0.3);">Domínio: ' + window.location.hostname + '</small>',
        icon: 'token',
        buttons: ['Fechar', 'Ir para Login'],
        onConfirm: function(c){ if(c) window.location.href = 'index.php'; }
    });
}

// Keyframes
(function(){
    if (!document.getElementById('modal-anim-style')) {
        var s = document.createElement('style');
        s.id = 'modal-anim-style';
        s.textContent = '@keyframes fadeInOv{from{opacity:0}to{opacity:1}}@keyframes slideUpM{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(s);
    }
})();


<?php if (isset($_SESSION['entrou_como_admin']) && $_SESSION['entrou_como_admin'] === true): ?>
<div id="voltarAdminBar" style="
    position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    padding: 10px 20px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 4px 20px rgba(124,58,237,0.4);
    font-family: 'Inter', 'Poppins', sans-serif;
">
    <div style="display:flex; align-items:center; gap:10px; color:white;">
        <i class='bx bx-info-circle' style="font-size:20px;"></i>
        <span style="font-size:13px; font-weight:600;">
            Você está logado como: <strong style="color:#c4b5fd;"><?php echo htmlspecialchars($_SESSION['login']); ?></strong>
        </span>
    </div>
    <button onclick="voltarAdmin()" style="
        background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
        color: white; padding: 6px 16px; border-radius: 8px;
        font-size: 12px; font-weight: 700; cursor: pointer;
        display: flex; align-items: center; gap: 6px;
        transition: all 0.2s;
    " onmouseover="this.style.background='rgba(255,255,255,0.3)'"
       onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        <i class='bx bx-arrow-back'></i> Voltar ao Painel Admin
    </button>
</div>
<div style="height: 48px;"></div><!-- Espaçamento para não cobrir o conteúdo -->

<script>
function voltarAdmin() {
    fetch('voltar_admin.php', { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.success) {
            window.location.href = resp.redirect;
        }
    })
    .catch(function() {
        window.location.href = 'admin/home.php';
    });
}
</script>
<?php endif; ?>

</script>

<?php include_once("AegisCore/modal_temas.php"); ?>

</body>
</html>
<?php
    }
    aleatorio653751($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
