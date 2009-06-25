<?

 // add highscore script
 // by Taras Filatov, www.injoit.com

 header ("content-type: text/xml");
 require('inc/config.php');

// OAuth

OAuthStore::instance('MySQL', array('conn' => $mysql_connect));

$server = new OAuthServer();


try {

      // store non-OAuth parameters

      $device_id = $server->getParam('device_id');
      $subgame_id = $server->getParam('subgame_id');
      $name = urldecode($server->getParam('name'));
      $email = urldecode($server->getParam('email'));
       if (!$email) $email = '';
 
      $value = $server->getParam('value');
      $one_user = $server->getParam('one_user');

      $limit_above = $server->getParam('limit_above');
      $limit_below = $server->getParam('limit_below');


      // store parameters custom for this current game (if any)

      $sql = "select field_name from custom_fields_names, subgames where (subgames.id = $subgame_id AND subgames.game_id = custom_fields_names.game_id)";
      // echo $sql; 
      $r2 = mysql_query($sql);      
      if (mysql_num_rows($r2)>0)
      {
       while ($f2 = mysql_fetch_array($r2))
       {
         $add_custom_params[$f2['field_name']] = $server->getParam($f2['field_name']);
         $custom_keys[] = $f2['field_name'];
	 // echo 'adding param: '.$f2['field_name']. ' equal to '.$server->getParam($f2['field_name']);       
       }
      }


      // verify access token

      $sql = "select game_id from subgames where (id = $subgame_id)";
      // echo $sql;
      $r = mysql_query($sql);
      $f = mysql_fetch_row($r);
 
      $server->authorizeVerifyAcc($f[0]);
      

      $consumer_key =  $_SESSION['verify_oauth_consumer_key'];

if ($consumer_key)
{


   // $one_user = 0;  // if set, high scores table allows adding 1 result per device only 
    $need_to_update = 0;
    $error_flag = '';

    
   // if ($_GET['one_user'] == '1') $one_user = 1;

    // check if all mandatory parameters have been supplied
    if ($subgame_id && $name && $value)
    {
    
        if ($one_user)
        {
         /*
         $sql = "select from scores, subgames, oauth_server_registry where (scores.subgame_id = '$subgame_id' AND device_id = '$device_id' AND scores.subgame_id = subgames.id AND subgames.game_id = oauth_server_registry.osr_id AND oauth_server_registry.osr_consumer_key = '$consumer_key')";
         $r = mysql_query($sql); 
         if (mysql_num_rows($r) > 0) $need_to_update = 1; 
          // determined that a results from device already added 
          // and need to update it with a new result 
	 */
        }                                                                                       

        if ($need_to_update)
        {
          /*
          $sql = "update scores where (scores.subgame_id = '$subgame_id' AND device_id = '$device_id' AND scores.subgame_id = subgames.id AND subgames.game_id = oauth_server_registry.osr_id AND oauth_server_registry.osr_consumer_key = '$consumer_key') set 
                    (email = '$email', name = '$name', value = '$value', timestamp = NOW() )";

          if ($r = mysql_query($sql)) $error_flag = 2;
	  */
        }
        else 
        {
          // check if this consumer has access to this subgame
          $sql = "select subgames.id from subgames, oauth_server_registry where (subgames.id = '$subgame_id' AND subgames.game_id = oauth_server_registry.osr_id AND oauth_server_registry.osr_consumer_key = '$consumer_key') limit 1";
          // echo $sql;
          $r = mysql_query($sql); 
          if (mysql_num_rows($r) > 0)
          {

	     $sIP = $_SERVER [ 'REMOTE_ADDR' ]; // get user's IP


	     // add score record	

             $sql = "insert into scores (subgame_id, device_id, email, name, value, timestamp, ip)
	      	   values ('$subgame_id', '$device_id', '$email', '$name', '$value', NOW(), '$sIP' )";


             if ($r = mysql_query($sql)) 
             {
              $error_flag = 0;
              
              // store the ID of the created record
              $inserted_id = mysql_insert_id();


              // add custom params records 

   	      for ($i=0; $i<sizeof($add_custom_params); $i++)
 		{
 		  $sql = "select id from custom_fields_names where field_name = '".$custom_keys[$i]."'";
 		  $r = mysql_query($sql);
 		  $f = mysql_fetch_row($r);

  		  $sql = "insert into custom_fields_values (score_id, field_id, field_value) 
 		    values ('$inserted_id', '".$f[0]."', '".$add_custom_params[$custom_keys[$i]]."') ";

 		  mysql_query($sql);
 		}


	     }		

          }
        }


    } // parameters
    else $error_flag = 4;


    include 'xml_template.php';
    $xml = new SimpleXMLElement($xmlstr);

 
    switch ($error_flag)
    {
     case 4: $error_message = 'An error occured - parameters missing.'; break;

     case 2: $error_message = 'Your score has been successfully updated.'; break;

     case 0: $error_message = 'Your score has been successfully added.'; break;

     default: $error_message = 'Unrecognized error.'; break;
    }

    $xml['error'] = $error_flag;
    $xml['error_message'] = $error_message;    
    

    /////// RETURN INSERTED RESULTS

    
    // if the parameters are given, we will return neighbouring scores as well

    if ($limit_above && $limit_below) // select everything if in preview mode, will cut off later
   // if (!$offset && ($limit || ($limit_above && $limit_below)) ) // select everything if in preview mode, will cut off later
    {

        $sql = "select scores.* from scores, subgames, oauth_server_registry where (subgames.id = '$subgame_id' AND scores.subgame_id = subgames.id AND subgames.game_id = oauth_server_registry.osr_id AND oauth_server_registry.osr_consumer_key = '$consumer_key' ";

        /*
        switch ($interval)
        {
         case 'day' : $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR))";
          break;

         case 'week' : $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY))";
          break;
           
         case 'month' : $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY))";
          break;

         case 'year' : $sql .= "AND (timestamp > DATE_SUB(NOW(), INTERVAL 12 MONTH))";
          break;

         default: break;
        }
        */

        $sql .= ") order by value desc";

        // echo $sql;
        
        $r = mysql_query($sql);

        $i = 0;

        while ($f = mysql_fetch_array($r))
        {
          unset($custom_keys);

          $scores[$i]['id'] = $f['id'];	
          $scores[$i]['name'] = $f['name'];
          $scores[$i]['email'] = $f['email'];
          $scores[$i]['value'] = $f['value'];
          $scores[$i]['datetime'] = $f['timestamp'];   
	  $scores[$i]['country_code'] = $f['country_code'];   

	  // adding custom values 
          $sql = "select distinct custom_fields_names.field_name, custom_fields_values.field_value  
                  from custom_fields_values, custom_fields_names where (score_id = ".$f['id']." 
                  AND custom_fields_values.field_id = custom_fields_names.id)";
          // echo $sql;
          $r3 = mysql_query($sql);
          while ($f3 = mysql_fetch_array($r3))
          {
           $scores[$i][$f3['field_name']] = $f3['field_value'];
           $custom_keys[] = $f3['field_name'];
          }


          $i++;
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

         // Sort results by value (bigger values go first)
         $scores = aSortBySecondIndex($scores, 'value');


         $start_position = 0;
         $finish_position = sizeof($scores);


         // if limits above and below are set, let's cut all the results that are outside the given interval
         if ($limit_above && $limit_below)
         {

            // find user's position
            for ($i=0; $i<sizeof($scores); $i++)
            {
             if ($scores[$i]['id'] == $inserted_id) $user_pos = $i;
            }

            $start_position = $user_pos - $limit_above;
            if ($start_position < 0) $start_position = 0;
             
            $finish_position = $user_pos + $limit_below;
            if ($finish_position > sizeof($scores)) $finish_position = sizeof($scores);

         }

         
         $scores_count = 0;

         for ($i=$start_position; $i<$finish_position; $i++)
         {
            $score = $xml->addChild('score');

            // add 'own' attribute - allows client to highlight user's own score
            if ($scores[$i]['id'] == $inserted_id)
          	   $score->addAttribute('own', '1');

	    $score->addChild('position', $i);  
            $score->addChild('name', $scores[$i]['name']);  
            $score->addChild('email', $scores[$i]['email']);    
            $score->addChild('value', $scores[$i]['value']);    
            $score->addChild('datetime', $scores[$i]['datetime']);    
            $score->addChild('country_code', $scores[$i]['country_code']);    

            // add custom fields
            for ($n=0;$n<sizeof($custom_keys);$n++)
            {
             $score->addChild($custom_keys[$n], $scores[$i][$custom_keys[$n]]);  
            }

            $scores_count++;
         }

        // record total number of results
        $xml['count'] = $scores_count;

     
    }
    /////// END OF RETURN INSERTED RESULTS

    echo $xml->asXML();

} // if consumer_key

  
} catch (OAuthException $e) {
  print($e->getMessage() . "\n<hr />\n");
  print_r($req);
  die();
}



?>