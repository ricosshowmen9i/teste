<?php
session_start();
error_reporting(0);
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Pegar parâmetros
$plano_id = isset($_GET['plano']) ? intval($_GET['plano']) : 0;
$token = isset($_GET['ref']) ? mysqli_real_escape_string($conn, $_GET['ref']) : '';

if (empty($plano_id) || empty($token)) {
    die("Link inválido!");
}

// Buscar revendedor pelo token
$sql_link = "SELECT l.*, a.login as revendedor, a.accesstoken, a.public_key, a.descricao_fatura 
             FROM links_venda l 
             JOIN accounts a ON l.revendedor_id = a.id 
             WHERE l.token = '$token' AND l.ativo = 1";
$result_link = $conn->query($sql_link);

if ($result_link->num_rows == 0) {
    die("Link de vendas inválido ou expirado!");
}

$link = $result_link->fetch_assoc();
$revendedor_id = $link['revendedor_id'];

// Buscar plano
$sql_plano = "SELECT * FROM planos WHERE id = '$plano_id' AND ativo = 1";
$result_plano = $conn->query($sql_plano);

if ($result_plano->num_rows == 0) {
    die("Plano não encontrado!");
}

$plano = $result_plano->fetch_assoc();

// Processar compra
if (isset($_POST['finalizar_compra'])) {
    $nome_cliente = mysqli_real_escape_string($conn, $_POST['nome']);
    $email_cliente = mysqli_real_escape_string($conn, $_POST['email']);
    $whatsapp_cliente = mysqli_real_escape_string($conn, $_POST['whatsapp']);
    
    // Gerar ID do pedido
    $pedido_id = uniqid() . rand(1000, 9999);
    
    // Aqui você integraria com o Mercado Pago
    // Por enquanto, vamos apenas registrar
    
    $_SESSION['pedido'] = [
        'id' => $pedido_id,
        'plano' => $plano,
        'revendedor' => $revendedor_id,
        'cliente' => [
            'nome' => $nome_cliente,
            'email' => $email_cliente,
            'whatsapp' => $whatsapp_cliente
        ]
    ];
    
    header("Location: pagamento.php?pedido=" . $pedido_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo $plano['nome']; ?></title>
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
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .checkout-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--tertiary) 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .card-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body {
            padding: 30px;
        }

        .plano-info {
            text-align: center;
            margin-bottom: 30px;
        }

        .plano-nome {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .plano-preco {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .plano-detalhes {
            list-style: none;
            margin: 20px 0;
        }

        .plano-detalhes li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .plano-detalhes i {
            color: var(--primary);
            font-size: 18px;
            width: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
        }

        .form-group label i {
            color: var(--primary);
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(65,88,208,0.1);
        }

        .btn-finalizar {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-finalizar:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(65,88,208,0.4);
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

        .seguranca-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            border-radius: 30px;
            padding: 10px 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #64748b;
        }

        .seguranca-badge i {
            color: var(--success);
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkout-grid">
            <!-- Coluna do Plano -->
            <div class="checkout-card">
                <div class="card-header">
                    <h2>Resumo do Pedido</h2>
                    <p>Revendedor: <?php echo $link['revendedor']; ?></p>
                </div>
                <div class="card-body">
                    <div class="plano-info">
                        <div class="plano-nome"><?php echo $plano['nome']; ?></div>
                        <div class="plano-preco">R$ <?php echo number_format($plano['preco'], 2, ',', '.'); ?></div>
                        <div class="plano-periodo">por <?php echo $plano['duracao_dias']; ?> dias</div>
                    </div>
                    
                    <ul class="plano-detalhes">
                        <li><i class='bx bx-check-circle'></i> <?php echo $plano['limite_conexoes']; ?> conexões simultâneas</li>
                        <li><i class='bx bx-time'></i> <?php echo $plano['duracao_dias']; ?> dias de acesso</li>
                        <li><i class='bx bx-shield'></i> Suporte técnico incluso</li>
                        <li><i class='bx bx-credit-card'></i> Pagamento via Mercado Pago</li>
                    </ul>

                    <div class="seguranca-badge">
                        <i class='bx bx-lock-alt'></i>
                        <span>Pagamento 100% seguro processado pelo Mercado Pago</span>
                    </div>
                </div>
            </div>

            <!-- Coluna do Formulário -->
            <div class="checkout-card">
                <div class="card-header">
                    <h2>Dados do Cliente</h2>
                    <p>Preencha seus dados para continuar</p>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label><i class='bx bx-user'></i> Nome Completo</label>
                            <input type="text" name="nome" class="form-control" required placeholder="Digite seu nome completo">
                        </div>

                        <div class="form-group">
                            <label><i class='bx bx-envelope'></i> E-mail</label>
                            <input type="email" name="email" class="form-control" required placeholder="seu@email.com">
                        </div>

                        <div class="form-group">
                            <label><i class='bx bxl-whatsapp'></i> WhatsApp</label>
                            <input type="text" name="whatsapp" class="form-control" required placeholder="5511999999999">
                            <small style="color: #64748b; margin-top: 5px; display: block;">Apenas números, com DDD</small>
                        </div>

                        <button type="submit" name="finalizar_compra" class="btn-finalizar">
                            <i class='bx bx-cart'></i> Finalizar Compra
                        </button>
                    </form>

                    <div style="text-align: center; margin-top: 20px;">
                        <a href="javascript:history.back()" class="btn-voltar">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>