<?

 // a script to update country codes (to be launched by cron)
 // by Taras Filatov, www.injoit.com, 2009


 require('inc/config.php');

 $n = 0;

 $sql = "select id, ip from scores where (country_code = '')";
 $r = mysql_query($sql);
 while ($f = mysql_fetch_row($r))
 {
  $sql = "update scores set country_code = '".countryCodeFromIP($f[1])."' where (id = '".$f[0]."')";
  // echo $sql;
  mysql_query($sql);
  $n++;
 }

 echo 'Country codes added for <b>'.$n.'</b> score records';


function countryCodeFromIP($ipAddr)
{
  //function to find <strong class="highlight">country</strong> and city from <strong class="highlight">IP</strong> address
  //Developed <strong class="highlight">by</strong> Roshan Bhattarai [url]http://roshanbh.com.np[/url]

  //verify the <strong class="highlight">IP</strong> address for the
  ip2long($ipAddr)== -1 || ip2long($ipAddr) === false ? trigger_error("Invalid IP", E_USER_ERROR) : "";
  $ipDetail=array(); //initialize a blank array

  //get the XML result from hostip.info
  $xml = file_get_contents("http://api.hostip.info/?ip=".$ipAddr);


  //get the <strong class="highlight">country</strong> name inside the node <countryName> and </countryName>

  preg_match("@<countryName>(.*?)</countryName>@si",$xml,$cc_match);
//  preg_match("@<countryAbbrev>(.*?)</countryAbbrev>@si",$xml,$cc_match);
  $country_code = $cc_match[1]; //assing the <strong class="highlight">country</strong> code to array

  //return the array containing city, <strong class="highlight">country</strong> and <strong class="highlight">country</strong> code
  return $country_code;
}


?>