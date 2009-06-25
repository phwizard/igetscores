<?

require('inc/config.php');

OAuthStore::instance('MySQL', array('conn' => $mysql_connect));

$server = new OAuthServer();

$server->accessToken();



?>