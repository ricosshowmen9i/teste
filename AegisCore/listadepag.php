<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

$revendedor_id = $_SESSION['iduser'];

// ========== PROCESSAR EXCLUSÃO DE PAGAMENTO ==========
if (isset($_POST['excluir_pagamento'])) {
    $pagamento_id = intval($_POST['pagamento_id']);
    $tabela = $_POST['tabela'];
    $tipo_pagamento = isset($_POST['tipo_pagamento']) ? $_POST['tipo_pagamento'] : '';
    
    if ($tabela == 'pagamentos_renovacao') {
        $sql = "DELETE FROM pagamentos_renovacao WHERE id = '$pagamento_id' AND revendedor_id = '$revendedor_id'";
    } elseif ($tabela == 'pagamentos') {
        $sql = "DELETE FROM pagamentos WHERE id = '$pagamento_id' AND byid = '$revendedor_id'";
    } elseif ($tabela == 'pagamentos_revenda') {
        $sql = "DELETE FROM pagamentos_revenda WHERE id = '$pagamento_id' AND revendedor_id = '$revendedor_id'";
    } elseif ($tabela == 'pagamentos_unificado') {
        $sql = "DELETE FROM pagamentos_unificado WHERE id = '$pagamento_id' AND revendedor_id = '$revendedor_id'";
    } else {
        $sql = "DELETE FROM pagamentos WHERE id = '$pagamento_id' AND byid = '$revendedor_id'";
    }
    
    if (mysqli_query($conn, $sql)) {
        $excluido_com_sucesso = true;
    } else {
        $erro_exclusao = mysqli_error($conn);
    }
}

// ========== INICIALIZAR ARRAYS ==========
$pagamentos_pendentes = array();
$pagamentos_aprovados = array();

// ========== 1. TABELA pagamentos_renovacao ==========
$sql1 = "SELECT 
            pr.id,
            pr.payment_id,
            pr.user_id,
            pr.login,
            pr.valor,
            pr.status,
            pr.data_pagamento,
            pr.revendedor_id,
            'pagamentos_renovacao' as tabela_origem,
            'renovacao_usuario' as tipo_pagamento
        FROM pagamentos_renovacao pr
        WHERE pr.revendedor_id = '$revendedor_id'
        ORDER BY pr.data_pagamento DESC";

$result1 = mysqli_query($conn, $sql1);
if ($result1 && mysqli_num_rows($result1) > 0) {
    while ($row = mysqli_fetch_assoc($result1)) {
        $row['data']          = isset($row['data_pagamento']) ? $row['data_pagamento'] : date('Y-m-d H:i:s');
        $row['idpagamento']   = isset($row['payment_id'])     ? $row['payment_id']     : $row['id'];
        $row['login_cliente'] = isset($row['login'])          ? $row['login']          : 'N/A';
        $row['texto']         = 'Renovacao de Plano - Usuario: ' . $row['login_cliente'];
        $row['tipo_descricao']= 'Renovacao de Usuario';
        $row['valor']         = isset($row['valor'])          ? $row['valor']          : 0;

        $status = strtolower(isset($row['status']) ? $row['status'] : 'pending');
        if ($status == 'approved' || $status == 'aprovado' || $status == 'success') {
            $pagamentos_aprovados[] = $row;
        } else {
            $pagamentos_pendentes[] = $row;
        }
    }
}

// ========== 2. TABELA pagamentos ==========
$sql2 = "SELECT 
            p.id,
            p.payment_id,
            p.iduser,
            p.valor,
            p.status,
            p.data_criacao,
            p.byid,
            p.plano_nome,
            p.origem,
            u.login as login_usuario,
            'pagamentos' as tabela_origem,
            'compra_plano' as tipo_pagamento
        FROM pagamentos p
        LEFT JOIN ssh_accounts u ON u.id = p.iduser
        WHERE p.byid = '$revendedor_id'
        ORDER BY p.data_criacao DESC";

$result2 = mysqli_query($conn, $sql2);
if ($result2 && mysqli_num_rows($result2) > 0) {
    while ($row = mysqli_fetch_assoc($result2)) {
        $row['data']          = isset($row['data_criacao'])   ? $row['data_criacao']   : date('Y-m-d H:i:s');
        $row['idpagamento']   = isset($row['payment_id'])     ? $row['payment_id']     : $row['id'];
        $row['login_cliente'] = isset($row['login_usuario'])  ? $row['login_usuario']  : 'N/A';
        $row['texto']         = 'Compra de Plano - ' . (isset($row['plano_nome']) ? $row['plano_nome'] : 'Plano de Usuario');
        $row['tipo_descricao']= 'Compra de Plano';
        $row['valor']         = isset($row['valor'])          ? $row['valor']          : 0;

        $status = strtolower(isset($row['status']) ? $row['status'] : 'pending');
        if ($status == 'approved' || $status == 'aprovado' || $status == 'success') {
            $pagamentos_aprovados[] = $row;
        } else {
            $pagamentos_pendentes[] = $row;
        }
    }
}

// ========== 3. TABELA pagamentos_revenda ==========
$sql3 = "SELECT 
            pr.id,
            pr.payment_id,
            pr.user_id,
            pr.login,
            pr.valor,
            pr.status,
            pr.data_pagamento,
            pr.revendedor_id,
            pr.plano_id,
            pr.limite_creditos,
            pr.duracao_dias,
            'pagamentos_revenda' as tabela_origem,
            'compra_revenda' as tipo_pagamento
        FROM pagamentos_revenda pr
        WHERE pr.revendedor_id = '$revendedor_id'
        ORDER BY pr.data_pagamento DESC";

$result3 = mysqli_query($conn, $sql3);
if ($result3 && mysqli_num_rows($result3) > 0) {
    while ($row = mysqli_fetch_assoc($result3)) {
        $row['data']          = isset($row['data_pagamento'])  ? $row['data_pagamento']  : date('Y-m-d H:i:s');
        $row['idpagamento']   = isset($row['payment_id'])      ? $row['payment_id']      : $row['id'];
        $row['login_cliente'] = isset($row['login'])           ? $row['login']           : 'N/A';
        $row['texto']         = 'Compra de Plano de Revenda - ' . (isset($row['limite_creditos']) ? $row['limite_creditos'] : '0') . ' creditos - ' . (isset($row['duracao_dias']) ? $row['duracao_dias'] : '0') . ' dias';
        $row['tipo_descricao']= 'Compra de Plano de Revenda';
        $row['valor']         = isset($row['valor'])           ? $row['valor']           : 0;

        $status = strtolower(isset($row['status']) ? $row['status'] : 'pending');
        if ($status == 'approved' || $status == 'aprovado' || $status == 'success') {
            $pagamentos_aprovados[] = $row;
        } else {
            $pagamentos_pendentes[] = $row;
        }
    }
}

// ========== 4. TABELA pagamentos_unificado ==========
$sql4 = "SELECT 
            pu.id,
            pu.payment_id,
            pu.user_id,
            pu.login,
            pu.valor,
            pu.status,
            pu.data_criacao,
            pu.data_pagamento,
            pu.revendedor_id,
            pu.plano_id,
            pu.limite_creditos,
            pu.duracao_dias,
            pu.descricao,
            pu.tipo,
            'pagamentos_unificado' as tabela_origem,
            pu.tipo as tipo_pagamento
        FROM pagamentos_unificado pu
        WHERE pu.revendedor_id = '$revendedor_id'
        ORDER BY pu.data_criacao DESC";

$result4 = mysqli_query($conn, $sql4);
if ($result4 && mysqli_num_rows($result4) > 0) {
    while ($row = mysqli_fetch_assoc($result4)) {
        $payment_id_check = isset($row['payment_id']) ? $row['payment_id'] : '';
        $ja_existe = false;
        foreach ($pagamentos_pendentes as $p) {
            if (isset($p['idpagamento']) && $p['idpagamento'] == $payment_id_check) {
                $ja_existe = true;
                break;
            }
        }
        if (!$ja_existe) {
            foreach ($pagamentos_aprovados as $p) {
                if (isset($p['idpagamento']) && $p['idpagamento'] == $payment_id_check) {
                    $ja_existe = true;
                    break;
                }
            }
        }
        if ($ja_existe) continue;

        $tipo_pag = isset($row['tipo']) ? $row['tipo'] : 'compra_plano_usuario';
        $descricao = isset($row['descricao']) ? $row['descricao'] : 'Pagamento';

        if ($tipo_pag == 'compra_plano_usuario') {
            $texto = 'Compra de Plano - ' . $descricao;
            $tipo_desc = 'Compra de Plano (Usuario)';
        } elseif ($tipo_pag == 'compra_revenda' || $tipo_pag == 'compra_plano_revenda') {
            $texto = 'Compra de Plano de Revenda - ' . $descricao;
            $tipo_desc = 'Compra de Plano de Revenda';
        } elseif ($tipo_pag == 'renovacao_usuario') {
            $texto = 'Renovacao de Usuario - ' . $descricao;
            $tipo_desc = 'Renovacao de Usuario';
        } elseif ($tipo_pag == 'renovacao_revenda') {
            $texto = 'Renovacao de Revenda - ' . $descricao;
            $tipo_desc = 'Renovacao de Revenda';
        } else {
            $texto = $descricao;
            $tipo_desc = 'Pagamento';
        }

        $row['data']          = isset($row['data_criacao'])   ? $row['data_criacao']   : date('Y-m-d H:i:s');
        $row['idpagamento']   = isset($row['payment_id'])     ? $row['payment_id']     : $row['id'];
        $row['login_cliente'] = isset($row['login'])          ? $row['login']          : 'N/A';
        $row['texto']         = $texto;
        $row['tipo_descricao']= $tipo_desc;
        $row['tipo_pagamento']= $tipo_pag;
        $row['valor']         = isset($row['valor'])          ? $row['valor']          : 0;

        $status = strtolower(isset($row['status']) ? $row['status'] : 'pending');
        if ($status == 'approved' || $status == 'aprovado' || $status == 'success') {
            $pagamentos_aprovados[] = $row;
        } else {
            $pagamentos_pendentes[] = $row;
        }
    }
}

// ========== 5. TABELA renovar_plano ==========
$sql5 = "SELECT 
            p.id,
            p.payment_id,
            p.iduser,
            p.valor,
            p.status,
            p.data_criacao,
            p.byid,
            a.login as login_revendedor,
            'pagamentos' as tabela_origem,
            'renovacao_revenda' as tipo_pagamento
        FROM pagamentos p
        LEFT JOIN accounts a ON a.id = p.iduser
        WHERE p.byid = '$revendedor_id'
        AND p.origem = 'renovacao'
        AND p.tipo_conta = 'revenda'
        ORDER BY p.data_criacao DESC";

$result5 = mysqli_query($conn, $sql5);
if ($result5 && mysqli_num_rows($result5) > 0) {
    while ($row = mysqli_fetch_assoc($result5)) {
        $payment_id_check = isset($row['payment_id']) ? $row['payment_id'] : '';
        $ja_existe = false;
        foreach ($pagamentos_pendentes as $p) {
            if (isset($p['idpagamento']) && $p['idpagamento'] == $payment_id_check) {
                $ja_existe = true;
                break;
            }
        }
        if (!$ja_existe) {
            foreach ($pagamentos_aprovados as $p) {
                if (isset($p['idpagamento']) && $p['idpagamento'] == $payment_id_check) {
                    $ja_existe = true;
                    break;
                }
            }
        }
        if ($ja_existe) continue;

        $row['data']          = isset($row['data_criacao'])    ? $row['data_criacao']    : date('Y-m-d H:i:s');
        $row['idpagamento']   = isset($row['payment_id'])      ? $row['payment_id']      : $row['id'];
        $row['login_cliente'] = isset($row['login_revendedor'])? $row['login_revendedor']: 'N/A';
        $row['texto']         = 'Renovacao de Revenda - ' . $row['login_cliente'];
        $row['tipo_descricao']= 'Renovacao de Revenda';
        $row['valor']         = isset($row['valor'])           ? $row['valor']           : 0;

        $status = strtolower(isset($row['status']) ? $row['status'] : 'pending');
        if ($status == 'approved' || $status == 'aprovado' || $status == 'success') {
            $pagamentos_aprovados[] = $row;
        } else {
            $pagamentos_pendentes[] = $row;
        }
    }
}

$total_pendentes = count($pagamentos_pendentes);
$total_aprovados = count($pagamentos_aprovados);
$total_geral     = $total_pendentes + $total_aprovados;

$valor_total_aprovados = 0;
foreach ($pagamentos_aprovados as $p) {
    $valor_total_aprovados += floatval(isset($p['valor']) ? $p['valor'] : 0);
}
$valor_total_pendentes = 0;
foreach ($pagamentos_pendentes as $p) {
    $valor_total_pendentes += floatval(isset($p['valor']) ? $p['valor'] : 0);
}

// Merge all payments for unified view
$todos_pagamentos = array();
foreach ($pagamentos_pendentes as $p) { $p['_status_cat'] = 'pendente'; $todos_pagamentos[] = $p; }
foreach ($pagamentos_aprovados as $p) { $p['_status_cat'] = 'aprovado'; $todos_pagamentos[] = $p; }

// Count cancelled (status contains cancelled/cancelado/rejected)
$total_cancelados = 0;
foreach ($todos_pagamentos as $p) {
    $st = strtolower(isset($p['status']) ? $p['status'] : '');
    if ($st == 'cancelled' || $st == 'cancelado' || $st == 'rejected') $total_cancelados++;
}

// Pagination
$per_page = isset($_GET['pp']) ? intval($_GET['pp']) : 12;
if ($per_page < 1) $per_page = 12;
$total_items = count($todos_pagamentos);
$total_pages = max(1, ceil($total_items / $per_page));
$current_page = isset($_GET['pg']) ? max(1, min(intval($_GET['pg']), $total_pages)) : 1;
$offset = ($current_page - 1) * $per_page;
$pagamentos_pagina = array_slice($todos_pagamentos, $offset, $per_page);
?>

<style>
@media(max-width:768px){
    .items-grid{grid-template-columns:1fr!important;}
    .filter-group{flex-direction:column;}
}
</style>

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon blue"><i class='bx bx-credit-card'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Historico de Pagamentos</div>
        <div class="stats-card-value"><?php echo $total_geral; ?></div>
        <div class="stats-card-subtitle">pagamentos registrados &bull; Atualizado <?php echo date('d/m/Y H:i'); ?></div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-credit-card'></i></div>
</div>

<!-- Mini Stats -->
<div class="mini-stats">
    <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_aprovados; ?></div><div class="mini-stat-lbl">Aprovados</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_pendentes; ?></div><div class="mini-stat-lbl">Pendentes</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_cancelados; ?></div><div class="mini-stat-lbl">Cancelados</div></div>
    <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;">R$ <?php echo number_format($valor_total_aprovados, 2, ',', '.'); ?></div><div class="mini-stat-lbl">Total Recebido</div></div>
</div>

<!-- Filter Card -->
<div class="modern-card">
    <div class="card-header-custom primary">
        <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
        <div><div class="header-title">Filtros</div><div class="header-subtitle">Busque e filtre pagamentos</div></div>
    </div>
    <div class="card-body-custom">
        <div class="filter-group">
            <div class="filter-item" style="flex:2;">
                <div class="filter-label">Buscar</div>
                <input type="text" class="filter-input" id="searchInput" placeholder="Cliente, ID do pagamento...">
            </div>
            <div class="filter-item">
                <div class="filter-label">Status</div>
                <select class="filter-select" id="statusFilter">
                    <option value="todos">Todos</option>
                    <option value="pendente">Pendentes</option>
                    <option value="aprovado">Aprovados</option>
                </select>
            </div>
            <div class="filter-item">
                <div class="filter-label">Tipo</div>
                <select class="filter-select" id="tipoFilter">
                    <option value="todos">Todos os tipos</option>
                    <option value="renovacao_usuario">Renovacao de Usuario</option>
                    <option value="compra_plano">Compra de Plano</option>
                    <option value="compra_revenda">Compra de Revenda</option>
                    <option value="renovacao_revenda">Renovacao de Revenda</option>
                </select>
            </div>
            <div class="filter-item" style="flex:0 0 100px;">
                <div class="filter-label">Por Pagina</div>
                <select class="filter-select" id="perPageSelect" onchange="location.href='?pp='+this.value">
                    <?php foreach([12,24,48,96] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo $per_page==$pp?'selected':''; ?>><?php echo $pp; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Payments Grid -->
<div class="items-grid" id="paymentsGrid">
<?php if (count($pagamentos_pagina) > 0): ?>
    <?php foreach ($pagamentos_pagina as $pag):
        $tipo_pag  = isset($pag['tipo_pagamento']) ? $pag['tipo_pagamento'] : 'default';
        $login_cli = isset($pag['login_cliente'])  ? $pag['login_cliente']  : 'N/A';
        $valor_pag = isset($pag['valor'])          ? floatval($pag['valor']) : 0;
        $data_pag  = isset($pag['data'])           ? $pag['data']           : date('Y-m-d H:i:s');
        $id_pag    = isset($pag['idpagamento'])    ? $pag['idpagamento']    : '---';
        $texto_pag = isset($pag['texto'])          ? $pag['texto']          : 'Pagamento';
        $tipo_desc = isset($pag['tipo_descricao']) ? $pag['tipo_descricao'] : 'Pagamento';
        $db_id     = isset($pag['id'])             ? $pag['id']             : 0;
        $tabela    = isset($pag['tabela_origem'])  ? $pag['tabela_origem']  : 'pagamentos';
        $status_cat= isset($pag['_status_cat'])    ? $pag['_status_cat']    : 'pendente';

        if ($tipo_pag == 'renovacao_usuario')       $hdr_class = 'green';
        elseif ($tipo_pag == 'compra_plano')        $hdr_class = 'blue';
        elseif ($tipo_pag == 'compra_revenda')      $hdr_class = 'orange';
        elseif ($tipo_pag == 'renovacao_revenda')   $hdr_class = 'violet';
        else                                        $hdr_class = 'primary';

        if ($tipo_pag == 'renovacao_usuario')       $icone = 'bx-refresh';
        elseif ($tipo_pag == 'compra_plano')        $icone = 'bx-package';
        elseif ($tipo_pag == 'compra_revenda')      $icone = 'bx-store';
        elseif ($tipo_pag == 'renovacao_revenda')   $icone = 'bx-sync';
        else                                        $icone = 'bx-credit-card';

        if ($status_cat == 'aprovado') { $badge_class = 'badge-aprovado'; $badge_icon = 'bx-check-circle'; $badge_text = 'Aprovado'; }
        else { $badge_class = 'badge-pendente'; $badge_icon = 'bx-time'; $badge_text = 'Pendente'; }
    ?>
    <div class="item-card" data-login="<?php echo strtolower(htmlspecialchars($login_cli)); ?>" data-id="<?php echo strtolower(htmlspecialchars($id_pag)); ?>" data-tipo="<?php echo htmlspecialchars($tipo_pag); ?>" data-status="<?php echo $status_cat; ?>">
        <div class="card-header-custom <?php echo $hdr_class; ?>">
            <div class="header-icon"><i class='bx <?php echo $icone; ?>'></i></div>
            <div>
                <div class="header-title"><?php echo htmlspecialchars($tipo_desc); ?></div>
                <div class="header-subtitle"><?php echo htmlspecialchars($login_cli); ?></div>
            </div>
        </div>
        <div class="item-card-body">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-icon" style="color:#818cf8;"><i class='bx bx-hash'></i></div>
                    <div><div class="info-label">ID</div><div class="info-value info"><?php echo htmlspecialchars(substr($id_pag, -12)); ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon" style="color:#10b981;"><i class='bx bx-money'></i></div>
                    <div><div class="info-label">Valor</div><div class="info-value success">R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon" style="color:#fbbf24;"><i class='bx bx-calendar'></i></div>
                    <div><div class="info-label">Data</div><div class="info-value"><?php echo date('d/m/Y H:i', strtotime($data_pag)); ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon" style="color:#a78bfa;"><i class='bx bx-info-circle'></i></div>
                    <div><div class="info-label">Status</div><div class="info-value"><span class="badge-sm <?php echo $badge_class; ?>"><i class='bx <?php echo $badge_icon; ?>'></i> <?php echo $badge_text; ?></span></div></div>
                </div>
            </div>
            <div class="info-row" style="margin-top:4px;">
                <div class="info-icon" style="color:#f472b6;"><i class='bx bx-note'></i></div>
                <div><div class="info-label">Descricao</div><div class="info-value" style="white-space:normal;line-height:1.3;"><?php echo htmlspecialchars($texto_pag); ?></div></div>
            </div>
        </div>
        <div class="item-card-footer">
            <button class="action-btn btn-edit" onclick="verDetalhes('<?php echo htmlspecialchars(addslashes($login_cli)); ?>','<?php echo htmlspecialchars(addslashes(substr($id_pag,-12))); ?>','R$ <?php echo number_format($valor_pag,2,',','.'); ?>','<?php echo htmlspecialchars(addslashes($tipo_desc)); ?>','<?php echo date('d/m/Y H:i',strtotime($data_pag)); ?>','<?php echo $badge_text; ?>','<?php echo htmlspecialchars(addslashes($texto_pag)); ?>')"><i class='bx bx-show'></i> Detalhes</button>
            <button class="action-btn btn-danger" onclick="confirmarExclusao(<?php echo intval($db_id); ?>,'<?php echo addslashes($tabela); ?>','<?php echo addslashes($login_cli); ?>','<?php echo addslashes($tipo_pag); ?>','R$ <?php echo number_format($valor_pag,2,',','.'); ?>','<?php echo addslashes(substr($id_pag,-12)); ?>')"><i class='bx bx-trash'></i> Excluir</button>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <i class='bx bx-receipt'></i>
        <h3>Nenhum pagamento encontrado</h3>
        <p>Os pagamentos realizados aparecerao aqui</p>
    </div>
<?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination-wrapper">
    <div class="pagination">
        <?php if ($current_page > 1): ?>
        <a href="?pg=<?php echo $current_page-1; ?>&pp=<?php echo $per_page; ?>"><i class='bx bx-chevron-left'></i></a>
        <?php endif; ?>
        <?php
        $start_pg = max(1, $current_page - 2);
        $end_pg = min($total_pages, $current_page + 2);
        if ($start_pg > 1) echo '<a href="?pg=1&pp='.$per_page.'">1</a>';
        if ($start_pg > 2) echo '<span>...</span>';
        for ($i = $start_pg; $i <= $end_pg; $i++):
        ?>
        <?php if ($i == $current_page): ?>
            <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?pg=<?php echo $i; ?>&pp=<?php echo $per_page; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
        <?php endfor; ?>
        <?php if ($end_pg < $total_pages - 1) echo '<span>...</span>'; ?>
        <?php if ($end_pg < $total_pages) echo '<a href="?pg='.$total_pages.'&pp='.$per_page.'">'.$total_pages.'</a>'; ?>
        <?php if ($current_page < $total_pages): ?>
        <a href="?pg=<?php echo $current_page+1; ?>&pp=<?php echo $per_page; ?>"><i class='bx bx-chevron-right'></i></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modal Detalhes -->
<div id="modalDetalhes" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom info">
                <h5 style="margin:0;display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:white;"><i class='bx bx-detail'></i> Detalhes do Pagamento</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalDetalhes')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-ic info"><i class='bx bx-credit-card'></i></div>
                <div style="background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Cliente</div><div class="modal-info-value" id="det-cliente">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-hash' style="color:#c084fc;"></i> ID</div><div class="modal-info-value" id="det-id">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-money' style="color:#10b981;"></i> Valor</div><div class="modal-info-value" id="det-valor">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-tag' style="color:#f97316;"></i> Tipo</div><div class="modal-info-value" id="det-tipo">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Data</div><div class="modal-info-value" id="det-data">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-check-circle' style="color:#34d399;"></i> Status</div><div class="modal-info-value" id="det-status">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-note' style="color:#f472b6;"></i> Descricao</div><div class="modal-info-value" id="det-desc" style="white-space:normal;">-</div></div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-primary" onclick="fecharModal('modalDetalhes')"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusao -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom danger">
                <h5 style="margin:0;display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:white;"><i class='bx bx-trash'></i> Confirmar Exclusao</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-ic error"><i class='bx bx-trash'></i></div>
                <div style="background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);">
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Cliente</div><div class="modal-info-value credential" id="excluir-cliente">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-hash' style="color:#c084fc;"></i> ID</div><div class="modal-info-value" id="excluir-id">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-money' style="color:#10b981;"></i> Valor</div><div class="modal-info-value" id="excluir-valor">-</div></div>
                    <div class="modal-info-row"><div class="modal-info-label"><i class='bx bx-tag' style="color:#f97316;"></i> Tipo</div><div class="modal-info-value" id="excluir-tipo">-</div></div>
                </div>
                <p style="text-align:center;color:rgba(220,38,38,0.8);font-size:11px;margin-top:12px;">Esta acao nao pode ser desfeita!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i> Cancelar</button>
                <button type="button" class="btn-modal btn-modal-danger" id="btnConfirmarExclusao"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom success">
                <h5 style="margin:0;display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:white;"><i class='bx bx-check-circle'></i> Excluido com Sucesso!</h5>
                <button type="button" class="modal-close" onclick="fecharModalSucesso()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:8px;font-size:16px;">Pagamento Removido!</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;font-size:12px;" id="sucesso-mensagem">O pagamento foi removido com sucesso.</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-ok" onclick="fecharModalSucesso()"><i class='bx bx-check'></i> OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom danger">
                <h5 style="margin:0;display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:white;"><i class='bx bx-error-circle'></i> Erro!</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:8px;font-size:16px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;font-size:12px;" id="erro-mensagem">Erro ao processar solicitacao!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
var pagamentoIdParaExcluir=null, tabelaParaExcluir=null, tipoPagamentoParaExcluir=null;

function verDetalhes(cliente,id,valor,tipo,data,status,desc){
    document.getElementById('det-cliente').textContent=cliente;
    document.getElementById('det-id').textContent=id;
    document.getElementById('det-valor').textContent=valor;
    document.getElementById('det-tipo').textContent=tipo;
    document.getElementById('det-data').textContent=data;
    document.getElementById('det-status').textContent=status;
    document.getElementById('det-desc').textContent=desc;
    document.getElementById('modalDetalhes').classList.add('show');
}

function confirmarExclusao(id,tabela,clienteNome,tipoPagamento,valor,idPag){
    pagamentoIdParaExcluir=id;
    tabelaParaExcluir=tabela;
    tipoPagamentoParaExcluir=tipoPagamento;
    document.getElementById('excluir-cliente').textContent=clienteNome;
    document.getElementById('excluir-id').textContent=idPag;
    document.getElementById('excluir-valor').textContent=valor;
    var tipoDisplay='';
    if(tipoPagamento==='renovacao_usuario') tipoDisplay='Renovacao de Usuario';
    else if(tipoPagamento==='compra_plano') tipoDisplay='Compra de Plano';
    else if(tipoPagamento==='compra_revenda') tipoDisplay='Compra de Revenda';
    else if(tipoPagamento==='renovacao_revenda') tipoDisplay='Renovacao de Revenda';
    else tipoDisplay='Pagamento';
    document.getElementById('excluir-tipo').textContent=tipoDisplay;
    document.getElementById('modalConfirmarExclusao').classList.add('show');
}

function fecharModal(id){ document.getElementById(id).classList.remove('show'); }
function fecharModalSucesso(){ fecharModal('modalSucesso'); location.reload(); }

document.getElementById('btnConfirmarExclusao').onclick=function(){
    if(!pagamentoIdParaExcluir||!tabelaParaExcluir) return;
    fecharModal('modalConfirmarExclusao');
    $.ajax({
        url:window.location.href, type:'POST',
        data:{ excluir_pagamento:1, pagamento_id:pagamentoIdParaExcluir, tabela:tabelaParaExcluir, tipo_pagamento:tipoPagamentoParaExcluir||'' },
        success:function(){ document.getElementById('sucesso-mensagem').textContent='Pagamento removido com sucesso!'; document.getElementById('modalSucesso').classList.add('show'); },
        error:function(){ document.getElementById('erro-mensagem').textContent='Erro ao conectar com o servidor!'; document.getElementById('modalErro').classList.add('show'); }
    });
};

// Search filter
document.getElementById('searchInput').addEventListener('keyup',function(){
    var s=this.value.toLowerCase();
    document.querySelectorAll('.item-card').forEach(function(c){
        var l=c.getAttribute('data-login')||'', id=c.getAttribute('data-id')||'';
        c.style.display=(l.indexOf(s)>=0||id.indexOf(s)>=0)?'':'none';
    });
});

// Status filter
document.getElementById('statusFilter').addEventListener('change',function(){
    var v=this.value;
    document.querySelectorAll('.item-card').forEach(function(c){
        if(v==='todos') c.style.display='';
        else c.style.display=(c.getAttribute('data-status')===v)?'':'none';
    });
});

// Type filter
document.getElementById('tipoFilter').addEventListener('change',function(){
    var v=this.value;
    document.querySelectorAll('.item-card').forEach(function(c){
        if(v==='todos') c.style.display='';
        else c.style.display=(c.getAttribute('data-tipo')===v)?'':'none';
    });
});

// Modal close on overlay/escape
document.addEventListener('click',function(e){
    if(e.target.classList.contains('modal-overlay')){
        if(e.target.id==='modalSucesso') fecharModalSucesso();
        else e.target.classList.remove('show');
    }
});
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        if(document.getElementById('modalSucesso').classList.contains('show')) fecharModalSucesso();
        else document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
    }
});

<?php if (isset($excluido_com_sucesso) && $excluido_com_sucesso): ?>
document.addEventListener('DOMContentLoaded',function(){ document.getElementById('modalSucesso').classList.add('show'); });
<?php endif; ?>

<?php if (isset($erro_exclusao)): ?>
document.addEventListener('DOMContentLoaded',function(){ document.getElementById('erro-mensagem').textContent='<?php echo addslashes($erro_exclusao); ?>'; document.getElementById('modalErro').classList.add('show'); });
<?php endif; ?>
</script>

</div></div></body></html>
