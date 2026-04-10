<?php
require_once __DIR__ . '/Logger.php';

class MercadoPagoAPI
{
    /**
     * Busca informações do pagamento no Mercado Pago
     */
    public static function getPaymentFromMercadoPago($paymentId, $accessToken)
    {
        $url = "https://api.mercadopago.com/v1/payments/{$paymentId}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("Erro HTTP {$httpCode}: " . $response);
        }

        $payment = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
        }

        return $payment;
    }
}
