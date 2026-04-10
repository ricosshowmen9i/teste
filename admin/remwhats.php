<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio527974($input)
    {
        ?>
    
<?php

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
include('headeradmin2.php');

    //truncate table whatsapp
    $sql = "TRUNCATE TABLE whatsapp";
    if (mysqli_query($conn, $sql)) {
        echo "Tabela whatsapp limpa com sucesso!";
    } else {
        echo "Erro ao limpar tabela whatsapp: " . mysqli_error($conn);
    }


?>
                       <?php
    }
    aleatorio527974($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
