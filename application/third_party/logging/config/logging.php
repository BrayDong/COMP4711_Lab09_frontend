<?php

# Kevin Traas :: April 9, 2010
# 
# These are all things I want set for every web-based application I write.
#------------------------------------------------------------------------------

global $logging;
$logging = array(
    "enabled"   => false,
    "logfile"   => "/Applications/XAMPP/logs/".$_SERVER['SERVER_NAME'].".log",
    "owner"     => get_current_user(),
    "group"     => get_current_user(),
    "perms"     => 0660,
    "echoToo"   => true,
    "asHTML"    => true,
    "append"    => true,
    "myProfiler" => false,
);

//echo get_current_user ();
//echo $logging["logfile"];
//logger("test");

//file_put_contents($loggingDefaults['logfile'], "test", FILE_APPEND);

const MIN_MESSAGE_UPDATE_MS = 10; // skip sending messages when last one was less than <this> ms ago









// }}}

?>
