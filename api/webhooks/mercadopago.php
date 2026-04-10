<?php
include('../../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Receber notificação do Mercado Pago
$postdata = file_get_contents("php://input");
$data = json_decode($postdata, true);

// Log de webhook para debug
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Webhook recebido: " . json_encode($data) . "\n", FILE_APPEND);

if (isset($data['action']) && $data['action'] == 'payment.updated') {
    $payment_id = $data['data']['id'];
    
    // Buscar pagamento
    $sql = "SELECT * FROM pagamentos WHERE idpagamento = '$payment_id'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $pagamento = $result->fetch_assoc();
        
        // Buscar status real do pagamento no Mercado Pago
        $access_token = $pagamento['access_token'] ?? '';
        
        if (!empty($access_token)) {
            $url = "https://api.mercadopago.com/v1/payments/" . $payment_id;
            $header = array(
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            $result_mp = curl_exec($ch);
            curl_close($ch);
            
            $status_mp = json_decode($result_mp);
            
            if (isset($status_mp->status) && $status_mp->status == "approved") {
                // PAGAMENTO APROVADO - Aplicar lógica de negócio
                
                $tipo = $pagamento['tipo'];
                $iduser = $pagamento['userid'];
                $addlimite = $pagamento['addlimite'] ?? 0;
                $byid = $pagamento['byid'];
                
                // RENOVAÇÃO DE PAINEL (Revendedor/Admin)
                if ($tipo == 'Renovacao Painel' || $tipo == 'renovacao') {
                    $sql_update = "SELECT * FROM atribuidos WHERE userid = $iduser";
                    $result_update = $conn->query($sql_update);
                    
                    if ($result_update->num_rows > 0) {
                        $row_update = $result_update->fetch_assoc();
                        $data_atual = date('Y-m-d H:i:s');
                        
                        // Se vencido, começar do hoje. Se ativo, adicionar aos dias existentes
                        if ($row_update['expira'] < $data_atual) {
                            $nova_data = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($data_atual)));
                        } else {
                            $nova_data = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($row_update['expira'])));
                        }
                        
                        // Atualizar expiração e reativar se suspenso
                        $sql_renov = "UPDATE atribuidos SET expira = '$nova_data', suspenso = '0' WHERE userid = $iduser";
                        $conn->query($sql_renov);
                        
                        // Se o revendedor tinha mainid = 'Vencido', limpar para reativar usuários
                        $sql_reativa = "UPDATE ssh_accounts SET mainid = '' WHERE byid = $iduser AND mainid = 'Vencido'";
                        $conn->query($sql_reativa);
                    }
                    
                    // Atualizar status do pagamento
                    $conn->query("UPDATE pagamentos SET status = 'Aprovado' WHERE idpagamento = '$payment_id'");
                    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Renovação de painel aprovada: $payment_id\n", FILE_APPEND);
                }
                
                // ADICIONAR LIMITE (Compra de limite)
                elseif ($tipo == 'Adicionar Limite' || $tipo == 'compra_limite') {
                    // Verificar se o vendedor tem limite disponível
                    $sql_vendedor = "SELECT * FROM atribuidos WHERE userid = '$byid'";
                    $result_vendedor = $conn->query($sql_vendedor);
                    
                    if ($result_vendedor->num_rows > 0) {
                        $row_vendedor = $result_vendedor->fetch_assoc();
                        $limite_vendedor = $row_vendedor['limite'];
                        
                        // Se não for admin, verificar capacidade
                        if ($byid != '1') {
                            // Calcular limite usado
                            $sql_usado = "SELECT sum(limite) AS limiterevenda FROM atribuidos where byid='$byid'";
                            $result_usado = $conn->query($sql_usado);
                            $row_usado = $result_usado->fetch_assoc();
                            $limiterevenda = $row_usado['limiterevenda'] ?? 0;
                            
                            // Contar usuários SSH criados
                            $sql_usuarios = "SELECT COUNT(*) as total FROM ssh_accounts WHERE byid = '$byid'";
                            $result_usuarios = $conn->query($sql_usuarios);
                            $row_usuarios = $result_usuarios->fetch_assoc();
                            $usadousuarios = $row_usuarios['total'];
                            
                            // Calcular total usado
                            $total_usado = $usadousuarios + $limiterevenda;
                            
                            // Verificar se tem limite disponível
                            if (($total_usado + $addlimite) > $limite_vendedor) {
                                $conn->query("UPDATE pagamentos SET status = 'Sem Limite' WHERE idpagamento = '$payment_id'");
                                file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Limite insuficiente: $payment_id\n", FILE_APPEND);
                            } else {
                                $conn->query("UPDATE atribuidos SET limite = limite + '$addlimite' WHERE userid = '$iduser'");
                                $conn->query("UPDATE pagamentos SET status = 'Aprovado' WHERE idpagamento = '$payment_id'");
                                file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Limite adicionado: $payment_id\n", FILE_APPEND);
                            }
                        } else {
                            // Admin pode adicionar limite sem restrição
                            $conn->query("UPDATE atribuidos SET limite = limite + '$addlimite' WHERE userid = '$iduser'");
                            $conn->query("UPDATE pagamentos SET status = 'Aprovado' WHERE idpagamento = '$payment_id'");
                            file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Limite adicionado (admin): $payment_id\n", FILE_APPEND);
                        }
                    }
                }
                
                // ADICIONAR CRÉDITO (Transferência de limite)
                elseif ($tipo == 'Adicionar Credito' || $tipo == 'credito') {
                    $sql_credito = "SELECT * FROM atribuidos WHERE userid = '$byid'";
                    $result_credito = $conn->query($sql_credito);
                    
                    if ($result_credito->num_rows > 0) {
                        $row_credito = $result_credito->fetch_assoc();
                        $limite_credito = $row_credito['limite'];
                    } else {
                        $limite_credito = '1000000000000'; // Admin
                    }
                    
                    if ($addlimite > $limite_credito) {
                        $conn->query("UPDATE pagamentos SET status = 'Sem Limite' WHERE idpagamento = '$payment_id'");
                    } else {
                        $conn->query("UPDATE atribuidos SET limite = limite + '$addlimite' WHERE userid = '$iduser'");
                        $conn->query("UPDATE atribuidos SET limite = limite - '$addlimite' WHERE userid = '$byid'");
                        $conn->query("UPDATE pagamentos SET status = 'Aprovado' WHERE idpagamento = '$payment_id'");
                    }
                }
                
                // RENOVAÇÃO DE USUÁRIO SSH
                elseif ($tipo == 'Renovacao Usuario' || $tipo == 'renovacao_usuario') {
                    $sql_usuario = "SELECT * FROM ssh_accounts WHERE id = '$iduser'";
                    $result_usuario = $conn->query($sql_usuario);
                    
                    if ($result_usuario->num_rows > 0) {
                        $row_usuario = $result_usuario->fetch_assoc();
                        $data_expira = $row_usuario['expira'];
                        
                        // Calcular nova data
                        if ($data_expira < date('Y-m-d H:i:s')) {
                            $nova_data = date('Y-m-d H:i:s', strtotime("+30 days", strtotime(date('Y-m-d H:i:s'))));
                        } else {
                            $nova_data = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($data_expira)));
                        }
                        
                        // Atualizar usuário SSH
                        $conn->query("UPDATE ssh_accounts SET expira = '$nova_data', mainid = '' WHERE id = '$iduser'");
                        $conn->query("UPDATE pagamentos SET status = 'Aprovado' WHERE idpagamento = '$payment_id'");
                        file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Usuário SSH renovado: $payment_id\n", FILE_APPEND);
                    }
                }
                
                // COMPRA DE PLANO (Novo revendedor ou usuário SSH)
                elseif ($tipo == 'Compra de Plano' || $tipo == 'compra_plano' || $tipo == 'plano') {
                    // Este tipo é processado por outro endpoint (revenda/verifica.php)
                    $conn->query("UPDATE pagamentos SET status = 'Aprovado' WHERE idpagamento = '$payment_id'");
                    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Compra de plano marcada como aprovada: $payment_id\n", FILE_APPEND);
                }
                
                // Atualizar visitas do link
                $conn->query("UPDATE links_venda SET vendas = vendas + 1 WHERE revendedor_id = '{$pagamento['byid']}'");
            }
        }
    }
}

http_response_code(200);
?>
