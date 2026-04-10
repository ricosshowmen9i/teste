<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio512179($input)
    {
        ?>
    
<?php
ignore_user_abort(true);
set_time_limit(0);
session_start();

include '../AegisCore/conexao.php';
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
 
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

date_default_timezone_set('America/Sao_Paulo');
$dominioserver = 'apiwhats.atlaspainel.com.br';
//update atribuidos 
$dataset = date('Y-m-d H:i:s', strtotime('+2 day'));

$remove = "UPDATE atribuidos SET notificado = 'nao' WHERE expira > '$dataset'";
$result = $conn->query($remove);

$remove2 = "UPDATE ssh_accounts SET notificado = 'nao' WHERE expira > '$dataset'";
$result2 = $conn->query($remove2);


    $datainter = date('Y-m-d', strtotime('+1 day'));

    $sqlnotify = "SELECT atribuidos.*, whatsapp.token, whatsapp.sessao, accounts.login, accounts.senha, accounts.whatsapp AS numerowhatsapp
    FROM atribuidos
    INNER JOIN whatsapp ON atribuidos.byid = whatsapp.byid
    INNER JOIN accounts ON atribuidos.byid = accounts.id
    WHERE DATE(atribuidos.expira) = '{$datainter}' AND atribuidos.notificado = 'nao' AND accounts.id != '1'";

    $resultnotify = $conn->query($sqlnotify);
    if ($resultnotify->num_rows > 0) {
        while ($row = $resultnotify->fetch_assoc()) {
            $numerowpp = $row['numerowhatsapp'];
                    $sqlnotifymsn = "SELECT * FROM mensagens WHERE funcao = 'revendaexpirada' AND ativo = 'ativada' AND byid = '{$row['byid']}'";
                    $resultnotifymsn = $conn->query($sqlnotifymsn);
                    $rowmsn = $resultnotifymsn->fetch_assoc();
                        $tokenwpp = $row['token'];
                        $sessaowpp = $row['sessao'];
                        $numerowpp = $row['numerowhatsapp'];
                        //se o numero conter + remove
                        $numerowpp = str_replace("+", "", $numerowpp);
                        $mensagem = $rowmsn['mensagem'];
                        $horaEnvio = $rowmsn['hora']; 
                        $horaAtual = date('H:i');
            if ($horaAtual > $horaEnvio) {
                if (!empty($mensagem) && !empty($numerowpp)) {
                            $mensagem = strip_tags($mensagem);
                            $mensagem = str_replace("<br>", "\n", $mensagem);
                            $mensagem = str_replace("<br><br>", "\n", $mensagem);
                            $expira = $row['expira'];
                            $expira_formatada = date('d/m/Y', strtotime($expira));
                            $row['expira'] = $expira_formatada;                            
                            // Personalize a mensagem com as informações do cliente
                            $mensagem = str_replace("{login}", $row['login'], $mensagem);
                            $mensagem = str_replace("{usuario}", $row['login'], $mensagem);
                            $mensagem = str_replace("{senha}", $row['senha'], $mensagem);
                            $mensagem = str_replace("{validade}", $row['expira'], $mensagem);
                            $mensagem = str_replace("{limite}", $row['limite'], $mensagem);
                            $mensagem = str_replace("{dominio}", $_SERVER['HTTP_HOST'], $mensagem);

                            $mensagem = addslashes($mensagem);

                                $urlsend = "https://{$dominioserver}/message/sendText/{$sessaowpp}";
                                $headerssend = array(
                                    'accept: */*',
                                    'Authorization: Bearer '.$tokenwpp,
                                    'Content-Type: application/json'
                                );

                                // Monta os dados para o envio
                                $data = array(
                                    "number" => $numerowpp,
                                    "textMessage" => array("text" => $mensagem),
                                    "options" => array(
                                        "delay" => 0,
                                        "presence" => "composing"
                                    )
                                );                                

                                $ch = curl_init($urlsend);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerssend);
                                $response = curl_exec($ch);
                                curl_close($ch);
                    if ($response) {
                                    $response = json_decode($response, true);
                        if ($response['status'] == 'PENDING') {
                               $enviadosql = "UPDATE atribuidos SET notificado = 'sim' WHERE userid = '{$row['userid']}'";
                            if (mysqli_query($conn, $enviadosql)) {
                                            echo "Notificação enviada com sucesso aha!";
                            } else {
                                            echo "Erro ao atualizar tabela atribuidos: " . mysqli_error($conn);
                           } 
                    }
                    }
                }
            }
        }
    }

                $datainterssh = date('Y-m-d', strtotime('+1 day'));
                //consulta vencidos ssh
                $notifyuser = "SELECT * FROM ssh_accounts WHERE DATE(expira) = '{$datainterssh}' AND notificado = 'nao'";
                $resultnotifyuser = $conn->query($notifyuser);
            if ($resultnotifyuser->num_rows > 0) {
            
                while ($rowuser = $resultnotifyuser->fetch_assoc()) {
            //consulta mensagem
            $sqlnotifymsnuser = "SELECT * FROM mensagens WHERE funcao = 'contaexpirada' AND ativo = 'ativada' AND byid = '{$rowuser['byid']}'";
            $resultnotifymsnuser = $conn->query($sqlnotifymsnuser);
            $rowmsnuser = $resultnotifymsnuser->fetch_assoc();
            //consulta whatsapp
            $sqlnotifywhatsapi = "SELECT * FROM whatsapp WHERE byid = '{$rowuser['byid']}'";
            $resultnotifywhatsapi = $conn->query($sqlnotifywhatsapi);
            $rownotifywhatsapi = $resultnotifywhatsapi->fetch_assoc();
            if ($rownotifywhatsapi['sessao'] != '' && $rownotifywhatsapi['token'] != '' && $rowuser['whatsapp'] != '') {
            $numerowppuser = $rowuser['whatsapp'];
            $mensagemuser = $rowmsnuser['mensagem'];
            $horaEnvio = $rowmsnuser['hora']; 
            $sessaowpp = $rownotifywhatsapi['sessao'];
            $tokenwpp = $rownotifywhatsapi['token'];
            $horaAtual = date('H:i');
            if ($horaAtual > $horaEnvio) {
                if (!empty($mensagemuser) && !empty($numerowppuser)) {
                $mensagemuser = strip_tags($mensagemuser);
                $mensagemuser = str_replace("<br>", "\n", $mensagemuser);
                $mensagemuser = str_replace("<br><br>", "\n", $mensagemuser);
                //inverte a validade
                $expira = $rowuser['expira'];
                $expira_formatada = date('d/m/Y', strtotime($expira));
                $rowuser['expira'] = $expira_formatada;

                // Personalize a mensagem com as informações do cliente
                $mensagemuser = str_replace("{login}", $rowuser['login'], $mensagemuser);
                $mensagemuser = str_replace("{usuario}", $rowuser['login'], $mensagemuser);
                $mensagemuser = str_replace("{senha}", $rowuser['senha'], $mensagemuser);
                $mensagemuser = str_replace("{validade}", $rowuser['expira'], $mensagemuser);
                $mensagemuser = str_replace("{limite}", $rowuser['limite'], $mensagemuser);
                $mensagemuser = str_replace("{dominio}", $_SERVER['HTTP_HOST'], $mensagemuser);
                //remover + do numero 
                $numerowppuser = str_replace("+", "", $numerowppuser);
                $mensagemuser = addslashes($mensagemuser);

                    $urlsend = "https://{$dominioserver}/message/sendText/{$sessaowpp}";
                    $headerssend = array(
                        'accept: */*',
                        'Authorization: Bearer '.$tokenwpp,
                        'Content-Type: application/json'
                    );

                    $data = array(
                        "number" => $numerowppuser,
                        "textMessage" => array("text" => $mensagemuser),
                        "options" => array(
                            "delay" => 0,
                            "presence" => "composing"
                        )
                    );    

                    $ch = curl_init($urlsend);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerssend);
                    $response = curl_exec($ch);
                    curl_close($ch);

                        if ($response) {
                        $response = json_decode($response, true);
                        if ($response['status'] == 'PENDING') {
                             $enviadosql = "UPDATE ssh_accounts SET notificado = 'sim' WHERE id = '{$rowuser['id']}'";
                                if (mysqli_query($conn, $enviadosql)) {
                                echo "Notificação enviada com sucesso!";
                                } else {
                                echo "Erro ao atualizar tabela ssh_accounts: " . mysqli_error($conn);
        
                                } 
                            } 
                    } else {
                    }
                }
            }
        }
    }
    }



?>
                            
                       <?php
    }
    aleatorio512179($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
