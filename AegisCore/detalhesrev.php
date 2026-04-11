<?php
error_reporting(0);
session_start();

date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

$sql = "SELECT * FROM accounts WHERE login = '".$_SESSION['login']."' AND senha = '".$_SESSION['senha']."'";
$result = $conn->query($sql);
if ($result->num_rows > 0){
    while ($row = $result->fetch_assoc()){
        $iduser = $row['id'];
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
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "<script>alert('ID inválido!');history.back();</script>"; exit; }

// ========== DADOS DO REVENDEDOR ==========
$sql_rev = "SELECT * FROM accounts WHERE id = '$id'";
$result_rev = $conn->query($sql_rev);
if (!$result_rev || $result_rev->num_rows == 0) { echo "<script>alert('Revendedor não encontrado!');history.back();</script>"; exit; }
$rev = $result_rev->fetch_assoc();
$login = $rev['login'];

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
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
@keyframes fadeTab{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
</style>

<!-- ========== STATS CARD ========== -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-store-alt'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Detalhes do Revendedor</div>
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
    <a href="editarrev.php?id=<?php echo $id; ?>" class="btn btn-primary"><i class='bx bx-edit'></i> Editar</a>
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
    <div class="card-body-custom">
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
    <div class="card-body-custom">
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

<script>
new ApexCharts(document.querySelector("#chartLimite"),{
    series:[<?php echo $limite_usado_users; ?>,<?php echo $limite_usado_revs; ?>,<?php echo max(0,$limite_restante); ?>],
    chart:{type:'donut',height:170,background:'transparent'},
    labels:['Usuários','Revendedores','Disponível'],
    colors:['#4158D0','#7c3aed','#10b981'],
    dataLabels:{enabled:false},legend:{show:false},
    plotOptions:{pie:{donut:{size:'65%',labels:{show:true,total:{show:true,label:'Usado',color:'#fff',formatter:function(){return '<?php echo $pct_uso; ?>%';}}}}}},
    stroke:{width:0},theme:{mode:'dark'}
}).render();

function trocarTab(id,btn){
    document.querySelectorAll('.tab-content').forEach(function(t){t.classList.remove('active');});
    document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

function filtrarTabela(tabelaId,busca){
    busca=busca.toLowerCase();
    document.querySelectorAll('#'+tabelaId+' tbody tr').forEach(function(row){
        row.style.display=row.textContent.toLowerCase().includes(busca)?'':'none';
    });
}
</script>

</div></div></body></html>
