<?php
error_reporting(0);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

if (!file_exists('../admin/suspenderrev.php')) {
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

// Verificação de validade da conta do revendedor
$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row5 = $sql5->fetch_assoc();
$validade = $row5['expira'];
$tipo = $row5['tipo'];
$_SESSION['tipodeconta'] = $row5['tipo'];
date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito') {
    if ($validade < $hoje) {
        echo "<script>alert('Sua conta está vencida')</script>";
        echo "<script>window.location.href = '../home.php'</script>";
        exit;
    }
}

// Anti SQL na busca
$_GET['search'] = anti_sql($_GET['search'] ?? '');
if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $sql = "SELECT * FROM ssh_accounts WHERE login LIKE '%$search%' AND byid = '$_SESSION[iduser]' ORDER BY expira ASC";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT * FROM ssh_accounts WHERE byid = '$_SESSION[iduser]' ORDER BY expira ASC";
    $result = $conn->query($sql);
}

$sql44 = "SELECT * FROM configs";
$result44 = $conn->query($sql44);
while ($row44 = $result44->fetch_assoc()) {
    $deviceativo = $row44['deviceativo'];
}

// Stats rápidas
$total_registros = $result->num_rows;
$total_online = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND status='Online'"); if($r){$total_online=$r->fetch_assoc()['t'];}
$total_vencidos = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND expira < NOW()"); if($r){$total_vencidos=$r->fetch_assoc()['t'];}
$total_suspensos = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND mainid='Suspenso'"); if($r){$total_suspensos=$r->fetch_assoc()['t'];}
?>

<style>
.users-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}
.user-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);transition:all .2s;}
.user-card:hover{transform:translateY(-2px);border-color:var(--primaria,#7c3aed);}
.user-header{background:linear-gradient(135deg,var(--primaria,#7c3aed),var(--secundaria,#a78bfa));padding:12px;display:flex;align-items:center;justify-content:space-between;}
.user-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.user-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;color:white;}
.user-text{flex:1;min-width:0;}
.user-name{font-size:14px;font-weight:700;color:white;display:flex;align-items:center;gap:5px;flex-wrap:wrap;word-break:break-all;}
.user-senha{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;display:flex;align-items:center;gap:4px;}
.v2ray-badge{background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:20px;font-size:8px;font-weight:600;}
.user-body{padding:12px;}
.user-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;}
.modal-info-card{background:rgba(255,255,255,0.05);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid rgba(255,255,255,0.08);}
.modal-info-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:11px;font-weight:600;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:6px;}
.modal-info-label i{font-size:16px;}
.modal-info-value{font-size:12px;font-weight:700;color:white;}
.modal-info-value.credential{background:rgba(0,0,0,0.3);padding:3px 8px;border-radius:6px;font-family:monospace;letter-spacing:.5px;}
.modal-info-value.highlight-green{color:#10b981;}
.modal-server-list{background:rgba(0,0,0,0.3);border-radius:10px;padding:10px;margin-top:10px;}
.modal-server-badge{display:inline-block;background:rgba(16,185,129,0.2);border:1px solid rgba(16,185,129,0.3);color:#10b981;padding:3px 8px;border-radius:16px;font-size:10px;margin:3px;}
.modal-server-badge.fail{background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#dc2626;}
.modal-divider{border:none;border-top:1px solid rgba(255,255,255,0.08);margin:14px 0;}
.modal-success-title{text-align:center;color:#10b981;font-weight:700;font-size:13px;}
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-modal-copy{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
@media(max-width:768px){
    .users-grid{grid-template-columns:1fr;}
    .user-actions{display:grid;grid-template-columns:repeat(2,1fr);}
    .modal-info-row{flex-direction:column;align-items:flex-start;gap:4px;}
    .modal-footer-custom{flex-direction:column;}
    .btn-modal{width:100%;justify-content:center;}
}
</style>

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-user-circle'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Lista de Usuários</div>
        <div class="stats-card-value"><?php echo $total_registros; ?></div>
        <div class="stats-card-subtitle">usuários cadastrados no sistema</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-user-circle'></i></div>
</div>

<!-- Mini Stats -->
<div class="mini-stats">
    <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_registros; ?></div><div class="mini-stat-lbl">Total</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_online; ?></div><div class="mini-stat-lbl">Onlines</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_vencidos; ?></div><div class="mini-stat-lbl">Vencidos</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_suspensos; ?></div><div class="mini-stat-lbl">Suspensos</div></div>
</div>

<!-- Filtros -->
<div class="modern-card">
    <div class="card-header-custom blue">
        <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
        <div><div class="header-title">Filtros de Busca</div><div class="header-subtitle">Encontre rapidamente</div></div>
    </div>
    <div class="card-body-custom">
        <div class="filter-group">
            <div class="filter-item">
                <div class="filter-label">Buscar por Login</div>
                <input type="text" class="filter-input" id="searchInput" placeholder="Digite o nome..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="filter-item">
                <div class="filter-label">Filtrar por Status</div>
                <select class="filter-select" id="statusFilter">
                    <option value="todos">📋 Todos</option>
                    <option value="online">🟢 Online</option>
                    <option value="offline">⚫ Offline</option>
                    <option value="suspenso">🔒 Suspenso</option>
                    <option value="expirado">⏰ Expirado</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Grid de Usuários -->
<div class="users-grid" id="usersGrid">
<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id       = $row['id'];
        $login    = $row['login'];
        $senha    = $row['senha'];
        $limite   = $row['limite'];
        $status   = $row['status'];
        $categoria = $row['categoriaid'];
        $suspenso = $row['mainid'];
        $notas    = $row['lastview'];
        $uuid     = $row['uuid'];
        $expira   = $row['expira'];
        $expira_formatada = date('d/m/Y', strtotime($expira));

        $sql_online  = "SELECT quantidade FROM onlines WHERE usuario = '$login'";
        $res_online  = $conn->query($sql_online);
        $row_online  = $res_online->fetch_assoc();
        $usando      = $row_online['quantidade'] ?? 0;

        $data_validade  = strtotime($expira);
        $data_atual     = time();
        $diferenca      = $data_validade - $data_atual;
        $dias_restantes = floor($diferenca / (60 * 60 * 24));
        $horas_restantes = floor(($diferenca % (60 * 60 * 24)) / (60 * 60));

        if ($suspenso == 'Suspenso') { $status_class='status-suspended'; $status_icon='bx-lock'; $status_label='Suspenso'; }
        elseif ($suspenso == 'Limite Ultrapassado') { $status_class='status-limit'; $status_icon='bx-error'; $status_label='Limite'; }
        elseif ($status == 'Online') { $status_class='status-online'; $status_icon='bx-wifi'; $status_label='Online'; }
        else { $status_class='status-offline'; $status_icon='bx-power-off'; $status_label='Offline'; }

        $val_class = $dias_restantes < 0 ? 'danger' : ($dias_restantes <= 5 ? 'warning' : '');
        $val_label = $dias_restantes < 0 ? 'Expirado' : ($dias_restantes === 0 ? 'Hoje' : $dias_restantes.'d');

        $data_status = ($suspenso == 'Suspenso' || $suspenso == 'Limite Ultrapassado') ? 'suspenso' : strtolower($status_label);
?>
<div class="user-card" data-login="<?php echo strtolower(htmlspecialchars($login)); ?>" data-status="<?php echo $data_status; ?>" data-expirado="<?php echo $dias_restantes<0?'expirado':''; ?>" data-id="<?php echo $id; ?>" data-usuario="<?php echo htmlspecialchars($login); ?>" data-senha="<?php echo htmlspecialchars($senha); ?>" data-limite="<?php echo $limite; ?>" data-expira="<?php echo $expira_formatada; ?>">
    <div class="user-header">
        <div class="user-info">
            <div class="user-avatar"><i class='bx bx-user'></i></div>
            <div class="user-text">
                <div class="user-name">
                    <?php echo htmlspecialchars($login); ?>
                    <?php if (!empty($uuid)): ?><span class="v2ray-badge">V2Ray</span><?php endif; ?>
                </div>
                <div class="user-senha"><i class='bx bx-lock-alt'></i> <?php echo htmlspecialchars($senha); ?></div>
            </div>
        </div>
        <button class="btn-copy-card" onclick="copiarInfoCard(<?php echo $id; ?>, event)"><i class='bx bx-copy'></i> Copiar</button>
    </div>
    <div class="user-body">
        <div class="status-row">
            <div class="status-item-card">
                <div class="status-icon"><i class='bx <?php echo $status_icon; ?>'></i></div>
                <div><div class="status-label">STATUS</div><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></div>
            </div>
            <div class="status-item-card">
                <div class="status-icon"><i class='bx bx-calendar' style="color:#fbbf24;"></i></div>
                <div><div class="status-label">VALIDADE</div><div class="status-value <?php echo $val_class; ?>"><?php echo $val_label; ?></div></div>
            </div>
        </div>
        <div class="info-grid">
            <div class="info-row"><div class="info-icon"><i class='bx bx-user' style="color:#818cf8;"></i></div><div class="info-content"><div class="info-label">LOGIN</div><div class="info-value"><?php echo htmlspecialchars($login); ?></div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-lock-alt' style="color:#e879f9;"></i></div><div class="info-content"><div class="info-label">SENHA</div><div class="info-value"><?php echo htmlspecialchars($senha); ?></div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-group' style="color:#34d399;"></i></div><div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php if ($usando > 0): ?><span class="<?php echo $usando >= $limite ? 'danger' : ''; ?>"><?php echo $usando; ?>/<?php echo $limite; ?></span><?php else: ?><?php echo $limite; ?><?php endif; ?></div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i></div><div class="info-content"><div class="info-label">EXPIRA</div><div class="info-value <?php echo $val_class; ?>"><?php echo $expira_formatada; ?></div></div></div>
            <div class="info-row"><div class="info-icon"><i class='bx bx-category' style="color:#60a5fa;"></i></div><div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($categoria); ?></div></div></div>
            <?php if (!empty($notas)): ?>
            <div class="info-row"><div class="info-icon"><i class='bx bx-note' style="color:#a78bfa;"></i></div><div class="info-content"><div class="info-label">NOTAS</div><div class="info-value"><?php echo htmlspecialchars($notas); ?></div></div></div>
            <?php endif; ?>
        </div>
        <div class="user-actions">
            <button class="action-btn btn-edit" onclick="editarUsuario(<?php echo $id; ?>)"><i class='bx bx-edit'></i> Editar</button>
            <button class="action-btn btn-renew" onclick="renovardias(<?php echo $id; ?>)"><i class='bx bx-refresh'></i> Renovar</button>
            <?php if ($suspenso == 'Suspenso'): ?>
            <button class="action-btn btn-reactivate" onclick="reativar(<?php echo $id; ?>)"><i class='bx bx-check-circle'></i> Ativar</button>
            <?php else: ?>
            <button class="action-btn btn-warn" onclick="suspender(<?php echo $id; ?>)"><i class='bx bx-lock'></i> Suspender</button>
            <?php endif; ?>
            <button class="action-btn btn-danger" onclick="excluir(<?php echo $id; ?>)"><i class='bx bx-trash'></i> Deletar</button>
        </div>
    </div>
</div>
<?php
    }
} else {
    echo '<div class="empty-state"><i class="bx bx-user-x"></i><h3>Nenhum usuário encontrado</h3><p>Crie novos usuários para que apareçam aqui.</p></div>';
}
?>
</div>

<div class="pagination-info" style="text-align:center;margin-top:16px;color:rgba(255,255,255,0.3);font-size:11px;">
    Exibindo <?php echo $total_registros; ?> usuário(s)
</div>

<!-- Modal Processando -->
<div id="modalProcessando" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom processing"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando</h5></div>
    <div class="modal-body-custom">
        <div class="spinner-wrap"><div class="spinner-ring"></div><p style="color:rgba(255,255,255,0.6);font-size:13px;">Aguarde enquanto processamos...</p></div>
    </div>
</div></div>
</div>

<!-- Modal Confirmar Renovação -->
<div id="modalConfirmarRenovacao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-calendar-plus'></i> Confirmar Renovação</h5><button class="modal-close" onclick="fecharModal('modalConfirmarRenovacao')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-calendar-plus'></i></div>
        <div class="modal-info-card">
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Usuário</div><div class="modal-info-value credential" id="confirmar-login">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade Atual</div><div class="modal-info-value" id="confirmar-expira">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-plus-circle' style="color:#10b981;"></i> Dias a Adicionar</div><div class="modal-info-value highlight-green">+30 dias</div></div>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,0.4);font-size:11px;">A validade será extendida a partir da data de expiração atual.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarRenovacao')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" id="btnConfirmarRenovacao"><i class='bx bx-calendar-plus'></i> Renovar Agora</button>
    </div>
</div></div>
</div>

<!-- Modal Confirmar Suspensão -->
<div id="modalConfirmarSuspensao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-pause-circle'></i> Confirmar Suspensão</h5><button class="modal-close" onclick="fecharModal('modalConfirmarSuspensao')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-pause-circle'></i></div>
        <div class="modal-info-card">
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Usuário</div><div class="modal-info-value credential" id="suspender-login">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div><div class="modal-info-value credential" id="suspender-senha">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade</div><div class="modal-info-value" id="suspender-expira">—</div></div>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,0.4);font-size:11px;">Após suspenso, o usuário não poderá mais acessar até ser reativado.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarSuspensao')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" id="btnConfirmarSuspensao"><i class='bx bx-pause-circle'></i> Suspender Agora</button>
    </div>
</div></div>
</div>

<!-- Modal Confirmar Reativação -->
<div id="modalConfirmarReativacao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-refresh'></i> Confirmar Reativação</h5><button class="modal-close" onclick="fecharModal('modalConfirmarReativacao')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-refresh'></i></div>
        <div class="modal-info-card">
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Usuário</div><div class="modal-info-value credential" id="reativar-login">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div><div class="modal-info-value credential" id="reativar-senha">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade</div><div class="modal-info-value" id="reativar-expira">—</div></div>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,0.4);font-size:11px;">O usuário será reativado e poderá acessar normalmente.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarReativacao')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-ok" id="btnConfirmarReativacao"><i class='bx bx-refresh'></i> Reativar Agora</button>
    </div>
</div></div>
</div>

<!-- Modal Confirmar Exclusão -->
<div id="modalConfirmarExclusao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Confirmar Exclusão</h5><button class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-trash'></i></div>
        <div class="modal-info-card">
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Usuário</div><div class="modal-info-value credential" id="excluir-login">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div><div class="modal-info-value credential" id="excluir-senha">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade</div><div class="modal-info-value" id="excluir-expira">—</div></div>
        </div>
        <p style="text-align:center;color:rgba(220,38,38,0.8);font-size:11px;">⚠️ Esta ação não pode ser desfeita! O usuário será permanentemente removido.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" id="btnConfirmarExclusao"><i class='bx bx-trash'></i> Excluir Permanentemente</button>
    </div>
</div></div>
</div>

<!-- Modal Sucesso Renovação -->
<div id="modalSucessoRenovacao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Usuário Renovado!</h5><button class="modal-close" onclick="fecharModalSucesso()"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
        <div class="modal-info-card" id="renovacao-info-card">
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login</div><div class="modal-info-value credential" id="renovacao-login">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div><div class="modal-info-value credential" id="renovacao-senha">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar-x' style="color:#f87171;"></i> Anterior</div><div class="modal-info-value" id="renovacao-validade-anterior">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#10b981;"></i> Nova Validade</div><div class="modal-info-value highlight-green" id="renovacao-nova-validade">—</div></div>
            <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-layer' style="color:#34d399;"></i> Limite</div><div class="modal-info-value" id="renovacao-limite">—</div></div>
            <div class="modal-info-row" id="renovacao-row-uuid" style="display:none;"><div class="modal-info-label"><i class='bx bx-shield-quarter' style="color:#60a5fa;"></i> UUID</div><div class="modal-info-value" id="renovacao-uuid" style="font-size:10px;word-break:break-all;">—</div></div>
        </div>
        <div class="modal-server-list" id="renovacao-servidores-ok" style="display:none;"><div style="font-size:11px;margin-bottom:6px;color:rgba(255,255,255,0.7);"><i class='bx bx-server'></i> Servidores atualizados:</div><div id="renovacao-servidores-ok-lista"></div></div>
        <div class="modal-server-list" id="renovacao-servidores-fail" style="display:none;margin-top:6px;"><div style="font-size:11px;margin-bottom:6px;color:rgba(220,38,38,0.8);"><i class='bx bx-error-circle'></i> Servidores com falha:</div><div id="renovacao-servidores-fail-lista"></div></div>
        <hr class="modal-divider">
        <p class="modal-success-title">✨ Renovação realizada com sucesso! ✨</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-copy" onclick="copiarInformacoesRenovacao()"><i class='bx bx-copy'></i> Copiar</button>
        <button class="btn-modal btn-modal-ok" onclick="fecharModalSucesso()"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- Modal Sucesso Operação -->
<div id="modalSucessoOperacao" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Operação Realizada!</h5><button class="modal-close" onclick="fecharModalSucessoOperacao()"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
        <h3 style="color:white;text-align:center;margin-bottom:8px;font-size:15px;" id="sucesso-titulo">Sucesso!</h3>
        <p style="color:rgba(255,255,255,0.7);text-align:center;font-size:12px;" id="sucesso-mensagem">Operação realizada com sucesso!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-ok" onclick="fecharModalSucessoOperacao()"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <h3 style="color:white;text-align:center;margin-bottom:8px;font-size:15px;">Ops! Algo deu errado</h3>
        <p style="color:rgba(255,255,255,0.7);text-align:center;font-size:12px;" id="erro-mensagem">Erro ao processar solicitação!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
    </div>
</div></div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
let _renovacaoData = {};
let _operacaoData = {};

// ==================== FILTROS ====================
function filtrarUsuarios() {
    let search = document.getElementById('searchInput').value.toLowerCase();
    let status = document.getElementById('statusFilter').value;
    document.querySelectorAll('.user-card').forEach(card => {
        let login = card.getAttribute('data-login');
        let cardStatus = card.getAttribute('data-status');
        let expirado = card.getAttribute('data-expirado');
        let statusMatch = (status === 'todos' || cardStatus === status || (status === 'expirado' && expirado === 'expirado'));
        let searchMatch = login.includes(search);
        card.style.display = (searchMatch && statusMatch) ? '' : 'none';
    });
}
document.getElementById('searchInput').addEventListener('keyup', filtrarUsuarios);
document.getElementById('statusFilter').addEventListener('change', filtrarUsuarios);

// ==================== UTILIDADES ====================
function getCard(id) { return document.querySelector('.user-card[data-id="'+id+'"]'); }

function copiarInfoCard(id, event) {
    event.stopPropagation();
    const card = getCard(id);
    if (!card) return;
    let texto = '📋 INFORMAÇÕES DO USUÁRIO\n━━━━━━━━━━━━━━━━━━━━━\n';
    texto += '👤 Login: ' + card.getAttribute('data-usuario') + '\n';
    texto += '🔑 Senha: ' + card.getAttribute('data-senha') + '\n';
    texto += '🔗 Limite: ' + card.getAttribute('data-limite') + ' conexões\n';
    texto += '📅 Expira em: ' + card.getAttribute('data-expira') + '\n';
    texto += '━━━━━━━━━━━━━━━━━━━━━\n';
    texto += '📆 Data: ' + new Date().toLocaleString('pt-BR');
    navigator.clipboard.writeText(texto).then(function() {
        const btn = event.currentTarget;
        const orig = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bx bx-check"></i> Copiado!';
        mostrarToast('Informações copiadas!');
        setTimeout(function() { btn.classList.remove('copied'); btn.innerHTML = orig; }, 2000);
    }).catch(function() { mostrarToast('Erro ao copiar!', true); });
}

function editarUsuario(id) { window.location.href = 'editarlogin.php?id=' + id; }

function mostrarToast(msg, erro) {
    const t = document.createElement('div');
    t.className = 'toast-notification ' + (erro ? 'err' : 'ok');
    t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:18px;"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
}

function abrirModal(id) { document.getElementById(id).classList.add('show'); }
function fecharModal(id) { document.getElementById(id).classList.remove('show'); }
function fecharTodosModais() { document.querySelectorAll('.modal-overlay').forEach(function(m) { m.classList.remove('show'); }); }
function mostrarProcessando() { abrirModal('modalProcessando'); }
function esconderProcessando() { fecharModal('modalProcessando'); }

function mostrarErro(msg) {
    document.getElementById('erro-mensagem').textContent = msg;
    abrirModal('modalErro');
}

function mostrarSucesso(titulo, msg) {
    document.getElementById('sucesso-titulo').textContent = titulo;
    document.getElementById('sucesso-mensagem').textContent = msg;
    abrirModal('modalSucessoOperacao');
}

function fecharModalSucessoOperacao() { fecharModal('modalSucessoOperacao'); location.reload(); }
function fecharModalSucesso() { fecharModal('modalSucessoRenovacao'); location.reload(); }

// ==================== RENOVAÇÃO ====================
function renovardias(id) {
    const card = getCard(id);
    _renovacaoData = {
        id: id,
        usuario: card?.getAttribute('data-usuario') || '',
        expira: card?.getAttribute('data-expira') || '',
        senha: card?.getAttribute('data-senha') || '',
        limite: card?.getAttribute('data-limite') || ''
    };
    document.getElementById('confirmar-login').textContent = _renovacaoData.usuario;
    document.getElementById('confirmar-expira').textContent = _renovacaoData.expira;
    document.getElementById('btnConfirmarRenovacao').onclick = function() {
        fecharModal('modalConfirmarRenovacao');
        processarRenovacao(id);
    };
    abrirModal('modalConfirmarRenovacao');
}

function processarRenovacao(id) {
    mostrarProcessando();
    $.ajax({
        url: 'renovardias.php?id=' + id, type: 'GET', dataType: 'json', timeout: 60000,
        success: function(response) {
            esconderProcessando();
            if (response.status === 'success') { preencherModalSucessoRenovacao(response); abrirModal('modalSucessoRenovacao'); }
            else { mostrarErro(response.message || 'Erro ao renovar usuário!'); }
        },
        error: function(xhr) {
            esconderProcessando();
            let msg = 'Erro ao conectar com o servidor!';
            try { const r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch(e) { if (xhr.responseText) msg = xhr.responseText; }
            mostrarErro(msg);
        }
    });
}

function preencherModalSucessoRenovacao(data) {
    document.getElementById('renovacao-login').textContent = data.login || '—';
    document.getElementById('renovacao-senha').textContent = data.senha || '—';
    document.getElementById('renovacao-nova-validade').textContent = data.new_expiry || '—';
    document.getElementById('renovacao-validade-anterior').textContent = data.validade_anterior || '—';
    document.getElementById('renovacao-limite').textContent = data.limite ? data.limite + ' conexões' : '—';
    var rowUUID = document.getElementById('renovacao-row-uuid');
    if (data.uuid) { rowUUID.style.display = 'flex'; document.getElementById('renovacao-uuid').textContent = data.uuid; }
    else { rowUUID.style.display = 'none'; }
    var divOk = document.getElementById('renovacao-servidores-ok'), listaOk = document.getElementById('renovacao-servidores-ok-lista');
    if (data.servers && data.servers.length > 0) { divOk.style.display = 'block'; listaOk.innerHTML = data.servers.map(function(s) { return '<span class="modal-server-badge"><i class="bx bx-check-circle" style="font-size:10px;"></i> '+s+'</span>'; }).join(''); }
    else { divOk.style.display = 'none'; }
    var divFail = document.getElementById('renovacao-servidores-fail'), listaFail = document.getElementById('renovacao-servidores-fail-lista');
    if (data.failed && data.failed.length > 0) { divFail.style.display = 'block'; listaFail.innerHTML = data.failed.map(function(s) { return '<span class="modal-server-badge fail"><i class="bx bx-x-circle" style="font-size:10px;"></i> '+s+'</span>'; }).join(''); }
    else { divFail.style.display = 'none'; }
}

function copiarInformacoesRenovacao() {
    var login = document.getElementById('renovacao-login').textContent;
    var senha = document.getElementById('renovacao-senha').textContent;
    var novaVal = document.getElementById('renovacao-nova-validade').textContent;
    var antVal = document.getElementById('renovacao-validade-anterior').textContent;
    var limite = document.getElementById('renovacao-limite').textContent;
    var uuid = document.getElementById('renovacao-uuid')?.textContent || '';
    var texto = '✅ USUÁRIO RENOVADO COM SUCESSO!\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
    texto += '👤 Login: ' + login + '\n🔑 Senha: ' + senha + '\n📅 Anterior: ' + antVal + '\n✅ Nova Validade: ' + novaVal + '\n🔗 Limite: ' + limite + '\n';
    if (uuid && uuid !== '—') texto += '🛡️ UUID: ' + uuid + '\n';
    texto += '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📆 Data: ' + new Date().toLocaleString('pt-BR');
    navigator.clipboard.writeText(texto).then(function() { mostrarToast('Informações copiadas!'); });
}

// ==================== SUSPENDER ====================
function suspender(id) {
    const card = getCard(id);
    _operacaoData = { id: id, usuario: card?.getAttribute('data-usuario')||'', senha: card?.getAttribute('data-senha')||'', expira: card?.getAttribute('data-expira')||'' };
    document.getElementById('suspender-login').textContent = _operacaoData.usuario;
    document.getElementById('suspender-senha').textContent = _operacaoData.senha;
    document.getElementById('suspender-expira').textContent = _operacaoData.expira;
    document.getElementById('btnConfirmarSuspensao').onclick = function() { fecharModal('modalConfirmarSuspensao'); executarSuspensao(id); };
    abrirModal('modalConfirmarSuspensao');
}

function executarSuspensao(id) {
    mostrarProcessando();
    $.ajax({
        url: 'suspender.php?id=' + id, type: 'GET',
        success: function(data) {
            esconderProcessando();
            data = data.replace(/(\r\n|\n|\r)/gm, '');
            if (data == 'suspenso com sucesso') { mostrarSucesso('⚠️ Usuário Suspenso!', 'Usuário ' + _operacaoData.usuario + ' foi suspenso com sucesso!'); }
            else if (data == 'erro no servidor') { mostrarErro('Erro no servidor! Verifique se está online.'); }
            else { mostrarErro('Erro ao suspender usuário!'); }
        },
        error: function() { esconderProcessando(); mostrarErro('Erro ao conectar com o servidor!'); }
    });
}

// ==================== REATIVAR ====================
function reativar(id) {
    const card = getCard(id);
    _operacaoData = { id: id, usuario: card?.getAttribute('data-usuario')||'', senha: card?.getAttribute('data-senha')||'', expira: card?.getAttribute('data-expira')||'' };
    document.getElementById('reativar-login').textContent = _operacaoData.usuario;
    document.getElementById('reativar-senha').textContent = _operacaoData.senha;
    document.getElementById('reativar-expira').textContent = _operacaoData.expira;
    document.getElementById('btnConfirmarReativacao').onclick = function() { fecharModal('modalConfirmarReativacao'); executarReativacao(id); };
    abrirModal('modalConfirmarReativacao');
}

function executarReativacao(id) {
    mostrarProcessando();
    $.ajax({
        url: 'reativar.php?id=' + id, type: 'GET',
        success: function(data) {
            esconderProcessando();
            data = data.replace(/(\r\n|\n|\r)/gm, '');
            if (data == 'reativado com sucesso') { mostrarSucesso('✅ Usuário Reativado!', 'Usuário ' + _operacaoData.usuario + ' foi reativado com sucesso!'); }
            else { mostrarErro('Erro ao reativar usuário!'); }
        },
        error: function() { esconderProcessando(); mostrarErro('Erro ao conectar com o servidor!'); }
    });
}

// ==================== EXCLUIR ====================
function excluir(id) {
    const card = getCard(id);
    _operacaoData = { id: id, usuario: card?.getAttribute('data-usuario')||'', senha: card?.getAttribute('data-senha')||'', expira: card?.getAttribute('data-expira')||'' };
    document.getElementById('excluir-login').textContent = _operacaoData.usuario;
    document.getElementById('excluir-senha').textContent = _operacaoData.senha;
    document.getElementById('excluir-expira').textContent = _operacaoData.expira;
    document.getElementById('btnConfirmarExclusao').onclick = function() { fecharModal('modalConfirmarExclusao'); executarExclusao(id); };
    abrirModal('modalConfirmarExclusao');
}

function executarExclusao(id) {
    mostrarProcessando();
    $.ajax({
        url: 'excluiruser.php?id=' + id, type: 'GET',
        success: function(data) {
            esconderProcessando();
            data = data.replace(/(\r\n|\n|\r)/gm, '');
            if (data == 'excluido') { mostrarSucesso('🗑️ Usuário Excluído!', 'Usuário ' + _operacaoData.usuario + ' foi excluído permanentemente!'); }
            else { mostrarErro('Erro ao excluir usuário!'); }
        },
        error: function() { esconderProcessando(); mostrarErro('Erro ao conectar com o servidor!'); }
    });
}

// ==================== MODAL EVENTS ====================
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        var mid = e.target.id;
        if (mid === 'modalSucessoRenovacao') fecharModalSucesso();
        else if (mid === 'modalSucessoOperacao') fecharModalSucessoOperacao();
        else e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('modalSucessoRenovacao').classList.contains('show')) fecharModalSucesso();
        else if (document.getElementById('modalSucessoOperacao').classList.contains('show')) fecharModalSucessoOperacao();
        else fecharTodosModais();
    }
});
</script>

</div></div></body></html>
