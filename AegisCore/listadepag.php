<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include('conexao.php');
include('header2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

$revendedor_id = $_SESSION['iduser'];

// ========== PROCESSAR EXCLUSÃƒO DE PAGAMENTO ==========
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

// ========== 1. TABELA pagamentos_renovacao (renovaÃ§Ãµes de usuÃ¡rios SSH) ==========
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

// ========== 2. TABELA pagamentos (compras de planos de usuarios SSH) ==========
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

// ========== 3. TABELA pagamentos_revenda (compras de planos de revenda) ==========
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

// ========== 4. TABELA pagamentos_unificado (tabela unificada do pagamento_plano v2) ==========
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
        // Evitar duplicatas com pagamentos ja listados acima
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

// ========== 5. TABELA renovar_plano (renovacoes de revendedores) ==========
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
        // Evitar duplicatas
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

// Calcular totais em R$
$valor_total_aprovados = 0;
foreach ($pagamentos_aprovados as $p) {
    $valor_total_aprovados += floatval(isset($p['valor']) ? $p['valor'] : 0);
}
$valor_total_pendentes = 0;
foreach ($pagamentos_pendentes as $p) {
    $valor_total_pendentes += floatval(isset($p['valor']) ? $p['valor'] : 0);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Pagamentos</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Pagamentos</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0;
            --secondary: #C850C0;
            --tertiary: #FFCC70;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #0f172a, #1e1b4b); }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 1650px; margin: 0 auto 0 5px !important; padding: 0 !important; }

        .info-badge {
            display: inline-flex !important; align-items: center !important; gap: 8px !important;
            background: white !important; color: var(--dark) !important;
            padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
            margin-top: 5px !important; margin-bottom: 15px !important;
            border-left: 4px solid var(--primary) !important;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
        }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 12px; padding: 10px 15px; margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 8px; color: white;
        }
        .status-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; }
        .status-item i { font-size: 20px; color: var(--tertiary); }

        .resumo-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .resumo-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px;
            padding: 14px 16px;
            border: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .resumo-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 22px;
        }
        .resumo-icon.green  { background: rgba(16,185,129,0.15); color: #10b981; }
        .resumo-icon.yellow { background: rgba(245,158,11,0.15);  color: #f59e0b; }
        .resumo-icon.blue   { background: rgba(59,130,246,0.15);  color: #3b82f6; }
        .resumo-icon.purple { background: rgba(139,92,246,0.15);  color: #8b5cf6; }
        .resumo-label { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; }
        .resumo-value { font-size: 18px; font-weight: 700; color: white; }

        .filters-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 14px; padding: 14px; margin-bottom: 16px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .filters-title { font-size: 14px; font-weight: 700; color: white; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .filters-title i { color: var(--tertiary); font-size: 16px; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .filter-item { flex: 1 1 200px; min-width: 160px; }
        .filter-label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.5); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-input, .filter-select {
            width: 100%; padding: 7px 12px;
            background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 10px; font-size: 13px; color: white; transition: all 0.3s;
        }
        .filter-input:focus, .filter-select:focus { outline: none; border-color: rgba(65,88,208,0.6); }
        .filter-input::placeholder { color: rgba(255,255,255,0.3); }
        .filter-select { cursor: pointer; appearance: none; }
        .filter-select option { background: #1e293b; color: white; }

        .tabs-pagamentos {
            display: flex; gap: 8px; margin-bottom: 20px;
            background: rgba(255,255,255,0.05); padding: 6px;
            border-radius: 50px; width: fit-content;
        }
        .tab-pagamento {
            padding: 8px 24px; border: none; background: transparent;
            color: rgba(255,255,255,0.6); font-weight: 600; font-size: 13px;
            border-radius: 40px; cursor: pointer; transition: all 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        .tab-pagamento i { font-size: 16px; }
        .tab-pagamento.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; box-shadow: 0 4px 12px rgba(65,88,208,0.3); }
        .tab-pagamento:hover:not(.active) { background: rgba(255,255,255,0.1); color: white; }
        .tab-content-pagamento { display: none; }
        .tab-content-pagamento.active { display: block; }

        .pagamentos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 16px; margin-top: 14px; width: 100%;
        }

        .pagamento-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.08);
            animation: fadeIn 0.4s ease;
            position: relative;
        }
        .pagamento-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-header-custom {
            color: white; padding: 14px 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header-custom.tipo-renovacao-usuario  { background: linear-gradient(135deg, #10b981, #059669); }
        .card-header-custom.tipo-compra-plano        { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .card-header-custom.tipo-compra-revenda      { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .card-header-custom.tipo-renovacao-revenda   { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .card-header-custom.tipo-default             { background: linear-gradient(135deg, #C850C0, #4158D0); }

        .header-icon {
            width: 40px; height: 40px; background: rgba(255,255,255,0.2);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white;
        }
        .header-title { font-size: 15px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 2px; }

        .card-body-custom { padding: 16px; }

        .info-row {
            display: flex; align-items: center; padding: 8px 12px;
            background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s;
        }
        .info-row:hover { border-color: var(--primary); background: rgba(255,255,255,0.05); }
        .info-icon {
            width: 32px; height: 32px; background: rgba(255,255,255,0.03);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            margin-right: 10px; font-size: 16px; border: 1px solid rgba(255,255,255,0.05); flex-shrink: 0;
        }
        .info-content { flex: 1; min-width: 0; }
        .info-label { font-size: 9px; color: rgba(255,255,255,0.4); font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 2px; }
        .info-value { font-size: 13px; font-weight: 600; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .icon-user     { color: #818cf8; }
        .icon-money    { color: #10b981; }
        .icon-hash     { color: #c084fc; }
        .icon-calendar { color: #fbbf24; }
        .icon-note     { color: #a78bfa; }
        .icon-revenda  { color: #f97316; }
        .icon-plano    { color: #06b6d4; }

        .badge-custom { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-success { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .badge-warning { background: rgba(245,158,11,0.15);  color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
        .grid-2 .info-row { margin-bottom: 0; }

        .card-actions {
            display: flex; gap: 8px; margin-top: 12px;
            padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.08);
        }
        .btn-excluir-card {
            flex: 1; background: rgba(220,38,38,0.15); border: 1px solid rgba(220,38,38,0.3);
            padding: 8px 12px; border-radius: 30px; color: #f87171; font-weight: 600;
            font-size: 11px; cursor: pointer; display: flex; align-items: center;
            justify-content: center; gap: 6px; transition: all 0.3s;
        }
        .btn-excluir-card:hover { background: rgba(220,38,38,0.3); transform: translateY(-2px); }

        .empty-state {
            grid-column: 1/-1; text-align: center; padding: 50px 20px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px; border: 1px solid rgba(255,255,255,0.08); color: white;
        }
        .empty-state i { font-size: 60px; color: rgba(255,255,255,0.2); margin-bottom: 15px; }
        .empty-state h3 { color: white; font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: rgba(255,255,255,0.3); font-size: 14px; }

        /* Modal */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: none;
            align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalFadeIn 0.4s cubic-bezier(0.34,1.2,0.64,1); max-width: 500px; width: 90%; }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9) translateY(-30px); }
            to   { opacity: 1; transform: scale(1)   translateY(0); }
        }
        .modal-content-custom {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .modal-header-custom {
            color: white; padding: 20px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom h5 { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; }
        .modal-close:hover { opacity: 1; }
        .modal-body-custom { padding: 24px; color: white; }
        .modal-footer-custom { border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .modal-icon { text-align: center; margin-bottom: 20px; }
        .modal-icon i { font-size: 70px; }
        .modal-icon.success i { color: #10b981; filter: drop-shadow(0 0 15px rgba(16,185,129,0.5)); }
        .modal-icon.danger  i { color: #dc2626; filter: drop-shadow(0 0 15px rgba(220,38,38,0.5)); }
        .modal-info-card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 16px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.08); }
        .modal-info-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .modal-info-row:last-child { border-bottom: none; }
        .modal-info-label { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 8px; }
        .modal-info-label i { font-size: 18px; }
        .modal-info-value { font-size: 13px; font-weight: 700; color: white; }
        .modal-info-value.credential { background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 8px; font-family: monospace; }
        .btn-modal { padding: 9px 20px; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; box-shadow: 0 3px 8px rgba(0,0,0,0.2); color: white; }
        .btn-modal-cancel  { background: linear-gradient(135deg, #64748b, #475569); }
        .btn-modal-danger  { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-modal-ok      { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal:hover   { transform: translateY(-2px); }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { margin: 0 auto !important; padding: 10px !important; }
            .pagamentos-grid { grid-template-columns: 1fr; gap: 12px; }
            .tabs-pagamentos { width: 100%; justify-content: center; }
            .tab-pagamento { padding: 6px 16px !important; font-size: 11px !important; }
            .grid-2 { grid-template-columns: 1fr; }
            .modal-container { width: 95%; }
            .modal-info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .modal-footer-custom { flex-direction: column; }
            .btn-modal { width: 100%; justify-content: center; }
            .resumo-cards { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-credit-card'></i>
            <span>Historico de Pagamentos</span>
        </div>

      

        <!-- Cards de resumo -->
        <div class="resumo-cards">
            <div class="resumo-card">
                <div class="resumo-icon green"><i class='bx bx-check-circle'></i></div>
                <div>
                    <div class="resumo-label">Aprovados</div>
                    <div class="resumo-value"><?php echo $total_aprovados; ?></div>
                    <div style="font-size:11px; color:#10b981;">R$ <?php echo number_format($valor_total_aprovados, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-icon yellow"><i class='bx bx-time'></i></div>
                <div>
                    <div class="resumo-label">Pendentes</div>
                    <div class="resumo-value"><?php echo $total_pendentes; ?></div>
                    <div style="font-size:11px; color:#f59e0b;">R$ <?php echo number_format($valor_total_pendentes, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-icon blue"><i class='bx bx-list-ul'></i></div>
                <div>
                    <div class="resumo-label">Total de Pedidos</div>
                    <div class="resumo-value"><?php echo $total_geral; ?></div>
                    <div style="font-size:11px; color:#3b82f6;">R$ <?php echo number_format($valor_total_aprovados + $valor_total_pendentes, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-icon purple"><i class='bx bx-calendar'></i></div>
                <div>
                    <div class="resumo-label">Ultima Atualizacao</div>
                    <div class="resumo-value" style="font-size:14px;"><?php echo date('d/m/Y'); ?></div>
                    <div style="font-size:11px; color:#8b5cf6;"><?php echo date('H:i:s'); ?></div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <div class="filters-title"><i class='bx bx-filter-alt'></i> Filtros</div>
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">BUSCAR POR CLIENTE / ID</div>
                    <input type="text" class="filter-input" id="searchInput" placeholder="Digite para buscar...">
                </div>
                <div class="filter-item">
                    <div class="filter-label">FILTRAR POR TIPO</div>
                    <select class="filter-select" id="tipoFilter">
                        <option value="todos">Todos os tipos</option>
                        <option value="renovacao_usuario">Renovacao de Usuario</option>
                        <option value="compra_plano">Compra de Plano</option>
                        <option value="compra_revenda">Compra de Revenda</option>
                        <option value="renovacao_revenda">Renovacao de Revenda</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Abas -->
        <div class="tabs-pagamentos">
            <button class="tab-pagamento active" onclick="mudarTab('pendentes')">
                <i class='bx bx-time'></i> Pendentes
                <?php if ($total_pendentes > 0): ?>
                <span style="background:#f59e0b; color:white; padding:2px 6px; border-radius:20px; font-size:10px;"><?php echo $total_pendentes; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-pagamento" onclick="mudarTab('aprovados')">
                <i class='bx bx-check-circle'></i> Aprovados
                <?php if ($total_aprovados > 0): ?>
                <span style="background:#10b981; color:white; padding:2px 6px; border-radius:20px; font-size:10px;"><?php echo $total_aprovados; ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Aba Pendentes -->
        <div id="tab-pendentes" class="tab-content-pagamento active">
            <div class="pagamentos-grid" id="grid-pendentes">
                <?php if ($total_pendentes > 0): ?>
                    <?php foreach ($pagamentos_pendentes as $pag): ?>
                    <?php
                        $tipo_pag  = isset($pag['tipo_pagamento']) ? $pag['tipo_pagamento'] : 'default';
                        $login_cli = isset($pag['login_cliente'])  ? $pag['login_cliente']  : 'N/A';
                        $valor_pag = isset($pag['valor'])          ? floatval($pag['valor']) : 0;
                        $data_pag  = isset($pag['data'])           ? $pag['data']           : date('Y-m-d H:i:s');
                        $id_pag    = isset($pag['idpagamento'])    ? $pag['idpagamento']    : '---';
                        $texto_pag = isset($pag['texto'])          ? $pag['texto']          : 'Pagamento pendente';
                        $tipo_desc = isset($pag['tipo_descricao']) ? $pag['tipo_descricao'] : 'Pagamento';
                        $db_id     = isset($pag['id'])             ? $pag['id']             : 0;
                        $tabela    = isset($pag['tabela_origem'])  ? $pag['tabela_origem']  : 'pagamentos';

                        if ($tipo_pag == 'renovacao_usuario')  $header_class = 'tipo-renovacao-usuario';
                        elseif ($tipo_pag == 'compra_plano')   $header_class = 'tipo-compra-plano';
                        elseif ($tipo_pag == 'compra_revenda') $header_class = 'tipo-compra-revenda';
                        elseif ($tipo_pag == 'renovacao_revenda') $header_class = 'tipo-renovacao-revenda';
                        else $header_class = 'tipo-default';

                        if ($tipo_pag == 'renovacao_usuario')  $icone = 'bx-refresh';
                        elseif ($tipo_pag == 'compra_plano')   $icone = 'bx-package';
                        elseif ($tipo_pag == 'compra_revenda') $icone = 'bx-store';
                        elseif ($tipo_pag == 'renovacao_revenda') $icone = 'bx-sync';
                        else $icone = 'bx-credit-card';
                    ?>
                    <div class="pagamento-card"
                         data-login="<?php echo strtolower(htmlspecialchars($login_cli)); ?>"
                         data-id="<?php echo strtolower(htmlspecialchars($id_pag)); ?>"
                         data-tipo="<?php echo $tipo_pag; ?>">
                        <div class="card-header-custom <?php echo $header_class; ?>">
                            <div class="header-icon"><i class='bx <?php echo $icone; ?>'></i></div>
                            <div>
                                <div class="header-title"><?php echo htmlspecialchars($tipo_desc); ?></div>
                                <div class="header-subtitle"><?php echo htmlspecialchars($login_cli); ?></div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">CLIENTE</div>
                                        <div class="info-value"><?php echo htmlspecialchars($login_cli); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-money icon-money'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">VALOR</div>
                                        <div class="info-value">R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-hash icon-hash'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">ID PAGAMENTO</div>
                                        <div class="info-value"><?php echo htmlspecialchars(substr($id_pag, -12)); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-time' style="color:#f59e0b;"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">STATUS</div>
                                        <div class="info-value">
                                            <span class="badge-custom badge-warning"><i class='bx bx-time'></i> Pendente</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-note icon-note'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DETALHES</div>
                                    <div class="info-value" style="white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($texto_pag); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DATA</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($data_pag)); ?></div>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="btn-excluir-card" onclick="confirmarExclusao(<?php echo intval($db_id); ?>, '<?php echo addslashes($tabela); ?>', '<?php echo addslashes($login_cli); ?>', '<?php echo addslashes($tipo_pag); ?>', 'R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?>', '<?php echo addslashes(substr($id_pag, -12)); ?>')">
                                    <i class='bx bx-trash'></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bx bx-time"></i>
                        <h3>Nenhum pagamento pendente</h3>
                        <p>Nao ha pagamentos aguardando confirmacao no momento</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aba Aprovados -->
        <div id="tab-aprovados" class="tab-content-pagamento">
            <div class="pagamentos-grid" id="grid-aprovados">
                <?php if ($total_aprovados > 0): ?>
                    <?php foreach ($pagamentos_aprovados as $pag): ?>
                    <?php
                        $tipo_pag  = isset($pag['tipo_pagamento']) ? $pag['tipo_pagamento'] : 'default';
                        $login_cli = isset($pag['login_cliente'])  ? $pag['login_cliente']  : 'N/A';
                        $valor_pag = isset($pag['valor'])          ? floatval($pag['valor']) : 0;
                        $data_pag  = isset($pag['data'])           ? $pag['data']           : date('Y-m-d H:i:s');
                        $id_pag    = isset($pag['idpagamento'])    ? $pag['idpagamento']    : '---';
                        $texto_pag = isset($pag['texto'])          ? $pag['texto']          : 'Pagamento aprovado';
                        $tipo_desc = isset($pag['tipo_descricao']) ? $pag['tipo_descricao'] : 'Pagamento';
                        $db_id     = isset($pag['id'])             ? $pag['id']             : 0;
                        $tabela    = isset($pag['tabela_origem'])  ? $pag['tabela_origem']  : 'pagamentos';

                        if ($tipo_pag == 'renovacao_usuario')  $header_class = 'tipo-renovacao-usuario';
                        elseif ($tipo_pag == 'compra_plano')   $header_class = 'tipo-compra-plano';
                        elseif ($tipo_pag == 'compra_revenda') $header_class = 'tipo-compra-revenda';
                        elseif ($tipo_pag == 'renovacao_revenda') $header_class = 'tipo-renovacao-revenda';
                        else $header_class = 'tipo-default';

                        if ($tipo_pag == 'renovacao_usuario')  $icone = 'bx-refresh';
                        elseif ($tipo_pag == 'compra_plano')   $icone = 'bx-package';
                        elseif ($tipo_pag == 'compra_revenda') $icone = 'bx-store';
                        elseif ($tipo_pag == 'renovacao_revenda') $icone = 'bx-sync';
                        else $icone = 'bx-credit-card';
                    ?>
                    <div class="pagamento-card"
                         data-login="<?php echo strtolower(htmlspecialchars($login_cli)); ?>"
                         data-id="<?php echo strtolower(htmlspecialchars($id_pag)); ?>"
                         data-tipo="<?php echo $tipo_pag; ?>">
                        <div class="card-header-custom <?php echo $header_class; ?>">
                            <div class="header-icon"><i class='bx <?php echo $icone; ?>'></i></div>
                            <div>
                                <div class="header-title"><?php echo htmlspecialchars($tipo_desc); ?></div>
                                <div class="header-subtitle"><?php echo htmlspecialchars($login_cli); ?></div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">CLIENTE</div>
                                        <div class="info-value"><?php echo htmlspecialchars($login_cli); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-money icon-money'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">VALOR</div>
                                        <div class="info-value">R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-hash icon-hash'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">ID PAGAMENTO</div>
                                        <div class="info-value"><?php echo htmlspecialchars(substr($id_pag, -12)); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-check-circle' style="color:#10b981;"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">STATUS</div>
                                        <div class="info-value">
                                            <span class="badge-custom badge-success"><i class='bx bx-check-circle'></i> Aprovado</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-note icon-note'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DETALHES</div>
                                    <div class="info-value" style="white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($texto_pag); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DATA</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($data_pag)); ?></div>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="btn-excluir-card" onclick="confirmarExclusao(<?php echo intval($db_id); ?>, '<?php echo addslashes($tabela); ?>', '<?php echo addslashes($login_cli); ?>', '<?php echo addslashes($tipo_pag); ?>', 'R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?>', '<?php echo addslashes(substr($id_pag, -12)); ?>')">
                                    <i class='bx bx-trash'></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bx bx-check-circle"></i>
                        <h3>Nenhum pagamento aprovado</h3>
                        <p>Os pagamentos aprovados aparecerao aqui</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px; color:rgba(255,255,255,0.5); font-size:13px;">
            Pendentes: <?php echo $total_pendentes; ?> | Aprovados: <?php echo $total_aprovados; ?> | Total: <?php echo $total_geral; ?>
        </div>

    </div>
</div>

<!-- Modal Confirmar Exclusao -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-trash'></i> Confirmar Exclusao</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon danger"><i class='bx bx-trash'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Cliente</div>
                        <div class="modal-info-value credential" id="excluir-cliente">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-hash' style="color:#c084fc;"></i> ID</div>
                        <div class="modal-info-value" id="excluir-id">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-money' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value" id="excluir-valor">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-tag' style="color:#f97316;"></i> Tipo</div>
                        <div class="modal-info-value" id="excluir-tipo">-</div>
                    </div>
                </div>
                <p style="text-align:center; color:rgba(220,38,38,0.8); font-size:12px;">
                    Esta acao nao pode ser desfeita!
                </p>
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
                <h5><i class='bx bx-check-circle'></i> Excluido com Sucesso!</h5>
                <button type="button" class="modal-close" onclick="fecharModalSucesso()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon success"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Pagamento Removido!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">O pagamento foi removido com sucesso.</p>
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
            <div class="modal-header-custom error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon danger"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitacao!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
var pagamentoIdParaExcluir  = null;
var tabelaParaExcluir       = null;
var tipoPagamentoParaExcluir= null;

function mudarTab(tab) {
    document.querySelectorAll('.tab-pagamento').forEach(function(btn) { btn.classList.remove('active'); });
    document.getElementById('tab-pendentes').classList.remove('active');
    document.getElementById('tab-aprovados').classList.remove('active');

    if (tab === 'pendentes') {
        document.querySelectorAll('.tab-pagamento')[0].classList.add('active');
        document.getElementById('tab-pendentes').classList.add('active');
    } else {
        document.querySelectorAll('.tab-pagamento')[1].classList.add('active');
        document.getElementById('tab-aprovados').classList.add('active');
    }
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    var search = this.value.toLowerCase();
    document.querySelectorAll('.pagamento-card').forEach(function(card) {
        var login = card.getAttribute('data-login') || '';
        var id    = card.getAttribute('data-id')    || '';
        card.style.display = (login.indexOf(search) >= 0 || id.indexOf(search) >= 0) ? '' : 'none';
    });
});

document.getElementById('tipoFilter').addEventListener('change', function() {
    var tipo = this.value;
    document.querySelectorAll('.pagamento-card').forEach(function(card) {
        if (tipo === 'todos') {
            card.style.display = '';
        } else {
            card.style.display = (card.getAttribute('data-tipo') === tipo) ? '' : 'none';
        }
    });
});

function confirmarExclusao(id, tabela, clienteNome, tipoPagamento, valor, idPag) {
    pagamentoIdParaExcluir   = id;
    tabelaParaExcluir        = tabela;
    tipoPagamentoParaExcluir = tipoPagamento;

    document.getElementById('excluir-cliente').textContent = clienteNome;
    document.getElementById('excluir-id').textContent      = idPag;
    document.getElementById('excluir-valor').textContent   = valor;

    var tipoDisplay = '';
    if      (tipoPagamento === 'renovacao_usuario')  tipoDisplay = 'Renovacao de Usuario';
    else if (tipoPagamento === 'compra_plano')        tipoDisplay = 'Compra de Plano';
    else if (tipoPagamento === 'compra_revenda')      tipoDisplay = 'Compra de Revenda';
    else if (tipoPagamento === 'renovacao_revenda')   tipoDisplay = 'Renovacao de Revenda';
    else                                              tipoDisplay = 'Pagamento';
    document.getElementById('excluir-tipo').textContent = tipoDisplay;

    document.getElementById('modalConfirmarExclusao').classList.add('show');
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('show');
}

function fecharModalSucesso() {
    fecharModal('modalSucesso');
    location.reload();
}

document.getElementById('btnConfirmarExclusao').onclick = function() {
    if (!pagamentoIdParaExcluir || !tabelaParaExcluir) return;

    fecharModal('modalConfirmarExclusao');

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
            excluir_pagamento: 1,
            pagamento_id:      pagamentoIdParaExcluir,
            tabela:            tabelaParaExcluir,
            tipo_pagamento:    tipoPagamentoParaExcluir || ''
        },
        success: function() {
            document.getElementById('sucesso-mensagem').textContent = 'Pagamento removido com sucesso!';
            document.getElementById('modalSucesso').classList.add('show');
        },
        error: function() {
            document.getElementById('erro-mensagem').textContent = 'Erro ao conectar com o servidor!';
            document.getElementById('modalErro').classList.add('show');
        }
    });
};

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        var modalId = e.target.id;
        if (modalId === 'modalSucesso') { fecharModalSucesso(); }
        else { e.target.classList.remove('show'); }
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('modalSucesso').classList.contains('show')) { fecharModalSucesso(); }
        else { document.querySelectorAll('.modal-overlay.show').forEach(function(m) { m.classList.remove('show'); }); }
    }
});

<?php if (isset($excluido_com_sucesso) && $excluido_com_sucesso): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalSucesso').classList.add('show');
});
<?php endif; ?>

<?php if (isset($erro_exclusao)): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('erro-mensagem').textContent = '<?php echo addslashes($erro_exclusao); ?>';
    document.getElementById('modalErro').classList.add('show');
});
<?php endif; ?>
</script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-credit-card'></i>
            <span>Historico de Pagamentos</span>
        </div>

      

        <!-- Cards de resumo -->
        <div class="resumo-cards">
            <div class="resumo-card">
                <div class="resumo-icon green"><i class='bx bx-check-circle'></i></div>
                <div>
                    <div class="resumo-label">Aprovados</div>
                    <div class="resumo-value"><?php echo $total_aprovados; ?></div>
                    <div style="font-size:11px; color:#10b981;">R$ <?php echo number_format($valor_total_aprovados, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-icon yellow"><i class='bx bx-time'></i></div>
                <div>
                    <div class="resumo-label">Pendentes</div>
                    <div class="resumo-value"><?php echo $total_pendentes; ?></div>
                    <div style="font-size:11px; color:#f59e0b;">R$ <?php echo number_format($valor_total_pendentes, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-icon blue"><i class='bx bx-list-ul'></i></div>
                <div>
                    <div class="resumo-label">Total de Pedidos</div>
                    <div class="resumo-value"><?php echo $total_geral; ?></div>
                    <div style="font-size:11px; color:#3b82f6;">R$ <?php echo number_format($valor_total_aprovados + $valor_total_pendentes, 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="resumo-card">
                <div class="resumo-icon purple"><i class='bx bx-calendar'></i></div>
                <div>
                    <div class="resumo-label">Ultima Atualizacao</div>
                    <div class="resumo-value" style="font-size:14px;"><?php echo date('d/m/Y'); ?></div>
                    <div style="font-size:11px; color:#8b5cf6;"><?php echo date('H:i:s'); ?></div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <div class="filters-title"><i class='bx bx-filter-alt'></i> Filtros</div>
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">BUSCAR POR CLIENTE / ID</div>
                    <input type="text" class="filter-input" id="searchInput" placeholder="Digite para buscar...">
                </div>
                <div class="filter-item">
                    <div class="filter-label">FILTRAR POR TIPO</div>
                    <select class="filter-select" id="tipoFilter">
                        <option value="todos">Todos os tipos</option>
                        <option value="renovacao_usuario">Renovacao de Usuario</option>
                        <option value="compra_plano">Compra de Plano</option>
                        <option value="compra_revenda">Compra de Revenda</option>
                        <option value="renovacao_revenda">Renovacao de Revenda</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Abas -->
        <div class="tabs-pagamentos">
            <button class="tab-pagamento active" onclick="mudarTab('pendentes')">
                <i class='bx bx-time'></i> Pendentes
                <?php if ($total_pendentes > 0): ?>
                <span style="background:#f59e0b; color:white; padding:2px 6px; border-radius:20px; font-size:10px;"><?php echo $total_pendentes; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-pagamento" onclick="mudarTab('aprovados')">
                <i class='bx bx-check-circle'></i> Aprovados
                <?php if ($total_aprovados > 0): ?>
                <span style="background:#10b981; color:white; padding:2px 6px; border-radius:20px; font-size:10px;"><?php echo $total_aprovados; ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Aba Pendentes -->
        <div id="tab-pendentes" class="tab-content-pagamento active">
            <div class="pagamentos-grid" id="grid-pendentes">
                <?php if ($total_pendentes > 0): ?>
                    <?php foreach ($pagamentos_pendentes as $pag): ?>
                    <?php
                        $tipo_pag  = isset($pag['tipo_pagamento']) ? $pag['tipo_pagamento'] : 'default';
                        $login_cli = isset($pag['login_cliente'])  ? $pag['login_cliente']  : 'N/A';
                        $valor_pag = isset($pag['valor'])          ? floatval($pag['valor']) : 0;
                        $data_pag  = isset($pag['data'])           ? $pag['data']           : date('Y-m-d H:i:s');
                        $id_pag    = isset($pag['idpagamento'])    ? $pag['idpagamento']    : '---';
                        $texto_pag = isset($pag['texto'])          ? $pag['texto']          : 'Pagamento pendente';
                        $tipo_desc = isset($pag['tipo_descricao']) ? $pag['tipo_descricao'] : 'Pagamento';
                        $db_id     = isset($pag['id'])             ? $pag['id']             : 0;
                        $tabela    = isset($pag['tabela_origem'])  ? $pag['tabela_origem']  : 'pagamentos';

                        if ($tipo_pag == 'renovacao_usuario')  $header_class = 'tipo-renovacao-usuario';
                        elseif ($tipo_pag == 'compra_plano')   $header_class = 'tipo-compra-plano';
                        elseif ($tipo_pag == 'compra_revenda') $header_class = 'tipo-compra-revenda';
                        elseif ($tipo_pag == 'renovacao_revenda') $header_class = 'tipo-renovacao-revenda';
                        else $header_class = 'tipo-default';

                        if ($tipo_pag == 'renovacao_usuario')  $icone = 'bx-refresh';
                        elseif ($tipo_pag == 'compra_plano')   $icone = 'bx-package';
                        elseif ($tipo_pag == 'compra_revenda') $icone = 'bx-store';
                        elseif ($tipo_pag == 'renovacao_revenda') $icone = 'bx-sync';
                        else $icone = 'bx-credit-card';
                    ?>
                    <div class="pagamento-card"
                         data-login="<?php echo strtolower(htmlspecialchars($login_cli)); ?>"
                         data-id="<?php echo strtolower(htmlspecialchars($id_pag)); ?>"
                         data-tipo="<?php echo $tipo_pag; ?>">
                        <div class="card-header-custom <?php echo $header_class; ?>">
                            <div class="header-icon"><i class='bx <?php echo $icone; ?>'></i></div>
                            <div>
                                <div class="header-title"><?php echo htmlspecialchars($tipo_desc); ?></div>
                                <div class="header-subtitle"><?php echo htmlspecialchars($login_cli); ?></div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">CLIENTE</div>
                                        <div class="info-value"><?php echo htmlspecialchars($login_cli); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-money icon-money'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">VALOR</div>
                                        <div class="info-value">R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-hash icon-hash'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">ID PAGAMENTO</div>
                                        <div class="info-value"><?php echo htmlspecialchars(substr($id_pag, -12)); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-time' style="color:#f59e0b;"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">STATUS</div>
                                        <div class="info-value">
                                            <span class="badge-custom badge-warning"><i class='bx bx-time'></i> Pendente</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-note icon-note'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DETALHES</div>
                                    <div class="info-value" style="white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($texto_pag); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DATA</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($data_pag)); ?></div>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="btn-excluir-card" onclick="confirmarExclusao(<?php echo intval($db_id); ?>, '<?php echo addslashes($tabela); ?>', '<?php echo addslashes($login_cli); ?>', '<?php echo addslashes($tipo_pag); ?>', 'R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?>', '<?php echo addslashes(substr($id_pag, -12)); ?>')">
                                    <i class='bx bx-trash'></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bx bx-time"></i>
                        <h3>Nenhum pagamento pendente</h3>
                        <p>Nao ha pagamentos aguardando confirmacao no momento</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aba Aprovados -->
        <div id="tab-aprovados" class="tab-content-pagamento">
            <div class="pagamentos-grid" id="grid-aprovados">
                <?php if ($total_aprovados > 0): ?>
                    <?php foreach ($pagamentos_aprovados as $pag): ?>
                    <?php
                        $tipo_pag  = isset($pag['tipo_pagamento']) ? $pag['tipo_pagamento'] : 'default';
                        $login_cli = isset($pag['login_cliente'])  ? $pag['login_cliente']  : 'N/A';
                        $valor_pag = isset($pag['valor'])          ? floatval($pag['valor']) : 0;
                        $data_pag  = isset($pag['data'])           ? $pag['data']           : date('Y-m-d H:i:s');
                        $id_pag    = isset($pag['idpagamento'])    ? $pag['idpagamento']    : '---';
                        $texto_pag = isset($pag['texto'])          ? $pag['texto']          : 'Pagamento aprovado';
                        $tipo_desc = isset($pag['tipo_descricao']) ? $pag['tipo_descricao'] : 'Pagamento';
                        $db_id     = isset($pag['id'])             ? $pag['id']             : 0;
                        $tabela    = isset($pag['tabela_origem'])  ? $pag['tabela_origem']  : 'pagamentos';

                        if ($tipo_pag == 'renovacao_usuario')  $header_class = 'tipo-renovacao-usuario';
                        elseif ($tipo_pag == 'compra_plano')   $header_class = 'tipo-compra-plano';
                        elseif ($tipo_pag == 'compra_revenda') $header_class = 'tipo-compra-revenda';
                        elseif ($tipo_pag == 'renovacao_revenda') $header_class = 'tipo-renovacao-revenda';
                        else $header_class = 'tipo-default';

                        if ($tipo_pag == 'renovacao_usuario')  $icone = 'bx-refresh';
                        elseif ($tipo_pag == 'compra_plano')   $icone = 'bx-package';
                        elseif ($tipo_pag == 'compra_revenda') $icone = 'bx-store';
                        elseif ($tipo_pag == 'renovacao_revenda') $icone = 'bx-sync';
                        else $icone = 'bx-credit-card';
                    ?>
                    <div class="pagamento-card"
                         data-login="<?php echo strtolower(htmlspecialchars($login_cli)); ?>"
                         data-id="<?php echo strtolower(htmlspecialchars($id_pag)); ?>"
                         data-tipo="<?php echo $tipo_pag; ?>">
                        <div class="card-header-custom <?php echo $header_class; ?>">
                            <div class="header-icon"><i class='bx <?php echo $icone; ?>'></i></div>
                            <div>
                                <div class="header-title"><?php echo htmlspecialchars($tipo_desc); ?></div>
                                <div class="header-subtitle"><?php echo htmlspecialchars($login_cli); ?></div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-user icon-user'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">CLIENTE</div>
                                        <div class="info-value"><?php echo htmlspecialchars($login_cli); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-money icon-money'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">VALOR</div>
                                        <div class="info-value">R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-hash icon-hash'></i></div>
                                    <div class="info-content">
                                        <div class="info-label">ID PAGAMENTO</div>
                                        <div class="info-value"><?php echo htmlspecialchars(substr($id_pag, -12)); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-icon"><i class='bx bx-check-circle' style="color:#10b981;"></i></div>
                                    <div class="info-content">
                                        <div class="info-label">STATUS</div>
                                        <div class="info-value">
                                            <span class="badge-custom badge-success"><i class='bx bx-check-circle'></i> Aprovado</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-note icon-note'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DETALHES</div>
                                    <div class="info-value" style="white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($texto_pag); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon"><i class='bx bx-calendar icon-calendar'></i></div>
                                <div class="info-content">
                                    <div class="info-label">DATA</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($data_pag)); ?></div>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="btn-excluir-card" onclick="confirmarExclusao(<?php echo intval($db_id); ?>, '<?php echo addslashes($tabela); ?>', '<?php echo addslashes($login_cli); ?>', '<?php echo addslashes($tipo_pag); ?>', 'R$ <?php echo number_format($valor_pag, 2, ',', '.'); ?>', '<?php echo addslashes(substr($id_pag, -12)); ?>')">
                                    <i class='bx bx-trash'></i> Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bx bx-check-circle"></i>
                        <h3>Nenhum pagamento aprovado</h3>
                        <p>Os pagamentos aprovados aparecerao aqui</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px; color:rgba(255,255,255,0.5); font-size:13px;">
            Pendentes: <?php echo $total_pendentes; ?> | Aprovados: <?php echo $total_aprovados; ?> | Total: <?php echo $total_geral; ?>
        </div>

    </div>
</div>

<!-- Modal Confirmar Exclusao -->
<div id="modalConfirmarExclusao" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content-custom">
            <div class="modal-header-custom error">
                <h5><i class='bx bx-trash'></i> Confirmar Exclusao</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalConfirmarExclusao')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon danger"><i class='bx bx-trash'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Cliente</div>
                        <div class="modal-info-value credential" id="excluir-cliente">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-hash' style="color:#c084fc;"></i> ID</div>
                        <div class="modal-info-value" id="excluir-id">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-money' style="color:#10b981;"></i> Valor</div>
                        <div class="modal-info-value" id="excluir-valor">-</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-tag' style="color:#f97316;"></i> Tipo</div>
                        <div class="modal-info-value" id="excluir-tipo">-</div>
                    </div>
                </div>
                <p style="text-align:center; color:rgba(220,38,38,0.8); font-size:12px;">
                    Esta acao nao pode ser desfeita!
                </p>
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
                <h5><i class='bx bx-check-circle'></i> Excluido com Sucesso!</h5>
                <button type="button" class="modal-close" onclick="fecharModalSucesso()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon success"><i class='bx bx-check-circle'></i></div>
                <h3 style="color:white; text-align:center; margin-bottom:10px;">Pagamento Removido!</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="sucesso-mensagem">O pagamento foi removido com sucesso.</p>
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
            <div class="modal-header-custom error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button type="button" class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body-custom">
                <div class="modal-icon danger"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8); text-align:center;" id="erro-mensagem">Erro ao processar solicitacao!</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
var pagamentoIdParaExcluir  = null;
var tabelaParaExcluir       = null;
var tipoPagamentoParaExcluir= null;

function mudarTab(tab) {
    document.querySelectorAll('.tab-pagamento').forEach(function(btn) { btn.classList.remove('active'); });
    document.getElementById('tab-pendentes').classList.remove('active');
    document.getElementById('tab-aprovados').classList.remove('active');

    if (tab === 'pendentes') {
        document.querySelectorAll('.tab-pagamento')[0].classList.add('active');
        document.getElementById('tab-pendentes').classList.add('active');
    } else {
        document.querySelectorAll('.tab-pagamento')[1].classList.add('active');
        document.getElementById('tab-aprovados').classList.add('active');
    }
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    var search = this.value.toLowerCase();
    document.querySelectorAll('.pagamento-card').forEach(function(card) {
        var login = card.getAttribute('data-login') || '';
        var id    = card.getAttribute('data-id')    || '';
        card.style.display = (login.indexOf(search) >= 0 || id.indexOf(search) >= 0) ? '' : 'none';
    });
});

document.getElementById('tipoFilter').addEventListener('change', function() {
    var tipo = this.value;
    document.querySelectorAll('.pagamento-card').forEach(function(card) {
        if (tipo === 'todos') {
            card.style.display = '';
        } else {
            card.style.display = (card.getAttribute('data-tipo') === tipo) ? '' : 'none';
        }
    });
});

function confirmarExclusao(id, tabela, clienteNome, tipoPagamento, valor, idPag) {
    pagamentoIdParaExcluir   = id;
    tabelaParaExcluir        = tabela;
    tipoPagamentoParaExcluir = tipoPagamento;

    document.getElementById('excluir-cliente').textContent = clienteNome;
    document.getElementById('excluir-id').textContent      = idPag;
    document.getElementById('excluir-valor').textContent   = valor;

    var tipoDisplay = '';
    if      (tipoPagamento === 'renovacao_usuario')  tipoDisplay = 'Renovacao de Usuario';
    else if (tipoPagamento === 'compra_plano')        tipoDisplay = 'Compra de Plano';
    else if (tipoPagamento === 'compra_revenda')      tipoDisplay = 'Compra de Revenda';
    else if (tipoPagamento === 'renovacao_revenda')   tipoDisplay = 'Renovacao de Revenda';
    else                                              tipoDisplay = 'Pagamento';
    document.getElementById('excluir-tipo').textContent = tipoDisplay;

    document.getElementById('modalConfirmarExclusao').classList.add('show');
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('show');
}

function fecharModalSucesso() {
    fecharModal('modalSucesso');
    location.reload();
}

document.getElementById('btnConfirmarExclusao').onclick = function() {
    if (!pagamentoIdParaExcluir || !tabelaParaExcluir) return;

    fecharModal('modalConfirmarExclusao');

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
            excluir_pagamento: 1,
            pagamento_id:      pagamentoIdParaExcluir,
            tabela:            tabelaParaExcluir,
            tipo_pagamento:    tipoPagamentoParaExcluir || ''
        },
        success: function() {
            document.getElementById('sucesso-mensagem').textContent = 'Pagamento removido com sucesso!';
            document.getElementById('modalSucesso').classList.add('show');
        },
        error: function() {
            document.getElementById('erro-mensagem').textContent = 'Erro ao conectar com o servidor!';
            document.getElementById('modalErro').classList.add('show');
        }
    });
};

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        var modalId = e.target.id;
        if (modalId === 'modalSucesso') { fecharModalSucesso(); }
        else { e.target.classList.remove('show'); }
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('modalSucesso').classList.contains('show')) { fecharModalSucesso(); }
        else { document.querySelectorAll('.modal-overlay.show').forEach(function(m) { m.classList.remove('show'); }); }
    }
});

<?php if (isset($excluido_com_sucesso) && $excluido_com_sucesso): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalSucesso').classList.add('show');
});
<?php endif; ?>

<?php if (isset($erro_exclusao)): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('erro-mensagem').textContent = '<?php echo addslashes($erro_exclusao); ?>';
    document.getElementById('modalErro').classList.add('show');
});
<?php endif; ?>
</script>
</body>
</html>



