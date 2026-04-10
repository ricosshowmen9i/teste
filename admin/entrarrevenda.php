<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio735520($input)
    {
        ?>

<?php
    session_start();
    include_once("../AegisCore/conexao.php");
    $conexao = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    $id = $_SESSION['identrarrevenda'];

    $sql = "SELECT * FROM accounts WHERE id = '$id'";
    $result = mysqli_query($conexao, $sql);
    $user_data = mysqli_fetch_array($result);

    // ✅ Guarda o token/sessão do admin ANTES de limpar, para poder voltar
    $token_admin        = $_SESSION['token'];
    $tokenatual_admin   = $_SESSION['tokenatual'];
    $sgd_admin          = $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'];

    // ✅ Busca dados do atribuidos do revendedor para popular a sessão corretamente
    $sql_atr = "SELECT * FROM atribuidos WHERE userid = '$id'";
    $result_atr = mysqli_query($conexao, $sql_atr);
    $row_atr = mysqli_fetch_assoc($result_atr);

    // ✅ Limpa toda a sessão anterior (evita mistura de dados admin/revendedor)
    session_unset();

    // ✅ Repõe os dados do revendedor
    $_SESSION['login']   = $user_data['login'];
    $_SESSION['senha']   = $user_data['senha'];
    $_SESSION['iduser']  = $user_data['id'];
    $_SESSION['byid']    = $user_data['byid'];

    // ✅ Repõe dados do atribuidos na sessão
    if ($row_atr) {
        $_SESSION['expira']  = $row_atr['expira'];
        $_SESSION['limite']  = $row_atr['limite'];
        $_SESSION['tipo']    = $row_atr['tipo'];
    }

    // ✅ Repõe o token do sistema para que as verificações de segurança funcionem
    $_SESSION['token']                          = $token_admin;
    $_SESSION['tokenatual']                     = $tokenatual_admin;
    $_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe'] = $sgd_admin;

    // ✅ Marca que é o admin navegando como revendedor (para mostrar botão "Voltar")
    $_SESSION['admin564154156'] = true;

    // ✅ Reseta o timer de inatividade para evitar expiração imediata
    $_SESSION['last_activity'] = time();
    $_SESSION['LAST_ACTIVITY'] = time();

    echo "<script>window.location.href='../home.php';</script>";
    ?>
                       <?php
    }
    aleatorio735520($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>