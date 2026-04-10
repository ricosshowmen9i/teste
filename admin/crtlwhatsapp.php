<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio200513($input)
    {
        ?>
    
<?php

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
//include('headeradmin2.php');
error_reporting(0);

//se nao tiver logado redireciona para o login


if ($_POST['action'] == 'Ativado') {
    $sql = "UPDATE whatsapp SET ativo = '1'";
    if (mysqli_query($conn, $sql)) {
        echo "Tabela whatsapp atualizada com sucesso!";
    } else {
        echo "Erro ao atualizar tabela whatsapp: " . mysqli_error($conn);
    }
} elseif ($_POST['action'] == 'Desativado') {
    $sql = "UPDATE whatsapp SET ativo = '0'";
    if (mysqli_query($conn, $sql)) {
        echo "Tabela whatsapp atualizada com sucesso!";
    } else {
        echo "Erro ao atualizar tabela whatsapp: " . mysqli_error($conn);
    }
} elseif (isset($_POST['id'])) {
    $id = $_POST['id'];
    mysqli_set_charset($conn, "utf8mb4");
    // Prepara a consulta para obter os detalhes da mensagem com o ID fornecido
    $stmt = $conn->prepare("SELECT * FROM mensagens WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Crie um array com os detalhes da mensagem
        $detalhes = array(
            'mensagem' => $row['mensagem'],
            'funcao' => $row['funcao'],
            'ativo' => $row['ativo'],
            'hora' => $row['hora'],
        );

        // Retorna os detalhes da mensagem como resposta JSON
        header('Content-Type: application/json');
        echo json_encode($detalhes);
        exit;
    }
} else {
    echo "<script>window.location.href='whatsconect.php';</script>";
}


?>
                       <?php
    }
    aleatorio200513($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
