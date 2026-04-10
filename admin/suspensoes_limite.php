<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) security();
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

// ===== AÇÕES AJAX =====
if (isset($_POST['reativar_ajax'])) {
    $login = mysqli_real_escape_string($conn, $_POST['login']);
    $conn->query("UPDATE ssh_accounts SET mainid=NULL WHERE login='$login'");
    $conn->query("UPDATE suspensoes_limite SET reativado=1, data_reativacao=NOW() WHERE login='$login' AND reativado=0");
    echo 'ok'; exit;
}
if (isset($_POST['reativar_todos_ajax'])) {
    $r = $conn->query("SELECT login FROM suspensoes_limite WHERE reativado=0");
    $count = 0;
    while ($row = $r->fetch_assoc()) {
        $conn->query("UPDATE ssh_accounts SET mainid=NULL WHERE login='" . mysqli_real_escape_string($conn, $row['login']) . "'");
        $count++;
    }
    $conn->query("UPDATE suspensoes_limite SET reativado=1, data_reativacao=NOW() WHERE reativado=0");
    echo $count; exit;
}
if (isset($_POST['excluir_ajax'])) {
    $login = mysqli_real_escape_string($conn, $_POST['login']);
    $conn->query("DELETE FROM ssh_accounts WHERE login='$login'");
    $conn->query("DELETE FROM onlines WHERE usuario='$login'");
    $conn->query("UPDATE suspensoes_limite SET reativado=1, data_reativacao=NOW() WHERE login='$login' AND reativado=0");
    @shell_exec("pkill -u " . escapeshellarg($login) . " 2>/dev/null");
    @shell_exec("userdel -f " . escapeshellarg($login) . " 2>/dev/null");
    echo 'ok'; exit;
}
if (isset($_POST['limpar_historico_ajax'])) {
    $conn->query("DELETE FROM suspensoes_limite WHERE reativado=1");
    echo 'ok'; exit;
}

// ===== FILTROS =====
function anti_sql($i) {
    $s = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){return '';}, $i);
    return addslashes(strip_tags(trim($s)));
}

$busca = anti_sql($_GET['busca'] ?? '');
$filtro = anti_sql($_GET['filtro'] ?? 'todos');
$filtro_rev = anti_sql($_GET['rev'] ?? 'todos');

$where = "1=1";
if (!empty($busca)) $where .= " AND s.login LIKE '%$busca%'";
if ($filtro === 'suspensos') $where .= " AND s.reativado = 0";
elseif ($filtro === 'reativados') $where .= " AND s.reativado = 1";
if ($filtro_rev !== 'todos') $where .= " AND s.byid = '$filtro_rev'";

$por_pagina = 15;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $por_pagina;

$total = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite s WHERE $where")->fetch_assoc()['t'] ?? 0;
$total_pag = max(1, ceil($total / $por_pagina));

$registros = [];
$res = $conn->query("SELECT s.*, a.id as user_id, a.expira, a.senha, a.limite as limite_atual, a.uuid, a.mainid,
                            acc.login as acc_rev_nome
                     FROM suspensoes_limite s
                     LEFT JOIN ssh_accounts a ON a.login = s.login
                     LEFT JOIN accounts acc ON acc.id = s.byid
                     WHERE $where
                     ORDER BY s.reativado ASC, s.data_suspensao DESC
                     LIMIT $por_pagina OFFSET $offset");
if ($res) while ($r = $res->fetch_assoc()) $registros[] = $r;

$stat_total     = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite")->fetch_assoc()['t'] ?? 0;
$stat_suspensos = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE reativado=0")->fetch_assoc()['t'] ?? 0;
$stat_reat      = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE reativado=1")->fetch_assoc()['t'] ?? 0;
$stat_hoje      = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE DATE(data_suspensao)=CURDATE()")->fetch_assoc()['t'] ?? 0;
$stat_semana    = $conn->query("SELECT COUNT(*) as t FROM suspensoes_limite WHERE data_suspensao >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['t'] ?? 0;

$revs = [];
$rr = $conn->query("SELECT DISTINCT s.byid, COALESCE(acc.login, CONCAT('Rev #', s.byid)) as nome, COUNT(*) as total,
                           SUM(CASE WHEN s.reativado=0 THEN 1 ELSE 0 END) as ativos
                    FROM suspensoes_limite s LEFT JOIN accounts acc ON acc.id = s.byid
                    GROUP BY s.byid ORDER BY total DESC");
if ($rr) while ($row = $rr->fetch_assoc()) $revs[] = $row;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bloqueados por Limite</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if(function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); ?>

*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh}
.app-content{margin-left:0!important;padding:0!important}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important}

/* ========== STATS CARD ========== */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(239,68,68,.12);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s}
.stats-card:hover{transform:translateY(-2px);border-color:rgba(239,68,68,.3)}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,#ef4444,#dc2626);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;flex-shrink:0;position:relative}
.stats-card-icon .pulse-ring{position:absolute;inset:-5px;border:2px solid rgba(239,68,68,.3);border-radius:22px;animation:pulseRing 2s ease-in-out infinite}
@keyframes pulseRing{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.1);opacity:.3}}
.stats-card-content{flex:1}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#f87171,#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.05}
.stats-card-right{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}

/* ========== MINI STATS ========== */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s;cursor:pointer;text-decoration:none;color:inherit}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px)}
.mini-stat.active{border-color:#ef4444;background:rgba(239,68,68,.06)}
.mini-stat-ic{font-size:20px;margin-bottom:4px}
.mini-stat-val{font-size:18px;font-weight:800}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}

/* ========== MODERN CARD ========== */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}
.modern-card:hover{border-color:var(--primaria,#10b981)}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px}
.card-header-custom.red{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669)}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.card-header-custom.gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-title{font-size:14px;font-weight:700;color:#fff}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7)}
.card-body-custom{padding:16px}

/* ========== FILTROS ========== */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-item{flex:1;min-width:140px}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.filter-input,.filter-select{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;font-size:12px;color:#fff!important;transition:all .2s;font-family:inherit;outline:none}
.filter-input:focus,.filter-select:focus{border-color:#ef4444;background:rgba(255,255,255,.09)}
.filter-input::placeholder{color:rgba(255,255,255,.3)}
.filter-select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.filter-select option{background:#1e293b;color:#fff!important}

/* ========== ACTION BUTTONS ========== */
.action-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}
.action-btn:disabled{opacity:.35;cursor:not-allowed;transform:none!important}
.action-btn i{font-size:14px;pointer-events:none}
.btn-save{background:linear-gradient(135deg,#10b981,#059669)}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.btn-back{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.btn-ghost{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.5)}
.btn-ghost:hover{border-color:#ef4444;color:#f87171}
.actions-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}

/* ========== GRID ========== */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:14px}

/* ========== BLOCK CARD ========== */
.block-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .25s;border:1px solid rgba(255,255,255,.08);position:relative}
.block-card:hover{transform:translateY(-2px);border-color:rgba(239,68,68,.25);box-shadow:0 8px 20px rgba(0,0,0,.25)}
.block-card.reativado{opacity:.65;border-color:rgba(16,185,129,.1)}
.block-card.reativado:hover{opacity:1;border-color:rgba(16,185,129,.3)}
.block-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#ef4444,#f87171,#fca5a5,#f87171,#ef4444);background-size:200%;animation:shimmer 3s linear infinite}
.block-card.reativado::before{background:linear-gradient(90deg,#10b981,#34d399,#6ee7b7,#34d399,#10b981);background-size:200%}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

.block-card-header{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.block-card-header.suspended{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.block-card-header.reactivated{background:linear-gradient(135deg,#10b981,#059669)}

.block-user-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.block-avatar{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;color:#fff}
.block-user-name{font-size:13px;font-weight:700;color:#fff;display:flex;align-items:center;gap:4px}
.block-user-rev{font-size:9px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:4px;margin-top:1px}
.block-status-badge{background:rgba(255,255,255,.2);padding:2px 8px;border-radius:20px;font-size:8px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
.v2ray-tag{background:#10b981;color:#fff;padding:1px 5px;border-radius:4px;font-size:7px;font-weight:700}

.block-card-body{padding:12px 14px}

/* Motivo */
.motivo-box{border-radius:10px;padding:8px 10px;margin-bottom:8px;display:flex;align-items:flex-start;gap:8px}
.motivo-box.limite{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.18)}
.motivo-box.reativado-m{background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15)}
.motivo-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff;flex-shrink:0}
.motivo-icon.red{background:linear-gradient(135deg,#ef4444,#dc2626)}
.motivo-icon.green{background:linear-gradient(135deg,#10b981,#059669)}
.motivo-label{font-size:7px;color:rgba(255,255,255,.35);text-transform:uppercase;font-weight:700;letter-spacing:.4px;margin-bottom:2px}
.motivo-text{font-size:11px;font-weight:700;line-height:1.3}
.motivo-text.red{color:#f87171}
.motivo-text.green{color:#34d399}

/* Conexões */
.conexoes-box{background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.1);border-radius:9px;padding:7px 10px;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.block-card.reativado .conexoes-box{background:rgba(16,185,129,.05);border-color:rgba(16,185,129,.1)}
.conexoes-nums{display:flex;align-items:baseline;gap:2px}
.conexoes-used{font-size:18px;font-weight:800;color:#ef4444}
.block-card.reativado .conexoes-used{color:#34d399}
.conexoes-sep{font-size:12px;color:rgba(255,255,255,.2)}
.conexoes-max{font-size:13px;font-weight:600;color:rgba(255,255,255,.4)}
.conexoes-bar-wrap{flex:1;height:6px;background:rgba(255,255,255,.06);border-radius:10px;overflow:hidden}
.conexoes-bar-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#ef4444,#dc2626)}
.block-card.reativado .conexoes-bar-fill{background:linear-gradient(90deg,#10b981,#059669)}
.conexoes-pct{font-size:10px;font-weight:800;color:#f87171;flex-shrink:0;min-width:30px;text-align:right}
.block-card.reativado .conexoes-pct{color:#34d399}

/* Info rows */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:6px}
.info-row{display:flex;align-items:center;justify-content:space-between;padding:5px 8px;background:rgba(255,255,255,.025);border-radius:7px}
.info-row:hover{background:rgba(255,255,255,.04)}
.info-row.full{grid-column:1/-1}
.info-lbl{font-size:9px;color:rgba(255,255,255,.35);display:flex;align-items:center;gap:4px}
.info-lbl i{font-size:11px}
.info-val{font-size:10px;font-weight:600}
.exp-ok{color:#34d399}.exp-warn{color:#fbbf24}.exp-danger{color:#f87171}

/* Card actions */
.card-actions{display:flex;gap:5px;margin-top:8px}
.card-actions .action-btn{flex:1;padding:6px 8px;font-size:10px}

/* Card footer */
.block-card-footer{padding:8px 14px;border-top:1px solid rgba(255,255,255,.04);display:flex;align-items:center;justify-content:space-between}
.block-card-footer-info{font-size:9px;color:rgba(255,255,255,.2)}
.block-card-footer-id{font-size:9px;color:rgba(255,255,255,.2);font-family:monospace}

/* ========== EMPTY STATE ========== */
.empty-state{grid-column:1/-1;text-align:center;padding:50px 20px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08)}
.empty-icon{width:70px;height:70px;border-radius:50%;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:30px;color:#34d399;border:2px solid rgba(16,185,129,.2)}
.empty-state h3{font-size:15px;margin-bottom:6px}
.empty-state p{font-size:11px;color:rgba(255,255,255,.3)}

/* ========== PAGINATION ========== */
.pagination-wrapper{display:flex;justify-content:center;align-items:center;gap:12px;flex-wrap:wrap;margin-top:20px;padding:10px 0}
.pagination{display:flex;align-items:center;gap:5px}
.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;text-decoration:none;font-size:11px;font-weight:500;transition:all .2s}
.pagination a:hover{background:#ef4444;border-color:#ef4444}
.pagination .active{background:#ef4444;border-color:#ef4444}
.pagination .disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,.3);font-size:10px}

/* ========== MODAL ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px}
.modal-overlay.show{display:flex}
.modal-container{animation:modalIn .3s ease;max-width:440px;width:92%}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5)}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669)}
.modal-header-custom.danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.modal-header-custom.loading{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}
.modal-body-custom{padding:18px;text-align:center}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(.34,1.56,.64,1) .15s both}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3)}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3)}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669)}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}

/* Modal info */
.modal-info-box{background:rgba(255,255,255,.04);border-radius:10px;padding:10px;margin-bottom:10px;border:1px solid rgba(255,255,255,.05)}
.modal-info-row{display:flex;align-items:center;gap:6px;padding:3px 0}
.modal-info-row i{font-size:13px;width:16px;text-align:center}
.modal-info-row span{font-size:11px;color:rgba(255,255,255,.6)}
.modal-info-row strong{font-size:11px;color:#fff}

/* Spinner */
.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:12px;padding:16px 0}
.spinner-ring{width:40px;height:40px;border:3px solid rgba(255,255,255,.06);border-top-color:#ef4444;border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ========== TOAST ========== */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669)}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .content-wrapper{padding:10px!important}
    .cards-grid{grid-template-columns:1fr}
    .stats-card{flex-wrap:wrap;padding:14px;gap:14px}
    .stats-card-icon{width:48px;height:48px;font-size:24px}
    .stats-card-value{font-size:28px}
    .stats-card-right{width:100%;justify-content:center}
    .mini-stats{flex-wrap:wrap}.mini-stat{min-width:70px}
    .filter-group{flex-direction:column}
    .info-grid{grid-template-columns:1fr}
    .card-actions{display:grid;grid-template-columns:1fr 1fr 1fr}
    .actions-row{flex-direction:column}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

<!-- STATS CARD -->
<div class="stats-card">
<div class="stats-card-icon"><div class="pulse-ring"></div><i class='bx bx-shield-x'></i></div>
<div class="stats-card-content">
<div class="stats-card-title">Bloqueados por Limite</div>
<div class="stats-card-value"><?php echo $stat_suspensos;?> Bloqueados</div>
<div class="stats-card-subtitle">Clientes bloqueados automaticamente por ultrapassar o limite de conexões</div>
</div>
<div class="stats-card-right">
<a href="home.php" class="action-btn btn-back"><i class='bx bx-arrow-back'></i> Voltar</a>
</div>
<div class="stats-card-decoration"><i class='bx bx-shield-x'></i></div>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
<a href="?filtro=suspensos&busca=<?php echo urlencode($busca);?>&rev=<?php echo $filtro_rev;?>" class="mini-stat <?php echo $filtro==='suspensos'?'active':'';?>"><div class="mini-stat-ic" style="color:#ef4444"><i class='bx bx-block'></i></div><div class="mini-stat-val" style="color:#ef4444"><?php echo $stat_suspensos;?></div><div class="mini-stat-lbl">Bloqueados</div></a>
<a href="?filtro=reativados&busca=<?php echo urlencode($busca);?>&rev=<?php echo $filtro_rev;?>" class="mini-stat <?php echo $filtro==='reativados'?'active':'';?>"><div class="mini-stat-ic" style="color:#34d399"><i class='bx bx-check-circle'></i></div><div class="mini-stat-val" style="color:#34d399"><?php echo $stat_reat;?></div><div class="mini-stat-lbl">Reativados</div></a>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#fbbf24"><i class='bx bx-calendar'></i></div><div class="mini-stat-val" style="color:#fbbf24"><?php echo $stat_hoje;?></div><div class="mini-stat-lbl">Hoje</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#60a5fa"><i class='bx bx-calendar-week'></i></div><div class="mini-stat-val" style="color:#60a5fa"><?php echo $stat_semana;?></div><div class="mini-stat-lbl">Semana</div></div>
<a href="?filtro=todos&busca=<?php echo urlencode($busca);?>&rev=<?php echo $filtro_rev;?>" class="mini-stat <?php echo $filtro==='todos'?'active':'';?>"><div class="mini-stat-ic" style="color:#a78bfa"><i class='bx bx-list-ul'></i></div><div class="mini-stat-val" style="color:#a78bfa"><?php echo $stat_total;?></div><div class="mini-stat-lbl">Total</div></a>
</div>

<!-- FILTROS -->
<div class="modern-card">
<div class="card-header-custom red">
<div class="header-icon"><i class='bx bx-filter-alt'></i></div>
<div><div class="header-title">Filtros & Ações</div><div class="header-subtitle">Busque e gerencie os bloqueios</div></div>
</div>
<div class="card-body-custom">
<div class="filter-group">
<div class="filter-item" style="min-width:200px">
<div class="filter-label">Buscar Login</div>
<input type="text" class="filter-input" id="searchInput" placeholder="Buscar por login..." value="<?php echo htmlspecialchars($busca);?>">
</div>
<div class="filter-item" style="max-width:160px">
<div class="filter-label">Status</div>
<select class="filter-select" id="filterStatus">
<option value="todos" <?php echo $filtro=='todos'?'selected':'';?>>📋 Todos</option>
<option value="suspensos" <?php echo $filtro=='suspensos'?'selected':'';?>>🔴 Bloqueados</option>
<option value="reativados" <?php echo $filtro=='reativados'?'selected':'';?>>🟢 Reativados</option>
</select>
</div>
<div class="filter-item" style="max-width:200px">
<div class="filter-label">Revendedor</div>
<select class="filter-select" id="filterRev">
<option value="todos" <?php echo $filtro_rev=='todos'?'selected':'';?>>Todos</option>
<?php foreach($revs as $rv):?>
<option value="<?php echo $rv['byid'];?>" <?php echo $filtro_rev==$rv['byid']?'selected':'';?>><?php echo htmlspecialchars($rv['nome']);?> (<?php echo $rv['total'];?>)</option>
<?php endforeach;?>
</select>
</div>
<div class="filter-item" style="max-width:fit-content;display:flex;gap:6px;align-items:flex-end">
<button class="action-btn btn-save" onclick="aplicarFiltros()" style="height:35px"><i class='bx bx-search'></i> Buscar</button>
</div>
</div>
<div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.06);display:flex;gap:6px;flex-wrap:wrap">
<?php if($stat_suspensos > 0):?>
<button class="action-btn btn-save" onclick="reativarTodos()"><i class='bx bx-check-double'></i> Reativar Todos (<?php echo $stat_suspensos;?>)</button>
<?php endif;?>
<?php if($stat_reat > 0):?>
<button class="action-btn btn-ghost" onclick="limparHistorico()"><i class='bx bx-trash'></i> Limpar Reativados (<?php echo $stat_reat;?>)</button>
<?php endif;?>
</div>
</div>
</div>

<!-- GRID -->
<div class="cards-grid">
<?php if(empty($registros)):?>
<div class="empty-state">
<div class="empty-icon"><i class='bx bx-check-shield'></i></div>
<h3>Nenhum bloqueio encontrado</h3>
<p><?php echo !empty($busca)?'Nenhum resultado para "'.htmlspecialchars($busca).'"':'Nenhum cliente foi bloqueado por ultrapassar o limite.';?></p>
</div>
<?php else: foreach($registros as $r):
    $is_reat = $r['reativado']==1;
    $rev_nome = !empty($r['acc_rev_nome'])?$r['acc_rev_nome']:'Rev #'.$r['byid'];
    $pct = $r['limite']>0?min(100,round(($r['conexoes']/$r['limite'])*100)):100;
    $expira_fmt = !empty($r['expira'])?date('d/m/Y',strtotime($r['expira'])):'N/A';
    $dias = !empty($r['expira'])?floor((strtotime($r['expira'])-time())/86400):999;
    $exp_class = $dias<=0?'exp-danger':($dias<=5?'exp-warn':'exp-ok');
?>
<div class="block-card <?php echo $is_reat?'reativado':'';?>">
<div class="block-card-header <?php echo $is_reat?'reactivated':'suspended';?>">
<div class="block-user-info">
<div class="block-avatar"><i class='bx bx-user'></i></div>
<div>
<div class="block-user-name">
<?php echo htmlspecialchars($r['login']);?>
<?php if(!empty($r['uuid'])):?><span class="v2ray-tag">V2RAY</span><?php endif;?>
</div>
<div class="block-user-rev"><i class='bx bx-store' style="font-size:10px"></i> <?php echo htmlspecialchars($rev_nome);?></div>
</div>
</div>
<span class="block-status-badge"><?php echo $is_reat?'✅ Reativado':'🔴 Bloqueado';?></span>
</div>
<div class="block-card-body">

<!-- Motivo -->
<div class="motivo-box <?php echo $is_reat?'reativado-m':'limite';?>">
<div class="motivo-icon <?php echo $is_reat?'green':'red';?>"><i class='bx bx-<?php echo $is_reat?'check-circle':'error-circle';?>'></i></div>
<div>
<div class="motivo-label">Motivo do bloqueio</div>
<div class="motivo-text <?php echo $is_reat?'green':'red';?>"><?php echo htmlspecialchars($r['motivo'] ?? 'Excedeu limite de dispositivos');?></div>
</div>
</div>

<!-- Conexões -->
<div class="conexoes-box">
<div class="conexoes-nums">
<span class="conexoes-used"><?php echo $r['conexoes'];?></span>
<span class="conexoes-sep">/</span>
<span class="conexoes-max"><?php echo $r['limite'];?></span>
</div>
<div class="conexoes-bar-wrap"><div class="conexoes-bar-fill" style="width:<?php echo $pct;?>%"></div></div>
<span class="conexoes-pct"><?php echo $pct;?>%</span>
</div>

<!-- Info Grid -->
<div class="info-grid">
<div class="info-row"><span class="info-lbl"><i class='bx bx-calendar-exclamation'></i> Bloqueado</span><span class="info-val"><?php echo date('d/m/Y H:i',strtotime($r['data_suspensao']));?></span></div>
<?php if($is_reat && $r['data_reativacao']):?>
<div class="info-row"><span class="info-lbl"><i class='bx bx-calendar-check' style="color:#34d399"></i> Reativado</span><span class="info-val" style="color:#34d399"><?php echo date('d/m/Y H:i',strtotime($r['data_reativacao']));?></span></div>
<?php endif;?>
<div class="info-row"><span class="info-lbl"><i class='bx bx-calendar'></i> Expira</span><span class="info-val <?php echo $exp_class;?>"><?php echo $expira_fmt;?> <?php echo $dias<=0?'(Expirado)':($dias<=5?"({$dias}d)":'');?></span></div>
<div class="info-row"><span class="info-lbl"><i class='bx bx-lock-alt'></i> Senha</span><span class="info-val" style="font-family:monospace;letter-spacing:.5px"><?php echo htmlspecialchars($r['senha'] ?? '—');?></span></div>
</div>

<!-- Actions -->
<div class="card-actions">
<?php if(!$is_reat):?>
<button class="action-btn btn-save" onclick="reativar('<?php echo addslashes($r['login']);?>')"><i class='bx bx-refresh'></i> Reativar</button>
<?php else:?>
<button class="action-btn btn-save" disabled><i class='bx bx-check'></i> OK</button>
<?php endif;?>
<button class="action-btn" style="background:linear-gradient(135deg,#3b82f6,#2563eb)" onclick="copiar('<?php echo addslashes($r['login']);?>','<?php echo addslashes($r['senha'] ?? '');?>','<?php echo addslashes($r['motivo'] ?? 'Excedeu limite');?>','<?php echo $r['conexoes'];?>','<?php echo $r['limite'];?>','<?php echo addslashes($rev_nome);?>')"><i class='bx bx-copy'></i> Copiar</button>
<button class="action-btn btn-danger" onclick="excluir('<?php echo addslashes($r['login']);?>')"><i class='bx bx-trash'></i> Excluir</button>
</div>
</div>
<div class="block-card-footer">
<span class="block-card-footer-info"><i class='bx bx-shield-x' style="font-size:11px"></i> <?php echo $is_reat?'Já reativado':'Aguardando ação';?></span>
<span class="block-card-footer-id">#<?php echo $r['id'];?></span>
</div>
</div>
<?php endforeach; endif;?>
</div>

<!-- PAGINAÇÃO -->
<?php if($total_pag > 1):?>
<div class="pagination-wrapper">
<div class="pagination">
<?php if($pagina>1):?><a href="?busca=<?php echo urlencode($busca);?>&filtro=<?php echo $filtro;?>&rev=<?php echo $filtro_rev;?>&pagina=<?php echo $pagina-1;?>"><i class='bx bx-chevron-left'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-left'></i></span><?php endif;?>
<?php
$ini=max(1,$pagina-2);$fim=min($total_pag,$pagina+2);
if($ini>1){echo '<a href="?busca='.urlencode($busca).'&filtro='.$filtro.'&rev='.$filtro_rev.'&pagina=1">1</a>';if($ini>2)echo '<span class="disabled">…</span>';}
for($p=$ini;$p<=$fim;$p++){echo($p==$pagina)?'<span class="active">'.$p.'</span>':'<a href="?busca='.urlencode($busca).'&filtro='.$filtro.'&rev='.$filtro_rev.'&pagina='.$p.'">'.$p.'</a>';}
if($fim<$total_pag){if($fim<$total_pag-1)echo '<span class="disabled">…</span>';echo '<a href="?busca='.urlencode($busca).'&filtro='.$filtro.'&rev='.$filtro_rev.'&pagina='.$total_pag.'">'.$total_pag.'</a>';}
?>
<?php if($pagina<$total_pag):?><a href="?busca=<?php echo urlencode($busca);?>&filtro=<?php echo $filtro;?>&rev=<?php echo $filtro_rev;?>&pagina=<?php echo $pagina+1;?>"><i class='bx bx-chevron-right'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-right'></i></span><?php endif;?>
</div>
</div>
<?php endif;?>
<div class="pagination-info">Mostrando <?php echo count($registros);?> de <?php echo $total;?> registros • <?php echo date('d/m/Y H:i:s');?></div>

</div></div>

<!-- MODAL CONFIRMAR -->
<div id="modalConfirmar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom success" id="mConfH"><h5 id="mConfTitle"><i class='bx bx-refresh'></i> Confirmar</h5><button class="modal-close" onclick="fecharM('modalConfirmar')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic success" id="mConfIc"><i class='bx bx-refresh'></i></div>
<div class="modal-info-box" id="mConfInfo"></div>
<p style="font-size:10px;color:rgba(255,255,255,.35)" id="mConfDesc"></p>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-cancel" onclick="fecharM('modalConfirmar')"><i class='bx bx-x'></i> Cancelar</button>
<button class="btn-modal btn-modal-ok" id="btnConf"><i class='bx bx-check'></i> Confirmar</button>
</div>
</div></div>
</div>

<!-- MODAL PROCESSANDO -->
<div id="modalProc" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom loading"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
<div class="modal-body-custom"><div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:12px;color:rgba(255,255,255,.5)">Aguarde...</p></div></div>
</div></div>
</div>

<!-- MODAL SUCESSO -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharM('modalSucesso');location.reload()"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
<p style="font-size:13px;font-weight:600" id="sucMsg"></p>
</div>
<div class="modal-footer-custom"><button class="btn-modal btn-modal-ok" onclick="fecharM('modalSucesso');location.reload()"><i class='bx bx-check'></i> OK</button></div>
</div></div>
</div>

<script>
function abrirM(id){document.getElementById(id).classList.add('show')}
function fecharM(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show')})});

function showToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove()},3000)}

function post(data,cb){
    fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data})
    .then(function(r){return r.text()}).then(cb)
    .catch(function(){fecharM('modalProc');showToast('Erro de conexão!','err')});
}

// Filtros
var debounceTimer;
document.getElementById('searchInput').addEventListener('input',function(){clearTimeout(debounceTimer);debounceTimer=setTimeout(aplicarFiltros,400)});
document.getElementById('filterStatus').addEventListener('change',aplicarFiltros);
document.getElementById('filterRev').addEventListener('change',aplicarFiltros);

function aplicarFiltros(){
    var b=document.getElementById('searchInput').value;
    var f=document.getElementById('filterStatus').value;
    var r=document.getElementById('filterRev').value;
    window.location.href='?busca='+encodeURIComponent(b)+'&filtro='+f+'&rev='+r;
}

// Confirmar genérico
function confirmar(title,icon,hClass,info,desc,btnTxt,btnCls,cb){
    document.getElementById('mConfTitle').innerHTML='<i class="bx '+icon+'"></i> '+title;
    var h=document.getElementById('mConfH');h.className='modal-header-custom '+(hClass==='red'?'danger':'success');
    document.getElementById('mConfIc').innerHTML='<i class="bx '+icon+'"></i>';
    document.getElementById('mConfIc').className='modal-ic '+(hClass==='red'?'error':'success');
    document.getElementById('mConfInfo').innerHTML=info;
    document.getElementById('mConfDesc').textContent=desc;
    var b=document.getElementById('btnConf');
    b.innerHTML='<i class="bx '+icon+'"></i> '+btnTxt;
    b.className='btn-modal '+(btnCls||'btn-modal-ok');
    b.onclick=function(){fecharM('modalConfirmar');cb()};
    abrirM('modalConfirmar');
}

function reativar(login){
    confirmar('Reativar Cliente','bx-refresh','green',
        '<div class="modal-info-row"><i class="bx bx-user" style="color:#34d399"></i> <span>Cliente:</span> <strong>'+login+'</strong></div><div class="modal-info-row"><i class="bx bx-refresh" style="color:#34d399"></i> <span>Ação:</span> <strong>Remover bloqueio</strong></div>',
        'O cliente poderá conectar normalmente.','Reativar','btn-modal-ok',
        function(){abrirM('modalProc');post('reativar_ajax=1&login='+encodeURIComponent(login),function(d){fecharM('modalProc');if(d.trim()==='ok'){document.getElementById('sucMsg').textContent='Cliente "'+login+'" reativado!';abrirM('modalSucesso')}else showToast('Erro!','err')})});
}

function reativarTodos(){
    confirmar('Reativar Todos','bx-check-double','green',
        '<div class="modal-info-row"><i class="bx bx-group" style="color:#34d399"></i> <span>Ação:</span> <strong>Reativar todos os <?php echo $stat_suspensos;?> bloqueados</strong></div>',
        'Todos os clientes bloqueados serão liberados.','Reativar Todos','btn-modal-ok',
        function(){abrirM('modalProc');post('reativar_todos_ajax=1',function(d){fecharM('modalProc');var n=parseInt(d)||0;document.getElementById('sucMsg').textContent=n+' cliente(s) reativado(s)!';abrirM('modalSucesso')})});
}

function excluir(login){
    confirmar('Excluir Cliente','bx-trash','red',
        '<div class="modal-info-row"><i class="bx bx-user" style="color:#f87171"></i> <span>Cliente:</span> <strong>'+login+'</strong></div><div class="modal-info-row"><i class="bx bx-trash" style="color:#f87171"></i> <span>Ação:</span> <strong style="color:#f87171">Remoção permanente</strong></div>',
        '⚠️ O cliente será removido permanentemente!','Excluir','btn-modal-danger',
        function(){abrirM('modalProc');post('excluir_ajax=1&login='+encodeURIComponent(login),function(d){fecharM('modalProc');if(d.trim()==='ok'){document.getElementById('sucMsg').textContent='Cliente "'+login+'" excluído!';abrirM('modalSucesso')}else showToast('Erro!','err')})});
}

function limparHistorico(){
    confirmar('Limpar Histórico','bx-trash','red',
        '<div class="modal-info-row"><i class="bx bx-trash" style="color:#fbbf24"></i> <span>Ação:</span> <strong>Remover <?php echo $stat_reat;?> registros reativados</strong></div>',
        'Apenas registros já reativados serão removidos.','Limpar','btn-modal-danger',
        function(){abrirM('modalProc');post('limpar_historico_ajax=1',function(d){fecharM('modalProc');if(d.trim()==='ok'){document.getElementById('sucMsg').textContent='Histórico limpo!';abrirM('modalSucesso')}else showToast('Erro!','err')})});
}

function copiar(login,senha,motivo,conexoes,limite,rev){
    var t='🔴 BLOQUEADO POR LIMITE\n━━━━━━━━━━━━━━━━━━━━━\n';
    t+='👤 Login: '+login+'\n🔑 Senha: '+senha+'\n⚠️ Motivo: '+motivo+'\n🔗 Conexões: '+conexoes+'/'+limite+'\n🏪 Revendedor: '+rev+'\n━━━━━━━━━━━━━━━━━━━━━\n📆 '+new Date().toLocaleString('pt-BR');
    navigator.clipboard.writeText(t).then(function(){showToast('Informações copiadas!')});
}
</script>
</body>
</html>

