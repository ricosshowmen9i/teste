<?php
session_start();
error_reporting(0);

include_once("../AegisCore/conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Verificar login
if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_login'])) {
    header('Location: ../index.php');
    exit;
}

$usuario_id    = $_SESSION['usuario_id'];
$usuario_login = $_SESSION['usuario_login'];

// ========== BUSCAR PAGAMENTOS DA TABELA UNIFICADA ==========
// Lê pendentes E aprovados de todos os tipos do usuário logado
$pagamentos = [];

// Verificar se a tabela unificada existe
$tabela_existe = mysqli_query($conn, "SHOW TABLES LIKE 'pagamentos_unificado'");

if ($tabela_existe && mysqli_num_rows($tabela_existe) > 0) {

    // Buscar por user_id
    $sql = "SELECT * FROM pagamentos_unificado
            WHERE user_id = ?
            ORDER BY data_criacao DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $usuario_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $row['forma_pagamento'] = 'PIX';
            // Normalizar campo data_pagamento para o template
            if (empty($row['data_pagamento'])) {
                $row['data_pagamento'] = $row['data_criacao'];
            }
            $pagamentos[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    // Se não encontrou por user_id, buscar por login
    if (empty($pagamentos) && !empty($usuario_login)) {
        $sql2 = "SELECT * FROM pagamentos_unificado
                 WHERE login = ?
                 ORDER BY data_criacao DESC";
        $stmt2 = mysqli_prepare($conn, $sql2);
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, "s", $usuario_login);
            mysqli_stmt_execute($stmt2);
            $result2 = mysqli_stmt_get_result($stmt2);
            while ($row = mysqli_fetch_assoc($result2)) {
                $row['forma_pagamento'] = 'PIX';
                if (empty($row['data_pagamento'])) {
                    $row['data_pagamento'] = $row['data_criacao'];
                }
                $pagamentos[] = $row;
            }
            mysqli_stmt_close($stmt2);
        }
    }

} else {

    // ======================================================
    // FALLBACK: tabela unificada ainda não existe
    // Lê da tabela antiga pagamentos_renovacao
    // ======================================================
    $sql = "SELECT * FROM pagamentos_renovacao
            WHERE user_id = ?
            ORDER BY data_pagamento DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $usuario_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $row['forma_pagamento'] = 'PIX';
            $pagamentos[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($pagamentos)) {
        $sql2 = "SELECT * FROM pagamentos_renovacao
                 WHERE login = ?
                 ORDER BY data_pagamento DESC";
        $stmt2 = mysqli_prepare($conn, $sql2);
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, "s", $usuario_login);
            mysqli_stmt_execute($stmt2);
            $result2 = mysqli_stmt_get_result($stmt2);
            while ($row = mysqli_fetch_assoc($result2)) {
                $row['forma_pagamento'] = 'PIX';
                $pagamentos[] = $row;
            }
            mysqli_stmt_close($stmt2);
        }
    }
}

// Estatísticas
$total_aprovados       = 0;
$total_pendentes       = 0;
$total_cancelados      = 0;
$valor_total_aprovados = 0;

foreach ($pagamentos as $pag) {
    $status = strtolower($pag['status'] ?? 'pending');
    $valor  = floatval($pag['valor'] ?? 0);

    if (in_array($status, ['approved', 'aprovado', 'success'])) {
        $total_aprovados++;
        $valor_total_aprovados += $valor;
    } elseif (in_array($status, ['pending', 'pendente'])) {
        $total_pendentes++;
    } else {
        $total_cancelados++;
    }
}

// Configurações do painel
$result_cfg  = $conn->query("SELECT * FROM configs");
$cfg         = $result_cfg->fetch_assoc();
$nomepainel  = $cfg['nomepainel'] ?? 'Painel';
$logo        = $cfg['logo']       ?? '';
$icon        = $cfg['icon']       ?? '';
$csspersonali = $cfg['corfundologo'] ?? '';

include_once("../AegisCore/temas.php");
$temaUsuario = initTemas($conn);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Histórico de Pagamentos</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        <?php echo $csspersonali; ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29, #1e1b4b, #0f172a);
            min-height: 100vh;
        }
        
        .dashboard-container { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 260px;
            background: rgba(15, 25, 35, 0.92);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            margin: 16px 0 16px 16px;
            padding: 18px 0;
            position: fixed;
            height: calc(100vh - 32px);
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.3); border-radius: 4px; }
        
        .sidebar-logo { text-align: center; padding: 0 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 16px; }
        .sidebar-logo img { max-height: 45px; max-width: 160px; }
        
        .sidebar-nav { padding: 0 12px; }
        
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 9px 14px;
            color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px;
            margin-bottom: 4px; transition: all 0.3s; font-size: 13px; font-weight: 500;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; transform: translateX(4px); }
        .nav-item.active { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.1)); color: #10b981; border-left: 3px solid #10b981; }
        
        .nav-item:nth-child(1) i { color: #3b82f6; }
        .nav-item:nth-child(2) i { color: #f59e0b; }
        .nav-item:nth-child(3) i { color: #ec489a; }
        .nav-item:nth-child(4) i { color: #8b5cf6; }
        .nav-item:nth-child(5) i { color: #ef4444; }
        .nav-item i { font-size: 18px; width: 22px; transition: all 0.3s; }
        .nav-item:hover i { transform: scale(1.1); }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 260px; height: auto; max-height: 85vh; border-radius: 24px;
                margin: 0; position: fixed; top: 16px; left: 16px;
                transform: translateX(-120%); transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
        }
        
        .main-content { flex: 1; margin-left: 280px; padding: 20px 24px; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: white; margin-bottom: 8px; }
        .page-header p { color: rgba(255,255,255,0.6); font-size: 14px; }
        
        .stats-section {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        
        .stat-card {
            text-align: center; padding: 16px 12px;
            background: rgba(255,255,255,0.02); border-radius: 18px; transition: all 0.3s;
        }
        .stat-card:hover { background: rgba(255,255,255,0.05); transform: translateY(-2px); }
        
        .stat-icon {
            width: 42px; height: 42px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 10px; font-size: 20px;
        }
        .stat-icon.green  { background: rgba(16,185,129,0.15); color: #10b981; }
        .stat-icon.orange { background: rgba(245,158,11,0.15);  color: #fbbf24; }
        .stat-icon.red    { background: rgba(220,38,38,0.15);   color: #f87171; }
        
        .stat-number { font-size: 26px; font-weight: 800; color: white; }
        .stat-label  { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 6px; }
        .stat-total  { font-size: 10px; color: #10b981; margin-top: 6px; }
        
        .historico-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
        }
        .historico-title {
            font-size: 18px; font-weight: 700; color: white;
            display: flex; align-items: center; gap: 10px;
        }
        .historico-title i { color: #10b981; font-size: 22px; }
        
        /* Tipo badge */
        .tipo-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 20px;
            font-size: 9px; font-weight: 600; margin-bottom: 8px;
            background: rgba(59,130,246,0.15); color: #60a5fa;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        
        .payment-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 16px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .payment-card:hover { transform: translateY(-3px); border-color: rgba(16,185,129,0.3); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .payment-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #10b981, #3b82f6); opacity: 0; transition: opacity 0.3s; }
        .payment-card:hover::before { opacity: 1; }
        
        .card-header-payment { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .payment-id { font-size: 11px; color: rgba(255,255,255,0.5); background: rgba(0,0,0,0.3); padding: 3px 8px; border-radius: 20px; font-family: monospace; }
        .payment-date { font-size: 11px; color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 4px; }
        
        .payment-value { font-size: 24px; font-weight: 800; color: #10b981; margin: 12px 0; text-align: center; }
        
        .payment-info {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 0;
            border-top: 1px solid rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 12px;
        }
        .payment-method { display: flex; align-items: center; gap: 6px; font-size: 12px; color: rgba(255,255,255,0.7); }
        
        .status-badge-card { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .status-approved-card  { background: rgba(16,185,129,0.15); color: #10b981; }
        .status-pending-card   { background: rgba(245,158,11,0.15);  color: #fbbf24; }
        .status-cancelled-card { background: rgba(220,38,38,0.15);   color: #f87171; }
        
        .payment-footer { display: flex; justify-content: space-between; align-items: center; font-size: 11px; }
        .payment-detail { color: rgba(255,255,255,0.4); }
        
        .empty-state { text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.03); border-radius: 24px; }
        .empty-state i { font-size: 64px; color: rgba(255,255,255,0.2); margin-bottom: 16px; display: block; }
        .empty-state h3 { font-size: 18px; color: white; margin-bottom: 8px; }
        .empty-state p { font-size: 13px; color: rgba(255,255,255,0.5); }
        .empty-state .btn-planos { display: inline-block; margin-top: 20px; padding: 10px 24px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 40px; color: white; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s; }
        .empty-state .btn-planos:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
        
        .footer { text-align: center; padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); font-size: 12px; margin-top: 30px; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
            .page-header h1 { font-size: 24px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .stat-card { padding: 12px 8px; }
            .stat-number { font-size: 22px; }
            .stat-icon { width: 36px; height: 36px; font-size: 18px; margin-bottom: 6px; }
            .stat-label { font-size: 10px; }
            .stat-total { font-size: 9px; }
            .cards-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .payment-card { padding: 12px; }
            .payment-value { font-size: 20px; margin: 8px 0; }
            .payment-id { font-size: 9px; }
            .payment-date { font-size: 9px; }
            .payment-method { font-size: 10px; }
            .status-badge-card { font-size: 9px; padding: 3px 8px; }
        }
        
        @media (max-width: 480px) {
            .cards-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .payment-card { padding: 10px; }
            .payment-value { font-size: 18px; }
        }
        
        .menu-toggle {
            display: none; position: fixed; top: 16px; left: 16px; z-index: 1001;
            background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.3);
            border-radius: 14px; padding: 10px 12px; color: white; cursor: pointer;
            backdrop-filter: blur(8px); font-size: 20px;
        }
        @media (max-width: 768px) { .menu-toggle { display: block; } }

    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaUsuario)); ?>">
<?php echo getFundoPersonalizadoCSS($conn, $temaUsuario); ?>
<button class="menu-toggle" id="menuToggle" onclick="toggleMenu()">
    <i class='bx bx-menu'></i>
</button>

<div class="dashboard-container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <?php if (!empty($logo)): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="logo">
            <?php else: ?>
                <div style="color: white; font-size: 16px; font-weight: 700;"><?php echo htmlspecialchars($nomepainel); ?></div>
            <?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class='bx bx-home'></i><span>Página Inicial</span></a>
            <a href="historico.php" class="nav-item active"><i class='bx bx-list-ul'></i><span>Listar pagamentos</span></a>
            <a href="planos_disponiveis.php" class="nav-item"><i class='bx bx-crown'></i><span>Planos</span></a>
            <a href="perfil.php" class="nav-item"><i class='bx bx-user'></i><span>Perfil</span></a>
            <a href="../logout_usuario.php" class="nav-item"><i class='bx bx-log-out'></i><span>Sair</span></a>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Histórico de Pagamentos</h1>
            <p>Visualize todas as suas transações realizadas</p>
        </div>
        
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
                    <div class="stat-number"><?php echo $total_aprovados; ?></div>
                    <div class="stat-label">APROVADOS</div>
                    <div class="stat-total">R$ <?php echo number_format($valor_total_aprovados, 2, ',', '.'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class='bx bx-time'></i></div>
                    <div class="stat-number"><?php echo $total_pendentes; ?></div>
                    <div class="stat-label">PENDENTES</div>
                    <div class="stat-total">Aguardando</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class='bx bx-x-circle'></i></div>
                    <div class="stat-number"><?php echo $total_cancelados; ?></div>
                    <div class="stat-label">CANCELADOS</div>
                    <div class="stat-total">Cancelados</div>
                </div>
            </div>
        </div>
        
        <div class="historico-header">
            <div class="historico-title">
                <i class='bx bx-receipt'></i>
                Histórico de Transações
            </div>
            <div style="font-size: 12px; color: rgba(255,255,255,0.4);">
                <i class='bx bx-calendar'></i> <?php echo date('d/m/Y'); ?>
            </div>
        </div>
        
        <?php if (count($pagamentos) > 0): ?>
        <div class="cards-grid">
            <?php foreach ($pagamentos as $pag):
                $status = strtolower($pag['status'] ?? 'pending');

                if (in_array($status, ['approved', 'aprovado', 'success'])) {
                    $status_class = 'status-approved-card';
                    $status_text  = 'Aprovado';
                    $status_icon  = 'bx-check-circle';
                } elseif (in_array($status, ['pending', 'pendente'])) {
                    $status_class = 'status-pending-card';
                    $status_text  = 'Pendente';
                    $status_icon  = 'bx-time';
                } else {
                    $status_class = 'status-cancelled-card';
                    $status_text  = 'Cancelado';
                    $status_icon  = 'bx-x-circle';
                }

                // Tipo legível
                $tipo_map = [
                    'renovacao_usuario'    => '🔄 Renovação',
                    'compra_plano_usuario' => '💰 Compra de Plano',
                    'compra_plano_revenda' => '🏪 Plano Revenda',
                    'renovacao_revenda'    => '🔁 Renovação Revenda',
                ];
                $tipo_texto = $tipo_map[$pag['tipo'] ?? ''] ?? '💳 Pagamento';

                $data_ref     = $pag['data_pagamento'] ?? $pag['data_criacao'] ?? '';
                $data_formatada = $data_ref ? date('d/m/Y', strtotime($data_ref)) : '--/--/----';
                $hora_formatada = $data_ref ? date('H:i',   strtotime($data_ref)) : '--:--';
                $payment_id   = $pag['payment_id'] ?? $pag['id'] ?? '---';
                $valor        = floatval($pag['valor'] ?? 0);
            ?>
            <div class="payment-card">
                <div class="card-header-payment">
                    <span class="payment-id">#<?php echo substr($payment_id, -8); ?></span>
                    <span class="payment-date">
                        <i class='bx bx-calendar'></i> <?php echo $data_formatada; ?>
                        <span style="margin-left: 4px;"><?php echo $hora_formatada; ?></span>
                    </span>
                </div>

                <!-- ✅ NOVO: Tipo do pagamento -->
                <div class="tipo-badge">
                    <?php echo $tipo_texto; ?>
                </div>
                
                <div class="payment-value">
                    R$ <?php echo number_format($valor, 2, ',', '.'); ?>
                </div>
                
                <div class="payment-info">
                    <div class="payment-method">
                        <i class='bx bx-credit-card' style="color: #009ee3;"></i>
                        PIX
                    </div>
                    <div class="status-badge-card <?php echo $status_class; ?>">
                        <i class='bx <?php echo $status_icon; ?>'></i>
                        <?php echo $status_text; ?>
                    </div>
                </div>
                
                <div class="payment-footer">
                    <span class="payment-detail">
                        <i class='bx bx-user'></i> <?php echo htmlspecialchars($pag['login'] ?? $usuario_login); ?>
                    </span>
                    <span class="payment-detail">
                        <i class='bx bx-id'></i> ID: <?php echo $pag['id'] ?? '---'; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class='bx bx-receipt'></i>
            <h3>Nenhuma transação encontrada</h3>
            <p>Você ainda não realizou nenhum pagamento.</p>
            <p style="font-size: 12px; margin-top: 8px;">Escolha um plano e faça sua primeira compra!</p>
            <a href="planos_disponiveis.php" class="btn-planos">
                <i class='bx bx-crown'></i> Ver Planos Disponíveis
            </a>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <?php echo htmlspecialchars($nomepainel); ?> &copy; <?php echo date('Y'); ?> - Todos os direitos reservados
        </div>
    </main>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
    if (sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function(event) {
    const sidebar    = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const isMobile   = window.innerWidth <= 768;
    if (isMobile && sidebar.classList.contains('open')) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            toggleMenu();
        }
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('open')) { toggleMenu(); }
    }
});
</script>
</body>
</html>