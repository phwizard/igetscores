<?

error_reporting(0);
define("DEBUG", 0);

// get highscores script
// by Taras Filatov, www.injoit.com


header ("content-type: text/xml");
require('inc/config.php');

// initialize OAuth
$store = OAuthStore::instance('MySQL', array('conn' => $mysql_connect));
$server = new OAuthServer();

try 
{
    if(!DEBUG){
        $subgame_id = $server->getParam('subgame_id');
        $name = urldecode($server->getParam('name'));
        $device_id = $server->getParam('device_id');
        
        $interval = $server->getParam('interval');
        $limit = $server->getParam('limit');
        $limit_above = $server->getParam('limit_above');
        $limit_below = $server->getParam('limit_below');
    }else{
        $subgame_id = 4;
        $interval = "month";
        $preview = 0;
    }
    
    // N records to skip (start = 10, limit = 10 - will show results 11-21)
    $offset = $server->getParam('offset');
    
    // if set, will return XML as if score inserted (return value should contain the score)
    $preview = $server->getParam('preview');

    // in in preview mode - get parameters custom for this current game (if any)
    $sql = "select field_name from custom_fields_names, subgames where (subgames.id = $subgame_id AND subgames.game_id = custom_fields_names.game_id)";
    // echo $sql; 
    $r2 = mysql_query($sql);      
    if (mysql_num_rows($r2)>0) {
        while ($f2 = mysql_fetch_array($r2)) {
            $preview_custom_params[$f2['field_name']] = $server->getParam($f2['field_name']);
            // echo 'getting param: '.$f2['field_name']. ' equal to '.$server->getParam($f2['field_name']);
        }
    }


    $sql = "select game_id from subgames where (id = $subgame_id)";
    // echo $sql;
    $r = mysql_query($sql);
    $f = mysql_fetch_row($r);
    
    if(!DEBUG){
        $server->authorizeVerifyAcc($f[0]);
    }
    
    // print_r($_SESSION);
    
    if(!DEBUG){
        $consumer_key =  $_SESSION['verify_oauth_consumer_key'];
    } else {
        $consumer_key = "icombatkey";
    }


//		$key   = $store->updateConsumer($_POST, 1, true);
//	if ($server->verifyIfSigned())
//	{
//		$token = $server->getParam('oauth_token', true);
//		$rs    = $store->getConsumerAccessToken($token);
/*	       
		$c = @$store->getConsumer($key);
		$id = @$store->getConsumer($user_id);
		echo 'Your consumer key is: <strong>' . $c['consumer_key'] . '</strong><br />';
		echo 'Your consumer secret is: <strong>' . $c['consumer_secret'] . '</strong><br />';
*/
//		echo 'Your ID is: <strong>' . $rs['consumer_key'] . '</strong><br />';
 // print implode("&", $total);
// print_r($total);

 
include 'xml_template.php';

$xml = new SimpleXMLElement($xmlstr);

if ($subgame_id)
{
/*    $sql = "select scores.* from scores, subgames, oauth_server_registry where (scores.subgame_id = '$subgame_id' AND scores.subgame_id = subgames.id AND subgames.game_id = oauth_server_registry.osr_id AND oauth_server_registry.osr_consumer_key = '$consumer_key' ";    
    switch ($interval) {
            case 'day' : 
                        $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR))";
                        break;
            case 'week' : 
                        $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY))";
                        break;
            case 'month' : 
                        $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                        break;
            
            case 'year' : 
                        $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 12 MONTH))";
                        break;
    }    
    $sql .= ") order by value desc";

 // echo $sql;


  //if (!$preview && !($limit_above && $limit_below)) // select everything if in preview mode, will cut off later
  //{
//    if ($offset) $sql .= " limit $offset, ".($limit+$offset);
//    elseif ($limit) $sql .= " limit $limit"; 
//  }
 
    $r = mysql_query($sql);
    
    $i = 0;
    // Store results in $scores array
    while ($f = mysql_fetch_array($r)) {
        echo "$i<br>";
        unset($custom_keys);
        
        $scores[$i]['name'] = $f['name'];
        $scores[$i]['email'] = $f['email'];
        $scores[$i]['value'] = $f['value'];
        $scores[$i]['datetime'] = $f['timestamp'];   
        $scores[$i]['country_code'] = $f['country_code'];   
        $scores[$i]['device_id'] = $f['device_id'];
        
        $sql = "select distinct custom_fields_names.field_name, custom_fields_values.field_value  
        from custom_fields_values, custom_fields_names where (score_id = ".$f['id']." 
        AND custom_fields_values.field_id = custom_fields_names.id)";
        // echo $sql;
        $r3 = mysql_query($sql);
        while ($f3 = mysql_fetch_array($r3)) {
            $scores[$i][$f3['field_name']] = $f3['field_value'];
            $custom_keys[] = $f3['field_name'];
        }               
        $i++;        
    }
*/
    // load custom fields
    $sql = "
        select
              s.id as score_id,
              cfn.field_name as field_name,
              cfv.field_value as field_value
        from custom_fields_values cfv
        inner join custom_fields_names cfn on cfv.field_id=cfn.id
        inner join scores s on cfv.score_id=s.id
        where s.id in (
                select
                      s.id
                from scores s
                inner join subgames sg on sg.id = s.subgame_id
                inner join oauth_server_registry osr on osr.osr_id = sg.game_id
                where
                s.subgame_id = {$subgame_id} 
                AND osr.osr_consumer_key = '{$consumer_key}'
    ";
    switch ($interval) {
            case 'day' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR))";
                        break;
            case 'week' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY))";
                        break;
            case 'month' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                        break;
            
            case 'year' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 12 MONTH))";
                        break;
    }    
    $sql .= ")";
    
    $cf_res = mysql_query($sql);
    $cf = array();
    while($cf_row = mysql_fetch_array($cf_res)){
        $cf[$cf_row['score_id']][$cf_row['field_name']] = $cf_row['field_value']; 
    }
    
    // load scores
    $sql = "
        select
              s.id,      
              s.name,
              s.email,
              s.value,
              s.timestamp,
              s.country_code,
              s.device_id
        from scores s
        inner join subgames sg on sg.id = s.subgame_id
        inner join oauth_server_registry osr on osr.osr_id = sg.game_id
        where
        s.subgame_id = {$subgame_id}
        AND osr.osr_consumer_key = '{$consumer_key}'
    ";    
    switch ($interval) {
            case 'day' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR))";
                        break;
            case 'week' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY))";
                        break;
            case 'month' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                        break;
            
            case 'year' : 
                        $sql .= "AND (s.timestamp > DATE_SUB(NOW(), INTERVAL 12 MONTH))";
                        break;
    }    
    $sql .= " order by value desc";
    $scores_res = mysql_query($sql);

    $i = 0;
    while ($f = mysql_fetch_array($scores_res)) {
        //echo "$i\n";
        unset($custom_keys);
        
        $scores[$i]['name'] = $f['name'];
        $scores[$i]['email'] = $f['email'];
        $scores[$i]['value'] = $f['value'];
        $scores[$i]['datetime'] = $f['timestamp'];   
        $scores[$i]['country_code'] = $f['country_code'];   
        $scores[$i]['device_id'] = $f['device_id'];
        
        foreach($cf[$f['id']] as $key => $value){
            $scores[$i][$key] = $value;
            $custom_keys[] = $key;            
        }
        $i++;        
    }

 // Add 'Your score' record in Preview mode
 if ($preview)
 {
   if ($name) $scores[$i]['name'] = $name;
     else $name = 'Your score';
   $scores[$i]['name'] = $name;
   $scores[$i]['email'] = '';
   $scores[$i]['value'] = $preview;
   $scores[$i]['datetime'] = date("Y-m-d H:i:s");

   // display custom fields
   for ($n=0;$n<sizeof($custom_keys);$n++)
    {
      $scores[$i][$custom_keys[$n]] = $preview_custom_params[$custom_keys[$n]];
    }

 }



 function aSortBySecondIndex($multiArray, $secondIndex) {
    while (list($firstIndex, ) = each($multiArray))
        $indexMap[$firstIndex] = $multiArray[$firstIndex][$secondIndex];
    arsort($indexMap);
    while (list($firstIndex, ) = each($indexMap))
        if (is_numeric($firstIndex))
            $sortedArray[] = $multiArray[$firstIndex];
        else $sortedArray[$firstIndex] = $multiArray[$firstIndex];
    return $sortedArray; 
 }

  // Check if we have scores to work with   
  if ( sizeof($scores) > 0 )
  {    

     // Sort results by value (bigger values go first)
     $scores = aSortBySecondIndex($scores, 'value');

     $start_position = 0;
     $finish_position = sizeof($scores);
     $newest_result_time = 0;

        // find user's position (to be used to display "own" flag)


        if ($preview)
        {

           for ($i=0; $i<sizeof($scores); $i++)
           {
            if ($scores[$i]['name'] == $name && $scores[$i]['value'] == $preview) $user_pos = $i + 1;
           }

	}
	else
	{
           for ($i=0; $i<sizeof($scores); $i++)
           {
            if ($scores[$i]['device_id'] == $device_id) 
              {

               // make sure we get the position of the most recent result achieved from this device
	        if (strtotime($scores[$i]['datetime']) > $newest_result_time)
		 {
		  $user_pos = $i + 1;
		  $newest_result_time = strtotime($scores[$i]['datetime']);
		 }

              } 

           }

        }


     // if limits above and below are set, let's cut all the results that are outside the given interval
     if ($limit_above && $limit_below)
     {
        $start_position = $user_pos - $limit_above;
        if ($start_position < 0) $start_position = 0;
         
        $finish_position = $user_pos + $limit_below;
        if ($finish_position > sizeof($scores)) $finish_position = sizeof($scores);
     }
     elseif ($limit)
     {
      // if $limit is set then we cut off the records after the limit number 
      // BUT we add the user's record in the last row no matter what his position is

        $finish_position = $limit;

         
     }



     $scores_count = 0;

     
     for ($i=$start_position; $i<$finish_position; $i++)
     {

        // when $limit is given and user's result doesn't fit, display his record in the last row anyway
 	if ($limit && ($user_pos > $finish_position) && ($i == $finish_position - 1))
          $i = $user_pos - 1;

      //  $score = $xml->game_scores->addChild('score');
        $score = $xml->addChild('score');

        // add 'own' attribute - allows client application to highlight user's own score
        if ($user_pos) {
             if ($i == ($user_pos - 1))             
                $score->addAttribute('own', '1');
 	}

        $score->addChild('position', $i);  
        $score->addChild('name', $scores[$i]['name']);  
        $score->addChild('email', $scores[$i]['email']);    
        $score->addChild('value', $scores[$i]['value']);    
        $score->addChild('datetime', $scores[$i]['datetime']);    

        // add 'minutes ago' server related time
        $minutes_ago =  round ((time() - strtotime($scores[$i]['datetime'])) / 60);
        $score->addChild('minutes_ago', $minutes_ago);    

        $score->addChild('country_code', $scores[$i]['country_code']);    

        // add custom fields
        for ($n=0;$n<sizeof($custom_keys);$n++)
        {
         $score->addChild($custom_keys[$n], $scores[$i][$custom_keys[$n]]);  
        }


        $scores_count++;
     }






  }
  else $scores_count = 0; // no scores in DB

  //   print_r($scores);

 // record total number of results
 $xml['count'] = $scores_count;

 $error_flag = 0;

 // process error messages

    switch ($error_flag)
    {
     case 4: $error_message = 'An error occured - parameters missing.'; break;

     case 0: $error_message = 'Scores returned.'; break;

     default: $error_message = 'Unrecognized error.'; break;
    }
  

    $xml['error'] = $error_flag;
    $xml['error_message'] = $error_message;    



 echo $xml->asXML();


// } // verify if signed


}

  
} catch (OAuthException $e) {
  print($e->getMessage() . "\n<hr />\n");
  print_r($req);
  die();
}



?>
