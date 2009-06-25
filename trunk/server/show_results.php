<? 

 // high scores management back end
 // by Taras Filatov, www.injoit.com


if ($_GET['action'] == 'logout')
{

 // Unset all of the session variables.
 $_SESSION = array();

 // If it's desired to kill the session, also delete the session cookie.
 // Note: This will destroy the session, and not just the session data!
 if (@isset($_COOKIE[session_name()])) {
     @setcookie(session_name(), '', time()-42000, '/');
 }

 // Finally, destroy the session.
 @session_destroy();
}


session_start();
require('inc/config.php');
$debug_mode = 0; // when set to 1, we allow to choose any game instead of authentication form 
$customer_id = $_SESSION['customer_id'];
$remove_id = $_GET['remove_id'];
$edit_id = $_GET['edit_id'];

$defImg = 'http://www.simplecoding.org/wp-content/themes/three_cols/images/myphoto.jpg';



// LOGIN FORM 

   // let's store our subgame id in the session 
   if ($_POST['subgame_id']) $_SESSION['subgame_id'] = $_POST['subgame_id'];
   $subgame_id = $_SESSION['subgame_id'];
	

echo '<div align="right"><a href="show_results.php?action=logout">Log out</a></div>';


if ($debug_mode)
{

   echo '<form action = "show_results.php" method="post">
   Select subgame:
   <select name="subgame_id"><option value="">---</option>
   ';

   $sql = "select id, subgame_title from subgames where (1 = 1)";
   $r = mysql_query ($sql);

   while ($f = mysql_fetch_row($r))
   {
    echo '<option value="'.$f[0].'"';
     if ($subgame_id == $f[0]) echo ' selected ';  
    echo '>'.$f[1].'</option>'; 
   }

   echo '</select>';

   echo '<input type="submit" value="Show" /></form>';


}
else // real mode
{

 if (!$_SESSION['customer_id'])
 {

  // print_r($_POST);

   if ($_POST['key'] && $_POST['secret'])
   {

    $sql = "select osr_id from oauth_server_registry where 
                (osr_consumer_key = '".$_POST['key']."' AND osr_consumer_secret = '".$_POST['secret']."')";
    $r = mysql_query($sql);

    if (mysql_num_rows($r)>0)
     {
       $f = mysql_fetch_row($r);
       $_SESSION['customer_id'] = $f[0]; 
       echo 'Logged in';	
     }
    else echo 'Error - log in failed';   
	
    
   }
   else
   {

      echo '<h3>iGetScores -  log in</h3>

      Please enter your consumer key and secret. 

      <form action="show_results.php?action=login" method="post">
       <br />Key: <input type="text" name="key">
       <br />Secret: <input type="password" name="secret">
       <br /><input type="submit" value="Log in" />
      </form>';

   }

 }

 if ($_SESSION['customer_id'])
 { 
 // display limited access selection form 


   echo '<form action = "show_results.php" method="post">
   Select game:
   <select name="subgame_id"><option value="">---</option>
   ';

   $sql = "select id, subgame_title from subgames where (game_id = '".$_SESSION['customer_id']."')";
   $r = mysql_query ($sql);

   while ($f = mysql_fetch_row($r))
   {
    echo '<option value="'.$f[0].'"';
     if ($subgame_id == $f[0]) echo ' selected ';  
    echo '>'.$f[1].'</option>'; 
   }

   echo '</select>';

   echo '<input type="submit" value="Show" /></form>';



 }


}



// REMOVE RECORD

if ($remove_id)
{

 $sql = "delete from scores where (id = $remove_id)";
 $sql2 = "delete from custom_fields_values where (score_id = $remove_id)";

 if ($r = mysql_query($sql) && $r2 = mysql_query($sql2)) echo ' Score record has been removed from DB. '; else echo ' Error - score record has not been removed '; 

}


// EDIT RECORD 

if ($_POST['edit_id'] && $_GET['action']=='save')
{
 // save the edited score record

 // update score itself
 $sql1 = "update scores set device_id = '".$_POST['device_id']."', 
	name = '".$_POST['name']."', 
	email = '".$_POST['email']."', 
	value = '".$_POST['value']."', 
	timestamp = '".$_POST['timestamp']."' where (id = '".$_POST['edit_id']."')";

 // update custom fields

 //for ($i = 0; $i < sizeof($_POST['custom']); $i++)

  $n=0;

  // get names
  $sql = "select id from custom_fields_names where (game_id = '".$_SESSION['customer_id']."')";
  $r = mysql_query($sql);
  while ($f = mysql_fetch_row($r))
   {

     // check if custom field record exists
     //$sql = "select id from custom_fields_values where (score_id = '".$_POST['edit_id']."' AND field_id = '".$_POST['custom'][$n]."')";
     $sql = "select id from custom_fields_values where (score_id = '".$_POST['edit_id']."' AND field_id = '".$f[0]."')";
     $r2 = mysql_query($sql);
     if (mysql_num_rows($r2) > 0)
      $sql2 = "update custom_fields_values set field_value = '".$_POST['custom'][$n]."' where (score_id = '".$_POST['edit_id']."' AND field_id = '".$f[0]."')";
     else
      $sql2 = "insert into custom_fields_values (score_id, field_id, field_value) values ('".$_POST['edit_id']."', '".$f[0]."', '".$_POST['custom'][$n]."')"; 

     // echo $_POST['custom'][$i];
     $n++;

     // echo $sql2;
     if ($r2 = mysql_query($sql2)) $custom_updated = $custom_updated * 1; else $custom_updated = $custom_updated * 0;

   }


 if ($r1 = mysql_query($sql1) && $custom_updated) echo '<br /> Record has been updated '; else echo '<br /> Error - record has not been updated (or has been updated with errors) ';



}
elseif ($edit_id)
{
 // display the form to edit the chosen score record 


 $sql = "select * from scores where (id = '".$edit_id."')";
 $r = mysql_query($sql);
 $f = mysql_fetch_array($r);

 echo '<form action="show_results.php?action=save" method="post">';

 echo '
 <table border="1">
 <tr bgcolor="lightgrey"><td>Device ID</td><td>Name</td><td>E-mail</td>';
 echo '<td>Score</td>';

 // display headers for custom fields
  $sql = "select id, field_name from custom_fields_names where (game_id = '".$_SESSION['customer_id']."')"; 
  $r2 = mysql_query($sql);
  if (mysql_num_rows($r2) > 0)
    { 
      while ($f2 = mysql_fetch_row($r2)) 
           {  echo '<td>'.$f2[1].'</td>'; $custom_fields_ids[] = $f2[0]; }
    }

  echo '<td>Date Time</td><td> --- </td></tr>';

  echo '<tr>';

  echo '<td><input type="text" value="'.$f['device_id'].'" name="device_id" /></td>';

  echo '<td><input type="text" value="'.$f['name'].'" name="name" />';
  echo '</td>';

  echo '<td> <input type="text" value="'.$f['email'].'" name="email" />';

  echo '</td>';

  echo '<td> <input type="text" value="'.$f['value'].'" name="value" /></td>';


 // display values for custom fields
  for ($n = 0; $n < sizeof($custom_fields_ids); $n++)
  {
     $sql = "select field_value from custom_fields_values where (score_id = '".$edit_id."' AND field_id = '".$custom_fields_ids[$n]."')"; 
     // echo $sql;
     $r2 = mysql_query($sql);
     $f2 = mysql_fetch_row($r2);

     echo '<td> <input type="text" value="';
       if ($f2) echo $f2[0];
     echo '" name="custom[]" /></td>';

  }




  echo '<td> <input type="text" value="'.$f['timestamp'].'" name="timestamp" /></td>';

  echo '<td> <input type="hidden" value="'.$edit_id.'" name="edit_id" /></td>';

  echo '<td><center><input type="submit" value="Update" /></center></td>';

  echo '</tr>'; 


 echo '</form>';

}



// ADD RECORD

if ($_GET['action'] == 'add_save')
{

 // insert record itself

 $sql1 = "insert into scores (subgame_id, device_id, email, name, value, timestamp) 
	 values ('$subgame_id', '".$_POST['device_id']."', '".$_POST['email']."', '".$_POST['name']."', 
	 '".$_POST['value']."', NOW())";

 // if ($r = mysql_query($sql)) echo ' Score record has been added. '; else echo ' Error - score record has not been added.';
 
 $r1 = mysql_query($sql1);

 $score_id = mysql_insert_id();

 // insert custom fields


  $n=0;

  // get names
  $sql = "select id from custom_fields_names where (game_id = '".$_SESSION['customer_id']."')";
  $r = mysql_query($sql);
  while ($f = mysql_fetch_row($r))
   {

      $sql2 = "insert into custom_fields_values (score_id, field_id, field_value) values ('$score_id', '".$f[0]."', '".$_POST['custom'][$n]."')"; 

     // echo $_POST['custom'][$i];
     $n++;

     // echo $sql2;
     if ($r2 = mysql_query($sql2)) $custom_inserted = $custom_inserted * 1; else $custom_inserted = $custom_inserted * 0;

   }


 if ($r1 && $custom_inserted) echo '<br /> Record has been added '; else echo '<br /> Error - record has not been added (or has been inserted with errors) ';




}
elseif ($_GET['action'] == 'add')
{
 
 echo '<form action="show_results.php?action=add_save" method="post">';

 echo '
 <table border="1">
 <tr bgcolor="lightgrey"><td>Device ID</td><td>Name</td><td>E-mail</td><td>Score</td>';


 // display headers for custom fields
  $sql = "select id, field_name from custom_fields_names where (game_id = '".$_SESSION['customer_id']."')"; 
  $r2 = mysql_query($sql);
  if (mysql_num_rows($r2) > 0)
    { 
      while ($f2 = mysql_fetch_row($r2)) 
           {  echo '<td>'.$f2[1].'</td>'; $custom_fields_ids[] = $f2[0]; }
    }


 echo '<td>Date Time</td><td> </td></tr>';

  echo '<tr>';

  echo '<td><input type="text" value="" name="device_id" /></td>';

  echo '<td><input type="text" value="" name="name" />';
  echo '</td>';

  echo '<td> <input type="text" value="" name="email" />';

  echo '</td>';

  echo '<td> <input type="text" value="" name="value" /></td>';


 // display values for custom fields
  for ($n = 0; $n < sizeof($custom_fields_ids); $n++)
  {
     $sql = "select field_value from custom_fields_values where (score_id = '".$edit_id."' AND field_id = '".$custom_fields_ids[$n]."')"; 
     // echo $sql;
     $r2 = mysql_query($sql);
     $f2 = mysql_fetch_row($r2);

     echo '<td> <input type="text" value="" name="custom[]" /></td>';

  }


  echo '<td> <input type="text" value="Now" name="timestamp" disabled /></td>';

  echo '<td><center><input type="submit" value="Add" /></center></td>';

  echo '</tr>'; 


 echo '</table></form>';

}





// DISPLAY RECORDS


if ($subgame_id)
{
 // display the table of scores

 unset($custom_fields_ids);
 
 echo '[ <a href="show_results.php?action=add">+ add a score record</a> ]';

 $sql = "select osr_application_title from oauth_server_registry, subgames where (subgames.id = '$subgame_id' AND subgames.game_id = oauth_server_registry.osr_id)";
 $r = mysql_query($sql);
 $f = mysql_fetch_row($r);

 echo '<h3>results for <b>'.$f[0].'</b>: </h3>';

 $sql = "select * from scores where (subgame_id = '$subgame_id') order by value desc";
 $r = mysql_query($sql);

 echo '
 <table border="1">
 <tr bgcolor="lightgrey"><td>Device ID</td><td>Player</td><td>E-mail</td><td>Score</td>';

 // display headers for custom fields
  $sql = "select id, field_name from custom_fields_names where (game_id = '".$_SESSION['customer_id']."')"; 
  // echo $sql;
  $r2 = mysql_query($sql);
  if (mysql_num_rows($r2) > 0)
    { 
      while ($f2 = mysql_fetch_row($r2)) 
           {  echo '<td>'.$f2[1].'</td>'; $custom_fields_ids[] = $f2[0]; }
    }

 echo '<td>Date Time</td><td>Edit?</td><td>Delete?</td></tr>';

 while ($f = mysql_fetch_array($r))
 {
  echo '<tr><td>'.$f['device_id'].'</td><td> <b>'.$f['name'].'</b>';

  echo '<br /> <img src="'.getGravatarUrl($f['email'], $defImg, '90', 'G').'" alt="gravatar" />';

  echo '</td>';
  echo '<td> '.$f['email'];

  echo '</td>';

  echo '<td> '.$f['value'].'</td>';


 // display values for custom fields

  for ($n = 0; $n < sizeof($custom_fields_ids); $n++)
  {
     $sql = "select field_value from custom_fields_values where (score_id = '".$f['id']."' AND field_id = '".$custom_fields_ids[$n]."')"; 
     // echo $sql;
     $r2 = mysql_query($sql);
     $f2 = mysql_fetch_row($r2);
     if ($f2) echo '<td>'.$f2[0].'</td>'; else echo '<td>&nbsp;</td>';
  }



  echo '<td> '.$f['timestamp'].'</td>';

  echo '<td><center><a href="show_results.php?edit_id='.$f['id'].'">E</a></center></td>';

  echo '<td><center><a href="show_results.php?remove_id='.$f['id'].'">x</a></center></td>';

  echo '</tr>'; 

 }

 echo '</table>'; 


echo '
<hr />
[ <a href="show_results_export_xml.php">Export to XML</a> ]';

}


function getGravatarUrl($email, $defImg, $size, $rating) 
{
 return "http://www.gravatar.com/avatar.php?gravatar_id=".md5($email)."&amp;amp;rating=".$rating."&amp;amp;size=".$size."&amp;amp;default=".urlencode($defImg);

}


?>