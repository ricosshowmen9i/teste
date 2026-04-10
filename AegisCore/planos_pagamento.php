<?php
error_reporting(0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once("conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Verificar login
if (!isset($_SESSION['login']) || !isset($_SESSION['iduser'])) {
    header('Location: ../index.php');
    exit;
}

$revendedor_id = $_SESSION['iduser'];

// Incluir arquivo de segurança
if (file_exists('../admin/suspenderrev.php')) {
    include_once('../admin/suspenderrev.php');
}

// Verificar token - se inválido, chama security() para revalidar
if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || 
    !isset($_SESSION['token']) || 
    !isset($_SESSION['tokenatual']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] || 
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!'); location.href='../index.php';</script>";
        exit;
    }
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

// ========== VARIÁVEIS PARA MODAIS ==========
$show_success_modal = false;
$show_error_modal = false;
$msg_sucesso = '';
$msg_erro = '';

// ========== PROCESSAR CRIAÇÃO DE PLANO ==========
if (isset($_POST['criar_plano'])) {
    $nome = anti_sql($_POST['nome']);
    $tipo = anti_sql($_POST['tipo']);
    $duracao_dias = intval($_POST['duracao_dias']);
    $valor = floatval($_POST['valor']);
    $limite = intval($_POST['limite'] ?? 1);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (empty($nome)) {
        $msg_erro = "Nome do plano é obrigatório!";
        $show_error_modal = true;
    } elseif ($duracao_dias <= 0) {
        $msg_erro = "Duração deve ser maior que 0!";
        $show_error_modal = true;
    } elseif ($valor <= 0) {
        $msg_erro = "Valor deve ser maior que 0!";
        $show_error_modal = true;
    } elseif ($limite <= 0) {
        $msg_erro = "Limite deve ser maior que 0!";
        $show_error_modal = true;
    } else {
        $sql = "INSERT INTO planos_pagamento (byid, nome, tipo, duracao_dias, valor, limite, descricao, status, data_criacao) 
                VALUES ('$revendedor_id', '$nome', '$tipo', '$duracao_dias', '$valor', '$limite', '$descricao', '$status', NOW())";
        
        if (mysqli_query($conn, $sql)) {
            $msg_sucesso = "Plano criado com sucesso!";
            $show_success_modal = true;
        } else {
            $msg_erro = "Erro ao criar plano: " . mysqli_error($conn);
            $show_error_modal = true;
        }
    }
}

// ========== PROCESSAR EDIÇÃO DE PLANO ==========
if (isset($_POST['editar_plano'])) {
    $plano_id = intval($_POST['plano_id']);
    $nome = anti_sql($_POST['nome']);
    $tipo = anti_sql($_POST['tipo']);
    $duracao_dias = intval($_POST['duracao_dias']);
    $valor = floatval($_POST['valor']);
    $limite = intval($_POST['limite'] ?? 1);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    
    $sql = "UPDATE planos_pagamento SET nome='$nome', tipo='$tipo', duracao_dias='$duracao_dias', valor='$valor', limite='$limite', descricao='$descricao', status='$status' WHERE id='$plano_id' AND byid='$revendedor_id'";
    
    if (mysqli_query($conn, $sql)) {
        $msg_sucesso = "Plano atualizado com sucesso!";
        $show_success_modal = true;
    } else {
        $msg_erro = "Erro ao atualizar plano: " . mysqli_error($conn);
        $show_error_modal = true;
    }
}

// ========== PROCESSAR EXCLUSÃO DE PLANO ==========
if (isset($_POST['excluir_plano'])) {
    $plano_id = intval($_POST['plano_id']);
    
    $sql = "DELETE FROM planos_pagamento WHERE id='$plano_id' AND byid='$revendedor_id'";
    
    if (mysqli_query($conn, $sql)) {
        $msg_sucesso = "Plano excluído com sucesso!";
        $show_success_modal = true;
    } else {
        $msg_erro = "Erro ao excluir plano: " . mysqli_error($conn);
        $show_error_modal = true;
    }
}

// ========== PROCESSAR ATIVAR/DESATIVAR PLANO ==========
if (isset($_POST['toggle_status'])) {
    $plano_id = intval($_POST['plano_id']);
    $novo_status = intval($_POST['novo_status']);
    
    $sql = "UPDATE planos_pagamento SET status='$novo_status' WHERE id='$plano_id' AND byid='$revendedor_id'";
    
    if (mysqli_query($conn, $sql)) {
        $msg_sucesso = $novo_status == 1 ? "Plano ativado com sucesso!" : "Plano desativado com sucesso!";
        $show_success_modal = true;
    } else {
        $msg_erro = "Erro ao alterar status do plano!";
        $show_error_modal = true;
    }
}

// ========== BUSCAR PLANOS ==========
$sql_planos = "SELECT * FROM planos_pagamento WHERE byid = '$revendedor_id' ORDER BY tipo, valor ASC";
$result_planos = mysqli_query($conn, $sql_planos);

$planos_usuario = [];
$planos_revenda = [];

while ($plano = mysqli_fetch_assoc($result_planos)) {
    if ($plano['tipo'] == 'usuario') {
        $planos_usuario[] = $plano;
    } else {
        $planos_revenda[] = $plano;
    }
}

// Configurações do painel
$result_cfg = $conn->query("SELECT * FROM configs");
$cfg = $result_cfg->fetch_assoc();
$nomepainel = $cfg['nomepainel'] ?? 'Painel';
$logo = $cfg['logo'] ?? '';
$icon = $cfg['icon'] ?? '';
$csspersonali = $cfg['corfundologo'] ?? '';

// Incluir header2 DEPOIS de toda lógica de processamento
include('header2.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Planos de Pagamento</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        <?php echo $csspersonali; ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Rubik', sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            min-height: 100vh;
        }
        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 1200px; margin: 0 auto 0 5px !important; padding: 0px !important; }
        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: #2c3e50 !important; padding: 8px 16px !important;
            border-radius: 30px !important; font-size: 13px !important; margin-top: 5px !important;
            margin-bottom: 15px !important; border-left: 4px solid #4158D0 !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: #4158D0; }
        .tabs-container {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px; padding: 6px; margin-bottom: 20px;
            display: inline-flex; gap: 8px; border: 1px solid rgba(255,255,255,0.08);
        }
        .tab-btn {
            padding: 10px 24px; border: none; background: transparent;
            color: rgba(255,255,255,0.6); font-weight: 600; font-size: 14px;
            border-radius: 12px; cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        .tab-btn i { font-size: 18px; }
        .tab-btn.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; box-shadow: 0 4px 12px rgba(65,88,208,0.3); }
        .tab-btn:hover:not(.active) { background: rgba(255,255,255,0.05); color: white; }
        .btn-criar-plano {
            background: linear-gradient(135deg, #10b981, #059669); border: none;
            padding: 10px 20px; border-radius: 12px; color: white; font-weight: 600;
            font-size: 13px; cursor: pointer; display: inline-flex; align-items: center;
            gap: 8px; transition: all 0.3s; margin-bottom: 20px;
        }
        .btn-criar-plano:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
        .planos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .plano-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden; transition: all 0.3s; position: relative;
        }
        .plano-card:hover { transform: translateY(-3px); border-color: rgba(16,185,129,0.3); box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .plano-card.inativo { opacity: 0.6; border-color: rgba(100,116,139,0.3); }
        .plano-header { background: linear-gradient(135deg, rgba(65,88,208,0.2), rgba(200,80,192,0.2)); padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .plano-nome { font-size: 18px; font-weight: 700; color: white; display: flex; justify-content: space-between; align-items: center; }
        .plano-status { font-size: 10px; padding: 3px 10px; border-radius: 20px; background: rgba(16,185,129,0.2); color: #10b981; }
        .plano-status.inativo { background: rgba(100,116,139,0.2); color: #94a3b8; }
        .plano-preco { text-align: center; padding: 20px; }
        .preco-valor { font-size: 28px; font-weight: 800; color: #10b981; }
        .preco-periodo { font-size: 12px; color: rgba(255,255,255,0.5); }
        .plano-body { padding: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-label { font-size: 12px; color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 5px; }
        .info-value { font-size: 12px; font-weight: 600; color: white; }
        .plano-descricao { background: rgba(255,255,255,0.03); border-radius: 12px; padding: 10px; margin: 12px 0; font-size: 11px; color: rgba(255,255,255,0.6); line-height: 1.4; }
        .plano-acoes { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
        .btn-acao { flex: 1; padding: 8px; border: none; border-radius: 30px; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.2s; }
        .btn-editar { background: rgba(59,130,246,0.2); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .btn-editar:hover { background: rgba(59,130,246,0.4); transform: translateY(-2px); }
        .btn-excluir { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .btn-excluir:hover { background: rgba(239,68,68,0.4); transform: translateY(-2px); }
        .btn-toggle { background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .btn-toggle:hover { background: rgba(245,158,11,0.4); transform: translateY(-2px); }
        .empty-state { text-align: center; padding: 60px; background: rgba(255,255,255,0.03); border-radius: 20px; }
        .empty-state i { font-size: 64px; color: rgba(255,255,255,0.2); margin-bottom: 16px; display: block; }
        .empty-state h3 { color: white; font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: rgba(255,255,255,0.5); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(8px); }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalFadeIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1); max-width: 500px; width: 90%; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-30px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-content-custom { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header-custom { color: white; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header-custom.processing { background: linear-gradient(135deg, #4158D0, #C850C0); }
        .modal-header-custom h5 { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8; }
        .modal-close:hover { opacity: 1; }
        .modal-body-custom { padding: 24px; color: white; }
        .modal-footer-custom { border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .modal-success-icon { text-align: center; margin-bottom: 20px; }
        .modal-success-icon i { font-size: 70px; color: #10b981; filter: drop-shadow(0 0 15px rgba(16,185,129,0.5)); }
        .modal-warning-icon { text-align: center; margin-bottom: 20px; }
        .modal-warning-icon i { font-size: 70px; color: #f59e0b; filter: drop-shadow(0 0 15px rgba(245,158,11,0.5)); }
        .modal-danger-icon { text-align: center; margin-bottom: 20px; }
        .modal-danger-icon i { font-size: 70px; color: #dc2626; filter: drop-shadow(0 0 15px rgba(220,38,38,0.5)); }
        .modal-info-card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 16px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.08); }
        .modal-info-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .modal-info-row:last-child { border-bottom: none; }
        .modal-info-label { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 8px; }
        .modal-info-label i { font-size: 18px; }
        .modal-info-value { font-size: 13px; font-weight: 700; color: white; }
        .modal-info-value.credential { background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 8px; font-family: monospace; }
        .processing-spinner { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 10px 0 20px; }
        .spinner-ring { width: 64px; height: 64px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #10b981; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner-text { color: rgba(255,255,255,0.7); font-size: 14px; font-weight: 500; }
        .btn-modal { padding: 9px 20px; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; box-shadow: 0 3px 8px rgba(0,0,0,0.2); color: white; }
        .btn-modal-cancel { background: linear-gradient(135deg, #64748b, #475569); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .form-select option { background: #1e293b; color: white; }
        .form-label { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; display: flex; align-items: center; gap: 4px; margin-bottom: 5px; }
        .form-input { width: 100%; padding: 10px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: white; font-size: 13px; outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: rgba(65,88,208,0.5); }
        .form-input::placeholder { color: rgba(255,255,255,0.25); }
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 10px !important; }
            .planos-grid { grid-template-columns: 1fr; }
            .tabs-container { width: 100%; justify-content: center; }
            .header-actions { flex-direction: column; align-items: stretch; }
            .btn-criar-plano { justify-content: center; }
            .modal-container { width: 95%; }
            .modal-info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .modal-footer-custom { flex-direction: column; }
            .btn-modal { width: 100%; justify-content: center; }
            .form-grid-modal { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        
        <div class="info-badge">
            <i class='bx bx-crown'></i>
            <span>Gerenciar Planos de Pagamento</span>
        </div>
        
        <div class="header-actions">
            <div class="tabs-container">
                <button class="tab-btn active" onclick="mudarTab('usuarios')">
                    <i class='bx bx-user'></i> Planos para Usuários
                </button>
                <button class="tab-btn" onclick="mudarTab('revendas')">
                    <i class='bx bx-store-alt'></i> Planos para Revendedores
                </button>
            </div>
            <button class="btn-criar-plano" onclick="abrirModalCriar()">
                <i class='bx bx-plus'></i> Novo Plano
            </button>
        </div>
        
        <!-- Tab Planos para Usuários -->
        <div id="tab-usuarios" class="tab-content active">
            <?php if (count($planos_usuario) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($planos_usuario as $plano): ?>
                <div class="plano-card <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <span class="plano-status <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                                <?php echo $plano['status'] == 1 ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="plano-preco">
                        <span class="preco-valor">R$ <?php echo number_format($plano['valor'], 2, ',', '.'); ?></span>
                        <span class="preco-periodo">/ <?php echo $plano['duracao_dias']; ?> dias</span>
                    </div>
                    <div class="plano-body">
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-wifi'></i> Limite</span>
                            <span class="info-value"><?php echo $plano['limite']; ?> conexões</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-calendar'></i> Duração</span>
                            <span class="info-value"><?php echo $plano['duracao_dias']; ?> dias</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-category'></i> Tipo</span>
                            <span class="info-value">Usuário Final</span>
                        </div>
                        <?php if (!empty($plano['descricao'])): ?>
                        <div class="plano-descricao"><?php echo htmlspecialchars($plano['descricao']); ?></div>
                        <?php endif; ?>
                        <div class="plano-acoes">
                            <button class="btn-acao btn-editar" onclick="editarPlano(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>', '<?php echo $plano['tipo']; ?>', <?php echo $plano['duracao_dias']; ?>, <?php echo $plano['valor']; ?>, <?php echo $plano['limite']; ?>, '<?php echo addslashes($plano['descricao'] ?? ''); ?>', <?php echo $plano['status']; ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="btn-acao btn-toggle" onclick="confirmarToggle(<?php echo $plano['id']; ?>, <?php echo $plano['status']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-<?php echo $plano['status'] == 1 ? 'x-circle' : 'check-circle'; ?>'></i>
                                <?php echo $plano['status'] == 1 ? 'Desativar' : 'Ativar'; ?>
                            </button>
                            <button class="btn-acao btn-excluir" onclick="confirmarExcluir(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-crown'></i>
                <h3>Nenhum plano para usuários</h3>
                <p>Clique em "Novo Plano" para criar seu primeiro plano.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Planos para Revendedores -->
        <div id="tab-revendas" class="tab-content">
            <?php if (count($planos_revenda) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($planos_revenda as $plano): ?>
                <div class="plano-card <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <span class="plano-status <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                                <?php echo $plano['status'] == 1 ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="plano-preco">
                        <span class="preco-valor">R$ <?php echo number_format($plano['valor'], 2, ',', '.'); ?></span>
                        <span class="preco-periodo">/ <?php echo $plano['duracao_dias']; ?> dias</span>
                    </div>
                    <div class="plano-body">
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-credit-card'></i> Créditos</span>
                            <span class="info-value"><?php echo $plano['limite']; ?> créditos</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-calendar'></i> Duração</span>
                            <span class="info-value"><?php echo $plano['duracao_dias']; ?> dias</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-category'></i> Tipo</span>
                            <span class="info-value">Revendedor</span>
                        </div>
                        <?php if (!empty($plano['descricao'])): ?>
                        <div class="plano-descricao"><?php echo htmlspecialchars($plano['descricao']); ?></div>
                        <?php endif; ?>
                        <div class="plano-acoes">
                            <button class="btn-acao btn-editar" onclick="editarPlano(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>', '<?php echo $plano['tipo']; ?>', <?php echo $plano['duracao_dias']; ?>, <?php echo $plano['valor']; ?>, <?php echo $plano['limite']; ?>, '<?php echo addslashes($plano['descricao'] ?? ''); ?>', <?php echo $plano['status']; ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="btn-acao btn-toggle" onclick="confirmarToggle(<?php echo $plano['id']; ?>, <?php echo $plano['status']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-<?php echo $plano['status'] == 1 ? 'x-circle' : 'check-circle'; ?>'></i>
                                <?php echo $plano['status'] == 1 ? 'Desativar' : 'Ativar'; ?>
                            </button>
                            <button class="btn-acao btn-excluir" onclick="confirmarExcluir(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-store-alt'></i>
                <h3>Nenhum plano para revendedores</h3>
                <p>Clique em "Novo Plano" para criar seu primeiro plano.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding: 16px; background: rgba(16,185,129,0.05); border-radius: 16px; border-left: 3px solid #10b981;">
            <p style="color: rgba(255,255,255,0.6); font-size: 12px; line-height: 1.5;">
                <i class='bx bx-info-circle'></i> 
                É obrigatório possuir 2 chips ativos no aparelho para o funcionamento correto do serviço.
            </p>
        </div>
        
    </div>
</div>

<!-- Modal Criar/Editar Plano -->
<div id="modalPlano" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom processing">
                <h5 id="modalTitulo"><i class='bx bx-plus'></i> Novo Plano de Pagamento</h5>
                <button class="modal-close" onclick="fecharModal('modalPlano')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <form method="POST" id="formPlano" action="">
                    <input type="hidden" name="plano_id" id="plano_id" value="">
                    <div class="form-grid-modal" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div style="grid-column: 1/-1;">
                            <label class="form-label"><i class='bx bx-tag'></i> NOME DO PLANO</label>
                            <input type="text" class="form-input" name="nome" id="plano_nome" placeholder="Ex: Plano Básico" required>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-dollar'></i> VALOR (R$)</label>
                            <input type="number" class="form-input" name="valor" id="plano_valor" step="0.01" min="0.01" placeholder="0,00" required>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-group'></i> LIMITE / CRÉDITOS</label>
                            <input type="number" class="form-input" name="limite" id="plano_limite" min="1" value="1" required>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-category'></i> TIPO</label>
                            <select class="form-input" name="tipo" id="plano_tipo" required>
                                <option value="usuario">Usuário Final</option>
                                <option value="revenda">Revendedor</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-calendar'></i> DURAÇÃO (DIAS)</label>
                            <input type="number" class="form-input" name="duracao_dias" id="plano_duracao_dias" min="1" value="30" required>
                        </div>
                        <div style="grid-column: 1/-1;">
                            <label class="form-label"><i class='bx bx-note'></i> DESCRIÇÃO DO PLANO</label>
                            <textarea class="form-input" name="descricao" id="plano_descricao" rows="3" placeholder="Descreva os benefícios do plano..."></textarea>
                        </div>
                        <div style="grid-column: 1/-1;">
                            <label class="form-label"><i class='bx bx-check-circle'></i> STATUS</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="status" id="plano_status" value="1" checked style="width: 40px; height: 20px;">
                                <span style="color: rgba(255,255,255,0.6); font-size: 12px;">Plano ativo (disponível para venda)</span>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 20px;">
                        <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalPlano')">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-modal btn-modal-ok" id="btnSubmitPlano" name="criar_plano">
                            <i class='bx bx-save'></i> Salvar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Processando -->
<div id="modalProcessando" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom processing">
                <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
            </div>
            <div class="modal-body-custom">
                <div class="processing-spinner">
                    <div class="spinner-ring"></div>
                    <p class="spinner-text">Aguarde enquanto processamos sua solicitação...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-danger-icon"><i class='bx bx-trash'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-crown'></i> Plano</div>
                        <div class="modal-info-value credential" id="excluir-plano-nome">-</div>
                    </div>
                </div>
                <p style="text-align:center; color: rgba(220,38,38,0.8); font-size: 12px;">
                    Esta ação não pode ser desfeita! O plano será permanentemente removido.
                </p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Ativar/Desativar -->
<div id="modalConfirmarToggle" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5 id="toggleModalTitulo"><i class='bx bx-refresh'></i> Confirmar Ação</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarToggle')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-warning-icon" id="toggleModalIcon"><i class='bx bx-question-mark'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-crown'></i> Plano</div>
                        <div class="modal-info-value credential" id="toggle-plano-nome">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-info-circle'></i> Ação</div>
                        <div class="modal-info-value" id="toggle-acao-texto">-</div>
                    </div>
                </div>
                <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px;" id="toggle-descricao"></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarToggle')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-warning" id="btnConfirmarToggle"><i class='bx bx-check'></i> Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-check-circle'></i> Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Operação Realizada!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem"><?php echo htmlspecialchars($msg_sucesso); ?></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')">
                    <i class='bx bx-check'></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-danger-icon"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem"><?php echo htmlspecialchars($msg_erro); ?></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
var planoIdParaExcluir = null;
var togglePlanoId = null;
var toggleNovoStatus = null;

function mudarTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
    if (tab === 'usuarios') {
        document.querySelector('.tab-btn:first-child').classList.add('active');
        document.getElementById('tab-usuarios').classList.add('active');
        document.getElementById('tab-revendas').classList.remove('active');
    } else {
        document.querySelector('.tab-btn:last-child').classList.add('active');
        document.getElementById('tab-revendas').classList.add('active');
        document.getElementById('tab-usuarios').classList.remove('active');
    }
}

function abrirModalCriar() {
    document.getElementById('modalTitulo').innerHTML = '<i class="bx bx-plus"></i> Novo Plano de Pagamento';
    document.getElementById('formPlano').reset();
    document.getElementById('plano_id').value = '';
    document.getElementById('plano_status').checked = true;
    document.getElementById('plano_limite').value = '1';
    document.getElementById('plano_duracao_dias').value = '30';
    document.getElementById('btnSubmitPlano').name = 'criar_plano';
    document.getElementById('btnSubmitPlano').innerHTML = '<i class="bx bx-save"></i> Salvar Plano';
    document.getElementById('modalPlano').classList.add('show');
}

function editarPlano(id, nome, tipo, duracao_dias, valor, limite, descricao, status) {
    document.getElementById('modalTitulo').innerHTML = '<i class="bx bx-edit"></i> Editar Plano';
    document.getElementById('plano_id').value = id;
    document.getElementById('plano_nome').value = nome;
    document.getElementById('plano_tipo').value = tipo;
    document.getElementById('plano_duracao_dias').value = duracao_dias;
    document.getElementById('plano_valor').value = valor;
    document.getElementById('plano_limite').value = limite;
    document.getElementById('plano_descricao').value = descricao;
    document.getElementById('plano_status').checked = (status == 1);
    document.getElementById('btnSubmitPlano').name = 'editar_plano';
    document.getElementById('btnSubmitPlano').innerHTML = '<i class="bx bx-save"></i> Salvar Alterações';
    document.getElementById('modalPlano').classList.add('show');
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('show');
}

function mostrarProcessando() {
    document.getElementById('modalProcessando').classList.add('show');
}

function confirmarExcluir(id, nome) {
    planoIdParaExcluir = id;
    document.getElementById('excluir-plano-nome').textContent = nome;
    document.getElementById('btnConfirmarExclusao').onclick = function() {
        fecharModal('modalConfirmarExclusao');
        executarExclusao();
    };
    document.getElementById('modalConfirmarExclusao').classList.add('show');
}

function executarExclusao() {
    mostrarProcessando();
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    var i1 = document.createElement('input');
    i1.type = 'hidden'; i1.name = 'excluir_plano'; i1.value = '1';
    form.appendChild(i1);
    var i2 = document.createElement('input');
    i2.type = 'hidden'; i2.name = 'plano_id'; i2.value = planoIdParaExcluir;
    form.appendChild(i2);
    document.body.appendChild(form);
    form.submit();
}

function confirmarToggle(id, statusAtual, nome) {
    togglePlanoId = id;
    toggleNovoStatus = statusAtual == 1 ? 0 : 1;
    var acao = toggleNovoStatus == 1 ? 'Ativar' : 'Desativar';
    var icone = toggleNovoStatus == 1 ? 'bx-check-circle' : 'bx-x-circle';
    var cor = toggleNovoStatus == 1 ? '#10b981' : '#f87171';
    document.getElementById('toggleModalTitulo').innerHTML = '<i class="bx ' + icone + '"></i> Confirmar ' + acao;
    document.getElementById('toggleModalIcon').innerHTML = '<i class="bx ' + icone + '" style="color:' + cor + ';font-size:70px;"></i>';
    document.getElementById('toggle-plano-nome').textContent = nome;
    document.getElementById('toggle-acao-texto').innerHTML = acao + ' plano';
    document.getElementById('toggle-descricao').innerHTML = toggleNovoStatus == 1
        ? 'O plano será ativado e ficará disponível para venda.'
        : 'O plano será desativado e não ficará mais visível para os clientes.';
    document.getElementById('btnConfirmarToggle').onclick = function() {
        fecharModal('modalConfirmarToggle');
        executarToggle();
    };
    document.getElementById('modalConfirmarToggle').classList.add('show');
}

function executarToggle() {
    mostrarProcessando();
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    var i1 = document.createElement('input');
    i1.type = 'hidden'; i1.name = 'toggle_status'; i1.value = '1';
    form.appendChild(i1);
    var i2 = document.createElement('input');
    i2.type = 'hidden'; i2.name = 'plano_id'; i2.value = togglePlanoId;
    form.appendChild(i2);
    var i3 = document.createElement('input');
    i3.type = 'hidden'; i3.name = 'novo_status'; i3.value = toggleNovoStatus;
    form.appendChild(i3);
    document.body.appendChild(form);
    form.submit();
}

<?php if ($show_success_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalSucesso').classList.add('show');
});
<?php endif; ?>

<?php if ($show_error_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalErro').classList.add('show');
});
<?php endif; ?>

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(m) {
            m.classList.remove('show');
        });
    }
});
</script>
</body>
</html>h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        
        <div class="info-badge">
            <i class='bx bx-crown'></i>
            <span>Gerenciar Planos de Pagamento</span>
        </div>
        
        <div class="header-actions">
            <div class="tabs-container">
                <button class="tab-btn active" onclick="mudarTab('usuarios')">
                    <i class='bx bx-user'></i> Planos para Usuários
                </button>
                <button class="tab-btn" onclick="mudarTab('revendas')">
                    <i class='bx bx-store-alt'></i> Planos para Revendedores
                </button>
            </div>
            <button class="btn-criar-plano" onclick="abrirModalCriar()">
                <i class='bx bx-plus'></i> Novo Plano
            </button>
        </div>
        
        <!-- Tab Planos para Usuários -->
        <div id="tab-usuarios" class="tab-content active">
            <?php if (count($planos_usuario) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($planos_usuario as $plano): ?>
                <div class="plano-card <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <span class="plano-status <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                                <?php echo $plano['status'] == 1 ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="plano-preco">
                        <span class="preco-valor">R$ <?php echo number_format($plano['valor'], 2, ',', '.'); ?></span>
                        <span class="preco-periodo">/ <?php echo $plano['duracao_dias']; ?> dias</span>
                    </div>
                    <div class="plano-body">
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-wifi'></i> Limite</span>
                            <span class="info-value"><?php echo $plano['limite']; ?> conexões</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-calendar'></i> Duração</span>
                            <span class="info-value"><?php echo $plano['duracao_dias']; ?> dias</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-category'></i> Tipo</span>
                            <span class="info-value">Usuário Final</span>
                        </div>
                        <?php if (!empty($plano['descricao'])): ?>
                        <div class="plano-descricao"><?php echo htmlspecialchars($plano['descricao']); ?></div>
                        <?php endif; ?>
                        <div class="plano-acoes">
                            <button class="btn-acao btn-editar" onclick="editarPlano(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>', '<?php echo $plano['tipo']; ?>', <?php echo $plano['duracao_dias']; ?>, <?php echo $plano['valor']; ?>, <?php echo $plano['limite']; ?>, '<?php echo addslashes($plano['descricao'] ?? ''); ?>', <?php echo $plano['status']; ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="btn-acao btn-toggle" onclick="confirmarToggle(<?php echo $plano['id']; ?>, <?php echo $plano['status']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-<?php echo $plano['status'] == 1 ? 'x-circle' : 'check-circle'; ?>'></i>
                                <?php echo $plano['status'] == 1 ? 'Desativar' : 'Ativar'; ?>
                            </button>
                            <button class="btn-acao btn-excluir" onclick="confirmarExcluir(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-crown'></i>
                <h3>Nenhum plano para usuários</h3>
                <p>Clique em "Novo Plano" para criar seu primeiro plano.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Planos para Revendedores -->
        <div id="tab-revendas" class="tab-content">
            <?php if (count($planos_revenda) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($planos_revenda as $plano): ?>
                <div class="plano-card <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <span class="plano-status <?php echo $plano['status'] == 0 ? 'inativo' : ''; ?>">
                                <?php echo $plano['status'] == 1 ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="plano-preco">
                        <span class="preco-valor">R$ <?php echo number_format($plano['valor'], 2, ',', '.'); ?></span>
                        <span class="preco-periodo">/ <?php echo $plano['duracao_dias']; ?> dias</span>
                    </div>
                    <div class="plano-body">
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-credit-card'></i> Créditos</span>
                            <span class="info-value"><?php echo $plano['limite']; ?> créditos</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-calendar'></i> Duração</span>
                            <span class="info-value"><?php echo $plano['duracao_dias']; ?> dias</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-category'></i> Tipo</span>
                            <span class="info-value">Revendedor</span>
                        </div>
                        <?php if (!empty($plano['descricao'])): ?>
                        <div class="plano-descricao"><?php echo htmlspecialchars($plano['descricao']); ?></div>
                        <?php endif; ?>
                        <div class="plano-acoes">
                            <button class="btn-acao btn-editar" onclick="editarPlano(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>', '<?php echo $plano['tipo']; ?>', <?php echo $plano['duracao_dias']; ?>, <?php echo $plano['valor']; ?>, <?php echo $plano['limite']; ?>, '<?php echo addslashes($plano['descricao'] ?? ''); ?>', <?php echo $plano['status']; ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="btn-acao btn-toggle" onclick="confirmarToggle(<?php echo $plano['id']; ?>, <?php echo $plano['status']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-<?php echo $plano['status'] == 1 ? 'x-circle' : 'check-circle'; ?>'></i>
                                <?php echo $plano['status'] == 1 ? 'Desativar' : 'Ativar'; ?>
                            </button>
                            <button class="btn-acao btn-excluir" onclick="confirmarExcluir(<?php echo $plano['id']; ?>, '<?php echo addslashes($plano['nome']); ?>')">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-store-alt'></i>
                <h3>Nenhum plano para revendedores</h3>
                <p>Clique em "Novo Plano" para criar seu primeiro plano.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding: 16px; background: rgba(16,185,129,0.05); border-radius: 16px; border-left: 3px solid #10b981;">
            <p style="color: rgba(255,255,255,0.6); font-size: 12px; line-height: 1.5;">
                <i class='bx bx-info-circle'></i> 
                É obrigatório possuir 2 chips ativos no aparelho para o funcionamento correto do serviço.
            </p>
        </div>
        
    </div>
</div>

<!-- Modal Criar/Editar Plano -->
<div id="modalPlano" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom processing">
                <h5 id="modalTitulo"><i class='bx bx-plus'></i> Novo Plano de Pagamento</h5>
                <button class="modal-close" onclick="fecharModal('modalPlano')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <form method="POST" id="formPlano" action="">
                    <input type="hidden" name="plano_id" id="plano_id" value="">
                    <div class="form-grid-modal" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div style="grid-column: 1/-1;">
                            <label class="form-label"><i class='bx bx-tag'></i> NOME DO PLANO</label>
                            <input type="text" class="form-input" name="nome" id="plano_nome" placeholder="Ex: Plano Básico" required>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-dollar'></i> VALOR (R$)</label>
                            <input type="number" class="form-input" name="valor" id="plano_valor" step="0.01" min="0.01" placeholder="0,00" required>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-group'></i> LIMITE / CRÉDITOS</label>
                            <input type="number" class="form-input" name="limite" id="plano_limite" min="1" value="1" required>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-category'></i> TIPO</label>
                            <select class="form-input" name="tipo" id="plano_tipo" required>
                                <option value="usuario">Usuário Final</option>
                                <option value="revenda">Revendedor</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label"><i class='bx bx-calendar'></i> DURAÇÃO (DIAS)</label>
                            <input type="number" class="form-input" name="duracao_dias" id="plano_duracao_dias" min="1" value="30" required>
                        </div>
                        <div style="grid-column: 1/-1;">
                            <label class="form-label"><i class='bx bx-note'></i> DESCRIÇÃO DO PLANO</label>
                            <textarea class="form-input" name="descricao" id="plano_descricao" rows="3" placeholder="Descreva os benefícios do plano..."></textarea>
                        </div>
                        <div style="grid-column: 1/-1;">
                            <label class="form-label"><i class='bx bx-check-circle'></i> STATUS</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="status" id="plano_status" value="1" checked style="width: 40px; height: 20px;">
                                <span style="color: rgba(255,255,255,0.6); font-size: 12px;">Plano ativo (disponível para venda)</span>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 20px;">
                        <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalPlano')">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                        <button type="submit" class="btn-modal btn-modal-ok" id="btnSubmitPlano" name="criar_plano">
                            <i class='bx bx-save'></i> Salvar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Processando -->
<div id="modalProcessando" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom processing">
                <h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5>
            </div>
            <div class="modal-body-custom">
                <div class="processing-spinner">
                    <div class="spinner-ring"></div>
                    <p class="spinner-text">Aguarde enquanto processamos sua solicitação...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-danger-icon"><i class='bx bx-trash'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-crown'></i> Plano</div>
                        <div class="modal-info-value credential" id="excluir-plano-nome">-</div>
                    </div>
                </div>
                <p style="text-align:center; color: rgba(220,38,38,0.8); font-size: 12px;">
                    Esta ação não pode ser desfeita! O plano será permanentemente removido.
                </p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Ativar/Desativar -->
<div id="modalConfirmarToggle" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom warning">
                <h5 id="toggleModalTitulo"><i class='bx bx-refresh'></i> Confirmar Ação</h5>
                <button class="modal-close" onclick="fecharModal('modalConfirmarToggle')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-warning-icon" id="toggleModalIcon"><i class='bx bx-question-mark'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-crown'></i> Plano</div>
                        <div class="modal-info-value credential" id="toggle-plano-nome">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-info-circle'></i> Ação</div>
                        <div class="modal-info-value" id="toggle-acao-texto">-</div>
                    </div>
                </div>
                <p style="text-align:center; color: rgba(255,255,255,0.5); font-size: 12px;" id="toggle-descricao"></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarToggle')"><i class='bx bx-x'></i> Cancelar</button>
                <button class="btn-modal btn-modal-warning" id="btnConfirmarToggle"><i class='bx bx-check'></i> Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5><i class='bx bx-check-circle'></i> Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-success-icon"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Operação Realizada!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem"><?php echo htmlspecialchars($msg_sucesso); ?></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')">
                    <i class='bx bx-check'></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-danger-icon"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem"><?php echo htmlspecialchars($msg_erro); ?></p>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
var planoIdParaExcluir = null;
var togglePlanoId = null;
var toggleNovoStatus = null;

function mudarTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
    if (tab === 'usuarios') {
        document.querySelector('.tab-btn:first-child').classList.add('active');
        document.getElementById('tab-usuarios').classList.add('active');
        document.getElementById('tab-revendas').classList.remove('active');
    } else {
        document.querySelector('.tab-btn:last-child').classList.add('active');
        document.getElementById('tab-revendas').classList.add('active');
        document.getElementById('tab-usuarios').classList.remove('active');
    }
}

function abrirModalCriar() {
    document.getElementById('modalTitulo').innerHTML = '<i class="bx bx-plus"></i> Novo Plano de Pagamento';
    document.getElementById('formPlano').reset();
    document.getElementById('plano_id').value = '';
    document.getElementById('plano_status').checked = true;
    document.getElementById('plano_limite').value = '1';
    document.getElementById('plano_duracao_dias').value = '30';
    document.getElementById('btnSubmitPlano').name = 'criar_plano';
    document.getElementById('btnSubmitPlano').innerHTML = '<i class="bx bx-save"></i> Salvar Plano';
    document.getElementById('modalPlano').classList.add('show');
}

function editarPlano(id, nome, tipo, duracao_dias, valor, limite, descricao, status) {
    document.getElementById('modalTitulo').innerHTML = '<i class="bx bx-edit"></i> Editar Plano';
    document.getElementById('plano_id').value = id;
    document.getElementById('plano_nome').value = nome;
    document.getElementById('plano_tipo').value = tipo;
    document.getElementById('plano_duracao_dias').value = duracao_dias;
    document.getElementById('plano_valor').value = valor;
    document.getElementById('plano_limite').value = limite;
    document.getElementById('plano_descricao').value = descricao;
    document.getElementById('plano_status').checked = (status == 1);
    document.getElementById('btnSubmitPlano').name = 'editar_plano';
    document.getElementById('btnSubmitPlano').innerHTML = '<i class="bx bx-save"></i> Salvar Alterações';
    document.getElementById('modalPlano').classList.add('show');
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('show');
}

function mostrarProcessando() {
    document.getElementById('modalProcessando').classList.add('show');
}

function confirmarExcluir(id, nome) {
    planoIdParaExcluir = id;
    document.getElementById('excluir-plano-nome').textContent = nome;
    document.getElementById('btnConfirmarExclusao').onclick = function() {
        fecharModal('modalConfirmarExclusao');
        executarExclusao();
    };
    document.getElementById('modalConfirmarExclusao').classList.add('show');
}

function executarExclusao() {
    mostrarProcessando();
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    var i1 = document.createElement('input');
    i1.type = 'hidden'; i1.name = 'excluir_plano'; i1.value = '1';
    form.appendChild(i1);
    var i2 = document.createElement('input');
    i2.type = 'hidden'; i2.name = 'plano_id'; i2.value = planoIdParaExcluir;
    form.appendChild(i2);
    document.body.appendChild(form);
    form.submit();
}

function confirmarToggle(id, statusAtual, nome) {
    togglePlanoId = id;
    toggleNovoStatus = statusAtual == 1 ? 0 : 1;
    var acao = toggleNovoStatus == 1 ? 'Ativar' : 'Desativar';
    var icone = toggleNovoStatus == 1 ? 'bx-check-circle' : 'bx-x-circle';
    var cor = toggleNovoStatus == 1 ? '#10b981' : '#f87171';
    document.getElementById('toggleModalTitulo').innerHTML = '<i class="bx ' + icone + '"></i> Confirmar ' + acao;
    document.getElementById('toggleModalIcon').innerHTML = '<i class="bx ' + icone + '" style="color:' + cor + ';font-size:70px;"></i>';
    document.getElementById('toggle-plano-nome').textContent = nome;
    document.getElementById('toggle-acao-texto').innerHTML = acao + ' plano';
    document.getElementById('toggle-descricao').innerHTML = toggleNovoStatus == 1
        ? 'O plano será ativado e ficará disponível para venda.'
        : 'O plano será desativado e não ficará mais visível para os clientes.';
    document.getElementById('btnConfirmarToggle').onclick = function() {
        fecharModal('modalConfirmarToggle');
        executarToggle();
    };
    document.getElementById('modalConfirmarToggle').classList.add('show');
}

function executarToggle() {
    mostrarProcessando();
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    var i1 = document.createElement('input');
    i1.type = 'hidden'; i1.name = 'toggle_status'; i1.value = '1';
    form.appendChild(i1);
    var i2 = document.createElement('input');
    i2.type = 'hidden'; i2.name = 'plano_id'; i2.value = togglePlanoId;
    form.appendChild(i2);
    var i3 = document.createElement('input');
    i3.type = 'hidden'; i3.name = 'novo_status'; i3.value = toggleNovoStatus;
    form.appendChild(i3);
    document.body.appendChild(form);
    form.submit();
}

<?php if ($show_success_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalSucesso').classList.add('show');
});
<?php endif; ?>

<?php if ($show_error_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalErro').classList.add('show');
});
<?php endif; ?>

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(m) {
            m.classList.remove('show');
        });
    }
});
</script>
</body>
</html>

