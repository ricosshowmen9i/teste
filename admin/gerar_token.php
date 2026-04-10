<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_gerar_token($input)
    {
        ?>
<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) { die("Falha na conexão: " . mysqli_connect_error()); }

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!file_exists('suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
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

if ($_SESSION['login'] !== 'admin') {
    echo 'Você não tem permissão para acessar essa página';
    exit;
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

function gerarToken($tamanho = 32) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < $tamanho; $i++) { $token .= $caracteres[rand(0, strlen($caracteres) - 1)]; }
    return $token;
}

// Resultado do form
$formResult = null;

if (isset($_POST['action'])) {
    $servidor_id = anti_sql($_POST['servidor_id'] ?? '');

    if ($_POST['action'] === 'gerar_token') {
        $novo_token = gerarToken(32);
        $token_md5 = md5($novo_token);
        mysqli_query($conn, "UPDATE servidor_tokens SET status = 'inativo' WHERE servidor_id = '$servidor_id'");
        $sql = "INSERT INTO servidor_tokens (servidor_id, token) VALUES ('$servidor_id', '$token_md5')";
        if (mysqli_query($conn, $sql)) {
            $formResult = ['tipo' => 'token_gerado', 'msg' => 'Token gerado com sucesso!', 'token' => $novo_token];
        } else {
            $formResult = ['tipo' => 'erro', 'msg' => 'Erro ao gerar token!'];
        }
    }

    if ($_POST['action'] === 'adicionar_manual') {
        $token_manual = anti_sql($_POST['token_manual']);
        if (strlen($token_manual) < 5) {
            $formResult = ['tipo' => 'erro', 'msg' => 'Token muito curto! Mínimo 5 caracteres.'];
        } else {
            $token_md5 = md5($token_manual);
            mysqli_query($conn, "UPDATE servidor_tokens SET status = 'inativo' WHERE servidor_id = '$servidor_id'");
            $sql = "INSERT INTO servidor_tokens (servidor_id, token) VALUES ('$servidor_id', '$token_md5')";
            if (mysqli_query($conn, $sql)) {
                $formResult = ['tipo' => 'sucesso', 'msg' => 'Token manual salvo com sucesso!'];
            } else {
                $formResult = ['tipo' => 'erro', 'msg' => 'Erro ao salvar token!'];
            }
        }
    }

    if ($_POST['action'] === 'editar_token') {
        $token_id = anti_sql($_POST['token_id']);
        $novo_token_manual = anti_sql($_POST['novo_token_manual']);
        if (strlen($novo_token_manual) < 5) {
            $formResult = ['tipo' => 'erro', 'msg' => 'Token muito curto!'];
        } else {
            $token_md5 = md5($novo_token_manual);
            if (mysqli_query($conn, "UPDATE servidor_tokens SET token = '$token_md5' WHERE id = '$token_id'")) {
                $formResult = ['tipo' => 'sucesso', 'msg' => 'Token atualizado com sucesso!'];
            } else {
                $formResult = ['tipo' => 'erro', 'msg' => 'Erro ao editar token!'];
            }
        }
    }

    if ($_POST['action'] === 'deletar_token') {
        $token_id = anti_sql($_POST['token_id']);
        if (mysqli_query($conn, "DELETE FROM servidor_tokens WHERE id = '$token_id'")) {
            $formResult = ['tipo' => 'sucesso', 'msg' => 'Token deletado com sucesso!'];
        } else {
            $formResult = ['tipo' => 'erro', 'msg' => 'Erro ao deletar token!'];
        }
    }

    if ($_POST['action'] === 'toggle_status') {
        $token_id = anti_sql($_POST['token_id']);
        $status_atual = anti_sql($_POST['status_atual']);
        $novo_status = $status_atual === 'ativo' ? 'inativo' : 'ativo';
        if ($novo_status === 'ativo') {
            $r = mysqli_query($conn, "SELECT servidor_id FROM servidor_tokens WHERE id = '$token_id'");
            $row = mysqli_fetch_assoc($r);
            mysqli_query($conn, "UPDATE servidor_tokens SET status = 'inativo' WHERE servidor_id = '{$row['servidor_id']}'");
        }
        mysqli_query($conn, "UPDATE servidor_tokens SET status = '$novo_status' WHERE id = '$token_id'");
        $formResult = ['tipo' => 'sucesso', 'msg' => 'Status do token alterado!'];
    }
}

// Stats
$total_servidores = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidores"); if ($r) $total_servidores = $r->fetch_assoc()['t'];
$total_tokens = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidor_tokens"); if ($r) $total_tokens = $r->fetch_assoc()['t'];
$total_tokens_ativos = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidor_tokens WHERE status='ativo'"); if ($r) $total_tokens_ativos = $r->fetch_assoc()['t'];
$total_tokens_inativos = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidor_tokens WHERE status='inativo'"); if ($r) $total_tokens_inativos = $r->fetch_assoc()['t'];

$sql_servidores = "SELECT s.* FROM servidores s ORDER BY s.id DESC";
$servidores = mysqli_query($conn, $sql_servidores);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Tokens dos Servidores - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if (function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); else echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;}'; ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:0px!important;padding:0!important;}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important;}

/* ========== STATS CARD ========== */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s ease;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Voltar */
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.5);font-size:11px;font-weight:600;text-decoration:none;transition:all .25s;margin-bottom:16px;cursor:pointer;}
.btn-back:hover{background:rgba(255,255,255,0.08);border-color:var(--primaria);color:#fff;transform:translateX(-3px);text-decoration:none;}
.btn-back i{font-size:16px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.card-header-custom.ciano{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filtros */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;}
.filter-item{flex:1;min-width:140px;}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.filter-input{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;font-size:12px;color:#ffffff!important;transition:all .2s;font-family:inherit;outline:none;}
.filter-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.filter-input::placeholder{color:rgba(255,255,255,0.3);}

/* Grid */
.servers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px;}

/* Card servidor */
.server-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,0.08);}
.server-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.server-header{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));padding:12px;display:flex;align-items:center;justify-content:space-between;}
.server-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.server-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.server-text{flex:1;min-width:0;}
.server-name{font-size:14px;font-weight:700;color:white;}
.server-ip{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;display:flex;align-items:center;gap:4px;}
.server-body{padding:12px;}

.status-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:16px;font-size:9px;font-weight:600;}
.status-online{background:rgba(16,185,129,0.2);color:#10b981;}
.status-offline{background:rgba(239,68,68,0.2);color:#f87171;}

/* Token box */
.token-box{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px;margin-bottom:8px;}
.token-box-label{font-size:8px;font-weight:700;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-bottom:6px;display:flex;align-items:center;gap:4px;}
.token-box-label i{font-size:12px;color:var(--primaria);}
.token-value-row{display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:7px 10px;margin-bottom:6px;}
.token-value-text{flex:1;font-family:monospace;font-size:10px;color:rgba(255,255,255,0.7);word-break:break-all;letter-spacing:.3px;}
.token-copy-btn{background:rgba(255,255,255,0.08);border:none;color:var(--primaria);cursor:pointer;padding:4px 8px;border-radius:6px;font-size:14px;transition:all .2s;flex-shrink:0;}
.token-copy-btn:hover{background:rgba(255,255,255,0.15);transform:scale(1.1);}
.token-copy-btn.copied{color:#34d399;}

/* Info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.info-content{flex:1;min-width:0;}
.info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.info-value{font-size:10px;font-weight:600;color:var(--texto,#fff);}

/* Actions */
.server-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;}
.action-btn{flex:1;min-width:70px;padding:6px 8px;border:none;border-radius:8px;font-weight:600;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;color:white;transition:all .2s;font-family:inherit;outline:none;-webkit-appearance:none;appearance:none;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.05);}
.action-btn i{font-size:13px;}

.sv-btn-roxo{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.sv-btn-verde{background:linear-gradient(135deg,#10b981,#059669);}
.sv-btn-amarelo{background:linear-gradient(135deg,#f59e0b,#d97706);}
.sv-btn-vermelho{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.sv-btn-cinza{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.5);}
.sv-btn-cinza:hover{border-color:#f87171;color:#f87171;}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state i{font-size:48px;color:rgba(255,255,255,0.15);margin-bottom:10px;}
.empty-state h3{font-size:15px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,0.3);font-size:10px;}

/* ========== MODAIS ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:480px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 60px rgba(0,0,0,0.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:white;}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.modal-header-custom.info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.modal-header-custom.ciano{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,0.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,0.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.info{background:rgba(6,182,212,.15);color:#22d3ee;border:2px solid rgba(6,182,212,.3);}

.modal-info-box{background:rgba(255,255,255,.04);border-radius:10px;padding:10px;margin-bottom:10px;border:1px solid rgba(255,255,255,.05);}
.modal-info-row{display:flex;align-items:center;gap:6px;padding:3px 0;}
.modal-info-row i{font-size:13px;width:16px;text-align:center;}
.modal-info-row span{font-size:11px;color:rgba(255,255,255,.6);}
.modal-info-row strong{font-size:11px;color:#fff;}

/* Form no modal */
.modal-form-group{margin-bottom:12px;}
.modal-form-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:5px;}
.modal-form-label i{font-size:14px;}
.modal-form-input{width:100%;padding:10px 14px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;font-size:13px;color:#ffffff;transition:all .2s;font-family:inherit;outline:none;}
.modal-form-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.modal-form-input::placeholder{color:rgba(255,255,255,0.3);}
.modal-form-hint{font-size:9px;color:rgba(255,255,255,0.3);margin-top:4px;display:flex;align-items:center;gap:4px;}
.modal-form-hint i{font-size:11px;color:#ec4899;}

/* Token copiável no modal */
.modal-token-display{background:rgba(255,255,255,0.06);border:1.5px solid rgba(16,185,129,0.2);border-radius:10px;padding:10px;margin:12px 0;display:flex;align-items:center;gap:8px;}
.modal-token-text{flex:1;font-family:monospace;font-size:11px;color:#34d399;word-break:break-all;letter-spacing:.5px;}
.modal-token-copy{background:rgba(16,185,129,0.15);border:none;color:#34d399;cursor:pointer;padding:6px 10px;border-radius:7px;font-size:13px;transition:all .2s;flex-shrink:0;}
.modal-token-copy:hover{background:rgba(16,185,129,0.25);transform:scale(1.1);}

.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-modal-info{background:linear-gradient(135deg,#06b6d4,#0891b2);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria,#10b981);border-right-color:var(--secundaria,#C850C0);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Histórico items no modal */
.hist-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(255,255,255,.04);border-radius:10px;margin-bottom:6px;border:1px solid rgba(255,255,255,.06);transition:all .2s;}
.hist-item:hover{border-color:var(--primaria);background:rgba(255,255,255,.06);}
.hist-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.hist-icon.ativo{background:rgba(16,185,129,.15);color:#34d399;}
.hist-icon.inativo{background:rgba(239,68,68,.15);color:#f87171;}
.hist-info{flex:1;min-width:0;}
.hist-token{font-family:monospace;font-size:9px;color:rgba(255,255,255,.6);word-break:break-all;}
.hist-status{font-size:8px;font-weight:700;text-transform:uppercase;margin-top:2px;}
.hist-status.ativo{color:#34d399;}
.hist-status.inativo{color:#f87171;}
.hist-actions{display:flex;gap:3px;flex-shrink:0;}
.hist-btn{background:rgba(255,255,255,.06);border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:4px 6px;border-radius:6px;font-size:12px;transition:all .2s;}
.hist-btn:hover{background:rgba(255,255,255,.12);color:#fff;}
.hist-btn.edit:hover{color:#fbbf24;}
.hist-btn.del:hover{color:#f87171;}
.hist-btn.toggle:hover{color:#34d399;}
.hist-empty{text-align:center;padding:20px;color:rgba(255,255,255,.3);font-size:11px;}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,0.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .servers-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .filter-group{flex-direction:column;}
    .server-actions{display:grid;grid-template-columns:repeat(2,1fr);}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:80px;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats Card -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-key'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Tokens dos Servidores</div>
            <div class="stats-card-value"><?php echo $total_tokens_ativos; ?> Ativos</div>
            <div class="stats-card-subtitle">Gerencie os tokens de autenticação dos servidores</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-key'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_servidores; ?></div><div class="mini-stat-lbl">Servidores</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_tokens_ativos; ?></div><div class="mini-stat-lbl">Ativos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_tokens_inativos; ?></div><div class="mini-stat-lbl">Inativos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_tokens; ?></div><div class="mini-stat-lbl">Total</div></div>
    </div>

    <!-- Voltar -->
    <a href="servidores.php" class="btn-back"><i class='bx bx-arrow-back'></i> Voltar para Servidores</a>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom blue">
            <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
            <div><div class="header-title">Filtros e Busca</div><div class="header-subtitle">Encontre servidores rapidamente</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Nome ou IP</div>
                    <input type="text" class="filter-input" id="searchInput" placeholder="🔍 Digite o nome ou IP..." onkeyup="filtrarServidores()">
                </div>
            </div>
        </div>
    </div>

    <!-- Grid -->
    <div class="servers-grid" id="serversGrid">
    <?php if (mysqli_num_rows($servidores) > 0): ?>
        <?php while ($s = mysqli_fetch_assoc($servidores)):
            $sql_token_ativo = "SELECT * FROM servidor_tokens WHERE servidor_id = '{$s['id']}' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
            $result_token_ativo = mysqli_query($conn, $sql_token_ativo);
            $token_ativo = mysqli_fetch_assoc($result_token_ativo);
            $tem_token = !empty($token_ativo);
        ?>
        <div class="server-card" data-nome="<?php echo strtolower($s['nome']); ?>" data-ip="<?php echo strtolower($s['ip']); ?>">
            <div class="server-header">
                <div class="server-info">
                    <div class="server-avatar"><i class='bx bx-server'></i></div>
                    <div class="server-text">
                        <div class="server-name"><?php echo htmlspecialchars($s['nome']); ?></div>
                        <div class="server-ip"><i class='bx bx-network-chart'></i> <?php echo $s['ip']; ?>:<?php echo $s['porta']; ?></div>
                    </div>
                </div>
                <span class="status-badge <?php echo $tem_token?'status-online':'status-offline'; ?>">
                    <i class='bx <?php echo $tem_token?'bx-check-circle':'bx-x-circle'; ?>'></i> <?php echo $tem_token?'Token Ativo':'Sem Token'; ?>
                </span>
            </div>
            <div class="server-body">
                <!-- Token Ativo -->
                <div class="token-box">
                    <div class="token-box-label"><i class='bx bx-key'></i> Token Ativo</div>
                    <?php if ($tem_token): ?>
                    <div class="token-value-row">
                        <span class="token-value-text" id="tokenText-<?php echo $s['id']; ?>"><?php echo $token_ativo['token']; ?></span>
                        <button class="token-copy-btn" onclick="copiarToken('<?php echo $token_ativo['token']; ?>',this)" title="Copiar"><i class='bx bx-copy'></i></button>
                    </div>
                    <?php else: ?>
                    <div class="token-value-row">
                        <span class="token-value-text" style="color:rgba(255,255,255,0.3);">Nenhum token ativo</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-shield-quarter' style="color:<?php echo $tem_token?'#34d399':'#f87171'; ?>;"></i></div>
                        <div class="info-content"><div class="info-label">STATUS</div><div class="info-value" style="color:<?php echo $tem_token?'#34d399':'#f87171'; ?>;"><?php echo $tem_token?'Protegido':'Desprotegido'; ?></div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class='bx bx-server' style="color:#818cf8;"></i></div>
                        <div class="info-content"><div class="info-label">SERVIDOR</div><div class="info-value">#<?php echo $s['id']; ?></div></div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="server-actions">
                    <form method="POST" style="flex:1;display:flex;">
                        <input type="hidden" name="servidor_id" value="<?php echo $s['id']; ?>">
                        <input type="hidden" name="action" value="gerar_token">
                        <button type="submit" class="action-btn sv-btn-roxo" style="width:100%;"><i class='bx bx-sync'></i> Gerar Auto</button>
                    </form>
                    <button class="action-btn sv-btn-verde" onclick="abrirModalManual(<?php echo $s['id']; ?>,'<?php echo htmlspecialchars(addslashes($s['nome'])); ?>')"><i class='bx bx-pencil'></i> Manual</button>
                    <button class="action-btn sv-btn-amarelo" onclick="abrirModalHistorico(<?php echo $s['id']; ?>,'<?php echo htmlspecialchars(addslashes($s['nome'])); ?>')"><i class='bx bx-history'></i> Histórico</button>
                    <?php if ($tem_token): ?>
                    <form method="POST" style="flex:1;display:flex;">
                        <input type="hidden" name="servidor_id" value="<?php echo $s['id']; ?>">
                        <input type="hidden" name="token_id" value="<?php echo $token_ativo['id']; ?>">
                        <input type="hidden" name="status_atual" value="ativo">
                        <input type="hidden" name="action" value="toggle_status">
                        <button type="submit" class="action-btn sv-btn-cinza" style="width:100%;"><i class='bx bx-power-off'></i> Desativar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class='bx bx-server'></i>
            <h3>Nenhum servidor encontrado</h3>
            <p>Adicione um servidor primeiro para gerenciar os tokens.</p>
            <button class="action-btn sv-btn-roxo" onclick="window.location.href='adicionarservidor.php'" style="margin-top:12px;padding:10px 20px;"><i class='bx bx-plus'></i> Adicionar Servidor</button>
        </div>
    <?php endif; ?>
    </div>

    <div class="pagination-info">Total de <?php echo $total_servidores; ?> servidor(es) · <?php echo $total_tokens; ?> token(s) · <?php echo date('d/m/Y H:i:s'); ?></div>

</div>
</div>

<!-- ========== MODAIS ========== -->

<!-- Modal Manual -->
<div id="modalManual" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-pencil'></i> Adicionar Token Manual</h5><button class="modal-close" onclick="fecharModal('modalManual')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-key'></i></div>
        <p style="text-align:center;font-size:13px;margin-bottom:14px;">Servidor: <strong id="manualServerNome" style="color:#34d399;"></strong></p>
        <form method="POST" id="formManual">
            <input type="hidden" name="servidor_id" id="manualServerId">
            <input type="hidden" name="action" value="adicionar_manual">
            <div class="modal-form-group">
                <div class="modal-form-label"><i class='bx bx-key' style="color:#34d399;"></i> Digite o Token</div>
                <input type="text" class="modal-form-input" name="token_manual" placeholder="Ex: SsDkpOAUj228ejqBjqBRRlHm2UfyyFyE" required>
                <div class="modal-form-hint"><i class='bx bx-info-circle'></i> O token será salvo em MD5 automaticamente</div>
            </div>
        </form>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalManual')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-ok" onclick="document.getElementById('formManual').submit()"><i class='bx bx-check'></i> Salvar Token</button>
    </div>
</div></div>
</div>

<!-- Modal Editar Token -->
<div id="modalEditar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-edit'></i> Editar Token</h5><button class="modal-close" onclick="fecharModal('modalEditar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-edit'></i></div>
        <form method="POST" id="formEditar">
            <input type="hidden" name="token_id" id="editarTokenId">
            <input type="hidden" name="action" value="editar_token">
            <div class="modal-form-group">
                <div class="modal-form-label"><i class='bx bx-key' style="color:#fbbf24;"></i> Novo Token</div>
                <input type="text" class="modal-form-input" name="novo_token_manual" id="editarTokenInput" placeholder="Digite o novo token..." required>
            </div>
        </form>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalEditar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" onclick="document.getElementById('formEditar').submit()"><i class='bx bx-save'></i> Atualizar</button>
    </div>
</div></div>
</div>

<!-- Modal Histórico -->
<div id="modalHistorico" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom info"><h5><i class='bx bx-history'></i> Histórico de Tokens</h5><button class="modal-close" onclick="fecharModal('modalHistorico')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <p style="text-align:center;font-size:13px;margin-bottom:14px;">Servidor: <strong id="histServerNome" style="color:#22d3ee;"></strong></p>
        <div id="historicoList" style="max-height:300px;overflow-y:auto;">
            <div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:11px;color:rgba(255,255,255,.4);">Carregando...</p></div>
        </div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalHistorico')"><i class='bx bx-x'></i> Fechar</button>
    </div>
</div></div>
</div>

<!-- Modal Deletar Token -->
<div id="modalDeletar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Deletar Token</h5><button class="modal-close" onclick="fecharModal('modalDeletar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;">Tem certeza que deseja deletar este token?</p>
        <p style="text-align:center;font-size:10px;color:rgba(255,255,255,.35);margin-top:4px;">⚠️ Esta ação não pode ser desfeita!</p>
        <form method="POST" id="formDeletar">
            <input type="hidden" name="token_id" id="deletarTokenId">
            <input type="hidden" name="action" value="deletar_token">
        </form>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDeletar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="document.getElementById('formDeletar').submit()"><i class='bx bx-trash'></i> Deletar</button>
    </div>
</div></div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso');location.reload();"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;" id="sucessoMsg"></p>
        <div id="tokenGeradoBox" style="display:none;">
            <div class="modal-token-display">
                <span class="modal-token-text" id="tokenGeradoText"></span>
                <button class="modal-token-copy" onclick="copiarTokenModal()" title="Copiar"><i class='bx bx-copy'></i></button>
            </div>
            <p style="text-align:center;font-size:9px;color:rgba(255,255,255,.3);margin-top:4px;">Clique para copiar · Salve em local seguro</p>
        </div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso');location.reload();"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;" id="erroMsg"></p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i> Fechar</button>
    </div>
</div></div>
</div>

<script>
// ===== Modais =====
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

// ===== Toast =====
function mostrarToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

// ===== Copiar token =====
function copiarToken(token,btn){
    navigator.clipboard.writeText(token).then(function(){
        if(btn){btn.classList.add('copied');btn.innerHTML='<i class="bx bx-check"></i>';setTimeout(function(){btn.classList.remove('copied');btn.innerHTML='<i class="bx bx-copy"></i>';},2000);}
        mostrarToast('Token copiado!','ok');
    });
}
function copiarTokenModal(){
    var t=document.getElementById('tokenGeradoText').textContent;
    navigator.clipboard.writeText(t).then(function(){mostrarToast('Token copiado!','ok');});
}

// ===== Filtrar =====
function filtrarServidores(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.server-card').forEach(function(card){
        var nome=card.getAttribute('data-nome')||'';
        var ip=card.getAttribute('data-ip')||'';
        card.style.display=(nome.includes(busca)||ip.includes(busca))?'':'none';
    });
}

// ===== Modal Manual =====
function abrirModalManual(id,nome){
    document.getElementById('manualServerId').value=id;
    document.getElementById('manualServerNome').textContent=nome;
    abrirModal('modalManual');
}

// ===== Modal Editar =====
function abrirModalEditar(tokenId,tokenAtual){
    document.getElementById('editarTokenId').value=tokenId;
    document.getElementById('editarTokenInput').value=tokenAtual;
    abrirModal('modalEditar');
}

// ===== Modal Deletar =====
function abrirModalDeletar(tokenId){
    document.getElementById('deletarTokenId').value=tokenId;
    abrirModal('modalDeletar');
}

// ===== Modal Histórico =====
function abrirModalHistorico(servidorId,nome){
    document.getElementById('histServerNome').textContent=nome;
    document.getElementById('historicoList').innerHTML='<div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:11px;color:rgba(255,255,255,.4);">Carregando...</p></div>';
    abrirModal('modalHistorico');
    fetch('get_historico_tokens.php?servidor_id='+servidorId)
        .then(function(r){return r.text();})
        .then(function(data){
            if(data.trim()===''){
                document.getElementById('historicoList').innerHTML='<div class="hist-empty"><i class="bx bx-info-circle" style="font-size:20px;"></i><p style="margin-top:4px;">Nenhum token encontrado</p></div>';
            } else {
                document.getElementById('historicoList').innerHTML=data;
            }
        })
        .catch(function(){
            document.getElementById('historicoList').innerHTML='<div class="hist-empty" style="color:#f87171;"><i class="bx bx-error-circle" style="font-size:20px;"></i><p style="margin-top:4px;">Erro ao carregar</p></div>';
        });
}

// ===== Resultado do PHP =====
<?php if ($formResult): ?>
document.addEventListener('DOMContentLoaded',function(){
    <?php if ($formResult['tipo'] === 'token_gerado'): ?>
    document.getElementById('sucessoMsg').textContent='<?php echo addslashes($formResult['msg']); ?>';
    document.getElementById('tokenGeradoBox').style.display='block';
    document.getElementById('tokenGeradoText').textContent='<?php echo addslashes($formResult['token']); ?>';
    abrirModal('modalSucesso');
    <?php elseif ($formResult['tipo'] === 'sucesso'): ?>
    document.getElementById('sucessoMsg').textContent='<?php echo addslashes($formResult['msg']); ?>';
    document.getElementById('tokenGeradoBox').style.display='none';
    abrirModal('modalSucesso');
    <?php elseif ($formResult['tipo'] === 'erro'): ?>
    document.getElementById('erroMsg').textContent='<?php echo addslashes($formResult['msg']); ?>';
    abrirModal('modalErro');
    <?php endif; ?>
});
<?php endif; ?>
</script>
</body>
</html>
<?php
    }
    aleatorio_gerar_token($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

