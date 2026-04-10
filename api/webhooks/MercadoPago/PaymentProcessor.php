<?php
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/PublicUserProcessor.php';

class PaymentProcessor
{
    private $pdo;
    private $resellerProcessor;
    private $userProcessor;
    private $cancelledProcessor;
    private $publicUserProcessor;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->resellerProcessor = new ResellerProcessor($pdo);
        $this->userProcessor = new UserProcessor($pdo);
        $this->cancelledProcessor = new CancelledProcessor($pdo);
        $this->publicUserProcessor = new PublicUserProcessor($pdo);
    }

    /**
     * Processa atualizações de pagamento
     */
    public function processPaymentUpdate($paymentId, $accessToken)
    {
        try {
            $mpPayment = MercadoPagoAPI::getPaymentFromMercadoPago($paymentId, $accessToken);

            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM pagamentos 
                    WHERE payment_id = ? 
                    FOR UPDATE
                ");
                $stmt->execute([$paymentId]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payment) {
                    Logger::logInfo("Pagamento {$paymentId} não encontrado no banco de dados local. Status MP: " . $mpPayment['status']);
                    $this->pdo->rollBack();
                    echo json_encode([
                        'message' => 'Notificação recebida (pagamento não encontrado no banco local)',
                        'status' => $mpPayment['status']
                    ]);
                    return;
                }

                $previousStatus = $payment['status'];

                // Evita processamento duplicado de pagamentos já aprovados
                if ($previousStatus === 'approved') {
                    Logger::logInfo("Pagamento {$paymentId} já aprovado anteriormente. Ignorando notificação.");
                    $this->pdo->rollBack();
                    echo json_encode([
                        'message' => 'Pagamento já aprovado anteriormente',
                        'status' => $mpPayment['status']
                    ]);
                    return;
                }

                if ($previousStatus !== $mpPayment['status']) {
                    $stmt = $this->pdo->prepare("
                        UPDATE pagamentos 
                        SET status = ?, data_pagamento = NOW() 
                        WHERE payment_id = ?
                    ");
                    $stmt->execute([$mpPayment['status'], $paymentId]);
                    
                    if ($mpPayment['status'] === 'approved') {
                        // Verifica se é pagamento de página pública (novo usuário/revenda)
                        $isPublicPayment = isset($payment['origem']) && $payment['origem'] === 'pagina_publica';
                        $isNewUser = intval($payment['iduser']) === 0;
                        
                        // Se é pagamento público com novo cadastro, usar PublicUserProcessor
                        // Ele trata tanto usuario quanto revenda internamente
                        if ($isPublicPayment && $isNewUser) {
                            $this->publicUserProcessor->processNewUser($payment);
                        }
                        // Processa operações de revenda existente se aplicável
                        elseif ($payment['tipo_conta'] === 'revenda') {
                            // Sempre processar como compra (unificação de fluxos)
                            $this->resellerProcessor->processPurchase($payment);
                        }
                        // Processa operações de usuário existente se aplicável
                        // Processa operações de usuário existente se aplicável
                        elseif ($payment['tipo_conta'] === 'usuario') {
                            // Sempre processar como compra (o usuário solicitou remover renovação)
                            $this->userProcessor->processPurchase($payment);
                        }

                        Logger::logInfo("Pagamento {$paymentId} atualizado com sucesso para status: " . $mpPayment['status']);

                        $this->pdo->commit();

                        echo json_encode([
                            'message' => 'Notificação processada',
                            'status' => $mpPayment['status']
                        ]);
                        return;
                    } elseif ($mpPayment['status'] === 'cancelled') {
                        // Processa cancelamento usando o CancelledProcessor (global)
                        $this->cancelledProcessor->processCancellation($paymentId);

                        $this->pdo->commit();

                        echo json_encode([
                            'message' => 'Pagamento cancelado processado',
                            'status' => $mpPayment['status']
                        ]);
                        return;
                    }
                }

                Logger::logInfo("Status do pagamento {$paymentId} não mudou (continua {$previousStatus}). Ignorando atualização.");

                $this->pdo->commit();

                echo json_encode([
                    'message' => 'Status do pagamento não mudou',
                    'status' => $mpPayment['status']
                ]);
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Logger::logError("Erro ao processar pagamento: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao processar pagamento']);
        }
    }
}
