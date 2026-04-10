<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    header('location:../index.php');
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$id = $_SESSION['iduser'];
include_once 'headeradmin2.php';

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

// Diretórios
foreach (['../loja/apps','../loja/icones','../loja/imagens','../loja/anuncios','../loja/videos','../loja/thumbnails'] as $dir) {
    if (!file_exists($dir)) mkdir($dir, 0755, true);
}

// Tabelas
$r = $conn->query("SHOW TABLES LIKE 'loja_apps'");
if ($r->num_rows == 0) {
    $conn->query("CREATE TABLE loja_apps (
        id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, descricao TEXT,
        categoria VARCHAR(100) NOT NULL, arquivo_apk VARCHAR(255) NOT NULL, icone VARCHAR(255) NOT NULL,
        imagem VARCHAR(255), data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP, downloads INT DEFAULT 0,
        versao VARCHAR(50), desenvolvedor VARCHAR(100), status ENUM('ativo','inativo') DEFAULT 'ativo'
    )");
}

$r = $conn->query("SHOW TABLES LIKE 'loja_anuncios'");
if ($r->num_rows == 0) {
    $conn->query("CREATE TABLE loja_anuncios (
        id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(255) NOT NULL, descricao TEXT,
        imagem VARCHAR(255) NOT NULL, url VARCHAR(255), data_inicio DATE, data_fim DATE,
        status ENUM('ativo','inativo') DEFAULT 'ativo'
    )");
} else {
    $c = $conn->query("SHOW COLUMNS FROM loja_anuncios LIKE 'link'");
    if ($c->num_rows > 0) $conn->query("ALTER TABLE loja_anuncios CHANGE link url VARCHAR(255)");
    $c = $conn->query("SHOW COLUMNS FROM loja_anuncios LIKE 'url'");
    if ($c->num_rows == 0) $conn->query("ALTER TABLE loja_anuncios ADD url VARCHAR(255) AFTER imagem");
}

$r = $conn->query("SHOW TABLES LIKE 'loja_videos_suporte'");
if ($r->num_rows == 0) {
    $conn->query("CREATE TABLE loja_videos_suporte (
        id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(255) NOT NULL, descricao TEXT,
        tipo ENUM('youtube','arquivo') NOT NULL, url_youtube VARCHAR(255), arquivo_video VARCHAR(255),
        thumbnail VARCHAR(255), data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('ativo','inativo') DEFAULT 'ativo'
    )");
}

if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) security();
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

function sanitize_file_name($f) {
    $f = preg_replace('/[^\p{L}\p{N}_\s.-]/u', '', $f);
    $f = str_replace(' ', '_', $f);
    $i = pathinfo($f);
    return $i['filename'] . '_' . time() . (isset($i['extension']) ? '.' . $i['extension'] : '');
}

$msg = ''; $msg_type = ''; $show_modal = false;

// === UPLOAD APP ===
if (isset($_POST['enviar_app'])) {
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $desc = mysqli_real_escape_string($conn, $_POST['descricao']);
    $cat = mysqli_real_escape_string($conn, $_POST['categoria']);
    $ver = mysqli_real_escape_string($conn, $_POST['versao']);
    $dev = mysqli_real_escape_string($conn, $_POST['desenvolvedor']);

    if (!empty($_FILES['arquivo_apk']['name']) && !empty($_FILES['icone']['name'])) {
        $apk = sanitize_file_name($_FILES['arquivo_apk']['name']);
        $ico = sanitize_file_name($_FILES['icone']['name']);
        $img = '';
        if (!empty($_FILES['imagem']['name'])) $img = sanitize_file_name($_FILES['imagem']['name']);

        if (move_uploaded_file($_FILES['arquivo_apk']['tmp_name'], '../loja/apps/' . $apk) && move_uploaded_file($_FILES['icone']['tmp_name'], '../loja/icones/' . $ico)) {
            if ($img) move_uploaded_file($_FILES['imagem']['tmp_name'], '../loja/imagens/' . $img);
            $imgVal = $img ? "'$img'" : "NULL";
            if ($conn->query("INSERT INTO loja_apps (nome,descricao,categoria,arquivo_apk,icone,imagem,versao,desenvolvedor) VALUES ('$nome','$desc','$cat','$apk','$ico',$imgVal,'$ver','$dev')")) {
                $msg = "Aplicativo \"$nome\" enviado com sucesso!"; $msg_type = 'success';
            } else { $msg = "Erro ao salvar no banco!"; $msg_type = 'error'; }
        } else { $msg = "Erro no upload dos arquivos!"; $msg_type = 'error'; }
    } else { $msg = "Selecione o APK e o ícone!"; $msg_type = 'error'; }
    $show_modal = true;
}

// === UPLOAD ANÚNCIO ===
if (isset($_POST['enviar_anuncio'])) {
    $titulo = mysqli_real_escape_string($conn, $_POST['titulo']);
    $desc = mysqli_real_escape_string($conn, $_POST['descricao_anuncio']);
    $url = mysqli_real_escape_string($conn, $_POST['url']);
    $di = mysqli_real_escape_string($conn, $_POST['data_inicio']);
    $df = mysqli_real_escape_string($conn, $_POST['data_fim']);

    if (!empty($_FILES['imagem_anuncio']['name'])) {
        $img = sanitize_file_name($_FILES['imagem_anuncio']['name']);
        if (move_uploaded_file($_FILES['imagem_anuncio']['tmp_name'], '../loja/anuncios/' . $img)) {
            if ($conn->query("INSERT INTO loja_anuncios (titulo,descricao,imagem,url,data_inicio,data_fim) VALUES ('$titulo','$desc','$img','$url','$di','$df')")) {
                $msg = "Anúncio publicado com sucesso!"; $msg_type = 'success';
            } else { $msg = "Erro ao salvar!"; $msg_type = 'error'; }
        } else { $msg = "Erro no upload!"; $msg_type = 'error'; }
    } else { $msg = "Selecione uma imagem!"; $msg_type = 'error'; }
    $show_modal = true;
}

// === UPLOAD VÍDEO ===
if (isset($_POST['enviar_video'])) {
    $titulo = mysqli_real_escape_string($conn, $_POST['titulo_video']);
    $desc = mysqli_real_escape_string($conn, $_POST['descricao_video']);
    $tipo = $_POST['tipo_video'];

    if ($tipo == 'youtube') {
        $yt = mysqli_real_escape_string($conn, $_POST['url_youtube']);
        $thumb = '';
        if (!empty($_FILES['thumbnail']['name'])) {
            $thumb = sanitize_file_name($_FILES['thumbnail']['name']);
            move_uploaded_file($_FILES['thumbnail']['tmp_name'], '../loja/thumbnails/' . $thumb);
        } else {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $yt, $m);
            $vid = $m[1] ?? '';
            $thumb = $vid . '.jpg';
            @file_put_contents('../loja/thumbnails/' . $thumb, file_get_contents("https://img.youtube.com/vi/$vid/maxresdefault.jpg"));
        }
        if ($conn->query("INSERT INTO loja_videos_suporte (titulo,descricao,tipo,url_youtube,thumbnail) VALUES ('$titulo','$desc','youtube','$yt','$thumb')")) {
            $msg = "Vídeo do YouTube adicionado!"; $msg_type = 'success';
        } else { $msg = "Erro ao salvar!"; $msg_type = 'error'; }
    } else {
        if (!empty($_FILES['arquivo_video']['name']) && !empty($_FILES['thumbnail']['name'])) {
            $vf = sanitize_file_name($_FILES['arquivo_video']['name']);
            $th = sanitize_file_name($_FILES['thumbnail']['name']);
            if (move_uploaded_file($_FILES['arquivo_video']['tmp_name'], '../loja/videos/' . $vf) && move_uploaded_file($_FILES['thumbnail']['tmp_name'], '../loja/thumbnails/' . $th)) {
                if ($conn->query("INSERT INTO loja_videos_suporte (titulo,descricao,tipo,arquivo_video,thumbnail) VALUES ('$titulo','$desc','arquivo','$vf','$th')")) {
                    $msg = "Vídeo enviado!"; $msg_type = 'success';
                } else { $msg = "Erro ao salvar!"; $msg_type = 'error'; }
            } else { $msg = "Erro no upload!"; $msg_type = 'error'; }
        } else { $msg = "Selecione vídeo e thumbnail!"; $msg_type = 'error'; }
    }
    $show_modal = true;
}

// === EXCLUIR APP ===
if (isset($_GET['excluir_app'])) {
    $eid = intval($_GET['excluir_app']);
    $r = $conn->query("SELECT arquivo_apk,icone,imagem FROM loja_apps WHERE id=$eid");
    if ($r && $r->num_rows > 0) {
        $rw = $r->fetch_assoc();
        @unlink("../loja/apps/" . $rw['arquivo_apk']);
        @unlink("../loja/icones/" . $rw['icone']);
        if ($rw['imagem']) @unlink("../loja/imagens/" . $rw['imagem']);
        $conn->query("DELETE FROM loja_apps WHERE id=$eid");
        $msg = "Aplicativo excluído!"; $msg_type = 'success';
    } else { $msg = "App não encontrado!"; $msg_type = 'error'; }
    $show_modal = true;
}

// === EXCLUIR ANÚNCIO ===
if (isset($_GET['excluir_anuncio'])) {
    $eid = intval($_GET['excluir_anuncio']);
    $r = $conn->query("SELECT imagem FROM loja_anuncios WHERE id=$eid");
    if ($r && $r->num_rows > 0) {
        $rw = $r->fetch_assoc();
        @unlink("../loja/anuncios/" . $rw['imagem']);
        $conn->query("DELETE FROM loja_anuncios WHERE id=$eid");
        $msg = "Anúncio excluído!"; $msg_type = 'success';
    } else { $msg = "Anúncio não encontrado!"; $msg_type = 'error'; }
    $show_modal = true;
}

// === EXCLUIR VÍDEO ===
if (isset($_GET['excluir_video'])) {
    $eid = intval($_GET['excluir_video']);
    $r = $conn->query("SELECT tipo,arquivo_video,thumbnail FROM loja_videos_suporte WHERE id=$eid");
    if ($r && $r->num_rows > 0) {
        $rw = $r->fetch_assoc();
        if ($rw['tipo'] == 'arquivo' && $rw['arquivo_video']) @unlink("../loja/videos/" . $rw['arquivo_video']);
        if ($rw['thumbnail']) @unlink("../loja/thumbnails/" . $rw['thumbnail']);
        $conn->query("DELETE FROM loja_videos_suporte WHERE id=$eid");
        $msg = "Vídeo excluído!"; $msg_type = 'success';
    } else { $msg = "Vídeo não encontrado!"; $msg_type = 'error'; }
    $show_modal = true;
}

// Stats
$total_apps = $conn->query("SELECT COUNT(*) as t FROM loja_apps")->fetch_assoc()['t'] ?? 0;
$total_anuncios = $conn->query("SELECT COUNT(*) as t FROM loja_anuncios")->fetch_assoc()['t'] ?? 0;
$total_videos = $conn->query("SELECT COUNT(*) as t FROM loja_videos_suporte")->fetch_assoc()['t'] ?? 0;
$total_downloads = $conn->query("SELECT COALESCE(SUM(downloads),0) as t FROM loja_apps")->fetch_assoc()['t'] ?? 0;
$anuncios_ativos = $conn->query("SELECT COUNT(*) as t FROM loja_anuncios WHERE status='ativo' AND data_fim >= CURDATE()")->fetch_assoc()['t'] ?? 0;

$tab_ativa = isset($_GET['tab']) ? $_GET['tab'] : 'apps';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciador da Loja</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if (function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); else echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;}'; ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}

/* Stats */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(59,130,246,0.15);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:#3b82f6;}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,#3b82f6,#2563eb);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#60a5fa,#93c5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;cursor:pointer;}
.mini-stat:hover{border-color:#3b82f6;transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Tabs */
.tabs-bar{display:flex;gap:5px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:4px;margin-bottom:16px;flex-wrap:wrap;overflow-x:auto;}
.tab-btn{padding:7px 14px;border:none;background:transparent;color:rgba(255,255,255,0.5);font-weight:600;font-size:11px;border-radius:9px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:5px;font-family:inherit;white-space:nowrap;}
.tab-btn i{font-size:14px;}
.tab-btn.active-apps{background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;box-shadow:0 4px 12px rgba(59,130,246,0.3);}
.tab-btn.active-anuncios{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;box-shadow:0 4px 12px rgba(245,158,11,0.3);}
.tab-btn.active-videos{background:linear-gradient(135deg,#ef4444,#dc2626);color:white;box-shadow:0 4px 12px rgba(239,68,68,0.3);}
.tab-btn.active-add-app{background:linear-gradient(135deg,#10b981,#059669);color:white;box-shadow:0 4px 12px rgba(16,185,129,0.3);}
.tab-btn.active-add-anuncio{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;box-shadow:0 4px 12px rgba(139,92,246,0.3);}
.tab-btn.active-add-video{background:linear-gradient(135deg,#ec4899,#db2777);color:white;box-shadow:0 4px 12px rgba(236,72,153,0.3);}
.tab-btn:hover:not([class*="active-"]){background:rgba(255,255,255,0.05);color:white;}
.tab-count{background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:20px;font-size:8px;font-weight:700;}

.tab-content{display:none;animation:fadeTab .3s ease;}.tab-content.active{display:block;}
@keyframes fadeTab{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:14px;transition:all .2s;}
.modern-card:hover{border-color:rgba(59,130,246,0.3);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.card-header-custom.amber{background:linear-gradient(135deg,#f59e0b,#d97706);}
.card-header-custom.red{background:linear-gradient(135deg,#ef4444,#dc2626);}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.card-header-custom.pink{background:linear-gradient(135deg,#ec4899,#db2777);}
.card-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-info{flex:1;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-full{grid-column:1/-1;}
.form-label{display:flex;align-items:center;gap:4px;font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.form-label i{font-size:12px;}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:#3b82f6;background:rgba(255,255,255,0.09);}
.form-control::placeholder{color:rgba(255,255,255,0.2);}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.form-control option{background:#1e293b;color:#fff;}
textarea.form-control{resize:vertical;min-height:70px;}
input[type="file"].form-control{padding:6px 10px;}

.btn-submit{width:100%;padding:10px;border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;color:white;transition:all .2s;font-family:inherit;margin-top:4px;}
.btn-submit:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-submit.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.btn-submit.amber{background:linear-gradient(135deg,#f59e0b,#d97706);}
.btn-submit.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.btn-submit.pink{background:linear-gradient(135deg,#ec4899,#db2777);}

/* Items Grid */
.items-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* App Card */
.app-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(59,130,246,0.12);transition:all .25s;position:relative;}
.app-card:hover{transform:translateY(-3px);border-color:#3b82f6;box-shadow:0 8px 25px rgba(59,130,246,0.12);}
.app-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#3b82f6,#60a5fa,#93c5fd,#60a5fa,#3b82f6);background-size:200% 100%;animation:shimmerBlue 3s linear infinite;}
@keyframes shimmerBlue{0%{background-position:200% 0}100%{background-position:-200% 0}}

.app-header{background:linear-gradient(135deg,#3b82f6,#2563eb);padding:12px;display:flex;align-items:center;gap:12px;}
.app-icon-wrap{width:48px;height:48px;border-radius:12px;overflow:hidden;border:2px solid rgba(255,255,255,0.2);flex-shrink:0;background:#1e293b;display:flex;align-items:center;justify-content:center;}
.app-icon-wrap img{width:100%;height:100%;object-fit:cover;}
.app-icon-placeholder{font-size:22px;color:white;}
.app-text{flex:1;min-width:0;}
.app-name{font-size:14px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.app-dev{font-size:9px;color:rgba(255,255,255,0.7);margin-top:1px;}

.app-body{padding:12px;}
.app-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.app-info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.app-info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.app-info-content{flex:1;min-width:0;}
.app-info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.app-info-value{font-size:10px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.app-desc{background:rgba(255,255,255,0.03);border-radius:8px;padding:8px 10px;font-size:10px;color:rgba(255,255,255,0.5);line-height:1.5;margin-bottom:8px;border-left:2px solid rgba(59,130,246,0.2);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}

/* Ad Card */
.ad-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(245,158,11,0.12);transition:all .25s;}
.ad-card:hover{transform:translateY(-3px);border-color:#f59e0b;box-shadow:0 8px 25px rgba(245,158,11,0.12);}
.ad-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#f59e0b,#fbbf24,#fde68a,#fbbf24,#f59e0b);background-size:200% 100%;animation:shimmerAmber 3s linear infinite;position:relative;display:block;}
@keyframes shimmerAmber{0%{background-position:200% 0}100%{background-position:-200% 0}}
.ad-image-wrap{width:100%;height:140px;overflow:hidden;position:relative;}
.ad-image-wrap img{width:100%;height:100%;object-fit:cover;}
.ad-image-placeholder{width:100%;height:100%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;font-size:36px;color:white;}
.ad-status-badge{position:absolute;top:8px;right:8px;padding:3px 8px;border-radius:16px;font-size:8px;font-weight:700;}
.ad-status-badge.ativo{background:rgba(16,185,129,0.9);color:white;}
.ad-status-badge.expirado{background:rgba(239,68,68,0.9);color:white;}
.ad-body{padding:12px;}
.ad-title{font-size:14px;font-weight:700;margin-bottom:8px;}

/* Video Card */
.video-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(239,68,68,0.12);transition:all .25s;}
.video-card:hover{transform:translateY(-3px);border-color:#ef4444;box-shadow:0 8px 25px rgba(239,68,68,0.12);}
.video-thumb-wrap{position:relative;width:100%;height:160px;overflow:hidden;cursor:pointer;}
.video-thumb-wrap img{width:100%;height:100%;object-fit:cover;}
.video-thumb-placeholder{width:100%;height:100%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;font-size:48px;color:white;}
.video-play-btn{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:48px;height:48px;background:rgba(0,0,0,0.6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;transition:all .3s;}
.video-thumb-wrap:hover .video-play-btn{background:#FF0000;transform:translate(-50%,-50%) scale(1.1);}
.video-type-badge{position:absolute;top:8px;left:8px;padding:3px 8px;border-radius:16px;font-size:8px;font-weight:700;background:rgba(0,0,0,0.6);color:white;display:flex;align-items:center;gap:3px;}
.video-body{padding:12px;}
.video-title{font-size:13px;font-weight:700;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.video-desc{font-size:10px;color:rgba(255,255,255,0.4);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:8px;}

/* Actions */
.item-actions{display:flex;gap:5px;flex-wrap:wrap;}
.action-btn{flex:1;min-width:55px;padding:6px 6px;border:none;border-radius:8px;font-weight:600;font-size:9px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:3px;color:white;transition:all .2s;font-family:inherit;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-download{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.btn-link{background:linear-gradient(135deg,#06b6d4,#0891b2);}

/* Video type selection */
.video-type-sel{display:flex;gap:12px;margin-bottom:16px;}
.vt-option{display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 16px;border-radius:10px;border:1.5px solid rgba(255,255,255,0.08);transition:all .2s;font-size:12px;font-weight:600;color:rgba(255,255,255,0.5);}
.vt-option:hover{border-color:rgba(255,255,255,0.15);color:white;}
.vt-option.active{border-color:#ec4899;background:rgba(236,72,153,0.1);color:#f472b6;}
.vt-option input{display:none;}
.video-form{display:none;}.video-form.active{display:block;}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:50px 20px;background:rgba(255,255,255,0.03);border-radius:16px;border:1px solid rgba(255,255,255,0.06);}
.empty-state-icon{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:32px;border:2px solid;}
.empty-state-icon.blue{background:rgba(59,130,246,0.1);color:#60a5fa;border-color:rgba(59,130,246,0.2);}
.empty-state-icon.amber{background:rgba(245,158,11,0.1);color:#fbbf24;border-color:rgba(245,158,11,0.2);}
.empty-state-icon.red{background:rgba(239,68,68,0.1);color:#f87171;border-color:rgba(239,68,68,0.2);}
.empty-state h3{font-size:15px;margin-bottom:5px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

/* Search */
.search-bar{margin-bottom:14px;}
.search-bar .form-control{max-width:100%;}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:500px;width:92%;}
.modal-container.wide{max-width:800px;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.video{background:linear-gradient(135deg,#FF0000,#cc0000);}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-info-card{background:rgba(255,255,255,.04);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid rgba(255,255,255,.06);}
.modal-info-row{display:flex;align-items:center;gap:8px;padding:4px 0;}
.modal-info-row i{font-size:14px;width:18px;text-align:center;}
.modal-info-row span{font-size:12px;color:rgba(255,255,255,.7);}
.modal-info-row strong{font-size:12px;color:#fff;}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

.video-container{position:relative;width:100%;padding-bottom:56.25%;background:#000;border-radius:12px;overflow:hidden;}
.video-container iframe,.video-container video{position:absolute;top:0;left:0;width:100%;height:100%;border:none;}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

.pagination-info{text-align:center;margin-top:16px;color:rgba(255,255,255,0.3);font-size:10px;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .items-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:70px;}
    .tabs-bar{overflow-x:auto;flex-wrap:nowrap;-webkit-overflow-scrolling:touch;}
    .form-grid{grid-template-columns:1fr;}
    .item-actions{display:grid;grid-template-columns:1fr 1fr;}
    .video-type-sel{flex-direction:column;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-store-alt'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Loja de Aplicativos</div>
            <div class="stats-card-value"><?php echo $total_apps + $total_anuncios + $total_videos; ?> Itens</div>
            <div class="stats-card-subtitle">Gerencie apps, anúncios e vídeos de suporte</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-store-alt'></i></div>
    </div>

    <div class="mini-stats">
        <div class="mini-stat" onclick="openTab('apps')"><div class="mini-stat-val" style="color:#60a5fa;"><?php echo $total_apps; ?></div><div class="mini-stat-lbl">Apps</div></div>
        <div class="mini-stat" onclick="openTab('anuncios')"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_anuncios; ?></div><div class="mini-stat-lbl">Anúncios</div></div>
        <div class="mini-stat" onclick="openTab('videos')"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_videos; ?></div><div class="mini-stat-lbl">Vídeos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_downloads; ?></div><div class="mini-stat-lbl">Downloads</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $anuncios_ativos; ?></div><div class="mini-stat-lbl">Anúncios Ativos</div></div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar">
        <button class="tab-btn <?php echo $tab_ativa=='apps'?'active-apps':''; ?>" onclick="openTab('apps')"><i class='bx bx-mobile-alt'></i> Apps <span class="tab-count"><?php echo $total_apps; ?></span></button>
        <button class="tab-btn <?php echo $tab_ativa=='anuncios'?'active-anuncios':''; ?>" onclick="openTab('anuncios')"><i class='bx bx-bullhorn'></i> Anúncios <span class="tab-count"><?php echo $total_anuncios; ?></span></button>
        <button class="tab-btn <?php echo $tab_ativa=='videos'?'active-videos':''; ?>" onclick="openTab('videos')"><i class='bx bx-video'></i> Vídeos <span class="tab-count"><?php echo $total_videos; ?></span></button>
        <button class="tab-btn <?php echo $tab_ativa=='add_app'?'active-add-app':''; ?>" onclick="openTab('add_app')"><i class='bx bx-plus-circle'></i> + App</button>
        <button class="tab-btn <?php echo $tab_ativa=='add_anuncio'?'active-add-anuncio':''; ?>" onclick="openTab('add_anuncio')"><i class='bx bx-plus-circle'></i> + Anúncio</button>
        <button class="tab-btn <?php echo $tab_ativa=='add_video'?'active-add-video':''; ?>" onclick="openTab('add_video')"><i class='bx bx-plus-circle'></i> + Vídeo</button>
    </div>

    <!-- TAB: APPS -->
    <div id="tab-apps" class="tab-content <?php echo $tab_ativa=='apps'?'active':''; ?>">
        <div class="search-bar"><input type="text" class="form-control" id="searchApps" placeholder="🔍 Buscar aplicativo..." oninput="filtrar('searchApps','.app-card','data-nome')"></div>
        <div class="items-grid">
        <?php
        $r = $conn->query("SELECT * FROM loja_apps ORDER BY id DESC");
        if ($r && $r->num_rows > 0): while ($app = $r->fetch_assoc()):
            $ico_path = '../loja/icones/' . $app['icone'];
            $ico_ok = file_exists($ico_path) && !empty($app['icone']);
        ?>
        <div class="app-card" data-nome="<?php echo strtolower(htmlspecialchars($app['nome'])); ?>">
            <div class="app-header">
                <div class="app-icon-wrap">
                    <?php if ($ico_ok): ?><img src="<?php echo $ico_path; ?>" alt="Ícone"><?php else: ?><i class='bx bx-mobile-alt app-icon-placeholder'></i><?php endif; ?>
                </div>
                <div class="app-text">
                    <div class="app-name"><?php echo htmlspecialchars($app['nome']); ?></div>
                    <div class="app-dev"><?php echo htmlspecialchars($app['desenvolvedor']); ?></div>
                </div>
            </div>
            <div class="app-body">
                <?php if (!empty($app['descricao'])): ?><div class="app-desc"><?php echo htmlspecialchars($app['descricao']); ?></div><?php endif; ?>
                <div class="app-info-grid">
                    <div class="app-info-row"><div class="app-info-icon"><i class='bx bx-category' style="color:#60a5fa;"></i></div><div class="app-info-content"><div class="app-info-label">CATEGORIA</div><div class="app-info-value"><?php echo htmlspecialchars($app['categoria']); ?></div></div></div>
                    <div class="app-info-row"><div class="app-info-icon"><i class='bx bx-code-alt' style="color:#34d399;"></i></div><div class="app-info-content"><div class="app-info-label">VERSÃO</div><div class="app-info-value"><?php echo htmlspecialchars($app['versao']); ?></div></div></div>
                    <div class="app-info-row"><div class="app-info-icon"><i class='bx bx-download' style="color:#fbbf24;"></i></div><div class="app-info-content"><div class="app-info-label">DOWNLOADS</div><div class="app-info-value"><?php echo $app['downloads']; ?></div></div></div>
                    <div class="app-info-row"><div class="app-info-icon"><i class='bx bx-id-card' style="color:#a78bfa;"></i></div><div class="app-info-content"><div class="app-info-label">ID</div><div class="app-info-value">#<?php echo $app['id']; ?></div></div></div>
                </div>
                <div class="item-actions">
                    <a href="../loja/apps/<?php echo $app['arquivo_apk']; ?>" download class="action-btn btn-download"><i class='bx bx-download'></i> APK</a>
                    <button class="action-btn btn-danger" onclick="confirmarExclusao('app',<?php echo $app['id']; ?>,'<?php echo addslashes($app['nome']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state"><div class="empty-state-icon blue"><i class='bx bx-mobile-alt'></i></div><h3>Nenhum aplicativo</h3><p>Adicione apps na aba "+ App"</p></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: ANÚNCIOS -->
    <div id="tab-anuncios" class="tab-content <?php echo $tab_ativa=='anuncios'?'active':''; ?>">
        <div class="items-grid">
        <?php
        $r = $conn->query("SELECT * FROM loja_anuncios ORDER BY id DESC");
        if ($r && $r->num_rows > 0): while ($ad = $r->fetch_assoc()):
            $img_path = '../loja/anuncios/' . $ad['imagem'];
            $img_ok = file_exists($img_path) && !empty($ad['imagem']);
            $ativo = ($ad['status'] == 'ativo' && $ad['data_fim'] >= date('Y-m-d'));
        ?>
        <div class="ad-card" style="position:relative;">
            <div class="ad-image-wrap">
                <?php if ($img_ok): ?><img src="<?php echo $img_path; ?>" alt="Anúncio"><?php else: ?><div class="ad-image-placeholder"><i class='bx bx-image'></i></div><?php endif; ?>
                <span class="ad-status-badge <?php echo $ativo?'ativo':'expirado'; ?>"><?php echo $ativo?'✅ ATIVO':'❌ EXPIRADO'; ?></span>
            </div>
            <div class="ad-body">
                <div class="ad-title"><?php echo htmlspecialchars($ad['titulo']); ?></div>
                <div class="app-info-grid">
                    <div class="app-info-row"><div class="app-info-icon"><i class='bx bx-calendar' style="color:#34d399;"></i></div><div class="app-info-content"><div class="app-info-label">INÍCIO</div><div class="app-info-value"><?php echo date('d/m/Y', strtotime($ad['data_inicio'])); ?></div></div></div>
                    <div class="app-info-row"><div class="app-info-icon"><i class='bx bx-calendar-x' style="color:#f87171;"></i></div><div class="app-info-content"><div class="app-info-label">FIM</div><div class="app-info-value"><?php echo date('d/m/Y', strtotime($ad['data_fim'])); ?></div></div></div>
                </div>
                <?php if (!empty($ad['descricao'])): ?><div class="app-desc"><?php echo htmlspecialchars($ad['descricao']); ?></div><?php endif; ?>
                <div class="item-actions">
                    <?php if (!empty($ad['url'])): ?><a href="<?php echo htmlspecialchars($ad['url']); ?>" target="_blank" class="action-btn btn-link"><i class='bx bx-link-external'></i> Link</a><?php endif; ?>
                    <button class="action-btn btn-danger" onclick="confirmarExclusao('anuncio',<?php echo $ad['id']; ?>,'<?php echo addslashes($ad['titulo']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state"><div class="empty-state-icon amber"><i class='bx bx-bullhorn'></i></div><h3>Nenhum anúncio</h3><p>Adicione na aba "+ Anúncio"</p></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: VÍDEOS -->
    <div id="tab-videos" class="tab-content <?php echo $tab_ativa=='videos'?'active':''; ?>">
        <div class="items-grid">
        <?php
        $r = $conn->query("SELECT * FROM loja_videos_suporte ORDER BY id DESC");
        if ($r && $r->num_rows > 0): while ($v = $r->fetch_assoc()):
            $th_path = '../loja/thumbnails/' . $v['thumbnail'];
            $th_ok = file_exists($th_path) && !empty($v['thumbnail']);
        ?>
        <div class="video-card">
            <div class="video-thumb-wrap" onclick="playVideo('<?php echo addslashes($v['tipo']); ?>','<?php echo addslashes($v['url_youtube'] ?? ''); ?>','<?php echo addslashes($v['arquivo_video'] ?? ''); ?>')">
                <?php if ($th_ok): ?><img src="<?php echo $th_path; ?>" alt="Thumb"><?php else: ?><div class="video-thumb-placeholder"><i class='bx bx-video'></i></div><?php endif; ?>
                <div class="video-play-btn"><i class='bx bx-play'></i></div>
                <span class="video-type-badge"><i class='bx bx-<?php echo $v['tipo']=='youtube'?'bxl-youtube':'film'; ?>'></i> <?php echo ucfirst($v['tipo']); ?></span>
            </div>
            <div class="video-body">
                <div class="video-title"><?php echo htmlspecialchars($v['titulo']); ?></div>
                <?php if (!empty($v['descricao'])): ?><div class="video-desc"><?php echo htmlspecialchars($v['descricao']); ?></div><?php endif; ?>
                <div class="item-actions">
                    <button class="action-btn btn-danger" onclick="confirmarExclusao('video',<?php echo $v['id']; ?>,'<?php echo addslashes($v['titulo']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="empty-state"><div class="empty-state-icon red"><i class='bx bx-video-off'></i></div><h3>Nenhum vídeo</h3><p>Adicione na aba "+ Vídeo"</p></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- TAB: ADD APP -->
    <div id="tab-add_app" class="tab-content <?php echo $tab_ativa=='add_app'?'active':''; ?>">
        <div class="modern-card">
            <div class="card-header-custom green"><div class="header-icon"><i class='bx bx-mobile-alt'></i></div><div class="header-info"><div class="header-title">Adicionar Aplicativo</div><div class="header-subtitle">Envie um novo app para a loja</div></div></div>
            <div class="card-body-custom">
                <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-full"><label class="form-label"><i class='bx bx-tag' style="color:#60a5fa;"></i> Nome</label><input type="text" class="form-control" name="nome" placeholder="Nome do aplicativo" required></div>
                    <div class="form-full"><label class="form-label"><i class='bx bx-note' style="color:#a78bfa;"></i> Descrição</label><textarea class="form-control" name="descricao" rows="3" placeholder="Descreva o app..." required></textarea></div>
                    <div><label class="form-label"><i class='bx bx-category' style="color:#fbbf24;"></i> Categoria</label><select class="form-control" name="categoria" required><option value="Internet VPN">Internet VPN</option><option value="Utilitários">Utilitários</option><option value="Jogos">Jogos</option><option value="Redes Sociais">Redes Sociais</option><option value="Ferramentas">Ferramentas</option></select></div>
                    <div><label class="form-label"><i class='bx bx-code-alt' style="color:#34d399;"></i> Versão</label><input type="text" class="form-control" name="versao" placeholder="1.0.0" required></div>
                    <div><label class="form-label"><i class='bx bx-user' style="color:#e879f9;"></i> Desenvolvedor</label><input type="text" class="form-control" name="desenvolvedor" required></div>
                    <div><label class="form-label"><i class='bx bx-file' style="color:#f87171;"></i> Arquivo APK</label><input type="file" class="form-control" name="arquivo_apk" accept=".apk" required></div>
                    <div><label class="form-label"><i class='bx bx-image' style="color:#60a5fa;"></i> Ícone</label><input type="file" class="form-control" name="icone" accept="image/*" required></div>
                    <div><label class="form-label"><i class='bx bx-photo-album' style="color:#fbbf24;"></i> Imagem (opcional)</label><input type="file" class="form-control" name="imagem" accept="image/*"></div>
                    <div class="form-full"><button type="submit" name="enviar_app" class="btn-submit blue"><i class='bx bx-upload'></i> Enviar Aplicativo</button></div>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: ADD ANÚNCIO -->
    <div id="tab-add_anuncio" class="tab-content <?php echo $tab_ativa=='add_anuncio'?'active':''; ?>">
        <div class="modern-card">
            <div class="card-header-custom purple"><div class="header-icon"><i class='bx bx-bullhorn'></i></div><div class="header-info"><div class="header-title">Adicionar Anúncio</div><div class="header-subtitle">Publique um novo anúncio na loja</div></div></div>
            <div class="card-body-custom">
                <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-full"><label class="form-label"><i class='bx bx-tag' style="color:#a78bfa;"></i> Título</label><input type="text" class="form-control" name="titulo" required></div>
                    <div class="form-full"><label class="form-label"><i class='bx bx-note' style="color:#60a5fa;"></i> Descrição</label><textarea class="form-control" name="descricao_anuncio" rows="3" required></textarea></div>
                    <div><label class="form-label"><i class='bx bx-link' style="color:#34d399;"></i> URL (opcional)</label><input type="url" class="form-control" name="url" placeholder="https://..."></div>
                    <div><label class="form-label"><i class='bx bx-image' style="color:#fbbf24;"></i> Imagem</label><input type="file" class="form-control" name="imagem_anuncio" accept="image/*" required></div>
                    <div><label class="form-label"><i class='bx bx-calendar' style="color:#34d399;"></i> Data Início</label><input type="date" class="form-control" name="data_inicio" required></div>
                    <div><label class="form-label"><i class='bx bx-calendar-x' style="color:#f87171;"></i> Data Fim</label><input type="date" class="form-control" name="data_fim" required></div>
                    <div class="form-full"><button type="submit" name="enviar_anuncio" class="btn-submit amber"><i class='bx bx-bullhorn'></i> Publicar Anúncio</button></div>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: ADD VÍDEO -->
    <div id="tab-add_video" class="tab-content <?php echo $tab_ativa=='add_video'?'active':''; ?>">
        <div class="modern-card">
            <div class="card-header-custom pink"><div class="header-icon"><i class='bx bx-film'></i></div><div class="header-info"><div class="header-title">Adicionar Vídeo de Suporte</div><div class="header-subtitle">YouTube ou upload de arquivo</div></div></div>
            <div class="card-body-custom">
                <div class="video-type-sel">
                    <label class="vt-option active" onclick="toggleVideoForm('youtube',this)"><input type="radio" name="vtype" checked> <i class='bx bxl-youtube' style="font-size:16px;color:#FF0000;"></i> YouTube</label>
                    <label class="vt-option" onclick="toggleVideoForm('arquivo',this)"><input type="radio" name="vtype"> <i class='bx bx-upload' style="font-size:16px;"></i> Upload de Arquivo</label>
                </div>

                <div id="form-youtube" class="video-form active">
                    <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="tipo_video" value="youtube">
                    <div class="form-grid">
                        <div><label class="form-label"><i class='bx bx-tag' style="color:#f87171;"></i> Título</label><input type="text" class="form-control" name="titulo_video" required></div>
                        <div><label class="form-label"><i class='bx bxl-youtube' style="color:#FF0000;"></i> URL do YouTube</label><input type="url" class="form-control" name="url_youtube" placeholder="https://youtube.com/watch?v=..." required></div>
                        <div class="form-full"><label class="form-label"><i class='bx bx-note' style="color:#a78bfa;"></i> Descrição</label><textarea class="form-control" name="descricao_video" rows="3" required></textarea></div>
                        <div class="form-full"><label class="form-label"><i class='bx bx-image' style="color:#fbbf24;"></i> Thumbnail (opcional — auto do YouTube)</label><input type="file" class="form-control" name="thumbnail" accept="image/*"></div>
                        <div class="form-full"><button type="submit" name="enviar_video" class="btn-submit purple"><i class='bx bxl-youtube'></i> Adicionar Vídeo</button></div>
                    </div>
                    </form>
                </div>

                <div id="form-arquivo" class="video-form">
                    <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="tipo_video" value="arquivo">
                    <div class="form-grid">
                        <div><label class="form-label"><i class='bx bx-tag' style="color:#f87171;"></i> Título</label><input type="text" class="form-control" name="titulo_video" required></div>
                        <div><label class="form-label"><i class='bx bx-film' style="color:#ec4899;"></i> Arquivo de Vídeo</label><input type="file" class="form-control" name="arquivo_video" accept="video/*" required></div>
                        <div class="form-full"><label class="form-label"><i class='bx bx-note' style="color:#a78bfa;"></i> Descrição</label><textarea class="form-control" name="descricao_video" rows="3" required></textarea></div>
                        <div class="form-full"><label class="form-label"><i class='bx bx-image' style="color:#fbbf24;"></i> Thumbnail</label><input type="file" class="form-control" name="thumbnail" accept="image/*" required></div>
                        <div class="form-full"><button type="submit" name="enviar_video" class="btn-submit pink"><i class='bx bx-upload'></i> Upload Vídeo</button></div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="pagination-info">Total: <?php echo $total_apps; ?> apps · <?php echo $total_anuncios; ?> anúncios · <?php echo $total_videos; ?> vídeos — <?php echo date('d/m/Y H:i'); ?></div>

</div>
</div>

<!-- MODAIS -->

<!-- Excluir -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <div class="modal-info-card" id="excluirInfo"></div>
        <p style="text-align:center;font-size:11px;color:#f87171;font-weight:600;">⚠�� Esta ação NÃO pode ser desfeita! Os arquivos serão removidos.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" id="btnConfExcluir"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div></div>

<!-- Vídeo Player -->
<div id="modalVideo" class="modal-overlay">
<div class="modal-container wide"><div class="modal-content-custom">
    <div class="modal-header-custom video"><h5><i class='bx bx-play-circle'></i> Assistir Vídeo</h5><button class="modal-close" onclick="fecharVideo()"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom" style="padding:0 16px 16px;"><div class="video-container" id="videoContainer"></div></div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-cancel" onclick="fecharVideo()"><i class='bx bx-x'></i> Fechar</button></div>
</div></div></div>

<!-- Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom green"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom"><div class="modal-ic success"><i class='bx bx-check-circle'></i></div><p style="text-align:center;font-size:14px;font-weight:600;" id="sucessoMsg"></p></div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')"><i class='bx bx-check'></i> OK</button></div>
</div></div></div>

<!-- Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom"><div class="modal-ic error"><i class='bx bx-error-circle'></i></div><p style="text-align:center;font-size:14px;font-weight:600;" id="erroMsg"></p></div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i> Fechar</button></div>
</div></div></div>

<script>
// Modal utils
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o){o.classList.remove('show');if(o.id==='modalVideo')document.getElementById('videoContainer').innerHTML='';}});});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});document.getElementById('videoContainer').innerHTML='';}});

// Tabs
var tabMap = {apps:'active-apps',anuncios:'active-anuncios',videos:'active-videos',add_app:'active-add-app',add_anuncio:'active-add-anuncio',add_video:'active-add-video'};
function openTab(name){
    document.querySelectorAll('.tab-content').forEach(function(c){c.classList.remove('active');});
    document.querySelectorAll('.tab-btn').forEach(function(b){
        for(var k in tabMap) b.classList.remove(tabMap[k]);
    });
    var el=document.getElementById('tab-'+name);
    if(el)el.classList.add('active');
    // Find matching button
    var btns=document.querySelectorAll('.tab-btn');
    btns.forEach(function(b,i){
        var keys=Object.keys(tabMap);
        if(i<keys.length && keys[i]===name) b.classList.add(tabMap[name]);
    });
    history.replaceState(null,null,'?tab='+name);
}

// Search
function filtrar(inputId,selector,attr){
    var val=document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll(selector).forEach(function(c){
        var d=c.getAttribute(attr)||'';
        c.style.display=d.includes(val)?'':'none';
    });
}

// Video type toggle
function toggleVideoForm(tipo,el){
    document.querySelectorAll('.vt-option').forEach(function(o){o.classList.remove('active');});
    el.classList.add('active');
    document.querySelectorAll('.video-form').forEach(function(f){f.classList.remove('active');});
    document.getElementById('form-'+tipo).classList.add('active');
}

// Excluir
var _delType='',_delId=0;
function confirmarExclusao(tipo,id,nome){
    _delType=tipo;_delId=id;
    var iconMap={app:'bx-mobile-alt',anuncio:'bx-bullhorn',video:'bx-video'};
    var colorMap={app:'#60a5fa',anuncio:'#fbbf24',video:'#f87171'};
    var labelMap={app:'Aplicativo',anuncio:'Anúncio',video:'Vídeo'};
    document.getElementById('excluirInfo').innerHTML='<div class="modal-info-row"><i class="bx '+iconMap[tipo]+'" style="color:'+colorMap[tipo]+';"></i> <span>'+labelMap[tipo]+':</span> <strong>'+nome+'</strong></div><div class="modal-info-row"><i class="bx bx-id-card" style="color:#fbbf24;"></i> <span>ID:</span> <strong>#'+id+'</strong></div>';
    document.getElementById('btnConfExcluir').onclick=function(){
        var urlMap={app:'?excluir_app=',anuncio:'?excluir_anuncio=',video:'?excluir_video='};
        window.location.href=urlMap[_delType]+_delId;
    };
    abrirModal('modalExcluir');
}

// Video player
function playVideo(tipo,urlYt,arqVideo){
    var c=document.getElementById('videoContainer');c.innerHTML='';
    if(tipo==='youtube'){
        var m=urlYt.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
        var vid=m?m[1]:'';
        if(vid)c.innerHTML='<iframe src="https://www.youtube.com/embed/'+vid+'?autoplay=1" allowfullscreen></iframe>';
    }else if(arqVideo){
        c.innerHTML='<video src="../loja/videos/'+arqVideo+'" controls autoplay></video>';
    }
    abrirModal('modalVideo');
}
function fecharVideo(){fecharModal('modalVideo');document.getElementById('videoContainer').innerHTML='';}

// Show result modal
<?php if($show_modal): ?>
document.addEventListener('DOMContentLoaded',function(){
    <?php if($msg_type=='success'): ?>
    document.getElementById('sucessoMsg').textContent=<?php echo json_encode($msg); ?>;
    abrirModal('modalSucesso');
    <?php else: ?>
    document.getElementById('erroMsg').textContent=<?php echo json_encode($msg); ?>;
    abrirModal('modalErro');
    <?php endif; ?>
});
<?php endif; ?>
</script>
</body>
</html>

