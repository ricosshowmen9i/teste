<?php
// Este arquivo processa a compra de um plano de revenda
// Quando compra, ADICIONA créditos ao limite atual e ATUALIZA o valor do plano

session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

function processarCompraPlano($conn, $revenda_id, $plano_id) {
    // Buscar dados do plano
    $sql_plano = "SELECT * FROM planos_pagamento WHERE id = '$plano_id' AND status = 1 AND tipo = 'revenda'";
    $result_plano = mysqli_query($conn, $sql_plano);
    $plano = mysqli_fetch_assoc($result_plano);
    
    if (!$plano) {
        return ['success' => false, 'message' => 'Plano não encontrado'];
    }
    
    // Buscar dados atuais do revendedor
    $sql_revenda = "SELECT * FROM atribuidos WHERE userid = '$revenda_id'";
    $result_revenda = mysqli_query($conn, $sql_revenda);
    $revenda = mysqli_fetch_assoc($result_revenda);
    
    if (!$revenda) {
        return ['success' => false, 'message' => 'Revendedor não encontrado'];
    }
    
    $limite_atual = intval($revenda['limite']);
    $valor_atual = floatval($revenda['valor'] ?? 0);
    $plano_anterior_id = $revenda['id_plano'] ?? 0;
    
    $limite_plano = intval($plano['limite']);
    $valor_plano = floatval($plano['valor']);
    $duracao_dias = intval($plano['duracao_dias']);
    $vendedor_id = $plano['byid'];
    
    // ADICIONA créditos (não substitui)
    $novo_limite = $limite_atual + $limite_plano;
    
    // ATUALIZA o valor do plano (para renovação futura)
    $novo_valor = $valor_plano;
    
    // Calcular nova validade (adiciona dias à validade atual)
    $nova_validade = date('Y-m-d H:i:s', strtotime("+$duracao_dias days", strtotime($revenda['expira'])));
    
    // Iniciar transação
    mysqli_begin_transaction($conn);
    
    try {
        // Atualizar atribuidos
        $sql_update = "UPDATE atribuidos SET 
                        limite = '$novo_limite',
                        expira = '$nova_validade',
                        valor = '$novo_valor',
                        id_plano = '$plano_id',
                        suspenso = 0 
                       WHERE userid = '$revenda_id'";
        mysqli_query($conn, $sql_update);
        
        // Registrar compra
        $sql_compra = "INSERT INTO compras_planos_revenda (revenda_id, plano_id, limite_comprado, valor_pago, data_compra, status) 
                       VALUES ('$revenda_id', '$plano_id', '$limite_plano', '$valor_plano', NOW(), 'aprovado')";
        mysqli_query($conn, $sql_compra);
        
        // Registrar histórico
        $sql_historico = "INSERT INTO historico_planos_revenda (revenda_id, plano_anterior_id, plano_novo_id, limite_anterior, limite_novo, valor_anterior, valor_novo, data_alteracao, tipo_alteracao) 
                          VALUES ('$revenda_id', '$plano_anterior_id', '$plano_id', '$limite_atual', '$novo_limite', '$valor_atual', '$novo_valor', NOW(), 'compra')";
        mysqli_query($conn, $sql_historico);
        
        // Registrar log
        $datahoje = date('d-m-Y H:i:s');
        $sql_log = "INSERT INTO logs (revenda, validade, texto, userid) 
                    VALUES ((SELECT login FROM accounts WHERE id = '$revenda_id'), '$datahoje', 
                    'Comprou plano: {$plano['nome']} - Adicionou $limite_plano créditos. Novo limite: $novo_limite', '$revenda_id')";
        mysqli_query($conn, $sql_log);
        
        mysqli_commit($conn);
        
        return [
            'success' => true,
            'message' => 'Plano comprado com sucesso!',
            'limite_anterior' => $limite_atual,
            'limite_adicionado' => $limite_plano,
            'novo_limite' => $novo_limite,
            'novo_valor' => $novo_valor,
            'nova_validade' => date('d/m/Y', strtotime($nova_validade))
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Erro ao processar compra: ' . $e->getMessage()];
    }
}
?>