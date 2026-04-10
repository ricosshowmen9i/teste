<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio236476($input)
    {
        ?>
    
<?php 
error_reporting(0);
session_start();
include 'header2.php';
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
require_once '../vendor/pix/autoload.php';
#timezone
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
    if (!file_exists('../admin/suspenderrev.php')) {
        exit ("<script>alert('Token Invalido!');</script>");
      }else{
        include_once '../admin/suspenderrev.php';
        
      }
      if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
        if (function_exists('security')) {
            security();
        } else {
            echo "<script>alert('Token Inválido!');</script>";
            echo "<script>location.href='../index.php';</script>";
            $telegram->sendMessage([
                'chat_id' => '2017803306',
                'text' => "O domínio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " inválido!"
            ]);
            $_SESSION['token_invalido_'] = true;
            exit;
        }
      }
date_default_timezone_set('America/Sao_Paulo');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
$sql4 = "SELECT * FROM accounts WHERE id = '$_SESSION[byid]'";
$result4 = $conn->query($sql4);
if ($result4->num_rows > 0) {
    while ($row4 = $result4->fetch_assoc()) {
        $_SESSION['access_token'] = $row4['accesstoken'];
        $_SESSION['valorcredito'] = $row4['mainid'];
        $_SESSION['acesstokenpaghiper'] = $row4['acesstokenpaghiper'];  
        $_SESSION['formadepag'] = $row4['formadepag'];
        $_SESSION['email'] = $row4['contato'];
        $_SESSION['nome'] = $row4['nome'];
        $_SESSION['tokenpaghiper'] = $row4['tokenpaghiper'];
    }
}
$sql34 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[byid]'";
$result34 = $conn->query($sql34);
if ($result34->num_rows > 0) {
    while ($row34 = $result34->fetch_assoc()) {
        $_SESSION['limite'] = $row34['limite'];
    }
}
if ($_SESSION['byid'] == 1){
    $_SESSION['limite'] = 100000;
}
if ($_SESSION['typecont'] == 'Credito'){
if ($_SESSION['limite'] < $_POST['addquantidade']) {
    echo '<script>alert("Seu Revendedor não tem Creditos suficiente!")</script>';
    echo '<script>window.location.href = "adicionar.php";</script>';
    exit();
}
}

function anti_sql($input)
{
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

//se o cupom for valido ele vai fazer o desconto



$access_token = $_SESSION['access_token'];
$_SESSION['access_token'] = $access_token;
$addquantidade = $_POST['addquantidade'];
$valorlogin = $_SESSION['valorrevenda'];
$data = $_SESSION['validade'];
$data = date('d/m/Y', strtotime($data));
$data = explode('/', $data);
$data = $data[2] . '-' . $data[1] . '-' . $data[0];
$data = strtotime($data);
$hoje = strtotime(date('Y-m-d'));
$dias = floor(($data - $hoje) / (60 * 60 * 24));
$valordia = $valorlogin / 30;
$valor1 = $valordia * $dias;
$texto = "O Usuario '$_SESSION[login] Adicionou $addquantidade Creditos ao painel";
$valoradd = $valor1 * $addquantidade;
//converter para ,00 para o mercado pago
$valoradd = number_format($valoradd, 2, '.', '');


$_SESSION['addquantidade'] = $addquantidade;
$access_token = $_SESSION['access_token'];


if ($_SESSION['typecont'] == 'Credito'){
    $valoradd = $_SESSION['addquantidade'] * $_SESSION['valorcredito'];
    $valoradd = number_format($valoradd, 2, '.', '');
    $_SESSION['valoradd'] = $valoradd;
    $_SESSION['valor'] = $valoradd;
    $tipo = 'Adicionar Credito';
} else {
    $tipo = 'Adicionar Limite';
    if ($valoradd < 0) {
        echo "<script>alert('Renove Primeiro')</script>";
        echo "<script>window.location = ('pagamento.php')</script>";
        exit;
    }
}

$cupom = $_POST['cupom'];
//antisql 
$cupom = anti_sql($cupom);
$sql22 = "SELECT * FROM cupons WHERE cupom = '$cupom' AND byid = $_SESSION[byid]";
$result22 = $conn->query($sql22);
if ($result22->num_rows > 0) {
    while ($row22 = $result22->fetch_assoc()) {
        $desconto = $row22['desconto'];
        $valoradd = $valoradd - ($valoradd * $desconto / 100);
        $valoradd = number_format($valoradd, 2, '.', '');
        $sql23 = "UPDATE cupons SET usado = usado + 1 WHERE cupom = '$cupom'";
        $result23 = $conn->query($sql23);
    }
}
$login = $_SESSION['login'];

if ($_SESSION['formadepag'] == 1){
// Cria um objeto DateTime com a data e hora atuais
$dt = new DateTime();

// Adiciona um intervalo de 30 minutos
$interval = date_interval_create_from_date_string('30 minutes');
$dt->add($interval);

// Formata a data e hora no formato esperado
$formatted_date = $dt->format('Y-m-d\TH:i:s.000O');

$_SESSION['valoradd'] = $valoradd;
MercadoPago\SDK::setAccessToken($access_token);

$payment = new MercadoPago\Payment();
 $payment->transaction_amount = $valoradd;
 $payment->description = "Adição de $addquantidade Logins - Usuario: $_SESSION[login]";
 $payment->payment_method_id = "pix";
 $payment->date_of_expiration = $formatted_date;

 $payment->payer = array(
    "email" => "suporte@atlaspainel.com.br",
    "first_name" => "Venda",
    "last_name" => "Painel",
    "identification" => array(
        "type" => "CPF",
        "number" => "19119119100"
     ),
    "address"=>  array(
        "zip_code" => "06233200",
        "street_name" => "Av. das Nações Unidas",
        "street_number" => "3003",
        "neighborhood" => "Bonfim",
        "city" => "Osasco",
        "federal_unit" => "SP"
     )
  );


 $payment->save();


$_SESSION['expiracaopix'] = $payment->date_of_expiration;
$_SESSION['payment_id'] = $payment->id;
$_SESSION['qr_code_base64'] = $payment->point_of_interaction->transaction_data->qr_code_base64;
$_SESSION['qr_code'] = $payment->point_of_interaction->transaction_data->qr_code;
}elseif ($_SESSION['formadepag'] == 2){
    $idpedido = rand(1000000000, 9999999999);
    $_SESSION['valoradd'] = $valoradd;
    //valoradd para centavos
$valor_em_centavos = round($valoradd * 100);
//se valoremcentavos for menor que 3 reais ele vai cobrar 3 reais
if ($valor_em_centavos < 300) {
    echo "<script>alert('Valor minimo para pagamento e de R$3,00')</script>";
    echo "<script>window.location = ('pagamento.php')</script>";
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
      array ('description' => "Adição de $addquantidade Logins - Usuario: $_SESSION[login]",
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

$texto = 'Adição de '.$_SESSION['addquantidade'].' Logins - ' . $_SESSION['login'];

date_default_timezone_set('America/Sao_Paulo');



$sql2 = "SELECT * FROM pagamentos WHERE iduser = '$_SESSION[iduser]' AND status = 'Aguardando Pagamento'";
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
//armazena em pagamentos o id do pagamento , iduser, valor, texto
$data = date('d-m-Y H:i:s');
if ($_SESSION['formadepag'] == 2){
    $access_token = $_SESSION['acesstokenpaghiper'];
}
//anti sql
$valoradd = anti_sql($valoradd);
$login = anti_sql($login);
$texto = anti_sql($texto);
$data = anti_sql($data);
$tipo = anti_sql($tipo);
$sql10 = "INSERT INTO pagamentos SET valor = '$valoradd', formadepag = '$_SESSION[formadepag]', login = '$login', texto = '$texto', iduser = '$_SESSION[iduser]', byid = '$_SESSION[byid]', data = '$data', idpagamento = '$_SESSION[payment_id]', status = 'Aguardando Pagamento', tipo = '$tipo', addlimite = '$_SESSION[addquantidade]', access_token = '$access_token', tokenpaghiper = '$_SESSION[tokenpaghiper]'";
$result10 = $conn->query($sql10);
echo "<script>window.location = ('adcpagamento.php')</script>";
?>
                       <?php
    }
    aleatorio236476($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
