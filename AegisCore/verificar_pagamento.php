<?php
error_reporting(0);
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$conn) {
    die("Erro de conexão");
}

$payment_id = $_SESSION['payment_id'] ?? '';

if (empty($payment_id)) {
    echo "<script>alert('Nenhum pagamento em andamento!'); window.location.href='home.php';</script>";
    exit();
}

// Buscar status do pagamento
$sql = "SELECT * FROM pagamentos WHERE idpagamento = '$payment_id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    if ($row['status'] == 'Aprovado') {
        echo "<script>
            alert('✅ Pagamento confirmado com sucesso!');
            window.location.href='home.php';
        </script>";
    } else {
        echo "<script>
            alert('⏳ Pagamento ainda não confirmado. Aguarde alguns minutos e tente novamente.');
            window.location.href='pagamento.php';
        </script>";
    }
} else {
    echo "<script>
        alert('Pagamento não encontrado!');
        window.location.href='home.php';
    </script>";
}
?>