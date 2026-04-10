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

$usuario_id = $_SESSION['usuario_id'];
$usuario_login = $_SESSION['usuario_login'];
$usuario_limite = $_SESSION['usuario_limite'];
$usuario_byid = $_SESSION['usuario_byid'];

// ✅ Verificar se o revendedor configurou o Mercado Pago
$sql_rev = "SELECT mp_active, mp_access_token, mp_public_key FROM accounts WHERE id = ?";
$stmt_rev = mysqli_prepare($conn, $sql_rev);
mysqli_stmt_bind_param($stmt_rev, "i", $usuario_byid);
mysqli_stmt_execute($stmt_rev);
$result_rev = mysqli_stmt_get_result($stmt_rev);
$rev_data = mysqli_fetch_assoc($result_rev);

$mp_active = $rev_data['mp_active'] ?? 0;
$mp_access_token = $rev_data['mp_access_token'] ?? '';
$mp_public_key = $rev_data['mp_public_key'] ?? '';

$pagamento_configurado = ($mp_active == 1 && !empty($mp_access_token) && !empty($mp_public_key));

// Buscar APENAS planos do tipo USUARIO (não planos de revenda)
$sql_planos = "SELECT * FROM planos_pagamento WHERE byid = ? AND status = 1 AND tipo = 'usuario' ORDER BY valor ASC";
$stmt_planos = mysqli_prepare($conn, $sql_planos);
mysqli_stmt_bind_param($stmt_planos, "i", $usuario_byid);
mysqli_stmt_execute($stmt_planos);
$result_planos = mysqli_stmt_get_result($stmt_planos);

// Configurações do painel
$result_cfg = $conn->query("SELECT * FROM configs");
$cfg = $result_cfg->fetch_assoc();
$nomepainel = $cfg['nomepainel'] ?? 'Painel';
$logo = $cfg['logo'] ?? '';
$icon = $cfg['icon'] ?? '';
$csspersonali = $cfg['corfundologo'] ?? '';

include_once("../AegisCore/temas.php");
$temaUsuario = initTemas($conn);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Planos</title>
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
        
        /* ========== SIDEBAR MENU MODERNO ========== */
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
                width: 260px;
                height: auto;
                max-height: 85vh;
                border-radius: 24px;
                margin: 0;
                position: fixed;
                top: 16px;
                left: 16px;
                transform: translateX(-120%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px 24px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }
        
        .alert-warning-modern {
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 16px;
            backdrop-filter: blur(8px);
        }
        .alert-warning-modern i {
            font-size: 32px;
            color: #fbbf24;
        }
        .alert-warning-modern .alert-content {
            flex: 1;
        }
        .alert-warning-modern .alert-title {
            font-size: 16px;
            font-weight: 700;
            color: #fbbf24;
            margin-bottom: 4px;
        }
        .alert-warning-modern .alert-message {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }
        
        /* ========== PLANOS GRID - ESTILO IGUAL AO PAINEL ========== */
        .planos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .plano-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        
        .plano-card:hover {
            transform: translateY(-3px);
            border-color: rgba(16,185,129,0.3);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .plano-card.recomendado {
            border: 2px solid #10b981;
        }
        
        .recomendado-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            z-index: 1;
        }
        
        .plano-header {
            background: linear-gradient(135deg, rgba(65,88,208,0.2), rgba(200,80,192,0.2));
            padding: 20px 16px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        
        .plano-icon {
            width: 50px;
            height: 50px;
            background: rgba(16,185,129,0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        
        .plano-icon i {
            font-size: 26px;
            color: #10b981;
        }
        
        .plano-nome {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }
        
        .plano-dias {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        
        .plano-preco {
            padding: 20px;
            text-align: center;
            background: rgba(0,0,0,0.2);
        }
        
        .preco-valor {
            font-size: 28px;
            font-weight: 800;
            color: #10b981;
        }
        
        .preco-periodo {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        
        .plano-body {
            padding: 16px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-label i {
            font-size: 14px;
            color: #10b981;
        }
        
        .info-value {
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .plano-descricao {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 10px;
            margin: 12px 0;
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            line-height: 1.4;
        }
        
        .plano-footer {
            padding: 12px 16px 16px;
            text-align: center;
        }
        
        .btn-escolher {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            width: 100%;
        }
        
        .btn-escolher:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.4);
        }
        
        .btn-escolher.disabled {
            background: rgba(100,116,139,0.3);
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.6;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.3);
            font-size: 12px;
            margin-top: 30px;
        }
        
        @media (max-width: 1024px) {
            .planos-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
            .planos-grid { grid-template-columns: 1fr; gap: 15px; }
            .page-header h1 { font-size: 24px; }
            .alert-warning-modern { flex-direction: column; text-align: center; }
        }
        
        .menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1001;
            background: rgba(16,185,129,0.2);
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 14px;
            padding: 10px 12px;
            color: white;
            cursor: pointer;
            backdrop-filter: blur(8px);
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
        }

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
            <a href="index.php" class="nav-item">
                <i class='bx bx-home'></i>
                <span>Página Inicial</span>
            </a>
            <a href="historico.php" class="nav-item">
                <i class='bx bx-list-ul'></i>
                <span>Listar pagamentos</span>
            </a>
            <a href="planos_disponiveis.php" class="nav-item active">
                <i class='bx bx-crown'></i>
                <span>Planos</span>
            </a>
            <a href="perfil.php" class="nav-item">
                <i class='bx bx-user'></i>
                <span>Perfil</span>
            </a>
            <a href="../logout_usuario.php" class="nav-item">
                <i class='bx bx-log-out'></i>
                <span>Sair</span>
            </a>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Escolha seu Plano</h1>
            <p>Selecione o plano que melhor atende às suas necessidades</p>
        </div>
        
        <?php if (!$pagamento_configurado): ?>
        <div class="alert-warning-modern">
            <i class='bx bx-error-circle'></i>
            <div class="alert-content">
                <div class="alert-title">Pagamento não configurado!</div>
                <div class="alert-message">O revendedor ainda não configurou o sistema de pagamento. Entre em contato com o suporte para regularizar sua conta.</div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="planos-grid">
            <?php 
            if (mysqli_num_rows($result_planos) > 0):
                $i = 0;
                while ($plano = mysqli_fetch_assoc($result_planos)): 
                    $i++;
            ?>
            <div class="plano-card <?php echo ($i == 1) ? 'recomendado' : ''; ?>">
                <?php if ($i == 1): ?>
                    <div class="recomendado-badge">Mais Escolhido</div>
                <?php endif; ?>
                <div class="plano-header">
                    <div class="plano-icon">
                        <i class='bx bx-crown'></i>
                    </div>
                    <div class="plano-nome"><?php echo htmlspecialchars($plano['nome']); ?></div>
                    <div class="plano-dias">Válido por <?php echo $plano['duracao_dias']; ?> dias</div>
                </div>
                <div class="plano-preco">
                    <span class="preco-valor">R$ <?php echo number_format($plano['valor'], 2, ',', '.'); ?></span>
                    <span class="preco-periodo">/ <?php echo $plano['duracao_dias']; ?> dias</span>
                </div>
                <div class="plano-body">
                    <div class="info-row">
                        <div class="info-label">
                            <i class='bx bx-wifi'></i> Limite de conexões
                        </div>
                        <div class="info-value"><?php echo $plano['limite']; ?> dispositivos</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">
                            <i class='bx bx-calendar'></i> Duração
                        </div>
                        <div class="info-value"><?php echo $plano['duracao_dias']; ?> dias</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">
                            <i class='bx bx-category'></i> Tipo
                        </div>
                        <div class="info-value">Usuário Final</div>
                    </div>
                    <?php if (!empty($plano['descricao'])): ?>
                    <div class="plano-descricao">
                        <?php echo htmlspecialchars_decode($plano['descricao']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="plano-footer">
                    <?php if ($pagamento_configurado): ?>
                    <form action="pagamento_plano.php" method="POST">
                        <input type="hidden" name="plano_id" value="<?php echo $plano['id']; ?>">
                        <input type="hidden" name="plano_nome" value="<?php echo htmlspecialchars($plano['nome']); ?>">
                        <input type="hidden" name="plano_valor" value="<?php echo $plano['valor']; ?>">
                        <input type="hidden" name="plano_dias" value="<?php echo $plano['duracao_dias']; ?>">
                        <input type="hidden" name="plano_limite" value="<?php echo $plano['limite']; ?>">
                        <button type="submit" class="btn-escolher">
                            <i class='bx bx-credit-card'></i> Escolher Plano
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn-escolher disabled" disabled>
                        <i class='bx bx-lock'></i> Pagamento indisponível
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <div class="plano-card" style="text-align: center; padding: 40px;">
                <div class="plano-icon" style="background: rgba(239,68,68,0.2); margin-bottom: 15px;">
                    <i class='bx bx-error-circle' style="color: #f87171; font-size: 28px;"></i>
                </div>
                <div class="plano-nome" style="font-size: 16px;">Nenhum plano disponível</div>
                <div class="plano-dias">Não há planos para usuários cadastrados no momento</div>
                <div class="plano-footer" style="margin-top: 20px;">
                    <a href="index.php" class="btn-escolher" style="background: linear-gradient(135deg, #64748b, #475569);">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
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
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile && sidebar.classList.contains('open')) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            toggleMenu();
        }
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('open')) {
            toggleMenu();
        }
    }
});
</script>
</body>
</html>