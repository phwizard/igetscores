<?

require('inc/config.php');


OAuthStore::instance('MySQL', array('conn' => $mysql_connect));


$server = new OAuthServer();

	try
	{
		$server->authorizeVerify();
		$server->authorizeFinish(true, 1);
	}
	catch (OAuthException $e)
	{
		header('HTTP/1.1 400 Bad Request');
		header('Content-Type: text/plain');
		
		echo "Failed OAuth Request: " . $e->getMessage();
	}
	exit;


?>