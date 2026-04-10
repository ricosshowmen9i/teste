<?php
error_reporting(0);
session_start();
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy(); header('location:../index.php'); exit;
}
if ($_SESSION['login'] !== 'admin') {
    echo "<script>alert('Acesso negado!');window.location='home.php';</script>"; exit;
}
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("DB Error: " . mysqli_connect_error());

// Carregar temas ANTES de qualquer output
include_once '../AegisCore/temas.php';

// ── Processar POSTs de TEMA aqui (antes do headeradmin2 que já emite <!DOCTYPE html>) ──
if (isset($_POST['__setMeuTema']) || isset($_POST['__resetTema']) || isset($_POST['__toggleRotacao']) || isset($_POST['__toggleComemo']) || isset($_POST['__desativarTemas'])) {
    initTemas($conn); // garantir tabelas
    processarTemaPOST($conn);
    // processarTemaPOST faz header() redirect + exit — nunca chega aqui
    exit;
}

// ── Upload e remoção de fundo (POST antes de output) ──
$uploadMsg = '';
if (isset($_POST['upload_fundo_tema_id']) && isset($_FILES['fundo_file'])) {
    $tid = intval($_POST['upload_fundo_tema_id']);
    $file = $_FILES['fundo_file'];
    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif','webp','svg'];
        if (in_array($ext, $permitidas) && $file['size'] <= 10485760) {
            $pasta = '../uploads/fundos/';
            if (!is_dir($pasta)) mkdir($pasta, 0755, true);
            $nomeArquivo = 'fundo_tema_' . $tid . '_' . time() . '.' . $ext;
            $caminho = $pasta . $nomeArquivo;
            if (move_uploaded_file($file['tmp_name'], $caminho)) {
                salvarFundoPersonalizado($conn, $tid, 'uploads/fundos/' . $nomeArquivo, $file['name']);
                $uploadMsg = 'ok';
            } else { $uploadMsg = 'err_move'; }
        } else { $uploadMsg = 'err_format'; }
    } else { $uploadMsg = 'err_upload'; }
}
if (isset($_POST['remover_fundo_tema_id'])) {
    $tid = intval($_POST['remover_fundo_tema_id']);
    removerFundoPersonalizado($conn, $tid);
    $uploadMsg = 'removed';
}

include_once 'headeradmin2.php';
if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';
if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; exit; }
}

// Dados para a página — $temaAtual já vem do headeradmin2.php
$temas = getTemasDisponiveis();
try {
    $status = getTemasStatus($conn);
} catch (\Throwable $e) {
    // Fallback: getTemasStatus falhou (ex: servidor sem ext-calendar)
    // Tentar buscar dados individualmente para não perder funcionalidade
    try { $fundosFallback = listarFundosPersonalizados($conn); } catch(\Throwable $e2) { $fundosFallback = []; }
    try { $configFallback = getTemaConfig($conn); } catch(\Throwable $e2) { $configFallback = ['modo'=>'auto','rotacao_ativa'=>1,'comemo_ativa'=>1,'tema_global_id'=>1]; }
    $status = [
        'config' => $configFallback,
        'tema_atual' => $temaAtual ?? ['id' => 1, 'nome' => 'Dark Original', 'classe' => 'theme-dark'],
        'proximo_comemorativo' => null,
        'comemorativo_ativo_id' => null,
        'historico_recente' => [],
        'fundos_personalizados' => $fundosFallback,
        'total_temas' => count($temas),
        'total_comemorativos' => 0,
        'total_rotacao' => 0,
    ];
    echo '<div style="position:fixed;top:60px;right:10px;background:#f59e0b;color:#000;padding:8px 14px;border-radius:8px;z-index:99999;font-size:11px;font-family:monospace;max-width:400px;box-shadow:0 4px 20px rgba(0,0,0,.5);">Aviso: Suba o temas.php atualizado no servidor (erro: '.htmlspecialchars($e->getMessage()).')</div>';
}
$config = $status['config'];
$fundos = $status['fundos_personalizados'];
$temaAtivoId = is_array($status['tema_atual']) ? ($status['tema_atual']['id'] ?? 1) : intval($status['tema_atual']);
$comemorativoId = $status['comemorativo_ativo_id'];
$modo = $config['modo'] ?? 'auto';
$rotacaoAtiva = $config['rotacao_ativa'] ?? 1;
$comemoAtiva = $config['comemo_ativa'] ?? 1;

$rotStyle = $rotacaoAtiva
    ? 'border-color:rgba(59,130,246,.5);background:rgba(59,130,246,.08)'
    : 'border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.05)';
$comemoStyle = $comemoAtiva
    ? 'border-color:rgba(251,191,36,.5);background:rgba(251,191,36,.08)'
    : 'border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.05)';

$categorias = [
    'all' => 'Todos', 'padrao' => 'Padrão', 'moderno' => 'Moderno',
    'premium' => 'Premium', 'natureza' => 'Natureza', 'anime' => 'Anime',
    'games' => 'Games', 'datas' => 'Datas',
];
?>

<style>
/* Editor de Painel — CSS específico */
.ep-cw{max-width:1700px;margin:0 auto;padding:0}

/* Stats */
.sc{background:rgba(15,20,40,.75);border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,.10);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.sc:hover{border-color:var(--acc1,#10b981)}
.sc-ic{width:60px;height:60px;background:var(--grad,linear-gradient(135deg,#4158D0,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;flex-shrink:0}
.sc-body{flex:1}
.sc-t{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px}
.sc-v{font-size:36px;font-weight:800;background:var(--grad,linear-gradient(135deg,#fff,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.sc-s{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px}
.sc-deco{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.04}
.sc-btns{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}

/* Mini stats */
.ms{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.ms-i{flex:1;min-width:100px;background:rgba(15,20,40,.7);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.08);text-align:center;transition:all .2s;cursor:default;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.ms-i:hover{border-color:var(--acc1,#10b981)}
.ms-ic{font-size:20px;margin-bottom:4px}

/* Preview links */
/* removed pv-links */
.ms-v{font-size:18px;font-weight:800}
.ms-l{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}

/* Card genérico */
.mc{background:rgba(15,20,40,.75);border-radius:16px;border:1px solid rgba(255,255,255,.10);overflow:hidden;margin-bottom:16px;transition:all .2s;backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.mc:hover{border-color:var(--acc1,#10b981)}
.mc-h{padding:14px 18px;display:flex;align-items:center;gap:12px;background:var(--grad,linear-gradient(135deg,#4158D0,#C850C0))}
.mc-h-ic{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.mc-h-t{font-size:14px;font-weight:700;color:#fff}
.mc-h-s{font-size:10px;color:rgba(255,255,255,.7)}
.mc-b{padding:16px}

/* Filtro de categorias */
.cats{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.cat-btn{padding:6px 14px;border:1px solid rgba(255,255,255,.10);background:rgba(15,20,40,.6);color:#cbd5e1;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit}
.cat-btn:hover{border-color:var(--acc1,#10b981);color:#fff}
.cat-btn.active{background:var(--grad,linear-gradient(135deg,#4158D0,#C850C0));color:#fff;border-color:transparent}

/* Config toggles */
.cfg-row{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-radius:12px;background:rgba(15,20,40,.6);margin-bottom:8px;border:1px solid rgba(255,255,255,.08);flex-wrap:wrap;gap:8px}
.cfg-info{display:flex;align-items:center;gap:10px;min-width:0;flex:1}
.cfg-ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.cfg-name{font-size:12px;font-weight:600}
.cfg-desc{font-size:10px;color:rgba(255,255,255,.4);word-break:break-word;overflow-wrap:break-word}
.toggle{position:relative;width:42px;height:24px;cursor:pointer;flex-shrink:0}
.toggle input{display:none}
.toggle-sl{position:absolute;inset:0;background:rgba(255,255,255,.12);border-radius:24px;transition:.3s}
.toggle-sl::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.3s}
.toggle input:checked+.toggle-sl{background:var(--acc1,#10b981)}
.toggle input:checked+.toggle-sl::before{transform:translateX(18px)}
.toggle-blue input:checked+.toggle-sl{background:#3b82f6}
.toggle-yellow input:checked+.toggle-sl{background:#f59e0b}

/* Grid de temas */
.tg{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.tc{background:rgba(15,20,40,.7);border-radius:14px;overflow:hidden;transition:all .25s;border:1.5px solid rgba(255,255,255,.08);position:relative;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.tc:hover{transform:translateY(-3px);border-color:rgba(255,255,255,.15);box-shadow:0 8px 32px rgba(0,0,0,.3)}
.tc.ativo{border-color:var(--acc1,#10b981);box-shadow:0 0 20px rgba(16,185,129,.15)}
.tc.comemo{border-color:#fbbf24;box-shadow:0 0 20px rgba(251,191,36,.15)}

.tc-top{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.tc-top.at{background:var(--grad,linear-gradient(135deg,#10b981,#059669))}
.tc-top.cm{background:linear-gradient(135deg,#fbbf24,#f97316)}
.tc-top.in{background:linear-gradient(135deg,#475569,#334155)}
.tc-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.tc-av{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;border:2px solid rgba(255,255,255,.2)}
.tc-nm{font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tc-st{font-size:9px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:4px;margin-top:1px}
.tc-badge{background:rgba(255,255,255,.2);padding:2px 8px;border-radius:20px;font-size:8px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
.tc-cat{font-size:8px;font-weight:700;padding:2px 6px;border-radius:10px;position:absolute;top:8px;right:8px;text-transform:uppercase;letter-spacing:.3px}

.tc-body{padding:12px 14px}
.tc-pv{height:52px;border-radius:10px;margin-bottom:10px;position:relative;overflow:hidden;display:flex;border:1px solid rgba(255,255,255,.06)}
.tc-pv-sb{width:28%;height:100%}
.tc-pv-ct{flex:1;padding:8px;display:flex;flex-direction:column;gap:4px;justify-content:center}
.tc-pv-ln{height:5px;border-radius:3px;background:rgba(255,255,255,.1)}
.tc-pv-bt{height:8px;width:50%;border-radius:4px}
.tc-desc{font-size:10px;color:rgba(255,255,255,.4);margin-bottom:10px;display:flex;align-items:center;gap:5px}
.tc-fundo{padding:8px 10px;border-radius:8px;background:rgba(15,20,40,.5);margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid rgba(255,255,255,.06)}
.tc-fundo-info{display:flex;align-items:center;gap:6px;font-size:10px;color:rgba(255,255,255,.5);flex:1;min-width:0}
.tc-fundo-info i{font-size:14px;color:var(--acc1,#10b981)}
.tc-fundo-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
.tc-acts{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
.ab{padding:6px 10px;border:none;border-radius:8px;font-weight:700;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:5px;color:#fff;transition:all .2s;font-family:inherit}
.ab:hover{transform:translateY(-1px);filter:brightness(1.1)}
.ab:disabled{opacity:.4;cursor:not-allowed;transform:none!important}
.ab i{font-size:13px;pointer-events:none}
.ab-ok{background:linear-gradient(135deg,#10b981,#059669);flex:1}
.ab-info{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.ab-up{background:linear-gradient(135deg,#8b5cf6,#7c3aed);flex:1}
.ab-del{background:linear-gradient(135deg,#dc2626,#b91c1c)}
/* removed ab-login */
.ab-sm{padding:5px 8px;font-size:9px}
.tc-ft{padding:8px 14px;border-top:1px solid rgba(255,255,255,.04);display:flex;align-items:center;justify-content:space-between}
.tc-id{font-size:9px;color:rgba(255,255,255,.2);font-family:monospace}
.hist-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;background:rgba(15,20,40,.5);margin-bottom:6px;border:1px solid rgba(255,255,255,.06)}
.hist-ic{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.hist-text{flex:1;font-size:11px}
.hist-date{font-size:9px;color:rgba(255,255,255,.3);font-family:monospace}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:20px;overflow-y:auto}
.mo.show{display:flex}
.mo-c{animation:moIn .3s ease;max-width:480px;width:100%;margin:auto}
@keyframes moIn{from{opacity:0;transform:scale(.95) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}
.mo-ct{background:rgba(30,41,59,.98);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);backdrop-filter:blur(16px)}
.mo-h{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;background:var(--grad,linear-gradient(135deg,#4158D0,#C850C0))}
.mo-h h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff}
.mo-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.mo-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}
.mo-body{padding:18px}
.mo-ft{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px}
.uz{border:2px dashed rgba(255,255,255,.15);border-radius:14px;padding:24px;text-align:center;cursor:pointer;transition:.3s;background:rgba(255,255,255,.02)}
.uz:hover,.uz.drag{border-color:var(--acc1,#10b981);background:rgba(16,185,129,.05)}
.uz i{font-size:36px;color:rgba(255,255,255,.2);margin-bottom:8px;display:block}
.uz p{color:rgba(255,255,255,.5);font-size:12px}
.toast{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:tIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast.ok{background:linear-gradient(135deg,#10b981,#059669)}
.toast.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.toast.warn{background:linear-gradient(135deg,#f59e0b,#f97316)}
@keyframes tIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
.search-box{position:relative;margin-bottom:16px}
.search-box input{width:100%;padding:10px 14px 10px 38px;background:rgba(15,20,40,.6);border:1.5px solid rgba(255,255,255,.10);border-radius:12px;font-size:13px;color:#fff;outline:none;font-family:inherit;transition:.2s}
.search-box input:focus{border-color:var(--acc1,#10b981);background:rgba(15,20,40,.8)}
.search-box input::placeholder{color:rgba(255,255,255,.3)}
.search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);font-size:16px}
.tabs{display:flex;gap:4px;background:rgba(0,0,0,.5);padding:4px;border-radius:12px;width:fit-content;flex-wrap:wrap;margin-bottom:16px}
.tb{padding:8px 16px;border:none;background:transparent;color:rgba(255,255,255,.5);font-size:11px;font-weight:600;cursor:pointer;border-radius:10px;transition:all .3s;display:flex;align-items:center;gap:5px;font-family:inherit}
.tb i{font-size:14px}
.tb.active{background:var(--grad,linear-gradient(135deg,#4158D0,#C850C0));color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.3)}
.tb:hover:not(.active){background:rgba(255,255,255,.06);color:#fff}
.tp{display:none;animation:fadeTab .3s ease}
.tp.active{display:block}
@keyframes fadeTab{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:768px){
    .ep-cw{padding:10px!important}
    .tg{grid-template-columns:1fr}
    .sc{flex-wrap:wrap;padding:14px;gap:14px}
    .sc-ic{width:48px;height:48px;font-size:24px}
    .sc-v{font-size:28px}
    .sc-btns{width:100%;justify-content:center}
    .ms{flex-wrap:wrap}.ms-i{min-width:70px}
    .tc-acts{display:grid;grid-template-columns:1fr 1fr}
    .cats{justify-content:center}
    .tabs{width:100%}.tb{flex:1;justify-content:center;font-size:9px;padding:6px 8px}
    .cfg-row{flex-direction:column;align-items:flex-start;gap:10px}
    .cfg-row .toggle{align-self:flex-end}
}
</style>

<div class="ep-cw">

<!-- STATS -->
<div class="sc">
<div class="sc-ic"><i class='bx bx-palette'></i></div>
<div class="sc-body">
    <div class="sc-t">Editor de Painel — Temas</div>
    <div class="sc-v"><?php echo count($temas); ?></div>
    <div class="sc-s">temas disponíveis • Modo: <strong style="color:<?php echo $modo === 'desativado' ? '#ef4444' : 'var(--acc1,#10b981)'; ?>"><?php echo $modo === 'manual' ? 'Manual' : ($modo === 'desativado' ? 'Desativado' : 'Automático'); ?></strong>
    <?php if ($comemorativoId): ?>
    • <strong style="color:#fbbf24">Comemorativo ativo!</strong>
    <?php endif; ?>
    </div>
</div>
<div class="sc-btns">
    <form method="POST" style="display:inline"><input type="hidden" name="__resetTema" value="1">
    <button type="submit" class="ab ab-info ab-sm"><i class='bx bx-revision'></i> Resetar Tema</button></form>
    <form method="POST" style="display:inline" onsubmit="return confirm('Remover todos os temas visuais? As páginas voltarão ao visual padrão (sem tema algum aplicado).')"><input type="hidden" name="__desativarTemas" value="1">
    <button type="submit" class="ab ab-del ab-sm" title="Remove todos os temas visuais. As páginas voltam ao visual padrão sem nenhum tema aplicado."><i class='bx bx-power-off'></i> Sem Tema (Padrão)</button></form>
</div>
<div class="sc-deco"><i class='bx bx-palette'></i></div>
</div>

<!-- MINI STATS -->
<div class="ms">
<div class="ms-i"><div class="ms-ic" style="color:#818cf8"><i class='bx bx-grid-alt'></i></div><div class="ms-v" style="color:#818cf8"><?php echo count($temas); ?></div><div class="ms-l">Total</div></div>
<div class="ms-i"><div class="ms-ic" style="color:#34d399"><i class='bx bx-check-circle'></i></div><div class="ms-v" style="color:#34d399">1</div><div class="ms-l">Ativo</div></div>
<div class="ms-i"><div class="ms-ic" style="color:#fbbf24"><i class='bx bx-calendar-star'></i></div><div class="ms-v" style="color:#fbbf24"><?php echo $status['total_comemorativos']; ?></div><div class="ms-l">Comemorativos</div></div>
<div class="ms-i"><div class="ms-ic" style="color:#60a5fa"><i class='bx bx-refresh'></i></div><div class="ms-v" style="color:#60a5fa"><?php echo $status['total_rotacao']; ?></div><div class="ms-l">Na Rotação</div></div>
<div class="ms-i"><div class="ms-ic" style="color:#e879f9"><i class='bx bx-image'></i></div><div class="ms-v" style="color:#e879f9"><?php echo count($fundos); ?></div><div class="ms-l">Fundos Custom</div></div>
</div>

<!-- TABS -->
<div class="tabs">
<button class="tb active" onclick="showTab('temas',this)"><i class='bx bx-grid-alt'></i> Temas</button>
<button class="tb" onclick="showTab('config',this)"><i class='bx bx-cog'></i> Configurações</button>
<button class="tb" onclick="showTab('fundos',this)"><i class='bx bx-image'></i> Fundos</button>
<button class="tb" onclick="showTab('historico',this)"><i class='bx bx-history'></i> Histórico</button>
</div>

<!-- ============== TAB: TEMAS ============== -->
<div id="tab-temas" class="tp active">
<div class="mc">
<div class="mc-h">
    <div class="mc-h-ic"><i class='bx bx-palette'></i></div>
    <div><div class="mc-h-t">Temas do Sistema</div><div class="mc-h-s">Clique em "Aplicar" para definir o tema global</div></div>
</div>
<div class="mc-b">
    <!-- Search -->
    <div class="search-box">
        <i class='bx bx-search'></i>
        <input type="text" placeholder="Buscar tema..." oninput="filterTemas(this.value)">
    </div>
    <!-- Cats -->
    <div class="cats">
    <?php foreach($categorias as $key => $label): ?>
        <button class="cat-btn <?php echo $key==='all'?'active':''; ?>" onclick="filterCat('<?php echo $key; ?>',this)"><?php echo $label; ?></button>
    <?php endforeach; ?>
    </div>
    <!-- Grid -->
    <div class="tg" id="temasGrid">
    <?php foreach($temas as $tid => $t):
        $isAtivo = ($tid == $temaAtivoId);
        $isComemo = ($tid == $comemorativoId);
        $hasFundo = isset($fundos[$tid]);
        $catClass = $t['categoria'];
    ?>
    <div class="tc <?php echo $isAtivo?'ativo':''; ?> <?php echo $isComemo?'comemo':''; ?>" data-cat="<?php echo $catClass; ?>" data-nome="<?php echo strtolower(htmlspecialchars(stripEmoji($t['nome']))); ?>" data-id="<?php echo $tid; ?>">
        <div class="tc-top <?php echo $isComemo?'cm':($isAtivo?'at':'in'); ?>">
            <div class="tc-info">
                <div class="tc-av" style="background:<?php echo htmlspecialchars($t['preview']); ?>"><?php echo mb_substr(stripEmoji($t['nome']),0,2); ?></div>
                <div style="min-width:0">
                    <div class="tc-nm"><?php echo htmlspecialchars(stripEmoji($t['nome'])); ?></div>
                    <div class="tc-st">
                        <i class='bx <?php echo $isAtivo?'bx-check-circle':'bx-circle'; ?>' style="font-size:10px"></i>
                        <?php
                        if ($isComemo) echo 'Comemorativo ativo';
                        elseif ($isAtivo) echo ($modo==='manual'?'Manual':'Automático');
                        else echo 'Inativo';
                        ?>
                    </div>
                </div>
            </div>
            <span class="tc-badge"><?php
                if ($isComemo) echo '🎉 Comemo';
                elseif ($isAtivo) echo '✓ Ativo';
                elseif ($t['categoria']==='datas') echo '📅 Data';
                else echo ucfirst($catClass);
            ?></span>
        </div>
        <div class="tc-body">
            <!-- Preview -->
            <div class="tc-pv" style="background:<?php echo htmlspecialchars($t['preview']); ?>20">
                <div class="tc-pv-sb" style="background:<?php echo htmlspecialchars($t['preview']); ?>40"></div>
                <div class="tc-pv-ct">
                    <div class="tc-pv-ln" style="width:80%"></div>
                    <div class="tc-pv-ln" style="width:55%"></div>
                    <div class="tc-pv-bt" style="background:<?php echo htmlspecialchars($t['preview']); ?>"></div>
                </div>
            </div>
            <!-- Desc -->
            <div class="tc-desc"><i class='bx bx-info-circle'></i> <?php echo htmlspecialchars($t['desc']); ?> • <code style="font-size:9px;color:rgba(255,255,255,.3)"><?php echo $t['classe']; ?></code></div>
            <!-- Fundo -->
            <?php if ($hasFundo): ?>
            <div class="tc-fundo">
                <div class="tc-fundo-info"><i class='bx bx-image'></i>
                    <img src="../<?php echo htmlspecialchars($fundos[$tid]['caminho'] ?? ''); ?>" alt="Fundo" style="width:48px;height:32px;object-fit:cover;border-radius:5px;border:1px solid rgba(255,255,255,.12);flex-shrink:0">
                    <span class="tc-fundo-name"><?php echo htmlspecialchars($fundos[$tid]['original_name'] ?? 'fundo'); ?></span>
                </div>
                <form method="POST" style="margin:0" onsubmit="return confirm('Remover fundo e voltar ao visual padrão do tema?')">
                    <input type="hidden" name="remover_fundo_tema_id" value="<?php echo $tid; ?>">
                    <button type="submit" class="ab ab-del ab-sm" style="padding:3px 6px" title="Voltar ao fundo padrão"><i class='bx bx-revision' style="font-size:11px"></i></button>
                </form>
            </div>
            <?php endif; ?>
            <!-- Actions -->
            <div class="tc-acts">
                <?php if (!$isAtivo): ?>
                <form method="POST" style="flex:1;display:flex"><input type="hidden" name="__setMeuTema" value="<?php echo $tid; ?>">
                <button type="submit" class="ab ab-ok" style="width:100%"><i class='bx bx-check-circle'></i> Aplicar</button></form>
                <?php else: ?>
                <button class="ab ab-ok" disabled style="flex:1"><i class='bx bx-check-shield'></i> Ativo</button>
                <?php endif; ?>
                <button class="ab ab-up ab-sm" onclick="openUploadModal(<?php echo $tid; ?>,'<?php echo htmlspecialchars(addslashes(stripEmoji($t['nome']))); ?>')"><i class='bx bx-image-add'></i></button>
            </div>
        </div>
        <div class="tc-ft">
            <div style="font-size:10px;color:rgba(255,255,255,.3)"><i class='bx bx-category' style="font-size:11px"></i> <?php echo ucfirst($catClass); ?></div>
            <div class="tc-id">#<?php echo $tid; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
</div>
</div>

<!-- ============== TAB: CONFIGURAÇÕES ============== -->
<div id="tab-config" class="tp">
<div class="mc">
<div class="mc-h" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
    <div class="mc-h-ic"><i class='bx bx-cog'></i></div>
    <div><div class="mc-h-t">Configurações do Sistema de Temas</div><div class="mc-h-s">Controle de rotação automática e datas comemorativas</div></div>
</div>
<div class="mc-b">
    <!-- Rotação automática -->
    <div class="cfg-row" style="<?= $rotStyle ?>">
        <div class="cfg-info">
            <div class="cfg-ic" style="background:rgba(59,130,246,.15);color:#60a5fa"><i class='bx bx-refresh'></i></div>
            <div>
                <div class="cfg-name">Rotação Automática (dia 3)</div>
                <div class="cfg-desc">Todo dia 3 troca o tema automaticamente (sem repetir)</div>
            </div>
        </div>
        <form method="POST" style="display:flex;align-items:center;gap:6px">
            <input type="hidden" name="__toggleRotacao" value="1">
            <label class="toggle toggle-blue"><input type="checkbox" <?php echo $rotacaoAtiva?'checked':''; ?> onchange="this.form.submit()"><span class="toggle-sl"></span></label>
            <span style="font-size:9px;font-weight:700;padding:3px 8px;border-radius:6px;background:<?= $rotacaoAtiva ? 'rgba(59,130,246,.2)' : 'rgba(239,68,68,.2)' ?>;color:<?= $rotacaoAtiva ? '#60a5fa' : '#f87171' ?>"><?= $rotacaoAtiva ? '✓ Ativo' : '✗ Inativo' ?></span>
        </form>
    </div>
    <!-- Datas comemorativas -->
    <div class="cfg-row" style="<?= $comemoStyle ?>">
        <div class="cfg-info">
            <div class="cfg-ic" style="background:rgba(251,191,36,.15);color:#fbbf24;flex-shrink:0"><i class='bx bx-calendar-star'></i></div>
            <div style="min-width:0">
                <div class="cfg-name">Datas Comemorativas</div>
                <div class="cfg-desc">Ativa temas automáticos em datas comemorativas (7 dias antes, 2 depois)</div>
            </div>
        </div>
        <form method="POST" style="display:flex;align-items:center;gap:6px">
            <input type="hidden" name="__toggleComemo" value="1">
            <label class="toggle toggle-yellow"><input type="checkbox" <?php echo $comemoAtiva?'checked':''; ?> onchange="this.form.submit()"><span class="toggle-sl"></span></label>
            <span style="font-size:9px;font-weight:700;padding:3px 8px;border-radius:6px;background:<?= $comemoAtiva ? 'rgba(251,191,36,.2)' : 'rgba(239,68,68,.2)' ?>;color:<?= $comemoAtiva ? '#fbbf24' : '#f87171' ?>"><?= $comemoAtiva ? '✓ Ativo' : '✗ Inativo' ?></span>
        </form>
    </div>
    <!-- Modo atual -->
    <div class="cfg-row">
        <div class="cfg-info">
            <div class="cfg-ic" style="background:rgba(16,185,129,.15);color:#34d399"><i class='bx bx-slider'></i></div>
            <div>
                <div class="cfg-name">Modo Atual: <strong style="color:var(--acc1,#10b981)"><?php echo $modo==='manual'?'Manual':'Automático'; ?></strong></div>
                <div class="cfg-desc"><?php echo $modo==='manual'?'Tema fixado manualmente pelo admin':($modo==='desativado'?'Temas visuais estão desativados — visual padrão sem tema':'Sistema gerencia automaticamente (rotação + datas)'); ?></div>
            </div>
        </div>
        <?php if ($modo === 'manual' || $modo === 'desativado'): ?>
        <form method="POST"><input type="hidden" name="__resetTema" value="1">
        <button type="submit" class="ab ab-info ab-sm"><i class='bx bx-revision'></i> <?= $modo === 'desativado' ? 'Reativar Auto' : 'Resetar / Sem Tema' ?></button></form>
        <?php else: ?>
        <span style="font-size:10px;color:rgba(255,255,255,.3);padding:6px 12px;border-radius:8px;background:rgba(16,185,129,.1)">✓ Ativo</span>
        <?php endif; ?>
    </div>
    <!-- Tema global ativo -->
    <div class="cfg-row" style="margin-top:16px;border-color:var(--acc1,rgba(16,185,129,.3))">
        <div class="cfg-info">
            <div class="cfg-ic" style="background:var(--acc1,#10b981);color:#fff"><i class='bx bx-palette'></i></div>
            <div>
                <div class="cfg-name">Tema Global Ativo</div>
                <?php
                $temaAtivoInfo = $temas[$temaAtivoId] ?? ['nome'=>'Sem Tema','classe'=>'desativado','preview'=>'#475569'];
                ?>
                <div class="cfg-desc" style="color:var(--acc1,#10b981);font-weight:600"><?php echo htmlspecialchars(stripEmoji($temaAtivoInfo['nome'])); ?> (<?php echo $temaAtivoInfo['classe']; ?>)</div>
            </div>
        </div>
        <div class="tc-av" style="background:<?php echo htmlspecialchars($temaAtivoInfo['preview'] ?? '#6366f1'); ?>;width:32px;height:32px;border-radius:8px"></div>
    </div>
    <?php if ($status['proximo_comemorativo']): ?>
    <div class="cfg-row" style="margin-top:8px;border-color:rgba(251,191,36,.3)">
        <div class="cfg-info">
            <div class="cfg-ic" style="background:rgba(251,191,36,.15);color:#fbbf24"><i class='bx bx-calendar-event'></i></div>
            <div>
                <div class="cfg-name">Próximo Tema Comemorativo</div>
                <div class="cfg-desc" style="color:#fbbf24"><?php
                    $prox = $status['proximo_comemorativo'];
                    echo htmlspecialchars(stripEmoji($prox['nome'] ?? '?') . ' — ativa em ' . ($prox['ativa_em'] ?? '?'));
                ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>

<!-- ============== TAB: FUNDOS ============== -->
<div id="tab-fundos" class="tp">

<?php
// Verificar se o TEMA ATIVO tem fundo personalizado
$fundoAtivoAtual = isset($fundos[$temaAtivoId]) ? $fundos[$temaAtivoId] : null;
// Verificar se o arquivo existe fisicamente no disco
if ($fundoAtivoAtual && !empty($fundoAtivoAtual['arquivo'])) {
    $caminhoFisico = __DIR__ . '/../' . $fundoAtivoAtual['arquivo'];
    if (!file_exists($caminhoFisico)) {
        $fundoAtivoAtual = null; // Arquivo não existe, tratar como sem fundo
    }
}
$temaAtivoNome = isset($temas[$temaAtivoId]) ? stripEmoji($temas[$temaAtivoId]['nome']) : 'Tema Ativo';

// Filtrar fundos que não existem fisicamente no disco
foreach ($fundos as $fid => $f) {
    if (!empty($f['arquivo'])) {
        $caminhoFisico = __DIR__ . '/../' . $f['arquivo'];
        if (!file_exists($caminhoFisico)) {
            unset($fundos[$fid]); // Remover registros com arquivo inexistente
        }
    }
}
?>

<!-- Fundo do tema ativo — preview grande -->
<div class="mc" style="margin-bottom:16px">
<div class="mc-h" style="background:linear-gradient(135deg,<?php echo $fundoAtivoAtual ? '#10b981,#059669' : '#475569,#334155'; ?>)">
    <div class="mc-h-ic"><i class='bx bx-<?php echo $fundoAtivoAtual ? 'image' : 'landscape'; ?>'></i></div>
    <div><div class="mc-h-t">Fundo Atual — <?php echo htmlspecialchars($temaAtivoNome); ?></div><div class="mc-h-s"><?php echo $fundoAtivoAtual ? 'Imagem personalizada ativa' : 'Usando fundo padrão do tema (gradiente)'; ?></div></div>
</div>
<div class="mc-b">
    <?php if ($fundoAtivoAtual): ?>
    <div style="border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.1);margin-bottom:14px">
        <div style="height:200px;background:url('../<?php echo htmlspecialchars($fundoAtivoAtual['arquivo']); ?>') center/cover;position:relative">
            <div style="position:absolute;top:12px;left:12px;background:rgba(16,185,129,.9);padding:4px 12px;border-radius:8px;font-size:10px;font-weight:700;color:#fff;display:flex;align-items:center;gap:5px"><i class='bx bx-check-circle'></i> Imagem personalizada</div>
            <div style="position:absolute;bottom:0;left:0;right:0;padding:10px 14px;background:linear-gradient(transparent,rgba(0,0,0,.85))">
                <div style="font-size:13px;font-weight:700"><?php echo htmlspecialchars($temaAtivoNome); ?></div>
                <div style="font-size:10px;color:rgba(255,255,255,.5)"><?php echo htmlspecialchars($fundoAtivoAtual['original_name'] ?? 'fundo personalizado'); ?></div>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="ab ab-up" style="flex:1" onclick="openUploadModal(<?php echo $temaAtivoId; ?>,'<?php echo htmlspecialchars(addslashes($temaAtivoNome)); ?>')"><i class='bx bx-refresh'></i> Trocar Imagem</button>
        <form method="POST" onsubmit="return confirm('Remover a imagem de fundo e voltar ao gradiente padrão do tema?')" style="flex:1;display:flex">
            <input type="hidden" name="remover_fundo_tema_id" value="<?php echo $temaAtivoId; ?>">
            <button type="submit" class="ab ab-del" style="width:100%"><i class='bx bx-trash'></i> Remover Imagem</button>
        </form>
    </div>
    <?php else: ?>
    <?php
    // Mostrar preview do gradiente padrão do tema
    $previewCor = $temas[$temaAtivoId]['preview'] ?? '#6366f1';
    ?>
    <div style="border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.1);margin-bottom:14px">
        <div style="height:200px;background:radial-gradient(ellipse at 50% 0%, <?php echo htmlspecialchars($previewCor); ?>30 0%, #0f172a 70%);position:relative;display:flex;align-items:center;justify-content:center">
            <div style="position:absolute;top:12px;left:12px;background:rgba(71,85,105,.9);padding:4px 12px;border-radius:8px;font-size:10px;font-weight:700;color:#fff;display:flex;align-items:center;gap:5px"><i class='bx bx-landscape'></i> Gradiente padrão</div>
            <div style="text-align:center;position:relative;z-index:1">
                <div style="width:60px;height:60px;border-radius:50%;background:<?php echo htmlspecialchars($previewCor); ?>;margin:0 auto 10px;opacity:.5"></div>
                <div style="font-size:11px;color:rgba(255,255,255,.4)">Fundo padrão do tema</div>
            </div>
            <div style="position:absolute;bottom:0;left:0;right:0;padding:10px 14px;background:linear-gradient(transparent,rgba(0,0,0,.85))">
                <div style="font-size:13px;font-weight:700"><?php echo htmlspecialchars($temaAtivoNome); ?></div>
                <div style="font-size:10px;color:rgba(255,255,255,.5)">Usando gradiente padrão do tema</div>
            </div>
        </div>
    </div>
    <div style="text-align:center">
        <button class="ab ab-up" onclick="openUploadModal(<?php echo $temaAtivoId; ?>,'<?php echo htmlspecialchars(addslashes($temaAtivoNome)); ?>')"><i class='bx bx-image-add'></i> Enviar Imagem de Fundo para <?php echo htmlspecialchars($temaAtivoNome); ?></button>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Todos os fundos personalizados -->
<div class="mc">
<div class="mc-h" style="background:linear-gradient(135deg,#f59e0b,#f97316)">
    <div class="mc-h-ic"><i class='bx bx-image'></i></div>
    <div><div class="mc-h-t">Todos os Fundos Personalizados</div><div class="mc-h-s">Fundos enviados para cada tema — troque ou remova para voltar ao padrão</div></div>
</div>
<div class="mc-b">
    <?php if (empty($fundos)): ?>
    <div style="text-align:center;padding:40px">
        <i class='bx bx-image' style="font-size:48px;color:rgba(255,255,255,.1);display:block;margin-bottom:10px"></i>
        <p style="font-size:14px;font-weight:600;margin-bottom:6px">Nenhum fundo personalizado</p>
        <p style="font-size:11px;color:rgba(255,255,255,.3)">Use o botão <i class='bx bx-image-add'></i> nos cards de temas para fazer upload</p>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">
    <?php foreach($fundos as $fid => $f):
        $temaInfo = $temas[$fid] ?? ['nome'=>'Tema #'.$fid,'classe'=>'?','preview'=>'#666'];
        $isAtivoFundo = ($fid == $temaAtivoId);
    ?>
    <div style="background:rgba(255,255,255,.04);border-radius:12px;overflow:hidden;border:1px solid <?php echo $isAtivoFundo ? 'rgba(16,185,129,.4)' : 'rgba(255,255,255,.06)'; ?>;<?php echo $isAtivoFundo ? 'box-shadow:0 0 16px rgba(16,185,129,.1);' : ''; ?>">
        <div style="height:120px;background:url('../<?php echo htmlspecialchars($f['arquivo']); ?>') center/cover;position:relative">
            <?php if ($isAtivoFundo): ?>
            <div style="position:absolute;top:8px;right:8px;background:rgba(16,185,129,.9);padding:2px 8px;border-radius:6px;font-size:9px;font-weight:700;color:#fff">ATIVO</div>
            <?php endif; ?>
            <div style="position:absolute;bottom:0;left:0;right:0;padding:8px 12px;background:linear-gradient(transparent,rgba(0,0,0,.8))">
                <div style="font-size:12px;font-weight:700"><?php echo htmlspecialchars(stripEmoji($temaInfo['nome'])); ?></div>
                <div style="font-size:9px;color:rgba(255,255,255,.5)"><?php echo htmlspecialchars($f['original_name'] ?? 'fundo'); ?></div>
            </div>
        </div>
        <div style="padding:10px;display:flex;gap:6px">
            <button class="ab ab-up ab-sm" style="flex:1" onclick="openUploadModal(<?php echo $fid; ?>,'<?php echo htmlspecialchars(addslashes(stripEmoji($temaInfo['nome']))); ?>')"><i class='bx bx-refresh'></i> Trocar</button>
            <form method="POST" onsubmit="return confirm('Remover fundo e voltar ao padrão do tema?')">
                <input type="hidden" name="remover_fundo_tema_id" value="<?php echo $fid; ?>">
                <button type="submit" class="ab ab-del ab-sm" title="Voltar ao fundo padrão"><i class='bx bx-revision'></i></button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>
</div>

<!-- ============== TAB: HISTÓRICO ============== -->
<div id="tab-historico" class="tp">
<div class="mc">
<div class="mc-h" style="background:linear-gradient(135deg,#06b6d4,#0891b2)">
    <div class="mc-h-ic"><i class='bx bx-history'></i></div>
    <div><div class="mc-h-t">Histórico de Rotação</div><div class="mc-h-s">Últimas trocas automáticas de tema</div></div>
</div>
<div class="mc-b">
    <?php if (empty($status['historico_recente'])): ?>
    <div style="text-align:center;padding:40px">
        <i class='bx bx-history' style="font-size:48px;color:rgba(255,255,255,.1);display:block;margin-bottom:10px"></i>
        <p style="font-size:14px;font-weight:600;margin-bottom:6px">Nenhuma rotação registrada</p>
        <p style="font-size:11px;color:rgba(255,255,255,.3)">A rotação automática ocorre todo dia 3 de cada mês</p>
    </div>
    <?php else: ?>
    <?php foreach($status['historico_recente'] as $h):
        $hTema = $temas[$h['tema_id'] ?? 0] ?? ['nome'=>'?','preview'=>'#666'];
    ?>
    <div class="hist-item">
        <div class="hist-ic" style="background:<?php echo htmlspecialchars($hTema['preview']); ?>20;color:<?php echo htmlspecialchars($hTema['preview']); ?>"><?php echo mb_substr(stripEmoji($hTema['nome']),0,1); ?></div>
        <div class="hist-text">
            <strong><?php echo htmlspecialchars(stripEmoji($hTema['nome'])); ?></strong>
            <span style="color:rgba(255,255,255,.4)"> — <?php echo htmlspecialchars($h['tipo'] ?? 'rotação'); ?></span>
        </div>
        <div class="hist-date"><?php echo htmlspecialchars($h['data_ativacao'] ?? $h['created_at'] ?? '?'); ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
</div>

<!-- ====== MODAL UPLOAD FUNDO ====== -->
<div class="mo" id="uploadModal">
<div class="mo-c"><div class="mo-ct">
<div class="mo-h"><h5><i class='bx bx-image-add'></i> <span id="uploadTitle">Upload de Fundo</span></h5><button class="mo-close" onclick="closeUploadModal()"><i class='bx bx-x'></i></button></div>
<div class="mo-body">
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
    <input type="hidden" name="upload_fundo_tema_id" id="uploadTemaId" value="">
    <div class="uz" id="uploadZone" onclick="document.getElementById('fundoFile').click()">
        <input type="file" id="fundoFile" name="fundo_file" accept="image/*" style="display:none" onchange="previewUpload(this)">
        <i class='bx bx-cloud-upload'></i>
        <p>Clique ou arraste uma imagem</p>
        <p style="font-size:10px;color:rgba(255,255,255,.3);margin-top:4px">JPG, PNG, GIF, WebP, SVG — máx 10MB</p>
    </div>
    <div id="uploadPreview" style="display:none;margin-top:14px">
        <img id="uploadImg" src="" style="width:100%;max-height:200px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,.1)">
    </div>
    <div class="mo-ft" style="border:none;padding:14px 0 0">
        <button type="button" class="ab ab-del ab-sm" onclick="closeUploadModal()">Cancelar</button>
        <button type="submit" class="ab ab-ok"><i class='bx bx-upload'></i> Enviar Fundo</button>
    </div>
    </form>
</div>
</div></div>
</div>

<script>
// Toast
function showToast(msg, tipo) {
    var t = document.createElement('div');
    t.className = 'toast ' + (tipo || 'ok');
    t.innerHTML = '<i class="bx ' + (tipo === 'err' ? 'bx-error-circle' : tipo === 'warn' ? 'bx-info-circle' : 'bx-check-circle') + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function() { t.style.opacity = '0'; t.style.transform = 'translateX(50px)'; }, 3000);
    setTimeout(function() { t.remove(); }, 3500);
}

// Tabs
function showTab(tab, btn) {
    document.querySelectorAll('.tp').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.tb').forEach(function(b) { b.classList.remove('active'); });
    var panel = document.getElementById('tab-' + tab);
    if (panel) panel.classList.add('active');
    if (btn) btn.classList.add('active');
}

// Filtro por categoria
function filterCat(cat, btn) {
    document.querySelectorAll('.cat-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.tg .tc').forEach(function(c) {
        c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
    });
}

// Busca por nome
function filterTemas(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.tg .tc').forEach(function(c) {
        c.style.display = c.dataset.nome.includes(q) ? '' : 'none';
    });
}

// Upload modal
function openUploadModal(temaId, temaNome) {
    document.getElementById('uploadTemaId').value = temaId;
    document.getElementById('uploadTitle').textContent = 'Fundo — ' + temaNome;
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('fundoFile').value = '';
    document.getElementById('uploadModal').classList.add('show');
}
function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('show');
}
function previewUpload(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('uploadImg').src = e.target.result;
            document.getElementById('uploadPreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Modal handlers
document.querySelectorAll('.mo').forEach(function(o) {
    o.addEventListener('click', function(e) { if (e.target === o) o.classList.remove('show'); });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.querySelectorAll('.mo.show').forEach(function(m) { m.classList.remove('show'); });
});

// Upload toast on page load
<?php if ($uploadMsg === 'ok'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('Fundo enviado com sucesso!', 'ok');
    // Abrir aba Fundos automaticamente para ver o resultado
    var btnFundos = document.querySelector('.tb[onclick*="fundos"]');
    if (btnFundos) showTab('fundos', btnFundos);
});
<?php elseif ($uploadMsg === 'removed'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('Fundo removido!', 'warn');
    var btnFundos = document.querySelector('.tb[onclick*="fundos"]');
    if (btnFundos) showTab('fundos', btnFundos);
});
<?php elseif ($uploadMsg && $uploadMsg !== ''): ?>
document.addEventListener('DOMContentLoaded', function() { showToast('Erro no upload: <?php echo $uploadMsg; ?>', 'err'); });
<?php endif; ?>
</script>
</div> <!-- /ep-cw -->
</div> <!-- /page-body -->
</div> <!-- /main-wrap -->
</body>
</html>
