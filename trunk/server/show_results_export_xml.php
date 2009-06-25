<? 

 // High scores -> XML export script
 // by Taras Filatov, www.injoit.com, 2009


session_start();

header ("content-type: text/xml");
header('Content-Disposition: attachment; filename="igetscores_backup.xml"');

require('inc/config.php');
include 'xml_template.php';

$subgame_id = $_SESSION['subgame_id'];

$xml = new SimpleXMLElement($xmlstr);
	

if ($subgame_id)
{


 $sql = "select scores.* from scores, subgames, oauth_server_registry where (scores.subgame_id = '$subgame_id' AND scores.subgame_id = subgames.id AND subgames.game_id = oauth_server_registry.osr_id ";

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

 $sql .= ") order by value desc";

 // echo $sql;

  if (!$preview && !($limit_above && $limit_below)) // select everything if in preview mode, will cut off later
  {
    if ($offset) $sql .= " limit $offset, ".($limit+$offset);
    elseif ($limit) $sql .= " limit $limit"; 
  }

 
 $r = mysql_query($sql);

/*
 // record total number of results
 if ($preview)
  $xml['count'] = mysql_num_rows($r)+1;
 else
  $xml['count'] = mysql_num_rows($r);
*/

/*
 // check if there are custom fields for this game 

 $sql = "select id, field_name from custom_fields_names, subgames where (subgames.id = $subgame_id AND subgames.game_id = custom_fields_names.game_id)";
 $r2 = mysql_query($sql);
 
 if (mysql_num_rows($r2)>0)
 {
  $f2 = mysql_fetch_array($r2);
 }

*/




 $i = 0;

 // Store results in $scores array
 while ($f = mysql_fetch_array($r))
 {
   unset($custom_keys);

   $scores[$i]['name'] = $f['name'];
   $scores[$i]['email'] = $f['email'];
   $scores[$i]['value'] = $f['value'];
   $scores[$i]['datetime'] = $f['timestamp'];   
   $scores[$i]['country_code'] = $f['country_code'];   
   $i++;

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


 }

 // Add 'Your score' record in Preview mode
 if ($preview)
 {
   if ($name) $scores[$i]['name'] = $name;
     else $name = 'Your score';
   $scores[$i]['name'] = $name;
   $scores[$i]['email'] = '';
   $scores[$i]['value'] = $preview;
   $scores[$i]['datetime'] = '';   

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


     // if limits above and below are set, let's cut all the results that are outside the given interval
     if ($limit_above && $limit_below)
     {

        // find user's position
        for ($i=0; $i<sizeof($scores); $i++)
        {
         if ($scores[$i]['name'] == $name) $user_pos = $i + 1;
        }

        $start_position = $user_pos - $limit_above;
        if ($start_position < 0) $start_position = 0;
         
        $finish_position = $user_pos + $limit_below;
        if ($finish_position > sizeof($scores)) $finish_position = sizeof($scores);

     }

     $scores_count = 0;
     
     for ($i=$start_position; $i<$finish_position; $i++)
     {
      //  $score = $xml->game_scores->addChild('score');
        $score = $xml->addChild('score');

        if ($user_pos) {
             if ($i == ($user_pos - 1))             // add 'own' attribute - allows client to highlight user's own score
                $score->addAttribute('own', '1');
 	}

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





?>