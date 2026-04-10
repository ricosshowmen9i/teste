<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio409597($input)
    {
        ?>
    
<?php 
error_reporting(0);
session_start();
include 'index.php';
#timezone 
date_default_timezone_set('America/Sao_Paulo');
require_once '../vendor/pix/autoload.php';
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sqlvezesuso = "SELECT * FROM cupons";
    $resultvezesuso = $conn->query($sqlvezesuso);
    
    if ($resultvezesuso->num_rows > 0) {
        while ($rowvezesuso = $resultvezesuso->fetch_assoc()) {
            $vezesuso[] = $rowvezesuso;
        }
    
        foreach ($vezesuso as $cupom) {
            if ($cupom['usado'] >= $cupom['vezesuso']) {
                $cupomId = $cupom['id'];
                $sqlDelete = "DELETE FROM cupons WHERE id = $cupomId";
                $conn->query($sqlDelete);
            }
        }
    }
//consulta o cupom
$valor = $_SESSION['valor'];
$sql3 = "SELECT * FROM cupons WHERE cupom = '$_SESSION[cupom]' AND byid = $_SESSION[byid]";
$result3 = $conn->query($sql3);
//desconto em porcentagem
if ($result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        $desconto = $row['desconto'];
        $valor = $valor - ($valor * $desconto / 100);
        $valor = number_format($valor, 2, '.', '');
    }
}
$sql = "SELECT * FROM accounts WHERE id = '$_SESSION[byid]'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $_SESSION['tokenaccess'] = $row['accesstoken'];
        $_SESSION['valorusuario'] = $row['valorusuario'];
        $_SESSION['tokenaccess'] = $row['accesstoken'];
$_SESSION['formadepag'] = $row['formadepag'];
$_SESSION['email'] = $row['contato'];
$_SESSION['nome'] = $row['nome'];
$_SESSION['acesstokenpaghiper'] = $row['acesstokenpaghiper'];
$_SESSION['tokenpaghiper'] = $row['tokenpaghiper'];
    }
}


$_SESSION['valor'] = $valor;

$access_token = $_SESSION['tokenaccess'];

// Cria um objeto DateTime com a data e hora atuais
if ($_SESSION['formadepag'] == 1){
$dt = new DateTime();

// Adiciona um intervalo de 30 minutos
$interval = date_interval_create_from_date_string('30 minutes');
$dt->add($interval);

// Formata a data e hora no formato esperado
$formatted_date = $dt->format('Y-m-d\TH:i:s.000O');

MercadoPago\SDK::setAccessToken($access_token);

$payment = new MercadoPago\Payment();
 $payment->transaction_amount = $valor;
 $payment->description = "Renovação do usuario ".$_SESSION['login']."";
 $payment->payment_method_id = "pix";
 $payment->date_of_expiration = $formatted_date;
 $payment->payer = array(
    "email" => 'suporte@atlaspainel.com.br'
 );

 $payment->save();
 $_SESSION['expiracaopix'] = $payment->date_of_expiration;
 $_SESSION['payment_id'] = $payment->id;
 $_SESSION['qr_code_base64'] = $payment->point_of_interaction->transaction_data->qr_code_base64;
 $_SESSION['qr_code'] = $payment->point_of_interaction->transaction_data->qr_code;
}elseif ($_SESSION['formadepag'] == 2){
    $idpedido = rand(1000000000, 9999999999);
    //valoradd para centavos
$valor_em_centavos = round($valor * 100);
//se valoremcentavos for menor que 3 reais ele vai cobrar 3 reais
if ($valor_em_centavos < 300) {
    echo "<script>alert('Valor minimo para pagamento e de R$3,00')</script>";
    echo "<script>window.location = ('../revenda.php')</script>";
    exit;
}
$data = array(
  'apiKey' => $_SESSION['acesstokenpaghiper'], // sua apiKey
  'order_id' => $idpedido,
  'payer_email' => $_SESSION['email'],
  'payer_name' => $_SESSION['nome'],    
  'payer_cpf_cnpj' => '74293930043', // cpf ou cnpj
  'payer_phone' => '1140638785', // fixou ou móvel
  'notification_url' => 'https://mysite.com/notification/paghiper/',
  'shipping_methods' => 'PAC',
  'number_ntfiscal' => $idpedido,
  'fixed_description' => true,
  'days_due_date' => '1', // dias para vencimento do Pix
  'items' => array(
      array ('description' => "Renoval do usuario ".$_SESSION['login']."",
      'quantity' => '1',
'item_id' => '1',
'price_cents' => $valor_em_centavos),
  ),
);
$data_post = json_encode( $data );
$url = "https://pix.paghiper.com/invoice/create/";
$mediaType = "application/json"; // formato da requisição
$charSet = "UTF-8";
$headers = array();
$headers[] = "Accept: ".$mediaType;
$headers[] = "Accept-Charset: ".$charSet;
$headers[] = "Accept-Encoding: ".$mediaType;
$headers[] = "Content-Type: ".$mediaType.";charset=".$charSet;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$json = json_decode($result, true);
// captura o http code
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if($httpCode == 201):
$_SESSION['payment_id'] = $json['pix_create_request']['transaction_id'];
$_SESSION['qr_code_base64'] = $json['pix_create_request']['pix_code']['qrcode_base64'];
$_SESSION['qr_code'] = $json['pix_create_request']['pix_code']['emv'];
//converter para variavel
//echo $result;
else:
echo $result;
endif;
}
 $login = $_SESSION['login'];
$texto = 'Renovação do usuario '.$login.'';

date_default_timezone_set('America/Sao_Paulo');


//armazena em pagamentos o id do pagamento , iduser, valor, texto
//consulta se ele ja tem um pagamento pendente
$sql2 = "SELECT * FROM pagamentos WHERE iduser = '$_SESSION[id]' AND status = 'Aguardando Pagamento'";
$result2 = $conn->query($sql2);
//se tiver um pagamento pendente ele exclui
if ($result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $access_token_cancel = $row['access_token'];
        $data = array(
            "status" => "cancelled"
        );
        $json_data = json_encode($data);
        $headers = array(
            "Authorization: Bearer " . $access_token_cancel,
            "Content-Type: application/json"
        );
        $payment_id = $row['idpagamento'];
        // Define as opções da requisição cURL
        $options = array(
            CURLOPT_URL => "https://api.mercadopago.com/v1/payments/" . $payment_id,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true
        );
        
        // Inicializa a sessão cURL
        $curl = curl_init();
        
        // Define as opções da sessão cURL
        curl_setopt_array($curl, $options);
        
        // Executa a requisição cURL e armazena a resposta
        $response = curl_exec($curl);
        
        // Fecha a sessão cURL
        curl_close($curl);
        $sql3 = "DELETE FROM pagamentos WHERE idpagamento = '$row[idpagamento]'";
        $result3 = $conn->query($sql3);
    }
}
$data = date('d-m-Y H:i:s');
//armazena o pagamento
if ($_SESSION['formadepag'] == 2){
    $access_token = $_SESSION['acesstokenpaghiper'];
}
$sql10 = "INSERT INTO pagamentos SET valor = '$valor', login = '$login', texto = '$texto', iduser = '$_SESSION[id]', byid = '$_SESSION[byid]', data = '$data', idpagamento = '$_SESSION[payment_id]', status = 'Aguardando Pagamento', tipo = 'Renovacao Usuario', access_token = '$access_token', tokenpaghiper = '$_SESSION[tokenpaghiper]', formadepag = '$_SESSION[formadepag]'";
$result10 = $conn->query($sql10);
echo "<script>window.location = ('pagamento.php')</script>";
?>
                       <?php
    }
    aleatorio409597($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
