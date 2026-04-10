<?php
require_once __DIR__ . '/Logger.php';

class CancelledProcessor
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Processa cancelamentos de pagamento - Método global simples
     */
    public function processCancellation($paymentId)
    {
        try {
            // Atualiza apenas o status para cancelado usando o payment_id
            $stmt = $this->pdo->prepare("
                UPDATE pagamentos 
                SET status = 'Cancelado' 
                WHERE payment_id = ?
            ");
            $result = $stmt->execute([$paymentId]);

            if ($result && $stmt->rowCount() > 0) {
                // Log da operação
                Logger::logInfo("Pagamento {$paymentId} cancelado com sucesso");
                return true;
            } else {
                Logger::logInfo("Nenhum pagamento encontrado com payment_id: {$paymentId}");
                return false;
            }
        } catch (Exception $e) {
            Logger::logError("Erro ao processar cancelamento do pagamento {$paymentId}: " . $e->getMessage());
            throw $e;
        }
    }
}
