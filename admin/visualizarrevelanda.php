<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio562110($input)
    {
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

include('headeradmin2.php');

if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

// Sistema de Temas
if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else {
    $temaAtual = [];
}

// Anti SQL
function anti_sql_v($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m) { return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$id = anti_sql_v($_GET['id'] ?? '0');
if (!$id || !is_numeric($id)) { echo "<script>alert('ID inválido!');history.back();</script>"; exit; }

// ========== DADOS DO REVENDEDOR ==========
$sql_rev = "SELECT * FROM accounts WHERE id = '$id'";
$result_rev = $conn->query($sql_rev);
if (!$result_rev || $result_rev->num_rows == 0) { echo "<script>alert('Revendedor não encontrado!');history.back();</script>"; exit; }
$rev = $result_rev->fetch_assoc();

$sql_atrib = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result_atrib = $conn->query($sql_atrib);
$atrib = $result_atrib ? $result_atrib->fetch_assoc() : [];

// Dono
$dono_login = 'admin';
if (!empty($rev['byid'])) {
    $r_dono = $conn->query("SELECT login FROM accounts WHERE id = '".$rev['byid']."'");
    if ($r_dono && $r_dono->num_rows > 0) { $d = $r_dono->fetch_assoc(); $dono_login = $d['login']; }
}

// Categoria
$categoria_nome = 'N/A';
if (!empty($atrib['categoriaid'])) {
    $r_cat = $conn->query("SELECT nome FROM categorias WHERE subid = '".$atrib['categoriaid']."'");
    if ($r_cat && $r_cat->num_rows > 0) { $c = $r_cat->fetch_assoc(); $categoria_nome = $c['nome']; }
}

// Status
$suspenso = ($atrib['suspenso'] ?? 0) == 1;
$tipo = $atrib['tipo'] ?? 'Validade';
$limite = $atrib['limite'] ?? 0;
$expira_raw = $atrib['expira'] ?? '';
$expira_formatada = ($expira_raw != '') ? date('d/m/Y H:i', strtotime($expira_raw)) : 'Nunca';
$valormensal = $atrib['valormensal'] ?? '0.00';

$conta_vencida = false; $dias_restantes = 0; $horas_restantes = 0;
if ($tipo == 'Validade' && $expira_raw != '') {
    $diferenca = strtotime($expira_raw) - time();
    $dias_restantes = floor($diferenca / 86400);
    $horas_restantes = floor(($diferenca % 86400) / 3600);
    if ($dias_restantes < 0) $conta_vencida = true;
}

// Foto
$profile_image = $rev['profile_image'] ?? '';
$avatar_url = !empty($profile_image) ? '../uploads/profiles/' . $profile_image : 'https://ui-avatars.com/api/?name=' . urlencode($rev['login']) . '&size=120&background=7c3aed&color=fff&bold=true';

// ========== ESTATÍSTICAS ==========
$total_usuarios = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid = '$id'"); if ($r) { $rr = $r->fetch_assoc(); $total_usuarios = $rr['t']; }
$total_onlines = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid = '$id' AND status = 'Online'"); if ($r) { $rr = $r->fetch_assoc(); $total_onlines = $rr['t']; }
$total_vencidos = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid = '$id' AND expira < NOW()"); if ($r) { $rr = $r->fetch_assoc(); $total_vencidos = $rr['t']; }
$total_suspensos_user = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid = '$id' AND mainid = 'Suspenso'"); if ($r) { $rr = $r->fetch_assoc(); $total_suspensos_user = $rr['t']; }
$total_revendas = 0; $r = $conn->query("SELECT COUNT(*) as t FROM accounts WHERE byid = '$id' AND login != 'admin'"); if ($r) { $rr = $r->fetch_assoc(); $total_revendas = $rr['t']; }

$limite_usado_users = 0; $r = $conn->query("SELECT COALESCE(SUM(limite),0) as t FROM ssh_accounts WHERE byid = '$id'"); if ($r) { $rr = $r->fetch_assoc(); $limite_usado_users = $rr['t']; }
$limite_usado_revs = 0; $r = $conn->query("SELECT COALESCE(SUM(limite),0) as t FROM atribuidos WHERE byid = '$id'"); if ($r) { $rr = $r->fetch_assoc(); $limite_usado_revs = $rr['t']; }
$limite_usado_total = $limite_usado_users + $limite_usado_revs;
$limite_restante = $limite - $limite_usado_total;
$pct_uso = $limite > 0 ? round(($limite_usado_total / $limite) * 100) : 0;

// Total vendido
$total_vendido = 0;
$r = $conn->query("SELECT COALESCE(SUM(valor),0) as t FROM pagamentos WHERE byid = '$id' AND status = 'Aprovado'"); if ($r) { $rr = $r->fetch_assoc(); $total_vendido += $rr['t']; }
$r = $conn->query("SELECT COALESCE(SUM(valor),0) as t FROM pagamentos_unificado WHERE revendedor_id = '$id' AND status = 'approved'"); if ($r) { $rr = $r->fetch_assoc(); $total_vendido += $rr['t']; }

$total_pagamentos = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM pagamentos WHERE byid = '$id'"); if ($r) { $rr = $r->fetch_assoc(); $total_pagamentos += $rr['t']; }
$r = $conn->query("SELECT COUNT(*) as t FROM pagamentos_unificado WHERE revendedor_id = '$id'"); if ($r) { $rr = $r->fetch_assoc(); $total_pagamentos += $rr['t']; }

// Tabelas
$result_users = $conn->query("SELECT * FROM ssh_accounts WHERE byid = '$id' ORDER BY FIELD(status,'Online','Offline'), login ASC");
$result_sub_revs = $conn->query("SELECT a.*, at.limite as rev_limite, at.expira as rev_expira, at.tipo as rev_tipo, at.suspenso as rev_suspenso FROM accounts a LEFT JOIN atribuidos at ON at.userid = a.id WHERE a.byid = '$id' AND a.login != 'admin' ORDER BY a.id DESC");
$result_pags = $conn->query("SELECT * FROM pagamentos WHERE byid = '$id' ORDER BY id DESC LIMIT 50");
$result_pags_uni = $conn->query("SELECT * FROM pagamentos_unificado WHERE revendedor_id = '$id' ORDER BY id DESC LIMIT 50");

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar - <?php echo htmlspecialchars($rev['login']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
<?php
if (function_exists('getCSSVariables')) { echo getCSSVariables($temaAtual); }
else { echo ':root{--primaria:#7c3aed;--secundaria:#a78bfa;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}'; }
?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* ========== STATS CARD (cabeçalho igual criar usuário) ========== */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#7c3aed);}
.stats-card-icon{
    width:60px;height:60px;
    background:linear-gradient(135deg,#7c3aed,#a78bfa);
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;flex-shrink:0;
}
.stats-card-content{flex:1;min-width:0;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{
    font-size:36px;font-weight:800;
    background:linear-gradient(135deg,#fff,#a78bfa);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}
.sc-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:6px;font-size:9px;font-weight:700;}
.sc-badge-active{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3);}
.sc-badge-suspended{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);}
.sc-badge-expired{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}
.sc-badge-credit{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.3);}
.sc-badge-validity{background:rgba(139,92,246,.15);color:#a78bfa;border:1px solid rgba(139,92,246,.3);}

/* ========== MINI STATS ========== */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{
    flex:1;min-width:100px;
    background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;
    border:1px solid rgba(255,255,255,0.06);text-align:center;
    transition:all .2s;
}
.mini-stat:hover{border-color:var(--primaria,#7c3aed);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* ========== MODERN CARD (igual criar usuário) ========== */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;margin-bottom:16px;transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#7c3aed);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.primary{background:linear-gradient(135deg,#7c3aed,#a78bfa);}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.card-header-custom.orange{background:linear-gradient(135deg,#f59e0b,#f97316);}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.card-header-custom.cyan{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.header-icon{
    width:36px;height:36px;background:rgba(255,255,255,0.2);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body{padding:16px;}

/* ========== BOTÕES (igual criar usuário) ========== */
.btn{
    padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    gap:6px;color:white;transition:all .2s;text-decoration:none;font-family:inherit;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-primary{background:linear-gradient(135deg,#7c3aed,#a78bfa);}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-warning{background:linear-gradient(135deg,var(--aviso,#f59e0b),#f97316);}
.btn-info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.btn-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-cancel:hover{background:rgba(255,255,255,.15);}
.btn-sm{padding:5px 12px;font-size:11px;border-radius:8px;}
.action-buttons{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}

/* ========== PROFILE INFO GRID ========== */
.profile-info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-bottom:14px;}
.pi-item{background:rgba(255,255,255,.04);border-radius:10px;padding:10px 12px;border:1px solid rgba(255,255,255,.06);}
.pi-label{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;display:flex;align-items:center;gap:4px;}
.pi-label i{font-size:11px;}
.pi-value{font-size:13px;font-weight:700;word-break:break-all;}
.pi-value.success{color:#34d399;} .pi-value.warning{color:#fbbf24;} .pi-value.danger{color:#f87171;} .pi-value.info{color:#818cf8;} .pi-value.purple{color:#a78bfa;}

/* ========== LIMITE CARD ========== */
.limite-body{display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.limite-chart{flex-shrink:0;}
.limite-stats{flex:1;display:flex;flex-direction:column;gap:8px;min-width:200px;}
.ls-row{display:flex;align-items:center;gap:10px;}
.ls-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.ls-info{flex:1;}
.ls-label{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;}
.ls-val{font-size:15px;font-weight:700;}
.ls-bar{height:4px;background:rgba(255,255,255,.08);border-radius:10px;margin-top:3px;overflow:hidden;}
.ls-fill{height:100%;border-radius:10px;}
.limite-pct{font-size:28px;font-weight:800;background:linear-gradient(135deg,#7c3aed,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}

/* ========== TABS ========== */
.tabs-header{display:flex;gap:4px;flex-wrap:wrap;background:rgba(255,255,255,.04);border-radius:12px;padding:4px;margin-bottom:14px;border:1px solid rgba(255,255,255,.06);}
.tab-btn{
    flex:1;min-width:90px;padding:9px 14px;background:transparent;border:none;
    border-radius:9px;color:rgba(255,255,255,0.5);font-size:11px;font-weight:600;
    cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:5px;font-family:inherit;
}
.tab-btn:hover{color:white;background:rgba(255,255,255,.06);}
.tab-btn.active{background:linear-gradient(135deg,#7c3aed,#a78bfa);color:white;}
.tab-badge{background:rgba(255,255,255,.2);padding:1px 6px;border-radius:6px;font-size:9px;}
.tab-content{display:none;animation:fadeTab .3s ease;}
.tab-content.active{display:block;}
@keyframes fadeTab{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* ========== TABELA ========== */
.table-card-header{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.table-card-title{font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px;}
.table-card-title i{font-size:16px;color:var(--primaria,#7c3aed);}
.table-search{padding:6px 10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:white;font-size:11px;min-width:160px;outline:none;font-family:inherit;}
.table-search:focus{border-color:var(--primaria,#7c3aed);}
.table-search::placeholder{color:rgba(255,255,255,.3);}

.data-table{width:100%;border-collapse:collapse;}
.data-table th{padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(255,255,255,.06);}
.data-table td{padding:9px 12px;font-size:11px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
.data-table tbody tr:hover{background:rgba(255,255,255,.03);}
.data-table tbody tr:last-child td{border-bottom:none;}

.badge-sm{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:6px;font-size:9px;font-weight:600;}
.badge-online{background:rgba(16,185,129,.15);color:#34d399;}
.badge-offline{background:rgba(239,68,68,.15);color:#f87171;}
.badge-ativo{background:rgba(16,185,129,.15);color:#34d399;}
.badge-suspenso{background:rgba(239,68,68,.15);color:#f87171;}
.badge-aprovado{background:rgba(16,185,129,.15);color:#34d399;}
.badge-pendente{background:rgba(245,158,11,.15);color:#fbbf24;}
.badge-expirado{background:rgba(245,158,11,.15);color:#fbbf24;}

.table-responsive{overflow-x:auto;}
.table-responsive::-webkit-scrollbar{height:5px;}
.table-responsive::-webkit-scrollbar-track{background:rgba(255,255,255,.02);}
.table-responsive::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:3px;}

.empty-table{text-align:center;padding:30px;color:rgba(255,255,255,.3);font-size:12px;}
.empty-table i{font-size:30px;display:block;margin-bottom:6px;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:24px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:80px;}
    .profile-info-grid{grid-template-columns:1fr 1fr;}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
    .tabs-header{overflow-x:auto;flex-wrap:nowrap;}
    .tab-btn{min-width:80px;white-space:nowrap;}
    .limite-body{flex-direction:column;}
}
    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

    <!-- ========== STATS CARD (cabeçalho igual criar usuário) ========== -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-store-alt'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Visualizar Revendedor</div>
            <div class="stats-card-value"><?php echo htmlspecialchars($rev['login']); ?></div>
            <div class="stats-card-subtitle">
                ID #<?php echo $id; ?> — Dono: <?php echo htmlspecialchars($dono_login); ?>
                <?php if ($suspenso): ?><span class="sc-badge sc-badge-suspended"><i class='bx bx-lock'></i> Suspenso</span>
                <?php elseif ($conta_vencida): ?><span class="sc-badge sc-badge-expired"><i class='bx bx-time'></i> Vencido</span>
                <?php else: ?><span class="sc-badge sc-badge-active"><i class='bx bx-check-circle'></i> Ativo</span>
                <?php endif; ?>
                <?php if ($tipo == 'Credito'): ?><span class="sc-badge sc-badge-credit"><i class='bx bx-infinite'></i> Crédito</span>
                <?php else: ?><span class="sc-badge sc-badge-validity"><i class='bx bx-calendar'></i> Validade</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-store-alt'></i></div>
    </div>

    <!-- ========== MINI STATS ========== -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_usuarios; ?></div><div class="mini-stat-lbl">Usuários</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_onlines; ?></div><div class="mini-stat-lbl">Onlines</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_vencidos; ?></div><div class="mini-stat-lbl">Vencidos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_suspensos_user; ?></div><div class="mini-stat-lbl">Suspensos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_revendas; ?></div><div class="mini-stat-lbl">Sub-Revendas</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;">R$ <?php echo number_format($total_vendido, 2, ',', '.'); ?></div><div class="mini-stat-lbl">Vendido</div></div>
    </div>

    <!-- ========== AÇÕES RÁPIDAS ========== -->
    <div class="action-buttons">
        <a href="javascript:history.back()" class="btn btn-cancel"><i class='bx bx-arrow-back'></i> Voltar</a>
        <a href="editarrevenda.php?id=<?php echo $id; ?>" class="btn btn-primary"><i class='bx bx-edit'></i> Editar</a>
        <?php if ($tipo != 'Credito'): ?>
        <a href="renovarrevenda.php?id=<?php echo $id; ?>" class="btn btn-success"><i class='bx bx-calendar-plus'></i> Renovar</a>
        <?php endif; ?>
        <?php if (!$suspenso): ?>
        <a href="suspenderrevenda.php?id=<?php echo $id; ?>" class="btn btn-warning"><i class='bx bx-pause'></i> Suspender</a>
        <?php else: ?>
        <a href="reativarrevenda.php?id=<?php echo $id; ?>" class="btn btn-info"><i class='bx bx-refresh'></i> Reativar</a>
        <?php endif; ?>
        <a href="excluirrevenda.php?id=<?php echo $id; ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja deletar?')"><i class='bx bx-trash'></i> Deletar</a>
    </div>

    <!-- ========== DETALHES DO REVENDEDOR ========== -->
    <div class="modern-card">
        <div class="card-header-custom primary">
            <div class="header-icon"><i class='bx bx-id-card'></i></div>
            <div>
                <div class="header-title">Detalhes do Revendedor</div>
                <div class="header-subtitle">Informações completas da conta</div>
            </div>
        </div>
        <div class="card-body">
            <div class="profile-info-grid">
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login</div>
                    <div class="pi-value"><?php echo htmlspecialchars($rev['login']); ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                    <div class="pi-value"><?php echo htmlspecialchars($rev['senha']); ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-crown' style="color:#a78bfa;"></i> Dono</div>
                    <div class="pi-value purple"><?php echo htmlspecialchars($dono_login); ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-layer' style="color:#60a5fa;"></i> Limite Total</div>
                    <div class="pi-value info"><?php echo $limite; ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-category' style="color:#f472b6;"></i> Categoria</div>
                    <div class="pi-value"><?php echo htmlspecialchars($categoria_nome); ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-credit-card' style="color:#34d399;"></i> Modo</div>
                    <div class="pi-value success"><?php echo $tipo; ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Vencimento</div>
                    <div class="pi-value <?php echo $conta_vencida ? 'danger' : 'warning'; ?>">
                        <?php echo $tipo == 'Credito' ? 'Nunca' : $expira_formatada; ?>
                    </div>
                </div>
                <?php if ($tipo == 'Validade' && $expira_raw != ''): ?>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-time' style="color:#fb923c;"></i> Tempo Restante</div>
                    <div class="pi-value <?php echo $conta_vencida ? 'danger' : ($dias_restantes <= 5 ? 'warning' : 'success'); ?>">
                        <?php echo $conta_vencida ? 'Expirado há '.abs($dias_restantes).' dias' : $dias_restantes.'d '.$horas_restantes.'h'; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($rev['whatsapp'])): ?>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp</div>
                    <div class="pi-value"><?php echo htmlspecialchars($rev['whatsapp']); ?></div>
                </div>
                <?php endif; ?>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-dollar' style="color:#34d399;"></i> Total Vendido</div>
                    <div class="pi-value success">R$ <?php echo number_format($total_vendido, 2, ',', '.'); ?></div>
                </div>
                <div class="pi-item">
                    <div class="pi-label"><i class='bx bx-dollar-circle' style="color:#fbbf24;"></i> Valor Mensal</div>
                    <div class="pi-value warning">R$ <?php echo number_format((float)$valormensal, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== LIMITE ========== -->
    <div class="modern-card">
        <div class="card-header-custom green">
            <div class="header-icon"><i class='bx bx-bar-chart-alt-2'></i></div>
            <div>
                <div class="header-title">Uso do Limite — <span class="limite-pct" style="font-size:16px;"><?php echo $pct_uso; ?>%</span></div>
                <div class="header-subtitle">Distribuição de uso do limite total</div>
            </div>
        </div>
        <div class="card-body">
            <div class="limite-body">
                <div class="limite-chart"><div id="chartLimite"></div></div>
                <div class="limite-stats">
                    <div class="ls-row">
                        <div class="ls-icon" style="background:rgba(65,88,208,.2);color:#818cf8;"><i class='bx bx-user'></i></div>
                        <div class="ls-info"><div class="ls-label">Usuários</div><div class="ls-val"><?php echo $limite_usado_users; ?></div><div class="ls-bar"><div class="ls-fill" style="width:<?php echo $limite>0?round(($limite_usado_users/$limite)*100):0; ?>%;background:linear-gradient(90deg,#4158D0,#6366f1);"></div></div></div>
                    </div>
                    <div class="ls-row">
                        <div class="ls-icon" style="background:rgba(124,58,237,.2);color:#a78bfa;"><i class='bx bx-store-alt'></i></div>
                        <div class="ls-info"><div class="ls-label">Revendedores</div><div class="ls-val"><?php echo $limite_usado_revs; ?></div><div class="ls-bar"><div class="ls-fill" style="width:<?php echo $limite>0?round(($limite_usado_revs/$limite)*100):0; ?>%;background:linear-gradient(90deg,#7c3aed,#a78bfa);"></div></div></div>
                    </div>
                    <div class="ls-row">
                        <div class="ls-icon" style="background:rgba(16,185,129,.2);color:#34d399;"><i class='bx bx-check-circle'></i></div>
                        <div class="ls-info"><div class="ls-label">Disponível</div><div class="ls-val"><?php echo max(0, $limite_restante); ?></div><div class="ls-bar"><div class="ls-fill" style="width:<?php echo $limite>0?round((max(0,$limite_restante)/$limite)*100):0; ?>%;background:linear-gradient(90deg,#10b981,#34d399);"></div></div></div>
                    </div>
                    <div class="ls-row">
                        <div class="ls-icon" style="background:rgba(251,191,36,.15);color:#fbbf24;"><i class='bx bx-bar-chart'></i></div>
                        <div class="ls-info"><div class="ls-label">Total do Plano</div><div class="ls-val"><?php echo $limite; ?></div><div class="ls-bar"><div class="ls-fill" style="width:100%;background:linear-gradient(90deg,#fbbf24,#f59e0b);"></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== TABS ========== -->
    <div class="tabs-header">
        <button class="tab-btn active" onclick="trocarTab('tabUsuarios',this)"><i class='bx bx-user'></i> Usuários <span class="tab-badge"><?php echo $total_usuarios; ?></span></button>
        <button class="tab-btn" onclick="trocarTab('tabRevendas',this)"><i class='bx bx-store-alt'></i> Revendas <span class="tab-badge"><?php echo $total_revendas; ?></span></button>
        <button class="tab-btn" onclick="trocarTab('tabPagamentos',this)"><i class='bx bx-receipt'></i> Pagamentos <span class="tab-badge"><?php echo $total_pagamentos; ?></span></button>
    </div>

    <!-- TAB: USUÁRIOS -->
    <div class="tab-content active" id="tabUsuarios">
        <div class="modern-card">
            <div class="table-card-header">
                <div class="table-card-title"><i class='bx bx-user'></i> Usuários do Revendedor</div>
                <input type="text" class="table-search" placeholder="Buscar..." onkeyup="filtrarTabela('tabelaUsuarios',this.value)">
            </div>
            <div class="table-responsive">
                <table class="data-table" id="tabelaUsuarios">
                    <thead><tr><th>Login</th><th>Senha</th><th>Limite</th><th>Vencimento</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($result_users && $result_users->num_rows > 0): while ($u = $result_users->fetch_assoc()):
                        $u_exp = !empty($u['expira']) ? date('d/m/Y H:i', strtotime($u['expira'])) : 'N/A';
                        $u_venc = (!empty($u['expira']) && strtotime($u['expira']) < time());
                        $u_susp = ($u['mainid'] == 'Suspenso');
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($u['login']); ?></td>
                        <td><?php echo htmlspecialchars($u['senha']); ?></td>
                        <td><?php echo $u['limite']; ?></td>
                        <td><?php if($u_venc): ?><span class="badge-sm badge-expirado"><?php echo $u_exp; ?></span><?php else: echo $u_exp; endif; ?></td>
                        <td>
                            <?php if ($u_susp): ?><span class="badge-sm badge-suspenso"><i class='bx bx-lock'></i> Suspenso</span>
                            <?php elseif ($u['status'] == 'Online'): ?><span class="badge-sm badge-online"><i class='bx bx-wifi'></i> Online</span>
                            <?php else: ?><span class="badge-sm badge-offline"><i class='bx bx-wifi-off'></i> Offline</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5"><div class="empty-table"><i class='bx bx-user'></i>Nenhum usuário</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: REVENDAS -->
    <div class="tab-content" id="tabRevendas">
        <div class="modern-card">
            <div class="table-card-header">
                <div class="table-card-title"><i class='bx bx-store-alt'></i> Sub-Revendedores</div>
                <input type="text" class="table-search" placeholder="Buscar..." onkeyup="filtrarTabela('tabelaRevendas',this.value)">
            </div>
            <div class="table-responsive">
                <table class="data-table" id="tabelaRevendas">
                    <thead><tr><th>Login</th><th>Senha</th><th>Limite</th><th>Modo</th><th>Vencimento</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($result_sub_revs && $result_sub_revs->num_rows > 0): while ($sr = $result_sub_revs->fetch_assoc()):
                        $sr_exp = (!empty($sr['rev_expira'])) ? date('d/m/Y H:i', strtotime($sr['rev_expira'])) : 'Nunca';
                        $sr_susp = ($sr['rev_suspenso'] ?? 0) == 1;
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($sr['login']); ?></td>
                        <td><?php echo htmlspecialchars($sr['senha']); ?></td>
                        <td><?php echo $sr['rev_limite'] ?? 0; ?></td>
                        <td><?php echo $sr['rev_tipo'] ?? 'N/A'; ?></td>
                        <td><?php echo ($sr['rev_tipo'] ?? '') == 'Credito' ? 'Nunca' : $sr_exp; ?></td>
                        <td><?php if ($sr_susp): ?><span class="badge-sm badge-suspenso"><i class='bx bx-lock'></i> Suspenso</span><?php else: ?><span class="badge-sm badge-ativo"><i class='bx bx-check-circle'></i> Ativo</span><?php endif; ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6"><div class="empty-table"><i class='bx bx-store-alt'></i>Nenhum sub-revendedor</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: PAGAMENTOS -->
    <div class="tab-content" id="tabPagamentos">
        <div class="modern-card">
            <div class="table-card-header">
                <div class="table-card-title"><i class='bx bx-receipt'></i> Pagamentos Recebidos</div>
                <input type="text" class="table-search" placeholder="Buscar..." onkeyup="filtrarTabela('tabelaPagamentos',this.value)">
            </div>
            <div class="table-responsive">
                <table class="data-table" id="tabelaPagamentos">
                    <thead><tr><th>Login</th><th>ID Pagamento</th><th>Valor</th><th>Detalhes</th><th>Data</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php
                    $tem_pag = false;
                    if ($result_pags && $result_pags->num_rows > 0): $tem_pag = true; while ($pg = $result_pags->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($pg['login'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($pg['idpagamento'] ?? ''); ?></td>
                        <td style="font-weight:700;color:#34d399;">R$ <?php echo number_format((float)($pg['valor'] ?? 0), 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($pg['texto'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($pg['data'] ?? ''); ?></td>
                        <td><?php if (($pg['status'] ?? '') == 'Aprovado'): ?><span class="badge-sm badge-aprovado"><i class='bx bx-check'></i> Aprovado</span><?php else: ?><span class="badge-sm badge-pendente"><i class='bx bx-time'></i> Pendente</span><?php endif; ?></td>
                    </tr>
                    <?php endwhile; endif; ?>
                    <?php if ($result_pags_uni && $result_pags_uni->num_rows > 0): $tem_pag = true; while ($pu = $result_pags_uni->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($pu['payer_email'] ?? $pu['login'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($pu['payment_id'] ?? $pu['id']); ?></td>
                        <td style="font-weight:700;color:#34d399;">R$ <?php echo number_format((float)($pu['valor'] ?? 0), 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($pu['descricao'] ?? ''); ?></td>
                        <td><?php echo !empty($pu['created_at']) ? date('d/m/Y H:i', strtotime($pu['created_at'])) : ''; ?></td>
                        <td><?php if (($pu['status'] ?? '') == 'approved'): ?><span class="badge-sm badge-aprovado"><i class='bx bx-check'></i> Aprovado</span><?php else: ?><span class="badge-sm badge-pendente"><i class='bx bx-time'></i> <?php echo ucfirst($pu['status'] ?? 'Pendente'); ?></span><?php endif; ?></td>
                    </tr>
                    <?php endwhile; endif; ?>
                    <?php if (!$tem_pag): ?>
                    <tr><td colspan="6"><div class="empty-table"><i class='bx bx-receipt'></i>Nenhum pagamento</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div></div></div>

<script>
// Gráfico
new ApexCharts(document.querySelector("#chartLimite"),{
    series:[<?php echo $limite_usado_users; ?>,<?php echo $limite_usado_revs; ?>,<?php echo max(0,$limite_restante); ?>],
    chart:{type:'donut',height:170,background:'transparent'},
    labels:['Usuários','Revendedores','Disponível'],
    colors:['#4158D0','#7c3aed','#10b981'],
    dataLabels:{enabled:false},legend:{show:false},
    plotOptions:{pie:{donut:{size:'65%',labels:{show:true,total:{show:true,label:'Usado',color:'#fff',formatter:function(){return '<?php echo $pct_uso; ?>%';}}}}}},
    stroke:{width:0},theme:{mode:'dark'}
}).render();

// Tabs
function trocarTab(id,btn){
    document.querySelectorAll('.tab-content').forEach(function(t){t.classList.remove('active');});
    document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

// Filtro
function filtrarTabela(tabelaId,busca){
    busca=busca.toLowerCase();
    document.querySelectorAll('#'+tabelaId+' tbody tr').forEach(function(row){
        row.style.display=row.textContent.toLowerCase().includes(busca)?'':'none';
    });
}
</script>
</body>
</html>
<?php
    }
    aleatorio562110($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

