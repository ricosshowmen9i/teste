<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio483287($input)
    {
        ?>
    
<?php

//error_reporting(0);
include('../AegisCore/conexao.php');
//ini_set('display_errors',1); ini_set('display_startup_erros',1); error_reporting(E_ALL);//force php to show any error message

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

backup_tables($dbhost,$dbuser,$dbpass,$dbname);

function backup_tables($host,$user,$pass,$name){

    $link = mysqli_connect($host,$user,$pass);
    mysqli_select_db($link, $name);
        $tables = array();
        $result = mysqli_query($link, 'SHOW TABLES');
        $i=0;
        while($row = mysqli_fetch_row($result))
        {
            $tables[$i] = $row[0];
            $i++;
        }
    $return = "";
    foreach ($tables as $table) {
        $result = mysqli_query($link, 'SELECT * FROM ' . $table);
        $num_fields = mysqli_num_fields($result);
        $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
        $row2 = mysqli_fetch_row(mysqli_query($link, 'SHOW CREATE TABLE ' . $table));
        $return .= "\n\n" . $row2[1] . ";\n\n";
    
        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = mysqli_fetch_row($result)) {
                $return .= 'INSERT INTO ' . $table . ' VALUES(';
                for ($j = 0; $j < $num_fields; $j++) {
                    if (isset($row[$j])) {
                        $escapedValue = mysqli_real_escape_string($link, $row[$j]);
                        $return .= '"' . $escapedValue . '"';
                    } else {
                        $return .= '""';
                    }
                    if ($j < ($num_fields - 1)) {
                        $return .= ',';
                    }
                }
                $return .= ");\n";
            }
        }
        $return .= "\n\n\n";
    }
    
    // Save file
    $handle = fopen('atlasbackup.sql', 'w+');
    fwrite($handle, $return);
    fclose($handle);
    
}

$dominioserver = $_SERVER['SERVER_NAME'];
$sqladmin = "SELECT * FROM accounts WHERE id = 1";
$resultadmin = mysqli_query($conn, $sqladmin);
$rowadmin = mysqli_fetch_assoc($resultadmin);
$numeroadmin = $rowadmin['whatsapp'];

$sqltokenadmin = "SELECT * FROM whatsapp WHERE byid = 1";
$resulttokenadmin = mysqli_query($conn, $sqltokenadmin);
$rowtokenadmin = mysqli_fetch_assoc($resulttokenadmin);
$token = $rowtokenadmin['token'];
$sessaowpp = $rowtokenadmin['sessao'];
//replace +
$numeroadmin = str_replace("+", "", $numeroadmin);

$dominio = $_SERVER['SERVER_NAME'];
//verifica se tem ssl
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $dominio = "https://".$dominio;
} else {
    $dominio = "http://".$dominio;
}
//path do arquivo
$dominioserver = "apiwhats.atlaspainel.com.br";
$path = $dominio."/admin/atlasbackup.sql";
$urlsend = "https://$dominioserver/message/sendMedia/$sessaowpp";
$data = array(
    "number" => $numeroadmin,
    "mediaMessage" => array(
        "mediatype" => "document",
        "fileName" => "atlaspainel.sql",
        "caption" => "Backup do banco de dados do painel Atlas Painel",
        "media" => $path
    ),
    "options" => array(
        "delay" => 0,
        "presence" => "composing"
    )
);

$headers = array(
    'Accept: application/json',
    "Authorization: Bearer $token",
    'Content-Type: application/json'
);

$ch = curl_init($urlsend);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);


unlink('atlasbackup.sql');
?>
                       <?php
    }
    aleatorio483287($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
