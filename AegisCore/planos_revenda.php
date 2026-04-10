<?php
session_start();
error_reporting(0);

include_once("conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
include('header2.php');

// Verificar login
if (!isset($_SESSION['login']) || !isset($_SESSION['iduser'])) {
    header('Location: ../index.php');
    exit;
}

$comprador_id = $_SESSION['iduser'];

// ========== VERIFICAÇÃO DE TOKEN ==========
if (!file_exists('/admin/suspenderrev.php')) {
    // Não bloqueia
} else {
    include_once '/admin/suspenderrev.php';
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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

// ========== FUNÇÃO PARA CALCULAR LIMITE RESTANTE DO VENDEDOR ==========
function calcularLimiteRestante($conn, $vendedor_id) {
    // 1. Buscar limite total do vendedor
    $sql_total = "SELECT limite FROM atribuidos WHERE userid = '$vendedor_id'";
    $result_total = mysqli_query($conn, $sql_total);
    $total = mysqli_fetch_assoc($result_total);
    $limite_total = isset($total['limite']) ? intval($total['limite']) : 0;
    
    // 2. Somar limites das revendas que ele criou (byid = vendedor_id)
    $sql_revendas = "SELECT SUM(limite) as total FROM atribuidos WHERE byid = '$vendedor_id' AND userid != '$vendedor_id'";
    $result_revendas = mysqli_query($conn, $sql_revendas);
    $revendas = mysqli_fetch_assoc($result_revendas);
    $limite_revendas = isset($revendas['total']) ? intval($revendas['total']) : 0;
    
    // 3. Somar limites dos usuários que ele criou
    $sql_usuarios = "SELECT SUM(limite) as total FROM ssh_accounts WHERE byid = '$vendedor_id'";
    $result_usuarios = mysqli_query($conn, $sql_usuarios);
    $usuarios = mysqli_fetch_assoc($result_usuarios);
    $limite_usuarios = isset($usuarios['total']) ? intval($usuarios['total']) : 0;
    
    // 4. Calcular limite restante
    $limite_usado = $limite_revendas + $limite_usuarios;
    $limite_restante = $limite_total - $limite_usado;
    
    return [
        'total' => $limite_total,
        'usado' => $limite_usado,
        'restante' => $limite_restante
    ];
}

// ========== FUNÇÃO PARA CALCULAR LIMITE JÁ UTILIZADO PELO COMPRADOR ==========
function calcularLimiteUtilizado($conn, $comprador_id) {
    // 1. Somar limites das revendas que ele criou (byid = comprador_id)
    $sql_revendas = "SELECT SUM(limite) as total FROM atribuidos WHERE byid = '$comprador_id' AND userid != '$comprador_id'";
    $result_revendas = mysqli_query($conn, $sql_revendas);
    $revendas = mysqli_fetch_assoc($result_revendas);
    $limite_revendas = isset($revendas['total']) ? intval($revendas['total']) : 0;
    
    // 2. Somar limites dos usuários que ele criou
    $sql_usuarios = "SELECT SUM(limite) as total FROM ssh_accounts WHERE byid = '$comprador_id'";
    $result_usuarios = mysqli_query($conn, $sql_usuarios);
    $usuarios = mysqli_fetch_assoc($result_usuarios);
    $limite_usuarios = isset($usuarios['total']) ? intval($usuarios['total']) : 0;
    
    // 3. Calcular total utilizado
    $total_usado = $limite_revendas + $limite_usuarios;
    
    return $total_usado;
}

// ========== VARIÁVEIS PARA MODAIS ==========
$show_success_modal = false;
$show_error_modal = false;
$msg_sucesso = '';
$msg_erro = '';

// ========== BUSCAR LIMITE DO COMPRADOR ==========
$limite_comprador = calcularLimiteRestante($conn, $comprador_id);

// ========== BUSCAR LIMITE JÁ UTILIZADO PELO COMPRADOR ==========
$limite_utilizado_comprador = calcularLimiteUtilizado($conn, $comprador_id);

// ========== BUSCAR LIMITE ATUAL DO COMPRADOR ==========
$sql_limite_atual = "SELECT limite FROM atribuidos WHERE userid = '$comprador_id'";
$result_limite_atual = mysqli_query($conn, $sql_limite_atual);
$limite_atual_data = mysqli_fetch_assoc($result_limite_atual);
$limite_atual_comprador = isset($limite_atual_data['limite']) ? intval($limite_atual_data['limite']) : 0;

// ========== PROCESSAR COMPRA DE PLANO ==========
if (isset($_POST['comprar_plano'])) {
    $plano_id = intval($_POST['plano_id']);
    
    // Buscar dados do plano
    $sql_plano = "SELECT * FROM planos_pagamento WHERE id = '$plano_id' AND status = 1 AND tipo = 'revenda'";
    $result_plano = mysqli_query($conn, $sql_plano);
    $plano = mysqli_fetch_assoc($result_plano);
    
    if (!$plano) {
        $msg_erro = "Plano não encontrado ou indisponível!";
        $show_error_modal = true;
    } else {
        $vendedor_id = $plano['byid'];
        $limite_plano = intval($plano['limite']);
        
        // ========== NOVA VALIDAÇÃO: VERIFICAR SE O COMPRADOR NÃO ESTÁ USANDO MAIS DO QUE O NOVO LIMITE ==========
        if ($limite_plano < $limite_utilizado_comprador) {
            $msg_erro = "❌ VOCÊ NÃO PODE MUDAR PARA ESTE PLANO!\n\n";
            $msg_erro .= "Você está atualmente usando " . number_format($limite_utilizado_comprador, 0, ',', '.') . " créditos em revendas e usuários.\n";
            $msg_erro .= "O plano que você deseja comprar tem apenas " . number_format($limite_plano, 0, ',', '.') . " créditos de limite.\n\n";
            $msg_erro .= "⚠️ Para mudar para um plano com limite menor, você precisa primeiro:\n";
            $msg_erro .= "• Reduzir a quantidade de revendas que você criou\n";
            $msg_erro .= "• Excluir usuários inativos\n";
            $msg_erro .= "• Ou aguardar a expiração dos usuários\n\n";
            $msg_erro .= "📊 Seu uso atual: " . number_format($limite_utilizado_comprador, 0, ',', '.') . " / " . number_format($limite_atual_comprador, 0, ',', '.') . " créditos usados.";
            $show_error_modal = true;
        } else {
            // ========== CALCULAR LIMITE RESTANTE DO VENDEDOR ==========
            $limite_vendedor = calcularLimiteRestante($conn, $vendedor_id);
            
            // Buscar nome do vendedor
            $sql_vendedor_nome = "SELECT login, nome FROM accounts WHERE id = '$vendedor_id'";
            $result_vendedor_nome = mysqli_query($conn, $sql_vendedor_nome);
            $vendedor_dados = mysqli_fetch_assoc($result_vendedor_nome);
            $nome_vendedor = $vendedor_dados['nome'] ?? $vendedor_dados['login'] ?? 'Revendedor';
            
            // ========== TRAVA: Verificar se vendedor tem limite restante suficiente ==========
            if ($limite_vendedor['restante'] < $limite_plano) {
                $msg_erro = "❌ O vendedor '" . htmlspecialchars($nome_vendedor) . "' NÃO TEM LIMITE RESTANTE SUFICIENTE para vender este plano!\n\n";
                $msg_erro .= "Ele precisa de " . number_format($limite_plano, 0, ',', '.') . " créditos, mas tem apenas " . number_format($limite_vendedor['restante'], 0, ',', '.') . " disponíveis.\n\n";
                $msg_erro .= "📊 Limite Total: " . number_format($limite_vendedor['total'], 0, ',', '.') . "\n";
                $msg_erro .= "📊 Já utilizado: " . number_format($limite_vendedor['usado'], 0, ',', '.') . "\n";
                $msg_erro .= "📊 Disponível: " . number_format($limite_vendedor['restante'], 0, ',', '.');
                $show_error_modal = true;
            } else {
                $_SESSION['plano_compra'] = array(
                    'id' => $plano['id'],
                    'nome' => $plano['nome'],
                    'valor' => $plano['valor'],
                    'limite' => $limite_plano,
                    'duracao_dias' => $plano['duracao_dias'],
                    'byid' => $plano['byid'],
                    'vendedor_nome' => $nome_vendedor,
                    'vendedor_limite_restante' => $limite_vendedor['restante']
                );
                
                echo "<script>window.location.href = 'pagamento_revenda.php?plano_id=" . $plano['id'] . "';</script>";
                exit;
            }
        }
    }
}

// ========== BUSCAR PLANOS DE REVENDA DISPONÍVEIS ==========
$sql_planos = "SELECT p.*
               FROM planos_pagamento p
               WHERE p.tipo = 'revenda' AND p.status = 1 AND p.byid != '$comprador_id'
               ORDER BY p.valor ASC";
$result_planos = mysqli_query($conn, $sql_planos);

$planos_revenda = array();
while ($plano = mysqli_fetch_assoc($result_planos)) {
    // Buscar nome do vendedor
    $sql_vendedor = "SELECT login, nome FROM accounts WHERE id = '{$plano['byid']}'";
    $result_vendedor = mysqli_query($conn, $sql_vendedor);
    $vendedor = mysqli_fetch_assoc($result_vendedor);
    $plano['vendedor_nome'] = $vendedor['nome'] ?? $vendedor['login'] ?? 'Revendedor';
    
    // Calcular limite restante do vendedor
    $limite_vendedor = calcularLimiteRestante($conn, $plano['byid']);
    $plano['vendedor_limite_total'] = $limite_vendedor['total'];
    $plano['vendedor_limite_usado'] = $limite_vendedor['usado'];
    $plano['vendedor_limite_restante'] = $limite_vendedor['restante'];
    $plano['vendedor_tem_limite_suficiente'] = ($limite_vendedor['restante'] >= intval($plano['limite']));
    
    // VERIFICAR SE O PLANO É VIÁVEL PARA O COMPRADOR (limite do plano >= uso atual)
    $plano['plano_viável'] = (intval($plano['limite']) >= $limite_utilizado_comprador);
    
    $planos_revenda[] = $plano;
}

// Buscar planos do comprador
$sql_meus_planos = "SELECT * FROM planos_pagamento WHERE tipo = 'revenda' AND byid = '$comprador_id' ORDER BY valor ASC";
$result_meus_planos = mysqli_query($conn, $sql_meus_planos);
$meus_planos = array();
while ($plano = mysqli_fetch_assoc($result_meus_planos)) {
    $meus_planos[] = $plano;
}

// Configurações do painel
$result_cfg = $conn->query("SELECT * FROM configs");
$cfg = $result_cfg->fetch_assoc();
$nomepainel = isset($cfg['nomepainel']) ? $cfg['nomepainel'] : 'Painel';
$logo = isset($cfg['logo']) ? $cfg['logo'] : '';
$icon = isset($cfg['icon']) ? $cfg['icon'] : '';
$csspersonali = isset($cfg['corfundologo']) ? $cfg['corfundologo'] : '';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Comprar Planos de Revenda</title>
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
        .content-wrapper { max-width: 1630px; margin: 0 auto 0 5px !important; padding: 0px !important; }
        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: #2c3e50 !important; padding: 8px 16px !important;
            border-radius: 30px !important; font-size: 13px !important; margin-top: 5px !important;
            margin-bottom: 15px !important; border-left: 4px solid #4158D0 !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: #4158D0; }
        
        .meu-limite-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 20px;
            border: 1px solid rgba(16,185,129,0.3);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .meu-limite-card .label { font-size: 12px; color: rgba(255,255,255,0.6); }
        .meu-limite-card .value { font-size: 20px; font-weight: 800; color: #10b981; }
        .meu-limite-card .valoridade { font-size: 12px; color: rgba(255,255,255,0.5); }
        
        .uso-atual-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 20px;
            border: 1px solid rgba(245,158,11,0.3);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .uso-atual-card .label { font-size: 12px; color: rgba(255,255,255,0.6); }
        .uso-atual-card .value { font-size: 20px; font-weight: 800; color: #f59e0b; }
        .uso-atual-card .warning { font-size: 11px; color: #fbbf24; }
        
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
        .tab-btn.active { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .tab-btn:hover:not(.active) { background: rgba(255,255,255,0.05); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .planos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
        .plano-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden; transition: all 0.3s;
        }
        .plano-card.vendedor-sem-limite { opacity: 0.7; border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.05); }
        .plano-card.uso-excedente { border-color: rgba(245,158,11,0.5); background: rgba(245,158,11,0.05); }
        .plano-header { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.1)); padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .plano-nome { font-size: 18px; font-weight: 700; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .vendedor-badge { font-size: 10px; padding: 3px 10px; border-radius: 20px; background: rgba(65,88,208,0.2); color: #818cf8; }
        .limite-badge { font-size: 10px; padding: 3px 8px; border-radius: 20px; background: rgba(16,185,129,0.2); color: #10b981; }
        .limite-badge.sem-limite { background: rgba(239,68,68,0.2); color: #f87171; }
        .limite-badge.uso-excedente { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .plano-preco { text-align: center; padding: 20px; }
        .preco-valor { font-size: 28px; font-weight: 800; color: #10b981; }
        .preco-periodo { font-size: 12px; color: rgba(255,255,255,0.5); }
        .plano-body { padding: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-label { font-size: 12px; color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 6px; }
        .info-value { font-size: 12px; font-weight: 600; color: white; }
        .btn-comprar {
            width: 100%; padding: 12px; border: none; border-radius: 30px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; font-weight: 600; font-size: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.3s; margin-top: 12px;
        }
        .btn-comprar.disabled, .btn-comprar:disabled {
            background: linear-gradient(135deg, #64748b, #475569);
            cursor: not-allowed;
            opacity: 0.6;
        }
        .btn-comprar.warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }
        .empty-state { text-align: center; padding: 60px; background: rgba(255,255,255,0.03); border-radius: 20px; }
        .empty-state i { font-size: 64px; color: rgba(255,255,255,0.2); margin-bottom: 16px; display: block; }
        .empty-state h3 { color: white; font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: rgba(255,255,255,0.5); }
        
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalFadeIn 0.3s ease; max-width: 500px; width: 90%; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-30px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15);
        }
        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header h5 { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8; }
        .modal-close:hover { opacity: 1; }
        .modal-body { padding: 24px; color: white; text-align: center; }
        .modal-footer { border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .modal-icon { text-align: center; margin-bottom: 20px; }
        .modal-icon i { font-size: 70px; }
        .modal-icon.success i { color: #10b981; }
        .modal-icon.error i { color: #dc2626; }
        .modal-icon.warning i { color: #f59e0b; }
        .btn-modal { padding: 9px 20px; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; color: white; }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        
        .info-note {
            background: rgba(16,185,129,0.1);
            border-left: 3px solid #10b981;
            padding: 12px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .info-note.warning {
            background: rgba(245,158,11,0.1);
            border-left-color: #f59e0b;
        }
        
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 10px !important; }
            .planos-grid { grid-template-columns: 1fr; }
            .tabs-container { width: 100%; justify-content: center; }
            .modal-container { width: 95%; }
            .meu-limite-card, .uso-atual-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
session_start();
error_reporting(0);

include_once("conexao.php");
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
include('header2.php');

// Verificar login
if (!isset($_SESSION['login']) || !isset($_SESSION['iduser'])) {
    header('Location: ../index.php');
    exit;
}

$comprador_id = $_SESSION['iduser'];

// ========== VERIFICAÇÃO DE TOKEN ==========
if (!file_exists('/admin/suspenderrev.php')) {
    // Não bloqueia
} else {
    include_once '/admin/suspenderrev.php';
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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

// ========== FUNÇÃO PARA CALCULAR LIMITE RESTANTE DO VENDEDOR ==========
function calcularLimiteRestante($conn, $vendedor_id) {
    // 1. Buscar limite total do vendedor
    $sql_total = "SELECT limite FROM atribuidos WHERE userid = '$vendedor_id'";
    $result_total = mysqli_query($conn, $sql_total);
    $total = mysqli_fetch_assoc($result_total);
    $limite_total = isset($total['limite']) ? intval($total['limite']) : 0;
    
    // 2. Somar limites das revendas que ele criou (byid = vendedor_id)
    $sql_revendas = "SELECT SUM(limite) as total FROM atribuidos WHERE byid = '$vendedor_id' AND userid != '$vendedor_id'";
    $result_revendas = mysqli_query($conn, $sql_revendas);
    $revendas = mysqli_fetch_assoc($result_revendas);
    $limite_revendas = isset($revendas['total']) ? intval($revendas['total']) : 0;
    
    // 3. Somar limites dos usuários que ele criou
    $sql_usuarios = "SELECT SUM(limite) as total FROM ssh_accounts WHERE byid = '$vendedor_id'";
    $result_usuarios = mysqli_query($conn, $sql_usuarios);
    $usuarios = mysqli_fetch_assoc($result_usuarios);
    $limite_usuarios = isset($usuarios['total']) ? intval($usuarios['total']) : 0;
    
    // 4. Calcular limite restante
    $limite_usado = $limite_revendas + $limite_usuarios;
    $limite_restante = $limite_total - $limite_usado;
    
    return [
        'total' => $limite_total,
        'usado' => $limite_usado,
        'restante' => $limite_restante
    ];
}

// ========== FUNÇÃO PARA CALCULAR LIMITE JÁ UTILIZADO PELO COMPRADOR ==========
function calcularLimiteUtilizado($conn, $comprador_id) {
    // 1. Somar limites das revendas que ele criou (byid = comprador_id)
    $sql_revendas = "SELECT SUM(limite) as total FROM atribuidos WHERE byid = '$comprador_id' AND userid != '$comprador_id'";
    $result_revendas = mysqli_query($conn, $sql_revendas);
    $revendas = mysqli_fetch_assoc($result_revendas);
    $limite_revendas = isset($revendas['total']) ? intval($revendas['total']) : 0;
    
    // 2. Somar limites dos usuários que ele criou
    $sql_usuarios = "SELECT SUM(limite) as total FROM ssh_accounts WHERE byid = '$comprador_id'";
    $result_usuarios = mysqli_query($conn, $sql_usuarios);
    $usuarios = mysqli_fetch_assoc($result_usuarios);
    $limite_usuarios = isset($usuarios['total']) ? intval($usuarios['total']) : 0;
    
    // 3. Calcular total utilizado
    $total_usado = $limite_revendas + $limite_usuarios;
    
    return $total_usado;
}

// ========== VARIÁVEIS PARA MODAIS ==========
$show_success_modal = false;
$show_error_modal = false;
$msg_sucesso = '';
$msg_erro = '';

// ========== BUSCAR LIMITE DO COMPRADOR ==========
$limite_comprador = calcularLimiteRestante($conn, $comprador_id);

// ========== BUSCAR LIMITE JÁ UTILIZADO PELO COMPRADOR ==========
$limite_utilizado_comprador = calcularLimiteUtilizado($conn, $comprador_id);

// ========== BUSCAR LIMITE ATUAL DO COMPRADOR ==========
$sql_limite_atual = "SELECT limite FROM atribuidos WHERE userid = '$comprador_id'";
$result_limite_atual = mysqli_query($conn, $sql_limite_atual);
$limite_atual_data = mysqli_fetch_assoc($result_limite_atual);
$limite_atual_comprador = isset($limite_atual_data['limite']) ? intval($limite_atual_data['limite']) : 0;

// ========== PROCESSAR COMPRA DE PLANO ==========
if (isset($_POST['comprar_plano'])) {
    $plano_id = intval($_POST['plano_id']);
    
    // Buscar dados do plano
    $sql_plano = "SELECT * FROM planos_pagamento WHERE id = '$plano_id' AND status = 1 AND tipo = 'revenda'";
    $result_plano = mysqli_query($conn, $sql_plano);
    $plano = mysqli_fetch_assoc($result_plano);
    
    if (!$plano) {
        $msg_erro = "Plano não encontrado ou indisponível!";
        $show_error_modal = true;
    } else {
        $vendedor_id = $plano['byid'];
        $limite_plano = intval($plano['limite']);
        
        // ========== NOVA VALIDAÇÃO: VERIFICAR SE O COMPRADOR NÃO ESTÁ USANDO MAIS DO QUE O NOVO LIMITE ==========
        if ($limite_plano < $limite_utilizado_comprador) {
            $msg_erro = "❌ VOCÊ NÃO PODE MUDAR PARA ESTE PLANO!\n\n";
            $msg_erro .= "Você está atualmente usando " . number_format($limite_utilizado_comprador, 0, ',', '.') . " créditos em revendas e usuários.\n";
            $msg_erro .= "O plano que você deseja comprar tem apenas " . number_format($limite_plano, 0, ',', '.') . " créditos de limite.\n\n";
            $msg_erro .= "⚠️ Para mudar para um plano com limite menor, você precisa primeiro:\n";
            $msg_erro .= "• Reduzir a quantidade de revendas que você criou\n";
            $msg_erro .= "• Excluir usuários inativos\n";
            $msg_erro .= "• Ou aguardar a expiração dos usuários\n\n";
            $msg_erro .= "📊 Seu uso atual: " . number_format($limite_utilizado_comprador, 0, ',', '.') . " / " . number_format($limite_atual_comprador, 0, ',', '.') . " créditos usados.";
            $show_error_modal = true;
        } else {
            // ========== CALCULAR LIMITE RESTANTE DO VENDEDOR ==========
            $limite_vendedor = calcularLimiteRestante($conn, $vendedor_id);
            
            // Buscar nome do vendedor
            $sql_vendedor_nome = "SELECT login, nome FROM accounts WHERE id = '$vendedor_id'";
            $result_vendedor_nome = mysqli_query($conn, $sql_vendedor_nome);
            $vendedor_dados = mysqli_fetch_assoc($result_vendedor_nome);
            $nome_vendedor = $vendedor_dados['nome'] ?? $vendedor_dados['login'] ?? 'Revendedor';
            
            // ========== TRAVA: Verificar se vendedor tem limite restante suficiente ==========
            if ($limite_vendedor['restante'] < $limite_plano) {
                $msg_erro = "❌ O vendedor '" . htmlspecialchars($nome_vendedor) . "' NÃO TEM LIMITE RESTANTE SUFICIENTE para vender este plano!\n\n";
                $msg_erro .= "Ele precisa de " . number_format($limite_plano, 0, ',', '.') . " créditos, mas tem apenas " . number_format($limite_vendedor['restante'], 0, ',', '.') . " disponíveis.\n\n";
                $msg_erro .= "📊 Limite Total: " . number_format($limite_vendedor['total'], 0, ',', '.') . "\n";
                $msg_erro .= "📊 Já utilizado: " . number_format($limite_vendedor['usado'], 0, ',', '.') . "\n";
                $msg_erro .= "📊 Disponível: " . number_format($limite_vendedor['restante'], 0, ',', '.');
                $show_error_modal = true;
            } else {
                $_SESSION['plano_compra'] = array(
                    'id' => $plano['id'],
                    'nome' => $plano['nome'],
                    'valor' => $plano['valor'],
                    'limite' => $limite_plano,
                    'duracao_dias' => $plano['duracao_dias'],
                    'byid' => $plano['byid'],
                    'vendedor_nome' => $nome_vendedor,
                    'vendedor_limite_restante' => $limite_vendedor['restante']
                );
                
                echo "<script>window.location.href = 'pagamento_revenda.php?plano_id=" . $plano['id'] . "';</script>";
                exit;
            }
        }
    }
}

// ========== BUSCAR PLANOS DE REVENDA DISPONÍVEIS ==========
$sql_planos = "SELECT p.*
               FROM planos_pagamento p
               WHERE p.tipo = 'revenda' AND p.status = 1 AND p.byid != '$comprador_id'
               ORDER BY p.valor ASC";
$result_planos = mysqli_query($conn, $sql_planos);

$planos_revenda = array();
while ($plano = mysqli_fetch_assoc($result_planos)) {
    // Buscar nome do vendedor
    $sql_vendedor = "SELECT login, nome FROM accounts WHERE id = '{$plano['byid']}'";
    $result_vendedor = mysqli_query($conn, $sql_vendedor);
    $vendedor = mysqli_fetch_assoc($result_vendedor);
    $plano['vendedor_nome'] = $vendedor['nome'] ?? $vendedor['login'] ?? 'Revendedor';
    
    // Calcular limite restante do vendedor
    $limite_vendedor = calcularLimiteRestante($conn, $plano['byid']);
    $plano['vendedor_limite_total'] = $limite_vendedor['total'];
    $plano['vendedor_limite_usado'] = $limite_vendedor['usado'];
    $plano['vendedor_limite_restante'] = $limite_vendedor['restante'];
    $plano['vendedor_tem_limite_suficiente'] = ($limite_vendedor['restante'] >= intval($plano['limite']));
    
    // VERIFICAR SE O PLANO É VIÁVEL PARA O COMPRADOR (limite do plano >= uso atual)
    $plano['plano_viável'] = (intval($plano['limite']) >= $limite_utilizado_comprador);
    
    $planos_revenda[] = $plano;
}

// Buscar planos do comprador
$sql_meus_planos = "SELECT * FROM planos_pagamento WHERE tipo = 'revenda' AND byid = '$comprador_id' ORDER BY valor ASC";
$result_meus_planos = mysqli_query($conn, $sql_meus_planos);
$meus_planos = array();
while ($plano = mysqli_fetch_assoc($result_meus_planos)) {
    $meus_planos[] = $plano;
}

// Configurações do painel
$result_cfg = $conn->query("SELECT * FROM configs");
$cfg = $result_cfg->fetch_assoc();
$nomepainel = isset($cfg['nomepainel']) ? $cfg['nomepainel'] : 'Painel';
$logo = isset($cfg['logo']) ? $cfg['logo'] : '';
$icon = isset($cfg['icon']) ? $cfg['icon'] : '';
$csspersonali = isset($cfg['corfundologo']) ? $cfg['corfundologo'] : '';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Comprar Planos de Revenda</title>
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
        .content-wrapper { max-width: 1630px; margin: 0 auto 0 5px !important; padding: 0px !important; }
        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: #2c3e50 !important; padding: 8px 16px !important;
            border-radius: 30px !important; font-size: 13px !important; margin-top: 5px !important;
            margin-bottom: 15px !important; border-left: 4px solid #4158D0 !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: #4158D0; }
        
        .meu-limite-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 20px;
            border: 1px solid rgba(16,185,129,0.3);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .meu-limite-card .label { font-size: 12px; color: rgba(255,255,255,0.6); }
        .meu-limite-card .value { font-size: 20px; font-weight: 800; color: #10b981; }
        .meu-limite-card .valoridade { font-size: 12px; color: rgba(255,255,255,0.5); }
        
        .uso-atual-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 12px 18px; margin-bottom: 20px;
            border: 1px solid rgba(245,158,11,0.3);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .uso-atual-card .label { font-size: 12px; color: rgba(255,255,255,0.6); }
        .uso-atual-card .value { font-size: 20px; font-weight: 800; color: #f59e0b; }
        .uso-atual-card .warning { font-size: 11px; color: #fbbf24; }
        
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
        .tab-btn.active { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .tab-btn:hover:not(.active) { background: rgba(255,255,255,0.05); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .planos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
        .plano-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden; transition: all 0.3s;
        }
        .plano-card.vendedor-sem-limite { opacity: 0.7; border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.05); }
        .plano-card.uso-excedente { border-color: rgba(245,158,11,0.5); background: rgba(245,158,11,0.05); }
        .plano-header { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.1)); padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .plano-nome { font-size: 18px; font-weight: 700; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .vendedor-badge { font-size: 10px; padding: 3px 10px; border-radius: 20px; background: rgba(65,88,208,0.2); color: #818cf8; }
        .limite-badge { font-size: 10px; padding: 3px 8px; border-radius: 20px; background: rgba(16,185,129,0.2); color: #10b981; }
        .limite-badge.sem-limite { background: rgba(239,68,68,0.2); color: #f87171; }
        .limite-badge.uso-excedente { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .plano-preco { text-align: center; padding: 20px; }
        .preco-valor { font-size: 28px; font-weight: 800; color: #10b981; }
        .preco-periodo { font-size: 12px; color: rgba(255,255,255,0.5); }
        .plano-body { padding: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-label { font-size: 12px; color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 6px; }
        .info-value { font-size: 12px; font-weight: 600; color: white; }
        .btn-comprar {
            width: 100%; padding: 12px; border: none; border-radius: 30px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; font-weight: 600; font-size: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.3s; margin-top: 12px;
        }
        .btn-comprar.disabled, .btn-comprar:disabled {
            background: linear-gradient(135deg, #64748b, #475569);
            cursor: not-allowed;
            opacity: 0.6;
        }
        .btn-comprar.warning {
            background: linear-gradient(135deg, #f59e0b, #f97316);
        }
        .empty-state { text-align: center; padding: 60px; background: rgba(255,255,255,0.03); border-radius: 20px; }
        .empty-state i { font-size: 64px; color: rgba(255,255,255,0.2); margin-bottom: 16px; display: block; }
        .empty-state h3 { color: white; font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: rgba(255,255,255,0.5); }
        
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalFadeIn 0.3s ease; max-width: 500px; width: 90%; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-30px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15);
        }
        .modal-header {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .modal-header h5 { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8; }
        .modal-close:hover { opacity: 1; }
        .modal-body { padding: 24px; color: white; text-align: center; }
        .modal-footer { border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .modal-icon { text-align: center; margin-bottom: 20px; }
        .modal-icon i { font-size: 70px; }
        .modal-icon.success i { color: #10b981; }
        .modal-icon.error i { color: #dc2626; }
        .modal-icon.warning i { color: #f59e0b; }
        .btn-modal { padding: 9px 20px; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; color: white; }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-modal-warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
        
        .info-note {
            background: rgba(16,185,129,0.1);
            border-left: 3px solid #10b981;
            padding: 12px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .info-note.warning {
            background: rgba(245,158,11,0.1);
            border-left-color: #f59e0b;
        }
        
        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 10px !important; }
            .planos-grid { grid-template-columns: 1fr; }
            .tabs-container { width: 100%; justify-content: center; }
            .modal-container { width: 95%; }
            .meu-limite-card, .uso-atual-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        
        <div class="info-badge">
            <i class='bx bx-store-alt'></i>
            <span>Comprar Planos de Revenda</span>
        </div>
        
        <div class="meu-limite-card">
            <div>
                <span class="label"><i class='bx bx-credit-card'></i> Seu Limite</span>
                <div class="value">
                    <?php echo number_format($limite_comprador['total'], 0, ',', '.'); ?> créditos
                </div>
            </div>
            <div>
                <span class="label"><i class='bx bx-chart'></i> Já Utilizado</span>
                <div class="value" style="color: #fbbf24;">
                    <?php echo number_format($limite_comprador['usado'], 0, ',', '.'); ?> créditos
                </div>
            </div>
            <div>
                <span class="label"><i class='bx bx-check-circle'></i> Disponível</span>
                <div class="value" style="color: #10b981;">
                    <?php echo number_format($limite_comprador['restante'], 0, ',', '.'); ?> créditos
                </div>
            </div>
        </div>
        
        <div class="uso-atual-card">
            <div>
                <span class="label"><i class='bx bx-bar-chart-alt'></i> USO ATUAL DE CRÉDITOS</span>
                <div class="value">
                    <?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> / <?php echo number_format($limite_atual_comprador, 0, ',', '.'); ?> créditos
                </div>
            </div>
            <div>
                <span class="warning"><i class='bx bx-info-circle'></i> Você está usando <?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> créditos</span>
            </div>
        </div>
        
        <div class="tabs-container">
            <button class="tab-btn active" onclick="mudarTab('comprar')">
                <i class='bx bx-shopping-bag'></i> Planos Disponíveis
            </button>
            <button class="tab-btn" onclick="mudarTab('meus')">
                <i class='bx bx-crown'></i> Meus Planos (para vender)
            </button>
        </div>
        
        <!-- Tab Planos Disponíveis para Compra -->
        <div id="tab-comprar" class="tab-content active">
            <?php if (count($planos_revenda) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($planos_revenda as $plano): ?>
                <?php 
                $plano_viavel = $plano['plano_viável'];
                $vendedor_tem_limite = $plano['vendedor_tem_limite_suficiente'];
                $pode_comprar = ($plano_viavel && $vendedor_tem_limite);
                ?>
                <div class="plano-card 
                    <?php if (!$vendedor_tem_limite) echo 'vendedor-sem-limite'; ?>
                    <?php if (!$plano_viavel && $vendedor_tem_limite) echo 'uso-excedente'; ?>">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <span class="vendedor-badge">
                                    <i class='bx bx-user'></i> <?php echo htmlspecialchars($plano['vendedor_nome']); ?>
                                </span>
                                <?php if (!$vendedor_tem_limite): ?>
                                <span class="limite-badge sem-limite">
                                    <i class='bx bx-error-circle'></i> Vendedor sem limite
                                </span>
                                <?php elseif (!$plano_viavel): ?>
                                <span class="limite-badge uso-excedente">
                                    <i class='bx bx-error-circle'></i> Limite insuficiente
                                </span>
                                <?php else: ?>
                                <span class="limite-badge">
                                    <i class='bx bx-check-circle'></i> Disponível
                                </span>
                                <?php endif; ?>
                            </div>
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
                        <?php if (!$plano_viavel && $vendedor_tem_limite): ?>
                        <div class="info-row" style="background: rgba(245,158,11,0.2); border-radius: 8px; margin-top: 5px; padding: 8px;">
                            <span class="info-label"><i class='bx bx-error-circle' style="color: #f59e0b;"></i> ⚠️ Você usa mais que este plano</span>
                            <span class="info-value" style="color: #f59e0b;">
                                Seu uso: <?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> créditos
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row" style="background: <?php echo $vendedor_tem_limite ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; border-radius: 8px; margin-top: 5px; padding: 8px;">
                            <span class="info-label"><i class='bx bx-bar-chart-alt'></i> Limite do Vendedor</span>
                            <span class="info-value" style="color: <?php echo $vendedor_tem_limite ? '#10b981' : '#f87171'; ?>; font-weight: bold;">
                                Disponível: <strong><?php echo number_format($plano['vendedor_limite_restante'], 0, ',', '.'); ?></strong> / Necessário: <?php echo number_format($plano['limite'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="plano_id" value="<?php echo $plano['id']; ?>">
                            <?php if ($pode_comprar): ?>
                            <button type="submit" name="comprar_plano" class="btn-comprar">
                                <i class='bx bx-cart'></i> Comprar Agora
                            </button>
                            <?php elseif (!$plano_viavel && $vendedor_tem_limite): ?>
                            <button type="button" class="btn-comprar warning" disabled style="background: linear-gradient(135deg, #f59e0b, #f97316); cursor: not-allowed; opacity: 0.7;">
                                <i class='bx bx-error-circle'></i> Limite insuficiente para seu uso
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-comprar disabled" disabled>
                                <i class='bx bx-error-circle'></i> Vendedor sem limite suficiente
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-store-alt'></i>
                <h3>Nenhum plano disponível</h3>
                <p>No momento não há planos de revenda disponíveis para compra.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Meus Planos -->
        <div id="tab-meus" class="tab-content">
            <?php if (count($meus_planos) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($meus_planos as $plano): ?>
                <div class="plano-card">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <span class="vendedor-badge" style="background: rgba(16,185,129,0.2); color: #10b981;">
                                <i class='bx bx-check-circle'></i> Seu Plano
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
                        <?php if (!empty($plano['descricao'])): ?>
                        <div class="plano-descricao"><?php echo htmlspecialchars_decode($plano['descricao']); ?></div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-show'></i> Status</span>
                            <span class="info-value" style="color: <?php echo $plano['status'] == 1 ? '#10b981' : '#f87171'; ?>;">
                                <?php echo $plano['status'] == 1 ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-crown'></i>
                <h3>Você ainda não criou planos</h3>
                <p>Você pode criar planos para vender para outros revendedores.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="info-note <?php echo ($limite_utilizado_comprador > 0) ? 'warning' : ''; ?>">
            <i class='bx bx-info-circle'></i>
            <p><strong>⚠️ IMPORTANTE:</strong> Você só pode mudar para planos com limite IGUAL ou MAIOR que seu uso atual (<?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> créditos).<br>
            Se quiser um plano com limite menor, primeiro reduza suas revendas/usuários.</p>
        </div>
        
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-icon success"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; margin-bottom:10px;">Operação Realizada!</h3>
                <p style="color:rgba(255,255,255,0.8);"><?php echo htmlspecialchars($msg_sucesso); ?></p>
            </div>
            <div class="modal-footer">
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
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white; margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); white-space: pre-line;"><?php echo htmlspecialchars($msg_erro); ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
function mudarTab(tab) {
    var comprarBtn = document.querySelectorAll('.tab-btn')[0];
    var meusBtn = document.querySelectorAll('.tab-btn')[1];
    var comprarContent = document.getElementById('tab-comprar');
    var meusContent = document.getElementById('tab-meus');
    
    if (tab === 'comprar') {
        comprarBtn.classList.add('active');
        meusBtn.classList.remove('active');
        comprarContent.classList.add('active');
        meusContent.classList.remove('active');
    } else {
        meusBtn.classList.add('active');
        comprarBtn.classList.remove('active');
        meusContent.classList.add('active');
        comprarContent.classList.remove('active');
    }
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('show');
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modals = document.querySelectorAll('.modal-overlay.show');
        for (var i = 0; i < modals.length; i++) {
            modals[i].classList.remove('show');
        }
    }
});

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
</script>
</body>
</html>h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        
        <div class="info-badge">
            <i class='bx bx-store-alt'></i>
            <span>Comprar Planos de Revenda</span>
        </div>
        
        <div class="meu-limite-card">
            <div>
                <span class="label"><i class='bx bx-credit-card'></i> Seu Limite</span>
                <div class="value">
                    <?php echo number_format($limite_comprador['total'], 0, ',', '.'); ?> créditos
                </div>
            </div>
            <div>
                <span class="label"><i class='bx bx-chart'></i> Já Utilizado</span>
                <div class="value" style="color: #fbbf24;">
                    <?php echo number_format($limite_comprador['usado'], 0, ',', '.'); ?> créditos
                </div>
            </div>
            <div>
                <span class="label"><i class='bx bx-check-circle'></i> Disponível</span>
                <div class="value" style="color: #10b981;">
                    <?php echo number_format($limite_comprador['restante'], 0, ',', '.'); ?> créditos
                </div>
            </div>
        </div>
        
        <div class="uso-atual-card">
            <div>
                <span class="label"><i class='bx bx-bar-chart-alt'></i> USO ATUAL DE CRÉDITOS</span>
                <div class="value">
                    <?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> / <?php echo number_format($limite_atual_comprador, 0, ',', '.'); ?> créditos
                </div>
            </div>
            <div>
                <span class="warning"><i class='bx bx-info-circle'></i> Você está usando <?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> créditos</span>
            </div>
        </div>
        
        <div class="tabs-container">
            <button class="tab-btn active" onclick="mudarTab('comprar')">
                <i class='bx bx-shopping-bag'></i> Planos Disponíveis
            </button>
            <button class="tab-btn" onclick="mudarTab('meus')">
                <i class='bx bx-crown'></i> Meus Planos (para vender)
            </button>
        </div>
        
        <!-- Tab Planos Disponíveis para Compra -->
        <div id="tab-comprar" class="tab-content active">
            <?php if (count($planos_revenda) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($planos_revenda as $plano): ?>
                <?php 
                $plano_viavel = $plano['plano_viável'];
                $vendedor_tem_limite = $plano['vendedor_tem_limite_suficiente'];
                $pode_comprar = ($plano_viavel && $vendedor_tem_limite);
                ?>
                <div class="plano-card 
                    <?php if (!$vendedor_tem_limite) echo 'vendedor-sem-limite'; ?>
                    <?php if (!$plano_viavel && $vendedor_tem_limite) echo 'uso-excedente'; ?>">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <span class="vendedor-badge">
                                    <i class='bx bx-user'></i> <?php echo htmlspecialchars($plano['vendedor_nome']); ?>
                                </span>
                                <?php if (!$vendedor_tem_limite): ?>
                                <span class="limite-badge sem-limite">
                                    <i class='bx bx-error-circle'></i> Vendedor sem limite
                                </span>
                                <?php elseif (!$plano_viavel): ?>
                                <span class="limite-badge uso-excedente">
                                    <i class='bx bx-error-circle'></i> Limite insuficiente
                                </span>
                                <?php else: ?>
                                <span class="limite-badge">
                                    <i class='bx bx-check-circle'></i> Disponível
                                </span>
                                <?php endif; ?>
                            </div>
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
                        <?php if (!$plano_viavel && $vendedor_tem_limite): ?>
                        <div class="info-row" style="background: rgba(245,158,11,0.2); border-radius: 8px; margin-top: 5px; padding: 8px;">
                            <span class="info-label"><i class='bx bx-error-circle' style="color: #f59e0b;"></i> ⚠️ Você usa mais que este plano</span>
                            <span class="info-value" style="color: #f59e0b;">
                                Seu uso: <?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> créditos
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row" style="background: <?php echo $vendedor_tem_limite ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; border-radius: 8px; margin-top: 5px; padding: 8px;">
                            <span class="info-label"><i class='bx bx-bar-chart-alt'></i> Limite do Vendedor</span>
                            <span class="info-value" style="color: <?php echo $vendedor_tem_limite ? '#10b981' : '#f87171'; ?>; font-weight: bold;">
                                Disponível: <strong><?php echo number_format($plano['vendedor_limite_restante'], 0, ',', '.'); ?></strong> / Necessário: <?php echo number_format($plano['limite'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="plano_id" value="<?php echo $plano['id']; ?>">
                            <?php if ($pode_comprar): ?>
                            <button type="submit" name="comprar_plano" class="btn-comprar">
                                <i class='bx bx-cart'></i> Comprar Agora
                            </button>
                            <?php elseif (!$plano_viavel && $vendedor_tem_limite): ?>
                            <button type="button" class="btn-comprar warning" disabled style="background: linear-gradient(135deg, #f59e0b, #f97316); cursor: not-allowed; opacity: 0.7;">
                                <i class='bx bx-error-circle'></i> Limite insuficiente para seu uso
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-comprar disabled" disabled>
                                <i class='bx bx-error-circle'></i> Vendedor sem limite suficiente
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-store-alt'></i>
                <h3>Nenhum plano disponível</h3>
                <p>No momento não há planos de revenda disponíveis para compra.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Meus Planos -->
        <div id="tab-meus" class="tab-content">
            <?php if (count($meus_planos) > 0): ?>
            <div class="planos-grid">
                <?php foreach ($meus_planos as $plano): ?>
                <div class="plano-card">
                    <div class="plano-header">
                        <div class="plano-nome">
                            <?php echo htmlspecialchars($plano['nome']); ?>
                            <span class="vendedor-badge" style="background: rgba(16,185,129,0.2); color: #10b981;">
                                <i class='bx bx-check-circle'></i> Seu Plano
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
                        <?php if (!empty($plano['descricao'])): ?>
                        <div class="plano-descricao"><?php echo htmlspecialchars_decode($plano['descricao']); ?></div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label"><i class='bx bx-show'></i> Status</span>
                            <span class="info-value" style="color: <?php echo $plano['status'] == 1 ? '#10b981' : '#f87171'; ?>;">
                                <?php echo $plano['status'] == 1 ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class='bx bx-crown'></i>
                <h3>Você ainda não criou planos</h3>
                <p>Você pode criar planos para vender para outros revendedores.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="info-note <?php echo ($limite_utilizado_comprador > 0) ? 'warning' : ''; ?>">
            <i class='bx bx-info-circle'></i>
            <p><strong>⚠️ IMPORTANTE:</strong> Você só pode mudar para planos com limite IGUAL ou MAIOR que seu uso atual (<?php echo number_format($limite_utilizado_comprador, 0, ',', '.'); ?> créditos).<br>
            Se quiser um plano com limite menor, primeiro reduza suas revendas/usuários.</p>
        </div>
        
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-icon success"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; margin-bottom:10px;">Operação Realizada!</h3>
                <p style="color:rgba(255,255,255,0.8);"><?php echo htmlspecialchars($msg_sucesso); ?></p>
            </div>
            <div class="modal-footer">
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
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white; margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); white-space: pre-line;"><?php echo htmlspecialchars($msg_erro); ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
function mudarTab(tab) {
    var comprarBtn = document.querySelectorAll('.tab-btn')[0];
    var meusBtn = document.querySelectorAll('.tab-btn')[1];
    var comprarContent = document.getElementById('tab-comprar');
    var meusContent = document.getElementById('tab-meus');
    
    if (tab === 'comprar') {
        comprarBtn.classList.add('active');
        meusBtn.classList.remove('active');
        comprarContent.classList.add('active');
        meusContent.classList.remove('active');
    } else {
        meusBtn.classList.add('active');
        comprarBtn.classList.remove('active');
        meusContent.classList.add('active');
        comprarContent.classList.remove('active');
    }
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('show');
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modals = document.querySelectorAll('.modal-overlay.show');
        for (var i = 0; i < modals.length; i++) {
            modals[i].classList.remove('show');
        }
    }
});

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
</script>
</body>
</html>

