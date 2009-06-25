<?

 // DB connection

 $mysql_host = 'localhost';
 $mysql_dbname = '';
 $mysql_user = '';
 $mysql_password = '';

 $mysql_connect = mysql_connect($mysql_host, $mysql_user, $mysql_password);
 mysql_select_db($mysql_dbname);


 // launch OAuth

 require_once dirname(__FILE__) . '/../oauth-php/library/OAuthServer.php';


 require_once dirname(__FILE__) . '/../oauth-php/library/OAuthRequest.php';
 require_once dirname(__FILE__) . '/../oauth-php/library/OAuthRequester.php';
 require_once dirname(__FILE__) . '/../oauth-php/library/OAuthRequestSigner.php';
 require_once dirname(__FILE__) . '/../oauth-php/library/OAuthRequestVerifier.php';



?>