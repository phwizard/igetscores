

 *** [ iGetScores Online High Scores system ] ***

 This is an open-source project distributed under GPL license.

 Project started by Injoit company, www.injoit.com, (c) 2009, in order to provide developers 
 with an  alternative and scalable solution to add the online high scores support to their 
 iPhone games.

 Please feel free to use, test and contribute to the project. You may modify the source codes, 
 use the project for commercial needs and re-distribute it but you have to keep the original 
 references/copyrights and a link to project's home site. Also please submit all the valuable
 add ons and modifications to the development site for the benefit of the community and further
 development of the project.

 Google code development site:
 http://code.google.com/p/igetscores/

 Official website:
 http://www.igetscores.com


 ** To install ** 


 1. Upload all the files on your webserver via FTP ( for example www.mygame.com/hs/*.* )
    Make sure you're uploading to web folder, usually it would be under /public_html/

 2. Edit /inc/config.php - enter your MySQL database access details

 3. Add update_countries.php to cron jobs. For cPanel equipped hostings you would just go to
    Advanced - Cron jobs and then when in standard view, put in something like this (depends
    on your PHP path and of course your high scores server location):

    /usr/local/bin/php -f /home/igetscor/public_html/hs2/update_countries.php

    Make it run every fifteen minutes or so.

 4. Run the igetscor.sql database dump (either via shell or import it using phpMyAdmin).

 5. Edit the database via phpMyAdmin and add a record for your game in 'oauth_server_registry'.
    Make sure 'osr_id' and 'osr_usa_id_ref' have the same values.

 6. Go to 'subgames' table and add subgames (at least one) for your game. 'game_id' should correspond to 'osr_id' in 'oauth_server_registry'.




 ** Contributors **

 Taras Filatov - web server
 Andrew Kopanev - iPhone client
 Pavel Belevtsev - iPhone client OpenGL sample 

 Join the club!

