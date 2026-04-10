<?php
error_reporting(0);
session_start();
if ($_SESSION['formadepag'] == '1') {  
          $access_token = $_SESSION['tokenaccess'];
                      if (isset($_SESSION['payment_id'])) {
                        $url = "https://api.mercadopago.com/v1/payments/" . $_SESSION['payment_id'];
                        $token = $access_token;
                        $header = array(
                          'Authorization: Bearer ' . $token,
                          'Content-Type: application/json'
                        );
                        set_time_limit(0); // Limite de tempo de execução: 2h. Deixe 0 (zero) para sem limite
                        ignore_user_abort(true);
          
          
                        $ch = curl_init();
          
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_ENCODING, '');
                        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                        $result = curl_exec($ch);
          
                        curl_close($ch);
                        $status = json_decode($result);
                        $login = $_SESSION['login'];
                        $senha = $_SESSION['senha'];
          
                        if ($status->status == "approved") {
                          echo 'Aprovado';
                        }
                      }
                      //paghiper ---------------------------- 
}elseif ($_SESSION['formadepag'] == '2') {
                      $transaction_id = $_SESSION['payment_id'];
                      $api_key = $_SESSION['acesstokenpaghiper'];
                      $token = $_SESSION['tokenpaghiper'];
                      $data = array(
                          'transaction_id' => $transaction_id,
                          'apiKey' => $api_key,
                          'token' => $token
                      );
                      $json_data = json_encode($data);
                      $headers = array(
                          'Content-Type: application/json',
                          'Content-Length: ' . strlen($json_data)
                      );
                      $url = 'https://pix.paghiper.com/invoice/status/';
                      $ch = curl_init($url);
                      curl_setopt($ch, CURLOPT_POST, true);
                      curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                      $result = curl_exec($ch);
                      $json = json_decode($result, true);
                      curl_close($ch);
                      $status = $json['status_request']['status'];
                      if ($status == "paid") { 
                        echo 'Aprovado';
                      } 
                    }
          ?>