<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio589449($input)
    {
        ?>
    
<?php
session_start();
include 'headeradmin2.php';
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
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

$id = anti_sql($_POST['id']);

$sql_pag_cancel = "SELECT * FROM pagamentos WHERE idpagamento = '$id'";
$result_pag_cancel = $conn->query($sql_pag_cancel);
$row = $result_pag_cancel->fetch_assoc();
$access_token = $row['access_token'];
$payment_id = $row['idpagamento'];

        $data = array(
            "status" => "cancelled"
        );
        $json_data = json_encode($data);
        $headers = array(
            "Authorization: Bearer " . $access_token,
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
        ?>
                       <?php
    }
    aleatorio589449($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
