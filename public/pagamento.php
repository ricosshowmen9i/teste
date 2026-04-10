<?php
session_start();
error_reporting(0);
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Verificar se tem pedido na sessão
if (!isset($_SESSION['pedido'])) {
    header("Location: ../index.php");
    exit();
}

$pedido = $_SESSION['pedido'];
$plano = $pedido['plano'];
$cliente = $pedido['cliente'];
$pedido_id = $pedido['id'];

// Buscar credenciais do revendedor
$sql_rev = "SELECT * FROM accounts WHERE id = '{$pedido['revendedor']}'";
$result_rev = $conn->query($sql_rev);
$revendedor = $result_rev->fetch_assoc();

// Configurar Mercado Pago
require_once '../vendor/autoload.php';
MercadoPago\SDK::setAccessToken($revendedor['accesstoken']);

// Criar pagamento
$payment = new MercadoPago\Payment();
$payment->transaction_amount = $plano['preco'];
$payment->description = "Plano: " . $plano['nome'] . " - " . $cliente['nome'];
$payment->payment_method_id = "pix";
$payment->payer = array(
    "email" => $cliente['email']
);

$payment->save();

$qr_code = $payment->point_of_interaction->transaction_data->qr_code;
$qr_code_base64 = $payment->point_of_interaction->transaction_data->qr_code_base64;
$payment_id = $payment->id;

// Registrar no banco
$sql_pag = "INSERT INTO pagamentos (idpagamento, login, valor, texto, userid, byid, status, tipo, data) 
            VALUES ('$payment_id', '{$cliente['nome']}', '{$plano['preco']}', 'Compra do plano {$plano['nome']}', '0', '{$pedido['revendedor']}', 'Pendente', 'plano', NOW())";
$conn->query($sql_pag);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - PIX</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
            --light: #f8fafc;
            --border: #eef2f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .payment-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--tertiary) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card-header .valor {
            font-size: 36px;
            font-weight: 800;
        }

        .card-body {
            padding: 30px;
            text-align: center;
        }

        .qrcode-container {
            background: white;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            border: 2px solid var(--border);
        }

        .qrcode {
            max-width: 250px;
            margin: 0 auto;
        }

        .qrcode img {
            width: 100%;
            height: auto;
        }

        .pix-code {
            background: #f8fafc;
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 15px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            margin: 20px 0;
            position: relative;
        }

        .btn-copy {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0;
        }

        .btn-copy:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(65,88,208,0.3);
        }

        .btn-voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            margin-top: 20px;
        }

        .btn-voltar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(220,38,38,0.4);
        }

        .info-pagamento {
            background: #e8f5e9;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-pagamento i {
            font-size: 24px;
        }

        .status-pagamento {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-card">
            <div class="card-header">
                <h2>Pagamento via PIX</h2>
                <div class="valor">R$ <?php echo number_format($plano['preco'], 2, ',', '.'); ?></div>
                <p><?php echo $plano['nome']; ?></p>
            </div>
            <div class="card-body">
                <div class="status-pagamento">
                    <i class='bx bx-time'></i> Aguardando pagamento
                </div>

                <div class="qrcode-container">
                    <div class="qrcode">
                        <img src="data:image/png;base64,<?php echo $qr_code_base64; ?>" alt="QR Code PIX">
                    </div>
                </div>

                <div class="pix-code" id="pixCode">
                    <?php echo $qr_code; ?>
                </div>

                <button class="btn-copy" onclick="copiarPix()">
                    <i class='bx bx-copy'></i> Copiar código PIX
                </button>

                <div class="info-pagamento">
                    <i class='bx bx-check-shield'></i>
                    <span>Após o pagamento, seu acesso será liberado automaticamente em até 1 minuto.</span>
                </div>

                <a href="vendas.php?ref=<?php echo $_GET['ref'] ?? ''; ?>" class="btn-voltar">
                    <i class='bx bx-arrow-back'></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function copiarPix() {
            var pixCode = document.getElementById('pixCode').innerText;
            navigator.clipboard.writeText(pixCode).then(function() {
                swal({
                    title: "PIX copiado!",
                    text: "Código PIX copiado para área de transferência",
                    icon: "success",
                    timer: 2000,
                    buttons: false
                });
            });
        }

        // Verificar status do pagamento a cada 5 segundos
        setInterval(function() {
            // Aqui você faria uma requisição AJAX para verificar o status
            // Por simplicidade, vamos apenas simular
        }, 5000);
    </script>
</body>
</html>