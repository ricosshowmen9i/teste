<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio924926($input)
    {
?>
<?php
error_reporting(0);
session_start();
include('conexao.php');
include 'header2.php';
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Buscar logs
$sql2 = "SELECT * FROM logs WHERE revenda = '$_SESSION[login]' OR byid = '$_SESSION[iduser]' ORDER BY id DESC";
$result = mysqli_query($conn, $sql2);

// Se não houver registros, vamos criar alguns de exemplo para teste
if (mysqli_num_rows($result) == 0) {
    if (isset($exemplos) && is_array($exemplos)) {
        foreach ($exemplos as $exemplo) {
            $texto = $exemplo[0];
            $tempo = $exemplo[1];
            $data = date('Y-m-d H:i:s', strtotime("-$tempo"));
            $insert = "INSERT INTO logs (revenda, byid, texto, validade) VALUES (
                '{$_SESSION['login']}',
                '{$_SESSION['iduser']}',
                '$texto',
                '$data'
            )";
            mysqli_query($conn, $insert);
        }
        // Buscar novamente após inserir os exemplos
        $result = mysqli_query($conn, $sql2);
    }
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "O domínio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " inválido!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Contadores por tipo de ação
$all_logs = [];
$count_criou = $count_editou = $count_excluiu = $count_suspendeu = 0;
$count_pagamento = $count_reativou = $count_renovar = 0;
$total_logs = mysqli_num_rows($result);

while ($r = mysqli_fetch_assoc($result)) {
    $all_logs[] = $r;
    $t = strtolower($r['texto']);
    if (strpos($t, 'criou') !== false) $count_criou++;
    elseif (strpos($t, 'editou') !== false) $count_editou++;
    elseif (strpos($t, 'excluiu') !== false || strpos($t, 'exclu') !== false) $count_excluiu++;
    elseif (strpos($t, 'suspendeu') !== false) $count_suspendeu++;
    elseif (strpos($t, 'pagamento') !== false) $count_pagamento++;
    elseif (strpos($t, 'reativou') !== false) $count_reativou++;
    elseif (strpos($t, 'renovar') !== false || strpos($t, 'renovou') !== false) $count_renovar++;
}

// Paginação
$per_page = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = max(1, ceil($total_logs / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$paged_logs = array_slice($all_logs, $offset, $per_page);

// Helper: detectar tipo de ação
function getLogActionType($texto) {
    $t = strtolower($texto);
    if (strpos($t, 'criou') !== false) return 'criou';
    if (strpos($t, 'editou') !== false) return 'editou';
    if (strpos($t, 'excluiu') !== false || strpos($t, 'exclu') !== false) return 'excluiu';
    if (strpos($t, 'suspendeu') !== false) return 'suspendeu';
    if (strpos($t, 'pagamento') !== false) return 'pagamento';
    if (strpos($t, 'reativou') !== false) return 'reativou';
    if (strpos($t, 'renovar') !== false || strpos($t, 'renovou') !== false) return 'renovar';
    return 'outro';
}

function getLogIcon($type) {
    $map = [
        'criou' => 'bx-user-plus', 'editou' => 'bx-edit', 'excluiu' => 'bx-trash',
        'suspendeu' => 'bx-lock', 'pagamento' => 'bx-credit-card',
        'reativou' => 'bx-check-circle', 'renovar' => 'bx-refresh', 'outro' => 'bx-history'
    ];
    return $map[$type] ?? 'bx-history';
}

function getLogLabel($type) {
    $map = [
        'criou' => 'Criação', 'editou' => 'Edição', 'excluiu' => 'Exclusão',
        'suspendeu' => 'Suspensão', 'pagamento' => 'Pagamento',
        'reativou' => 'Reativação', 'renovar' => 'Renovação', 'outro' => 'Atividade'
    ];
    return $map[$type] ?? 'Atividade';
}
?>

<style>
.log-header-criou{background:linear-gradient(135deg,#10b981,#059669)!important;}
.log-header-editou{background:linear-gradient(135deg,#8b5cf6,#7c3aed)!important;}
.log-header-excluiu{background:linear-gradient(135deg,#dc2626,#b91c1c)!important;}
.log-header-suspendeu{background:linear-gradient(135deg,#f59e0b,#f97316)!important;}
.log-header-pagamento{background:linear-gradient(135deg,#3b82f6,#2563eb)!important;}
.log-header-reativou{background:linear-gradient(135deg,#06b6d4,#0891b2)!important;}
.log-header-renovar{background:linear-gradient(135deg,#10b981,#059669)!important;}
.log-header-outro{background:linear-gradient(135deg,#64748b,#475569)!important;}
.btn-clear-all{
    padding:8px 18px;border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;
    background:linear-gradient(135deg,#dc2626,#b91c1c);color:white;display:inline-flex;align-items:center;gap:6px;
    transition:all .2s;
}
.btn-clear-all:hover{filter:brightness(1.15);transform:translateY(-1px);}
.pagination-bar{display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap;}
.pagination-bar a,.pagination-bar span{
    padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;
    border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.6);transition:all .2s;
}
.pagination-bar a:hover{background:rgba(255,255,255,.08);color:white;}
.pagination-bar .pg-active{background:linear-gradient(135deg,#7c3aed,#a78bfa);color:white;border-color:transparent;}
.pagination-bar .pg-disabled{opacity:.3;pointer-events:none;}
.log-time-badge{
    display:inline-block;padding:2px 8px;background:rgba(255,255,255,.15);border-radius:10px;font-size:10px;
    color:rgba(255,255,255,.8);font-weight:600;
}
.log-action-tag{
    display:inline-block;padding:2px 8px;border-radius:8px;font-size:9px;font-weight:700;
    text-transform:uppercase;letter-spacing:.5px;background:rgba(255,255,255,.2);color:white;
}
.btn-delete-inline{
    background:rgba(220,38,38,.15);color:#f87171;border:1px solid rgba(220,38,38,.2);
    padding:6px 10px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;
    transition:all .2s;display:inline-flex;align-items:center;gap:4px;width:100%;justify-content:center;
}
.btn-delete-inline:hover{background:rgba(220,38,38,.25);transform:translateY(-1px);}
@media(max-width:768px){
    .items-grid{grid-template-columns:1fr!important;}
    .mini-stats{gap:6px!important;}
    .mini-stat{min-width:60px!important;padding:8px 6px!important;}
}
</style>

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-history'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Logs do Sistema</div>
        <div class="stats-card-value"><?php echo $total_logs; ?></div>
        <div class="stats-card-subtitle">registros de atividade</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-history'></i></div>
</div>

<!-- Mini Stats -->
<div class="mini-stats">
    <div class="mini-stat" data-filter="todos"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_logs; ?></div><div class="mini-stat-lbl">Total</div></div>
    <div class="mini-stat" data-filter="criou"><div class="mini-stat-val" style="color:#34d399;"><?php echo $count_criou; ?></div><div class="mini-stat-lbl">Criados</div></div>
    <div class="mini-stat" data-filter="editou"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $count_editou; ?></div><div class="mini-stat-lbl">Editados</div></div>
    <div class="mini-stat" data-filter="excluiu"><div class="mini-stat-val" style="color:#f87171;"><?php echo $count_excluiu; ?></div><div class="mini-stat-lbl">Excluídos</div></div>
    <div class="mini-stat" data-filter="suspendeu"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $count_suspendeu; ?></div><div class="mini-stat-lbl">Suspensos</div></div>
    <div class="mini-stat" data-filter="pagamento"><div class="mini-stat-val" style="color:#60a5fa;"><?php echo $count_pagamento; ?></div><div class="mini-stat-lbl">Pagamentos</div></div>
    <div class="mini-stat" data-filter="reativou"><div class="mini-stat-val" style="color:#22d3ee;"><?php echo $count_reativou; ?></div><div class="mini-stat-lbl">Reativados</div></div>
    <div class="mini-stat" data-filter="renovar"><div class="mini-stat-val" style="color:#34d399;"><?php echo $count_renovar; ?></div><div class="mini-stat-lbl">Renovados</div></div>
</div>

<!-- Filter Card -->
<div class="modern-card">
    <div class="card-header-custom blue">
        <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
        <div>
            <div class="header-title">Filtros de Busca</div>
            <div class="header-subtitle">Filtre e pesquise seus logs</div>
        </div>
    </div>
    <div class="card-body-custom">
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">Buscar</div>
                <input type="text" class="filter-input" id="searchInput" placeholder="Buscar nos logs...">
            </div>
            <div class="filter-item">
                <div class="filter-label">Tipo de Ação</div>
                <select class="filter-select" id="typeFilter">
                    <option value="todos">📋 Todos</option>
                    <option value="criou">🟢 Criação</option>
                    <option value="editou">🟣 Edição</option>
                    <option value="excluiu">🔴 Exclusão</option>
                    <option value="suspendeu">🟠 Suspensão</option>
                    <option value="pagamento">🔵 Pagamento</option>
                    <option value="reativou">🔵 Reativação</option>
                    <option value="renovar">🟢 Renovação</option>
                </select>
            </div>
            <div class="filter-item" style="flex:0 0 auto;">
                <div class="filter-label">&nbsp;</div>
                <button class="btn-clear-all" onclick="excluirTodosLogs()"><i class='bx bx-trash'></i> Limpar Todos</button>
            </div>
        </div>
    </div>
</div>

<!-- Logs Grid -->
<div class="items-grid" id="logsGrid">
<?php
if (count($paged_logs) > 0) {
    foreach ($paged_logs as $user_data) {
        $log_id = $user_data['id'];
        $data_log = $user_data['validade'];
        $data_formatada = date('d/m/Y H:i:s', strtotime($data_log));
        $action_type = getLogActionType($user_data['texto']);
        $action_icon = getLogIcon($action_type);
        $action_label = getLogLabel($action_type);

        // Calcular tempo relativo
        $timestamp = strtotime($data_log);
        $agora = time();
        $diferenca = $agora - $timestamp;

        if ($diferenca < 60) {
            $tempo_relativo = 'agora mesmo';
        } elseif ($diferenca < 3600) {
            $minutos = floor($diferenca / 60);
            $tempo_relativo = $minutos . ' min atrás';
        } elseif ($diferenca < 86400) {
            $horas = floor($diferenca / 3600);
            $tempo_relativo = $horas . 'h atrás';
        } elseif ($diferenca < 2592000) {
            $dias = floor($diferenca / 86400);
            $tempo_relativo = $dias . 'd atrás';
        } else {
            $tempo_relativo = date('d/m/Y', $timestamp);
        }
?>
<div class="item-card" data-type="<?php echo $action_type; ?>" data-search="<?php echo strtolower(htmlspecialchars($user_data['texto'] . ' ' . $user_data['revenda'])); ?>" data-data="<?php echo $data_log; ?>">
    <div class="item-card-header log-header-<?php echo $action_type; ?>" style="border-radius:14px 14px 0 0;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="header-icon"><i class='bx <?php echo $action_icon; ?>'></i></div>
            <div>
                <div class="header-title"><?php echo $action_label; ?></div>
                <div class="header-subtitle"><?php echo htmlspecialchars($user_data['revenda']); ?></div>
            </div>
        </div>
        <span class="log-time-badge"><?php echo $tempo_relativo; ?></span>
    </div>
    <div class="item-card-body" style="padding:10px 12px;">
        <div class="info-row" style="margin-bottom:6px;">
            <div class="info-icon"><i class='bx bx-detail' style="color:#a78bfa;"></i></div>
            <div class="info-content">
                <div class="info-label">Detalhes</div>
                <div class="info-value"><?php echo htmlspecialchars($user_data['texto']); ?></div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:6px;">
            <div class="info-row">
                <div class="info-icon"><i class='bx bx-calendar' style="color:#fbbf24;"></i></div>
                <div class="info-content">
                    <div class="info-label">Data</div>
                    <div class="info-value" style="font-size:10px;"><?php echo $data_formatada; ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon"><i class='bx bx-user' style="color:#818cf8;"></i></div>
                <div class="info-content">
                    <div class="info-label">Usuário</div>
                    <div class="info-value" style="font-size:10px;"><?php echo htmlspecialchars($user_data['revenda']); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="item-card-footer">
        <button class="btn-delete-inline" onclick="excluirLog(<?php echo $log_id; ?>, this)"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div>
<?php
    }
} else {
    echo '<div class="empty-state"><i class="bx bx-history"></i><h3>Nenhum log encontrado</h3><p>Os registros de atividades aparecerão aqui</p></div>';
}
?>
</div>

<!-- Paginação -->
<?php if ($total_pages > 1): ?>
<div class="pagination-bar">
    <a href="?page=<?php echo max(1, $page - 1); ?>" class="<?php echo $page <= 1 ? 'pg-disabled' : ''; ?>"><i class='bx bx-chevron-left'></i></a>
    <?php
    $start_pg = max(1, $page - 2);
    $end_pg = min($total_pages, $page + 2);
    if ($start_pg > 1) echo '<a href="?page=1">1</a>';
    if ($start_pg > 2) echo '<span style="border:none;padding:0 4px;">...</span>';
    for ($i = $start_pg; $i <= $end_pg; $i++):
    ?>
    <?php if ($i == $page): ?>
        <span class="pg-active"><?php echo $i; ?></span>
    <?php else: ?>
        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
    <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end_pg < $total_pages - 1) echo '<span style="border:none;padding:0 4px;">...</span>'; ?>
    <?php if ($end_pg < $total_pages) echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>'; ?>
    <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="<?php echo $page >= $total_pages ? 'pg-disabled' : ''; ?>"><i class='bx bx-chevron-right'></i></a>
</div>
<div style="text-align:center;margin-top:8px;color:rgba(255,255,255,.4);font-size:11px;">
    Página <?php echo $page; ?> de <?php echo $total_pages; ?> — <?php echo $total_logs; ?> registro(s)
</div>
<?php else: ?>
<div style="text-align:center;margin-top:16px;color:rgba(255,255,255,.4);font-size:11px;">
    Exibindo <?php echo $total_logs; ?> registro(s)
</div>
<?php endif; ?>

<script>
// Excluir log via AJAX
function excluirLog(logId, botao) {
    showModal({
        icon:'warning', title:'Tem certeza?', text:'Este log será excluído permanentemente!',
        buttons:['Cancelar','Excluir'], dangerMode:true,
        onConfirm: function(v){
            if(!v) return;
            var card = botao.closest('.item-card');
            var xhr = new XMLHttpRequest();
            xhr.open('POST','excluir_log.php',true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function(){
                if(xhr.responseText.trim()==='ok'){
                    card.style.transition='opacity .3s,transform .3s';
                    card.style.opacity='0'; card.style.transform='scale(.95)';
                    setTimeout(function(){ card.remove(); atualizarContador(); },300);
                    showModal({icon:'success',title:'Sucesso!',text:'Log excluído com sucesso!',timer:1500});
                } else {
                    showModal({icon:'error',title:'Erro!',text:'Erro ao excluir log.'});
                }
            };
            xhr.send('log_id='+logId);
        }
    });
}

// Excluir todos os logs via AJAX
function excluirTodosLogs() {
    showModal({
        icon:'warning', title:'Tem certeza?', text:'Todos os logs serão excluídos permanentemente!',
        buttons:['Cancelar','Excluir Todos'], dangerMode:true,
        onConfirm: function(v){
            if(!v) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST','excluir_todos_logs.php',true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function(){
                if(xhr.responseText.trim()==='ok'){
                    document.getElementById('logsGrid').innerHTML =
                        '<div class="empty-state"><i class="bx bx-history"></i><h3>Nenhum log encontrado</h3><p>Os registros de atividades aparecerão aqui</p></div>';
                    atualizarContador();
                    showModal({icon:'success',title:'Sucesso!',text:'Todos os logs foram excluídos!',timer:1500});
                } else {
                    showModal({icon:'error',title:'Erro!',text:'Erro ao excluir logs.'});
                }
            };
            xhr.send('excluir_todos=true');
        }
    });
}

// Atualizar contador
function atualizarContador() {
    var total = document.querySelectorAll('.item-card').length;
    var val = document.querySelector('.stats-card-value');
    if (val) val.textContent = total;
}

// Filtro de busca + tipo
function aplicarFiltros() {
    var search = document.getElementById('searchInput').value.toLowerCase();
    var type = document.getElementById('typeFilter').value;
    document.querySelectorAll('.item-card').forEach(function(card) {
        var matchSearch = !search || (card.getAttribute('data-search') || '').includes(search);
        var matchType = type === 'todos' || card.getAttribute('data-type') === type;
        card.style.display = (matchSearch && matchType) ? '' : 'none';
    });
}

document.getElementById('searchInput').addEventListener('keyup', aplicarFiltros);
document.getElementById('typeFilter').addEventListener('change', aplicarFiltros);

// Mini-stats clickable filter
document.querySelectorAll('.mini-stat[data-filter]').forEach(function(el) {
    el.style.cursor = 'pointer';
    el.addEventListener('click', function() {
        var f = this.getAttribute('data-filter');
        document.getElementById('typeFilter').value = f;
        document.querySelectorAll('.mini-stat').forEach(function(s){ s.classList.remove('active'); });
        this.classList.add('active');
        aplicarFiltros();
    });
});
</script>

</div><!-- page-body -->
</div><!-- main-wrap -->
</body>
</html>
<?php
    }
    aleatorio924926($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
