<?php
/**
 * Created by PhpStorm.
 * User: brayden
 * Date: 2017-12-02
 * Time: 7:18 PM
 */

require_once APPPATH.'third_party/logging/config/logging.php';

# logger() {{{
class Logger {
    const COLOR_BLINK = "\e[5m";
    const COLOR_NOBLINK = "\e[25m";
    const COLOR_DIM = "\e[2m";
    const COLOR_NODIM = "\e[22m";

    const COLOR_LIGHTGREY = "\e[37m";
    const COLOR_LIGHTGREEN = "\e[92m";
    const COLOR_LIGHTRED = "\e[91m";
    const COLOR_LIGHTYELLOW = "\e[93m";
    const COLOR_LIGHTMAGENTA = "\e[95m";
    const COLOR_LIGHTBLUE = "\e[94m";
    const COLOR_LIGHTCYAN = "\e[96m";

    const COLOR_MAGENTA = "\e[35m";
    const COLOR_BLUE = "\e[34m";
    const COLOR_GREEN = "\e[32m";
    const COLOR_YELLOW = "\e[33m";
    const COLOR_CYAN = "\e[36m";
    const COLOR_PINK = "\e[38;5;198m";


    const COLOR_OFF = "\e[0m";

    const COLOR_ERROR = self::COLOR_LIGHTRED;
    const COLOR_WARN = self::COLOR_LIGHTYELLOW;
    const COLOR_GOOD = self::COLOR_LIGHTGREEN;
    const COLOR_DEBUG = self::COLOR_CYAN;

    /*static $COLOR_LIST = [	Logger::COLOR_LIGHTYELLOW, Logger::COLOR_MAGENTA, Logger::COLOR_CYAN, Logger::COLOR_LIGHTBLUE,
        Logger::COLOR_LIGHTGREEN, Logger::COLOR_YELLOW, Logger::COLOR_PINK, Logger::COLOR_GREEN,
        Logger::COLOR_LIGHTCYAN, Logger::COLOR_LIGHTRED];*/

    static function colorList() {
        $disable = [0,7,8,16, 17, 231];
        $max = 230;
        $colors = [];
        for($i=0;$i<=$max; ++$i) {
            if(in_array($i,$disable)) continue;
            $colors[] = "\e[38;5;".$i."m";
        }
        return $colors;
    }

    // only print if in debg mode.
    static function debug($msg) {
        if(!defined("DEBUG_MODE") || !getBoolean(DEBUG_MODE))
            return false;

        $trace = debug_backtrace();
        $dbg =  Logger::COLOR_NODIM . basename( $trace[0]['file'] )
            . Logger::COLOR_DIM . "[" . Logger::COLOR_NODIM . Logger::COLOR_LIGHTMAGENTA  . $trace[0]['line'] . Logger::COLOR_OFF . Logger::COLOR_DIM . "]"
            . self::COLOR_DEBUG . " :: DEBUG ".self::COLOR_DEBUG." :: ";

        if(is_object($msg) || is_array($msg)) {
            $msg = print_r($msg,true);
        }
        logger( $dbg . $msg . self::COLOR_OFF);
        return true;
    }

    static function log($msg, $echoToo=null, $asHTML=null) {
        //echo $_SERVER['REMOTE_ADDR'];
        Global $logging, $auth;

        //print_r($logging);

# 	DO NOT USE PROFILE here.  The Profile class uses logger(), which causes infinite recursion.  Oops!
# 	Global $profile;
# 	if( is_object( $profile ) ) $profile->start();



        $logfile = "/tmp/webapp.log";
        if( $logging['logfile'] ) $logfile = $logging['logfile'];

        $append = null;
        if( $logging['append'] ) $append = FILE_APPEND;

        if( is_null( $echoToo ) )
        {
            if( isset( $logging['echoToo'] ) ) $echoToo = $logging['echoToo'];
            else $echoToo = false;
        }

        if( is_null( $asHTML ) )
        {
            if( isset( $logging['asHTML'] ) ) $asHTML = $logging['asHTML'];
            else $asHTML = true;
        }

        if( is_array( $msg ) || is_object($msg) ) $msg = print_r( $msg, true );		// Convert arrays to a string
        //if( strlen( $msg ) == 0 ) { /* if( is_object( $profile ) ) $profile->stop(); */ return false; }





        // Find platform {{{
        $platform = "WebApp";
        if(!empty($_SESSION['app_platform'])) {
            $platform = $_SESSION['app_platform'];
            $platform = str_ireplace("DeVry", "", $platform);
        }

        //echo "Platform: ". $platform . "<br>";

        $platform = Logger::COLOR_DIM .":" . Logger::COLOR_NODIM . " ".trim($platform);


        // }}}

        // Append username if known {{{
        $username = "";
        if(isset($_SESSION['app_user'])) {
            $username = $_SESSION['app_user'];
        } elseif(isset($_SESSION['app_platform'])) {
            $username = "";
        }

        if( !empty( $username ) ) {
            $userInfo = Logger::COLOR_DIM . "/" . Logger::COLOR_NODIM
                . getClientColor(getClientIP()) . $username . Logger::COLOR_OFF
                . Logger::COLOR_DIM;
        }
        else $userInfo = "";

        // }}}

        // If file does not exist, then create; setting ownership & perms {{{
        if( !file_exists( $logfile ) )
        {
            file_put_contents( $logfile, "", $append );
            @chmod( $logfile, $logging['perms'] );
            @chgrp( $logfile, $logging['group'] );
            @chown( $logfile, $logging['owner'] );

        } // }}}

        // so higher-level exception handlers (eg. mobile apps framework) can report their calling trace instead of themselves.
        $trace = isset($_SESSION['log_trace']) ? $_SESSION['log_trace'] : debug_backtrace();
        if( isset( $_SESSION['log_trace'] ) ) unset( $_SESSION['log_trace'] );

        $msg = paintWords($msg, "^", Logger::COLOR_ERROR); // queries
        $msg = paintWords($msg, "error", Logger::COLOR_ERROR);
        $msg = paintWords($msg, "fail", Logger::COLOR_ERROR);
        $msg = paintWords($msg,"warning", Logger::COLOR_WARN);
        $msg = paintWords($msg,"warn ", Logger::COLOR_WARN);
        $msg = paintWords($msg,"warn()", Logger::COLOR_WARN);
        $msg = paintWords($msg, "success", Logger::COLOR_GOOD);
        $msg = paintWords($msg, "notice", Logger::COLOR_CYAN);
        $msg = paintWords($msg, "debug", Logger::COLOR_DEBUG);


        $ip = getClientIP();
        $ip_bytes = explode(".",$ip);

        $hostname = getClientHostname($ip,false, true);
        if(isVPNIP($ip)) $hostname = "VPN";
        if(isLocalIP($ip) && !empty($hostname)) // if local ip and hostname doesn't contain the ip, show hostname and ip
            $hostname = strtoupper($hostname)
                . Logger::COLOR_DIM . "/$ip" . Logger::COLOR_NODIM;
        //  . Logger::COLOR_DIM  . "/" . $ip_bytes[count($ip_bytes)-2] . "." . $ip_bytes[count($ip_bytes)-1] . Logger::COLOR_NODIM;
        else
            $hostname = $ip;

//	if(stripos($hostname,'devry') === false &&
//		stripos($hostname, '.') !== false)



        if(@$_SESSION['hostname'] == 'RECORD') unset($_SESSION['hostname']);

        //echo "writing to $logfile";

        file_put_contents( $logfile, //$hostname . ":$ip##".
            Logger::COLOR_DIM . date( 'Y-m-d ')
            . Logger::COLOR_NODIM . date('H:i:s' )
            . ": " . getClientColor($ip) . $hostname . Logger::COLOR_OFF
            . $platform
            . $userInfo
            . ": " 	. Logger::COLOR_NODIM . basename( $trace[0]['file'] )
            . Logger::COLOR_DIM . "[" . Logger::COLOR_NODIM . Logger::COLOR_LIGHTMAGENTA  . $trace[0]['line'] . Logger::COLOR_OFF . Logger::COLOR_DIM . "]"
            . ": "	. Logger::COLOR_NODIM . trim($msg)."\n" .  Logger::COLOR_OFF,
            $append );

        if( !$logging['append'] )	// If we just created the file... {{{
        {
            chmod( $logfile, $logging['perms'] );
            chgrp( $logfile, $logging['group'] );
            chown( $logfile, $logging['owner'] );
        } // }}}


        // Dump to screen/console/HTML page too?
        if( $echoToo && $asHTML ) echo "\n<!-- ";
        if( $echoToo ) echo "$msg";							// Echo it out just as it was provided
        if( $echoToo && $asHTML ) echo " -->\n";
# 	if( $echoToo && !$asHTML )
# 		if( trim( strlen( $msg ) ) > 0  &&  $msg[strlen($msg)-1] != "\n" ) echo "\n";		// If string doesn't end with a newline, then add it.

        /* if( is_object( $profile ) ) $profile->stop(); */
        return true;
    }

}
function paintWords($msg, $caseInsensitiveMatch, $color) {

    // Kevin (Nov 16, 2017)
    // There's a bug in this function.
    // If the same word to be painted appears more than once in the line, the second painted word is placed into the message in the wrong position.
    // Example:   logger( __FUNCTION__ . "():  ERROR!  Distance difference is too big!  Refusing to update. \t\t\t*** ERROR ***" );
    // The second (painted) ERROR is placed before the string it's supposed to overwrite.
    // I'm assuming the second replacement isn't taking into account the control characters introduced during the first replacement....
# 	return $msg;


    $msgOrig = $msg;
    $length = strlen($caseInsensitiveMatch);

    $lastPos = 0;
    $positions = array();
    $words = array();

    while (($lastPos = stripos($msg, $caseInsensitiveMatch, $lastPos))!== false) {
        $positions[] = $lastPos;
        $words[$lastPos] = substr($msgOrig,$lastPos,$length);
        $lastPos = $lastPos + $length;
    }

    foreach($positions AS $position) {
        $msg = substr_replace($msg, $color . $words[$position] . Logger::COLOR_OFF,$position,$length);
    }
    /*
        $msg = str_ireplace($caseInsensitiveMatch,
                    $color . stripos($msg,$caseInsensitiveMatch),
                            $caseInsensitiveMatch);*/
    return $msg;
}
function getClientColor($ip) {

    $bytes = explode(".",$ip);
    $hash = 0;
    foreach($bytes AS $byte) {
        $hash += getInt($byte);
    }

    //$md5 = md5($ip);



    // for differentiating users
    $COLOR_LIST = Logger::colorList();
    $client_color = $COLOR_LIST[$hash % count($COLOR_LIST)];

    return $client_color;
}
function getClientHostname($ip, $forceRefresh=false, $returnNull = false) {

    if(stripos(@$_SESSION['hostname'], 'RECORD') !== FALSE || stripos(@$_SESSION['hostname'],"NXDOMAIN") !== FALSE || strpos(@$_SESSION['hostname'], "/") !== FALSE)
        $_SESSION['hostname'] = "";

    if(!$forceRefresh && !empty($_SESSION['hostname'])) {
        //echo "returning cached hostname: $_SESSION[hostname]";
        return $_SESSION['hostname'];
    }

    if(empty($ip)) return null;

    //echo "checking terminals..";

    // check terminal page
    $term = null;

    //echo " running host cmd ";
    $host = trim(trim(trim(`host "$ip" | awk 'NF>1{print \$NF}'`),'.'));
    if(stripos($host,"not found") === FALSE) {

        if(!empty($host)) {
            $host = str_ireplace(".devry.local", "", $host);
        }

        if(empty($host) || stripos($host,"NXDOMAIN") !== FALSE
            || stripos($host, "RECORD") !== FALSE) {
            if($returnNull) return null;
            else return $ip;
        }
        else $_SESSION['hostname'] = $host;
        //echo " returning found host $host \n";
        return $host;
    }


    if($returnNull) return null;

    $_SESSION['hostname'] = $ip;
    return $ip;
}
function logger( $msg, $echoToo=null, $asHTML=null )
{
    return Logger::log($msg, $echoToo, $asHTML);
}
# }}}

function showScriptExecutionTime( $asHTML=true, $echoToo=true, $timeLimit=2 ) # {{{
{
    Global $scriptStartTime;

    $scriptTime = round( ( microtime( true ) - $scriptStartTime ), 2 );

    $s = "";

    $s .= "\n";
    if( $asHTML ) $s .= "<!-- ";
    $s .= "Execution time: " . $scriptTime . "s";
    if( $asHTML ) $s .= " -->";
    $s .= "\n";

    if( $scriptTime > $timeLimit )
        logger( "*** ALERT ***:  Execution time ($scriptTime s) exceeded warning threshold ($timeLimit s)." );

    if( $echoToo ) echo $s;

    return $s;

}
$scriptStartTime = microtime( true );
# }}}

function varSize( &$var ) // {{{
{
    // Return the size (in bytes) of the passed variable.  (Passed by reference to reduce mem usage.)
    // NOTE:  This creates a copy of the variable, so we may run out of memory for very large vars....  8(

    $tmp = null;
    $start = memory_get_usage();
    try { $tmp = unserialize( serialize( $var ) ); } catch( Exception $e ) {}
    $usage = memory_get_usage() - $start;
    unset( $tmp );
    if( $usage <= 0 ) return false;
    return $usage;

} // }}}
function logSize( &$var, $func="", $return=false ) // {{{
{
    Global $profile;
    $profile->start();

    $rows = $bytes = "";
    if( !empty( $func ) ) $func = "$func():";

    if( is_array( $var ) ) $rows = number_format( count( $var ) ) . " Rows;";

    $byteSize = "Bytes";
    $bytes = varSize( $var );
    if( $bytes > 2000 ) { $bytes = $bytes/1024; $byteSize = "KB"; }
    if( $bytes > 2000 ) { $bytes = $bytes/1024; $byteSize = "MB"; }
    if( $bytes > 2000 ) { $bytes = $bytes/1024; $byteSize = "GB"; }
    $bytes = number_format( $bytes ) . " $byteSize";

    $str = "";
    if( !empty( $func ) ) $str .= "$func  ";
    $str .= "Data Size:  ";
    if( !empty( $rows ) ) $str .= "$rows  ";
    if( !empty( $bytes ) ) $str .= "$bytes  ";

    if( $profile->elapsed() > 2 ) logger( __FUNCTION__ . "():  WARNING!  This is severely slowing things down: " . $profile->elapsed() . "s" );
    if( !$return ) logger( $str );

    $profile->stop();
    if( $return ) return $str;
} // }}}


function ShortenText( $text, $chars=25 )  // {{{
{
    // Truncate a string to the specified number of chars
    // Truncate on whole word boundary only.
    // Add ... to the end of strings that need to be truncated.

    if( strlen( $text ) <= $chars ) return $text;

    $text = $text . " ";
    $text = substr( $text, 0, $chars );
    $text = substr( $text, 0, strrpos( $text, ' ' ) );
    $text = $text . "...";

    return $text;
} // }}}

function my_clone( $in ) // {{{
{
    return unserialize( serialize( $in ) );
} // }}}
function my_serialize( $in ) // {{{
{
    // This method "improves" on PHP's built-in serialize() function.
    // serialize() is not space-efficient (no compression) and is not DB query safe
    // This function takes care of these issues....

    return base64_encode( gzcompress( serialize( $in ) ) );
} // }}}
function my_unserialize( $in ) // {{{
{
    return unserialize( @gzuncompress( base64_decode( $in ) ) );
} // }}}

// Time/Date Stuff {{{
function getAgeStr( $start, $end=null, $precision=null ) // {{{
{
    // Return a string loosely describing how long ago (or from now) $start is from $end (or now if end is null)
    // i.e. "2.5 days"
    // i.e. "3.6 hrs ago"
    // precision == number of decimal places....

    // Get Start/End/Diff values {{{
    $start = getTimestamp( $start );

    if( is_null( $end ) ) $end = time();
    else $end = getTimestamp( $end );
    if( empty( $end ) ) $end = time();		// Fail-over to 'now'

    $age = $end - $start;
    // }}}

    // Calculate precision {{{
    if( is_null( $precision ) )
    {
        // Don't show partial minutes
        if( $age < ( 2 * 60 * 60 ) ) $precision = 0;
        // But do show partial hours, days, weeks, years....
        else $precision = 1;
    }
    if( $precision < 0 ) $precision = 0;
    elseif( $precision > 5 ) $precision = 5;
    // }}}

#  	logger( __FUNCTION__ . "():  start=$start, end=$end, precision=$precision, age=$age" );
# 	logger( __FUNCTION__ . "():  " . number_format( round( ($age/60/60),3 ), 3 ) . " hrs" );

    if( $age < 0 )	// Future
    {
        $age = $age * -1;		// Convert to "seconds from now"



        if( $age > (2*365*24*60*60) )		return number_format( round( ($age/365/24/60/60),$precision ), $precision ) . " yrs";
        if( $age > (28*24*60*60) )			return number_format( round( ($age/7/24/60/60),$precision ), $precision ) . " wks";
        if( $age > (2*24*60*60) )			return number_format( round( ($age/24/60/60),$precision ), $precision ) . " days";
        if( $age > (2*60*60) )				return number_format( round( ($age/60/60),$precision ), $precision ) . " hrs";
        if( $age > (2*60) )					return number_format( round( ($age/60),$precision ), $precision ) . " mins";
        return number_format( $age ) . " sec";
    }
    else			// Past
    {
        if( $age > (2*365*24*60*60) )		return number_format( round( ($age/365/24/60/60),$precision ), $precision ) . " yrs ago";
        if( $age > (28*24*60*60) )			return number_format( round( ($age/7/24/60/60),$precision ), $precision ) . " wks ago";
        if( $age > (2*24*60*60) )			return number_format( round( ($age/24/60/60),$precision ), $precision ) . " days ago";
        if( $age > (2*60*60) )				return number_format( round( ($age/60/60),$precision ), $precision ) . " hrs ago";
        if( $age > (2*60) )					return number_format( round( ($age/60),$precision ), $precision ) . " mins ago";
        return number_format( $age ) . " sec ago";
    }

} // }}}

function mins2Str( $in, $long=false ) // {{{
{
    // NOTE: only supports "X mins" or "X hrs, Y mins"
    // in	: minutes


# 	logger("mins2Str in: ".$in);

    $in = abs( getInt( $in ) );

    if( $in < 1 ) return "Online";
    if( $in == 1 ) return "$in min";
    if( $in < 60 ) return "$in mins";

    $h = floor( $in/60 );
    $m = $in - ( $h * 60 );

    $hs = "s"; $ms = "s";
    if( $h == 1 ) $hs = '';
    if( $m == 1 ) $ms = '';

    if( $h >= 48 )
        return round( $h/24, 1 ) . " days";
    elseif( $h >= 5 )
        return "$h hr$hs";
    else
    {
        if( $long ) return "$h hr$hs, $m min$ms";				#  --> 1 hr, 18 mins
        return $h . "." . round($m/60*10,0) . " hrs";			#  --> 1.3 hrs
    }

} // }}}

function getFutureDate( $interval, $now=null ) // {{{
{
    # $interval:  number of days into the future
    # returns a date formatted as YYYY-MM-DD $interval days in the future from right $now.

    $now = empty( $now ) ? time() : getDate2( $now );			// NOTE:  Use empty() in case '0' is passed in....

    return date( 'Y-m-d', strtotime( "+$interval days", $now ) );
} // }}}

function getWorkingDays( $dateA, $dateB, $roundUp=false ) // {{{
{
    // roundUp		- include both first and last day between the two timestamps.
    //				- FALSE:   2015-02-10 - 2015-02-12  ===> 2
    //				- TRUE:    2015-02-10 - 2015-02-12  ===> 3

    Global $profile;
    $profile->start();

    // Add 1 day to make dates inclusive.
    $inclusive = ( $roundUp ? 1 : 0 );		// Set to 1 to make inclusive of last day of work

    // Convert to timestamp (if required)
    if( !is_numeric( $dateA ) ) $dateA = strtotime( $dateA );
    if( !is_numeric( $dateB ) ) $dateB = strtotime( $dateB );

    // Use start/end in calculations so we can reference dateA and dateB again at the end....

    $start = $dateA;
    $end = $dateB;

    if( $start > $end ) { $x = $end; $end = $start ; $start = $x; }

    // Calculate total number of days between the two dates.  {{{

    //$days = ceil( ( ( $end - $start ) / ( 24 * 60 * 60 ) ) + $inclusive );
    $days = date( 'z', $end ) - date( 'z', $start );
    if( $days < 0 ) $days += 365;
    $days += $inclusive;
# 	logger( "days = $days" );

    $fullWeeks = floor( $days / 7 );
    $remainingDays = fmod( $days, 7 );

    $firstDOW = date( "N", $start );
    $lastDOW = date( "N", $end );

    if( $firstDOW <= $lastDOW )
    {
        if( $firstDOW <= 6  &&  6 <= $lastDOW ) $remainingDays--;
        if( $firstDOW <= 7  &&  7 <= $lastDOW ) $remainingDays--;
    }
    else
    {
        if( $firstDOW == 7 )
        {
            $remainingDays--;
            if( $lastDOW == 6 ) $remainingDays--;
        }
        else
        {
            $remainingDays -= 2;
        }
    }
# 	logger( "remainingDays=$remainingDays" );

    $workingDays = $fullWeeks * 5;
    if( $remainingDays > 0 ) $workingDays += $remainingDays;
# 	logger( "workingDays=$workingDays" );
    // }}}

    // Calculate Holiday days {{{

    // If we're only looking for holidays in one year, then reduce data set returned by getHolidayDates()
    $year = ( date( "Y", $start ) == date( "Y", $end ) ? date( "Y", $start ) : null );
    $holidays = getHolidayDates( $year );

    if(		$start  < strtotime( '2011-01-01' )  ||  $start  > strtotime( '2021-12-31' )
        ||	$end < strtotime( '2011-01-01' )  ||  $end > strtotime( '2021-12-31' )  )
    {
        logger( "*************************************************************************************\n\nWARNING from " . __FUNCTION__ . "()\n\nOne of the dates is out of range of the identified holidays.  start=".date('Y-m-d', $start ).", end=".date('Y-m-d', $end )."\n\n****************************************************************************************" );
    }

    // }}}

    // Now, subtract all the holidays between the two dates {{{
    foreach( $holidays AS $holiday )
    {
        $h = strtotime( $holiday );											// Convert to timestamp
        if( $h < $start  ||  $h > $end ) continue;							// Ignore holidays that fall outside our range....
        if( date( "N", $h ) == 6  || date( "N", $h ) == 7 ) continue;		// Ignore holidays that fall on a weekend

        // This holiday is within the specified date-range, so subtract it....
        $workingDays--;
    }
# 	logger( "workingDays=$workingDays" );
    // }}}

    if( $dateA > $dateB ) $workingDays = $workingDays * -1;		// Convert to negative value if the specified end date occurs before the start date
    if( $inclusive == 0  && $workingDays < 0 ) $workingDays--;	// Subtract one more day if we're negative - to include the due_date as a working day.
# 	logger( "workingDays=$workingDays" );

# 	logger( __FUNCTION__ . "():  There are $workingDays working days between " . date( "Y-m-d", $dateA ) . " and " . date( "Y-m-d", $dateB ) );
    $profile->stop();
    return $workingDays;

} // }}}
function getWorkingHours( $tsA, $tsB ) // {{{
{
    Global $profile;
    $profile->start();
    // Return how many working hours were accumulated between the two timestamps.
    // Assumes an 8 hour work day, starting at 8:30am and finishing at 5pm, with 30mins for lunch
    // A starting timestamp before 8:30am is added on as extra time.
    // A finishing timestamp after 5pm is added on as extra time.

    $verbose = false;


    // First, calculate how many whole-day working hours betwen the two timestamps
    $days = getWorkingDays( $tsA, $tsB, true ) - 2;  if( $days < 0 ) $days = 0;
    $hours = $days * 8;

    if( $verbose ) logger( "Start Time:  $tsA" );
    if( $verbose ) logger( "End   Time:  $tsB" );
    if( $verbose ) logger( "days=$days, whole_day_hours=$hours" );

    // If start/end times are on the same day....
    if( date( 'Y-m-d', strtotime( $tsA ) ) == date( 'Y-m-d', strtotime( $tsB ) ) )
    {
        $hours = round( ( ( strtotime( $tsB ) - strtotime( $tsA ) ) / 60 / 60 ), 1 );
        if( $verbose ) logger( "DEBUG 1.1: hours=$hours" );


        // If start time is before lunch and end time is after lunch, then subtract 30 minutes for lunch....
        if( date( 'G', strtotime( $tsA ) ) < 12  &&  date( 'G', strtotime( $tsB ) ) > 13 ) $hours -= 0.5;
        if( $verbose ) logger( "DEBUG 1.2: hours=$hours" );
    }
    else
    {
        // Add in the hours worked on the first day
        $x = 0;
        if( date( 'G', strtotime( $tsA ) ) < 17 ) $x = round( ( ( strtotime( '5pm', strtotime( $tsA ) ) - strtotime( $tsA ) ) / 60 / 60 ) , 1 );
        if( $verbose ) logger( "DEBUG 2.1: firstDayHours=$x" );

        // If start time is before lunch, then subtract 30 minutes for lunch....
        if( date( 'G', strtotime( $tsA ) ) < 12 ) $x -= 0.5;
        if( $verbose ) logger( "DEBUG 2.2: firstDayHours=$x" );

        $hours += $x;
        if( $verbose ) logger( "DEBUG 2.3: hours=$hours" );


        // Add in the hours worked on the last day
        $x = 0;
        if( date( 'G', strtotime( $tsB ) ) > 9 ) $x = round( ( ( strtotime( $tsB ) - strtotime( '9am', strtotime( $tsB ) ) ) / 60 / 60 ) , 1 );
        if( $verbose ) logger( "DEBUG 2.4: lastDayHours=$x" );

        // If finish time is after lunch, then subtract 30 minutes for lunch....
        if( date( 'G', strtotime( $tsB ) ) > 12 ) $x -= 0.5;
        if( $verbose ) logger( "DEBUG 2.5: lastDayHours=$x" );

        $hours += $x;
        if( $verbose ) logger( "DEBUG 2.6: hours=$hours" );
    }

# 	logger( __FUNCTION__ . "():  There are $hours working hours betwen $tsA and $tsB...." );
    $profile->stop();
    return $hours;

} // }}}

function getHolidayDates( $year=null, $country='CAN', $prov='BC' ) // {{{
{
    Global $profile;
    $profile->start();

    // This function is fairly "slow", and can sometimes be called thousands of times during a page-load (on large data-sets)
    // This allows holidays to only be calculated once per page-load, and kept in memory for each subsequent call.
    Global $HOLIDAY_DATES;
    if( !empty( $HOLIDAY_DATES[$year][$country][$prov] ) ) { $profile->stop(); return $HOLIDAY_DATES[$year][$country][$prov]; }


    // Return a list of holiday dates (for BC)
    $holidays = array();

    if( $country != 'CAN'  ||  $prov != 'BC' )
    {
        logger( __FUNCTION__ . "( '$year', '$country', '$prov' ):  WARNING!!!!!!!!   Unsupported Country or State/Prov.   Returning empty set." );
        return $holidays;
    }

    // Add all the fixed holidays - New Years, Remembrance, Christmas, Boxing
# 	for( $y=2000;$y<=date('Y')+25;$y++ ) $holidays = array_merge( $holidays, array( "$y-01-01", "$y-11-11", "$y-12-25", "$y-12-26" ) );
    for( $y=2000;$y<=date('Y')+25;$y++ ) { $holidays[] = "$y-01-01"; $holidays[] = "$y-11-11"; $holidays[] = "$y-12-25"; $holidays[] = "$y-12-26"; }


    // The following were derived from http://www.labour.gov.bc.ca/esb/facshts/stats.htm
    // ... and http://www.statutoryholidays.com/bc.php

    // Add Canada-wide holidays {{{
    // Good Friday
# 	$holidays = array_merge( $holidays, array( '2005-03-25', '2006-04-14', '2007-04-06', '2008-03-21', '2009-04-10', '2010-04-02', '2011-04-22', '2012-04-06', '2013-03-29', '2014-04-18', '2015-04-03', '2016-03-25', '2017-04-14', '2018-03-30', '2019-04-19', '2020-04-10', '2021-04-02' ) );
    foreach( array( '2005-03-25', '2006-04-14', '2007-04-06', '2008-03-21', '2009-04-10', '2010-04-02', '2011-04-22', '2012-04-06', '2013-03-29', '2014-04-18', '2015-04-03', '2016-03-25', '2017-04-14', '2018-03-30', '2019-04-19', '2020-04-10', '2021-04-02' ) AS $d ) $holidays[] = $d;

    // Easter Monday
# 	$holidays = array_merge( $holidays, array( '2005-03-28', '2006-04-17', '2007-04-09', '2008-03-24', '2009-04-13', '2010-04-05', '2011-04-25', '2012-04-09', '2013-04-01', '2014-04-21', '2015-04-06', '2016-03-28', '2017-04-17', '2018-04-02', '2019-04-22', '2020-04-13', '2021-04-05' ) );
    foreach( array( '2005-03-28', '2006-04-17', '2007-04-09', '2008-03-24', '2009-04-13', '2010-04-05', '2011-04-25', '2012-04-09', '2013-04-01', '2014-04-21', '2015-04-06', '2016-03-28', '2017-04-17', '2018-04-02', '2019-04-22', '2020-04-13', '2021-04-05' ) AS $d ) $holidays[] = $d;

    // Victoria Day - Monday before May 25
    for( $y=2000;$y<=date('Y')+25;$y++ ) $holidays[] = date( 'Y-m-d', strtotime( 'last monday', strtotime( "$y-05-25" ) ) );


    // Canada Day - July 1 or July 2 (if July 1 is a Sunday)
    for( $y=2000;$y<=date('Y')+25;$y++ ) { $d = "$y-07-01"; if( date( 'D', strtotime( $d ) ) == 'Sun' ) $d = "$y-07-02"; $holidays[] = $d; }

    // Labour Day - First Monday of Sep
    for( $y=2000;$y<=date('Y')+25;$y++ ) $holidays[] = getRelativeDate( $y, 'september', 'first monday' );

    // }}}


    // Add BC-specific holidays {{{

    // BC Family Day - 2nd Monday of Feb
    for( $y=2000;$y<=date('Y')+25;$y++ ) $holidays[] = getRelativeDate( $y, 'february', 'second monday' );

    // BC Day - First Monday of Aug
    for( $y=2000;$y<=date('Y')+25;$y++ ) $holidays[] = getRelativeDate( $y, 'august', 'first monday' );

    // }}}


    // If user only wants holidays for a given year, then strip out everything we don't need {{{
    if( !is_null( $year ) )
    {
        $tmp = array();
        foreach( $holidays AS $day ) if( substr( $day, 0, 4 ) == $year ) $tmp[] = $day;
        $holidays = $tmp;
    } // }}}

    // Cache for future iterations
    $HOLIDAY_DATES[$year][$country][$prov] = $holidays;

    $profile->stop();
    return $holidays;

} // }}}
function getRelativeDate( $year, $month, $offset ) // {{{
{
    // This is used by getHolidayDates() above to calculate things like...
    // Thanksgiving is the 2nd Monday in October, so... to get Thanksgiving for a given year...
    // $date = getRelativeDate( '2025', 'october', 'second monday' );

    return date( 'Y-m-d', strtotime( "$month $year $offset", strtotime( "jan 1 $year" ) ) );
} // }}}

function isWorkingDay( $date, $country='CAN', $prov='BC' ) // {{{
{
    // Return TRUE if the given date falls on a non-Stat-holiday week-day

    $date = date( "Y-m-d", strtotime( $date ) );

    if( date( "D", strtotime( $date ) ) == "Sat" ) return false;
    if( date( "D", strtotime( $date ) ) == "Sun" ) return false;

    $holidays = getHolidayDates( substr( $date, 0, 4 ), $country, $prov );
    if( in_array( $date, $holidays ) ) return false;

    return true;

} // }}}

function getAdjustedETAStr( $in ) // {{{
{
# 	logger( __FUNCTION__ . "( $in ):  datestamp=" . date( "Y-m-d H:i:s", $in ) . " returns " . getAgeStr( $in ) );
    //if( abs( time() - $in ) < 45 ) return "Any moment";
    if( (time()-$in) > 0 ) return "Any moment";
    return getAgeStr( $in );
} // }}}

function getISOWeeksInYear( $year ) // {{{
{
    // Some years have 52 weeks, others have 53.  This functions figures that out and returns 52 or 53
    // Source:  http://stackoverflow.com/questions/3319386/php-get-last-week-number-in-year

    $date = new DateTime;
    $date->setISODate( $year, 53 );
    return ( $date->format( 'W' ) === '53' ? 53 : 52 );
} // }}}

function getWeekStartStop( $year, $week ) // {{{
{
    // Return the first (Sunday) and last (Saturday) date of the given week
    // NOTE:  If this is returning "bad data", it may be because you're getting year and week
    // from PHP's date("Y-W") function for a given date.   However...  2013-12-30 (a Monday) will return
    // W=1, but Y=2013.   Which is as-expected, but... gives the totally wrong dates for below.
    // Instead,  2013-12-30 should input Y=2014 and W=1 for this function....
    // See my 'hack' in inc/invoices.php for a work-around....   :-/

    $info = array( 'year' => $year, 'week' => $week );

    $o = new DateTime();
    $o->setISODate( $year, $week );
    if( $o->format('D') == 'Mon' ) $o->modify( '-1 days' );		// If setISODate() set to a Monday, then bump back to Sunday....
    $info['start'] = $o->format( 'Y-m-d' );
    $o->modify( '+6 days' );
    $info['end'] = $o->format( 'Y-m-d' );
    $info['stop'] = $info['end']; // Brayden 2017-07-04 to match function name...

# 	$week--;
# 	$info['start'] = date( 'Y-m-d', strtotime( 'last sunday',   strtotime( "jan 3, $year + $week week" ) ) );
# 	$info['end']   = date( 'Y-m-d', strtotime( 'next saturday', strtotime( "jan 3, $year + $week week" ) ) );

# 	logger( $info );
    return $info;

} // }}}

function getLastFridayOfMonth( $year, $month, $excludeHolidays=true ) // {{{
{
    // Returns the last Friday of the given month in the format "YYYY-MM-DD"
    // if excludeHolidays==TRUE and last Friday is a holiday, then return the Thursday (or Wed or...)

    // Get "next month"
    $month++; if( $month > 12 ) { $month = 1; $year++; }

    // Back off to last day of previous month...
    $date = date( "Y-m-d", strtotime( "yesterday", strtotime( "$year-$month-01" ) ) );

    // While not a Friday...
    while( date( "D", strtotime( $date ) ) != "Fri" ) $date = date( "Y-m-d", strtotime( "yesterday", strtotime( $date ) ) );

    // While a holiday...
    if( $excludeHolidays ) while( !isWorkingDay( $date ) ) $date = date( "Y-m-d", strtotime( "yesterday", strtotime( $date ) ) );

    return $date;
} // }}}

function getMonths( $m=null ) // {{{
{
    // Return an array of Months
    // Allow user to specify a month number, short name, or description, and return details about just that month

    $months = array(
        0      => array( 'month' =>  0, 'short' => 'NA',  'description' => 'No Selection' ),
        1      => array( 'month' =>  1, 'short' => 'Jan', 'description' => 'January' ),
        2      => array( 'month' =>  2, 'short' => 'Feb', 'description' => 'February' ),
        3      => array( 'month' =>  3, 'short' => 'Mar', 'description' => 'March' ),
        4      => array( 'month' =>  4, 'short' => 'Apr', 'description' => 'April' ),
        5      => array( 'month' =>  5, 'short' => 'May', 'description' => 'May' ),
        6      => array( 'month' =>  6, 'short' => 'Jun', 'description' => 'June' ),
        7      => array( 'month' =>  7, 'short' => 'Jul', 'description' => 'July' ),
        8      => array( 'month' =>  8, 'short' => 'Aug', 'description' => 'August' ),
        9      => array( 'month' =>  9, 'short' => 'Sep', 'description' => 'September' ),
        10      => array( 'month' => 10, 'short' => 'Oct', 'description' => 'October' ),
        11      => array( 'month' => 11, 'short' => 'Nov', 'description' => 'November' ),
        12      => array( 'month' => 12, 'short' => 'Dec', 'description' => 'December' )
    );

    if( is_null( $m ) ) return $months;
    if( array_key_exists( $m, $months ) ) return $months[$m];
    if( ( $x = SubKeySearch( $months, 'short', ( strtoupper( $m ) == 'NA' ? 'NA' : ucwords( $m ) ) ) ) !== FALSE ) return $x;
    if( ( $x = SubKeySearch( $months, 'description', ucwords( $m ) ) ) !== FALSE ) return $x;
    return $months[0];      // No match found

} // }}}
function getMonthFromNumber( $num, $short=true ) // {{{
{
    $m = getMonths( (int)$num );
    if( $short ) return $m['short'];
    return $m['description'];
} // }}}
function getNumberFromMonth( $str="" ) // {{{
{
    $m = getMonths( $str );
    return $m['month'];
} // }}}

function days_diff( $d1, $d2 ) // {{{
{
    // Return the number of days between two dates.
    $d1 = new DateTime( '@' . getDate2( $d1 ) );
    $d2 = new DateTime( '@' . getDate2( $d2 ) );

    $x1 = daysX( $d1 );
    $x2 = daysX( $d2 );
    if( $x1 && $x2 ) return ( $x2 - $x1 );
    return 0;
} // }}}

function daysX( /** @var DateTime $x */ $x ) // {{{
{
    if( get_class( $x ) != 'DateTime' ) return false;

    $y = $x->format( 'Y' ) - 1;
    $days = $y * 365;

    $z = (int)( $y / 4 );
    $days += $z;

    $z = (int)( $y / 100 );
    $days -= $z;

    $z = (int)( $y / 400 );
    $days += $z;

    $days += (int)$x->format( 'z' );

    return $days;
} // }}}

// }}}

function SubKeySort( &$arr, $fields, $reverse=false, $numeric=false ) // {{{
{
    // Sort an assoc array by one or more of it's subkeys, and maintain key association.
    // $fields is an array of assoc array indexes to sort against.
    // For backwards-compatibility, $fields can be a text-string.
    // Example:  SubKeySortReverse( $dbResultSet, array( 'lastname', 'firstname' ) );
    // $numeric = TRUE is required when the column contains both positive and negative numbers

    $arrays = is_array( current( $arr ) ) ? true : false;
    $objects = is_object( current( $arr ) ) ? true : false;

    if( empty( $fields )  ||  count( $arr ) <= 1 ) return;
    if( !is_array( $fields ) ) $fields = array( $fields );		// If a single field (string) is passed in, convert it to an array

    // If user wants to sort on multiple keys, this algo needs to sort by the last key first, then the second-last, and so on....
    $fields = array_reverse( $fields );

    if( $numeric )
    {
        if( $arrays )
        {
            $code = '$c = (float)$a["'.$fields[0].'"] - (float)$b["'.$fields[0].'"]; $retval = ( $c < 0 ? -1 : ( $c > 0 ? 1 : 0 ) );';
            for( $i = 1; $i < count( $fields ); $i++ )
                $code .= '$c = (float)$a["'.$fields[$i].'"] - (float)$b["'.$fields[$i].'"]; $retval = ( $c < 0 ? -1 : ( $c > 0 ? 1 : 0 ) );';
        }
        elseif( $objects )
        {
            $code = '$c = (float)$a->'.$fields[0].' - (float)$b->'.$fields[0].'; $retval = ( $c < 0 ? -1 : ( $c > 0 ? 1 : 0 ) );';
            for( $i = 1; $i < count( $fields ); $i++ )
                $code .= '$c = (float)$a->'.$fields[$i].' - (float)$b->'.$fields[$i].'; $retval = ( $c < 0 ? -1 : ( $c > 0 ? 1 : 0 ) );';
        }
        else
        {
            logger( "Collection type is not supported.  No changes made.\n" );
            return false;
        }
    }
    else
    {
        if( $arrays )
        {
            $code = '$retval = strnatcmp( $a["'.$fields[0].'"], $b["'.$fields[0].'"] );';
            for( $i = 1; $i < count( $fields ); $i++ )
                $code .= 'if( !$retval) $retval = strnatcmp( $a["'.$fields[$i].'"], $b["'.$fields[$i].'"] );';
        }
        elseif( $objects )
        {
            $code = '$retval = strnatcmp( $a->'.$fields[0].', $b->'.$fields[0].' );';
            for( $i = 1; $i < count( $fields ); $i++ )
                $code .= 'if( !$retval) $retval = strnatcmp( $a->'.$fields[$i].', $b->'.$fields[$i].' );';
        }
        else
        {
            logger( "Collection type is not supported.  No changes made.\n" );
            return false;
        }
    }
    $code .= 'return $retval;';

    if( $reverse ) uasort( $arr, create_function( '$b,$a', $code ) );
    else uasort( $arr, create_function( '$a,$b', $code ) );

    // No return value because $arr is passed by reference
}
function SubkeySortReverse( &$arr, $fields, $numeric=false ) { SubKeySort( $arr, $fields, true, $numeric ); }

// The Old Way - Remove after some testing....  TODO
# function SubKeySort( &$arr, $key, $reverse=false ) // {{{
# {
# 	// Sort an array of arrays based on the values of a child element in each sub-array
# 	// Great for re-sorting database result sets.
# 	// This will attempt to do a numeric comparison if both values are numbers, otherwise string comparison
# 	// The is_numeric() test cost is almost negligible
#
# 	// One problem:  Any original sort-order of the array is completely lost through this function
# 	// I.e. you can't call this function twice to first sort by one element, then by another.   (i.e. last name, then first name)
# 	// Calling this function completely ignores any existing sorted order of the incoming array.   8(
#
# 	// SUBKEYSORT_INDEX better not already exist in the array we're processing.... :)
#
# 	foreach( $arr AS &$val ) $val['SUBKEYSORT_INDEX'] = $val[$key];
#
# 	if( $reverse ) uasort( $arr, 'SubKeySortReverse_Compare' );
# 	else uasort( $arr, 'SubKeySort_Compare' );
#
# 	foreach( $arr AS &$val ) unset( $val['SUBKEYSORT_INDEX'] );
# }
# function SubKeySort_Compare( $a, $b )
# {
# 	if( is_numeric( $a['SUBKEYSORT_INDEX'] )  &&  is_numeric( $b['SUBKEYSORT_INDEX'] ) )
# 		return $a['SUBKEYSORT_INDEX'] - $b['SUBKEYSORT_INDEX'];
# 	else
# 		return strcasecmp( $a['SUBKEYSORT_INDEX'], $b['SUBKEYSORT_INDEX'] );
# }
# function SubKeySortReverse_Compare( $b, $a )	// NOTE: The function input elements are reversed.  Otherwise, both this function and above are identical....
# {
# 	if( is_numeric( $a['SUBKEYSORT_INDEX'] )  &&  is_numeric( $b['SUBKEYSORT_INDEX'] ) )
# 		return $a['SUBKEYSORT_INDEX'] - $b['SUBKEYSORT_INDEX'];
# 	else
# 		return strcasecmp( $a['SUBKEYSORT_INDEX'], $b['SUBKEYSORT_INDEX'] );
# }
#
# // USAGE:
# // $a = array();
# // $a[] = array( 'mix_id' => "AAA", 'description' => 'Annuals - Regular' );
# // $a[] = array( 'mix_id' => "ABB", 'description' => 'Annuals - Regular' );
# // $a[] = array( 'mix_id' => "HBBB", 'description' => 'Hanging Baskets' );
# // $a[] = array( 'mix_id' => "HVBB", 'description' => 'Herbs/Veggies' );
# // $a[] = array( 'mix_id' => "PCABB", 'description' => 'Annuals - PC' );
#
# // SubKeySort( $a, 'description' );
# // }}}

// }}}

function SubKeySearch( $arr, $key, $val ) // {{{
{
    # Search an array of arrays based on the key of a child element
    # Returns the FIRST parent array element that contains the key being searched.
    #	If you want ALL matching elements in the parent array, use SubKeySearch2()
    # Great for searching a database result set.

    if( !is_array( $arr ) ) { logger( __FUNCTION__ . "():  Oops!  Didn't receive an array." ); return false; }

    foreach( $arr AS $k => $r ) if( $r[$key] == $val ) return $r;
    return false;		// If val not found
}

// USAGE:
// $a = array();
// $a[] = array( 'id' => 1, 'description' => 'one' );
// $a[] = array( 'id' => 2, 'description' => 'two' );
// $a[] = array( 'id' => 3, 'description' => 'three' );
// $a[] = array( 'id' => 4, 'description' => 'four' );
// $a[] = array( 'id' => 5, 'description' => 'five' );

// $r = SubKeySearch( $a, 'id', 3 );
// echo $r['description'] . "\n";   // 'three'

// }}}
function SubKeySearchPop( &$arr, $key, $val ) // {{{
{
    # Works just like SubKeySearch() above, however, it also pops the matching
    # array element off the parent array (passed by reference) before returning it.
    # I.e. the parent array is reduced by one element for each match found (and returned).

    if( !is_array( $arr ) ) { logger( __FUNCTION__ . "():  Oops!  Didn't receive an array." ); return false; }

    foreach( $arr AS $k => $r ) if( $r[$key] == $val ) { unset( $arr[$k] ); return $r; }
    return false;		// If val not found
}
// }}}
function SubKeySearch2( $arr, $key, $val ) // {{{
{
    # Search an array of arrays based on the key of a child element
    # Returns the parent array element(s) that contains the key being searched.
    # Indexes are maintained.
    # Returns ALL matching rows.
    # Returns FALSE if no matching rows.
    # Great for searching a database result set.

    if( !is_array( $arr ) ) { logger( __FUNCTION__ . "():  Oops!  Didn't receive an array." ); return false; }

    $results = array();

    foreach( $arr AS $k => $r ) if( $r[$key] == $val ) $results[$k] = $r;
    if( count( $results ) > 0 ) return $results;
    return false;		// If val not found
}

// USAGE:
// $a = array();
// $a[] = array( 'id' => 1, 'description' => 'one' );
// $a[] = array( 'id' => 2, 'description' => 'two' );
// $a[] = array( 'id' => 3, 'description' => 'three' );
// $a[] = array( 'id' => 4, 'description' => 'four' );
// $a[] = array( 'id' => 5, 'description' => 'five' );

// $r = SubKeySearch2( $a, 'id', 3 );
// echo $r[0]['description'] . "\n";   // 'three'

// }}}
function SubKeySearchKey( $arr, $key, $val ) // {{{
{
    # Search an array of arrays based on the key of a child element
    # Returns the index of the FIRST parent array element that contains the key being searched.
    # Great for finding the row of a database result set

    if( !is_array( $arr ) ) { logger( __FUNCTION__ . "():  Oops!  Didn't receive an array." ); return false; }

    foreach( $arr AS $k => $r ) if( $r[$key] == $val ) return $k;
    return false;		// If val not found
}

// USAGE:
// $a = array();
// $a[] = array( 'id' => 1, 'description' => 'one' );
// $a[] = array( 'id' => 2, 'description' => 'two' );
// $a[] = array( 'id' => 3, 'description' => 'three' );
// $a[] = array( 'id' => 4, 'description' => 'four' );
// $a[] = array( 'id' => 5, 'description' => 'five' );

// $r = SubKeySearch( $a, 'id', 3 );
// echo $r['description'] . "\n";   // 'three'

// }}}

// function array_keys_exists( $keys, $arr ) // {{{
if( !function_exists( 'array_keys_exists' ) )
{
    //logger( "Creating function array_keys_exists()..." );
    // PHP's array_key_exists() only accepts one key.  This allows searching for one or more keys in the array.
    function array_keys_exists( $keys, $arr )
    {
        //logger( __FUNCTION__ . "( $keys, $arr )" );
        if( empty( $keys ) ) return false;
        if( !is_array( $keys ) ) return array_key_exists( $keys, $arr );

        foreach( $keys AS $key )
        {
            //logger( __FUNCTION__ . "():  array_key_exists( $key )..." );
            if( array_key_exists( $key, $arr ) ) return true;
            //logger( __FUNCTION__ . "():  Nope." );
        }
        return false;
    }

}

// }}}
function array_unshift_assoc( &$arr, $key, $val ) // {{{
{
    // Push a keyed value onto the beginning of an Associative Array
    $arr = array_reverse( $arr, true );
    $arr[$key] = $val;
    $arr = array_reverse( $arr, true );
} // }}}
function array_delete( &$arr, $val ) // {{{
{
    // Remove the specified $val from the passed (by reference) array
    // All instances of $val will be removed from array
    foreach( array_keys( $arr, $val, true ) AS $key ) unset( $arr[$key] );
} // }}}
function array_diff_recursive( $a1, $a2 ) // {{{
{
    // Recursively parse through two arrays, computing the differences between them.

    $diff = array();

    foreach( $a1 AS $k1 => $v1 )
    {
        if( array_key_exists( $k1, $a2 ) )
        {
            if( is_array( $v1 ) )
            {
                $rdiff = array_diff_recursive( $v1, $a2[$k1] );
                if( count( $rdiff ) > 0 ) $diff[$k1] = $rdiff;
            }
            else
            {
                if( $v1 != $a2[$k1] ) $diff[$k1] = $v1;
            }
        }
        else
        {
            $diff[$k1] = $v1;
        }
    }

    return $diff;
} // }}}

function objectToArray( $object ) // {{{
{
    // Convert an Object (XML, etc.) to an Assoc Array

    $d = $object;

    if( is_object( $object ) )
    {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars( $d );
    }

    if( is_array( $d ) )
    {
        /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant)
        * for recursive call
        */
        return array_map( __FUNCTION__, $d );
    }
    else
    {
        // Return array
        return $d;
    }
} // }}}

function implodeKeyValues($array) {
    $debugSet = implode(', ', array_map(
        function ($v, $k) { return sprintf("%s='%s'", $k, $v); },
        $array,
        array_keys($array)
    ));
    return $debugSet;
}


// Filters/Validators for common inputs {{{
function getPrintable( $in ) // {{{
{
    // Include tabs, nl, cr, brackets, & most symbols
    return preg_replace( '/[^a-zA-Z0-9_:;#~@!\?$\^&%*<>=+{}\[\]()\.\ \\\\\-\'\"\/,\r\n\t]/si' , '' , $in );
} // }}}
function getAlNum( $in ) // {{{
{
    return preg_replace( '/[^a-zA-Z0-9_]/si', '', $in );
} // }}}
function getAlNumUC( $in ) // {{{
{
    return strtoupper( getAlNum( $in ) );
} // }}}
function getAlNumLC( $in ) // {{{
{
    return strtolower( getAlNum( $in ) );
} // }}}
# function getNum( $in ) // {{{
# {
# 	// Allow signed float
# 	return preg_replace( '/[^0-9\-\.]/si' , '' , $in );
# } // }}}
function getNumeric( $in=null, $min=null, $max=null, $default=null ) // {{{
{
    // Returns a signed, numeric value.
    // If min/max is set and default is null, will return min/max if out-of-bounds
    // If not numeric or min/max is set and default is not null, will return default

# 	logger( __FUNCTION__. "(): in=$in" );
    if( is_null( $in ) ) return $default;
    if( strlen( $in ) == 0 ) return $default;

    // NOTE:  PHP (auto) and floatval() ignore commas (and anything following)
    //        So... remove them first.   This, unfortunately, breaks European-formatted numbers.  FIXME
    //        floatval() removes any non-numeric characters (other than the first decimal)
    $in = floatval( str_replace( ",", "", $in ) );
# 	logger( __FUNCTION__. "(): in=$in" );

    if( !is_numeric( $in ) ) return $default;

    if( !is_null( $min )  &&  $in < $min ) $in = ( is_null( $default ) ? $min : $default );
    if( !is_null( $max )  &&  $in > $max ) $in = ( is_null( $default ) ? $max : $default );

# 	logger( __FUNCTION__. "(): in=$in" );

    return $in;
}
function getNumber( $in, $min=null, $max=null, $default=null ) { return getNumeric( $in, $min, $max, $default ); }
function getNum(    $in, $min=null, $max=null, $default=null ) { return getNumeric( $in, $min, $max, $default ); }
// }}}

function getInt( $in ) // {{{
{
    // NOTE:   Don't simply cast to (int) because any value > 2,147,483,647 is set to 2,147,483,647
    // Allow signed integer

    // "Remember" if this was a negative number or not....
    $neg = substr( $in, 0, 1 ) == '-' ? true : false;

    // Strip off anything after the first decimal...
    if( strpos( $in, "." ) !== FALSE )
    {
        $x = explode( ".", $in );
        $in = $x[0];
    }

    // Strip out all non numeric characters
    $in = preg_replace( '/[^0-9]/si' , '' , $in );

    // re-set to negative if needed
    if( $neg ) $in = $in * -1;

    return $in;
} // }}}

function getDigits($in) { // {{{
    // like getAlNum without the Al
    $in = preg_replace( '/[^0-9]/si' , '' , $in );
    return $in;
} // }}}

function getDate2( $in, $format="U" ) // {{{
{
    // Return a valid Unix Timestamp (or '$format'ted date/time string) based on the inbound date value
    // format is anything valid for date();  i.e. 'Y-m-d'
    if( empty( $in ) ) return false;

    // If this is already a Unix timestamp....
    if( (string)(int)$in == (string)$in ) return $in;

    // Else try to convert the input into a valid timestamp, or return FALSE on failure
    if( ( $out = strtotime( $in ) ) === FALSE ) return false;
    return date( $format, $out );
}
function getTimestamp( $in ) { return getDate2( $in ); }		// Alias to getDate2()
// }}}
function getBoolean( $in=null, $default=false ) // {{{
{
    // Analyze $in and return Boolean value.
    // Return $default if no pattern match

    if( is_bool( $in ) ) return $in;								// if it's already a boolean value
    if( is_null( $in ) || strlen( $in ) == 0 ) return $default;		// if null or empty string, return default

    $in = strtoupper( $in );
    $c = substr( $in, 0, 1 );

# 	logger( __FUNCTION__ . "(): in=$in" );

    if( $in == "ON"  ) return true;
    if( $c  == 'T'   ) return true;
    if( $c  == 'Y'   ) return true;
    if( $in  == 'CHECKED'   ) return true;

    if( $in == "OFF" ) return false;
    if( $c  == 'F'   ) return false;
    if( $c  == 'N'   ) return false;

    //if( $in == "" ) return false;						// empty string == false		(now handled above)
    if( strlen( $in > 0 ) ) return true;				// non-empty string == true

    return $default;									// null ==> default
} // }}}
function getBool( $in, $default=false ) // {{{
{
    return getBoolean( $in, $default );
} // }}}
function getMAC( $in ) // {{{
{
    // Returns a valid MAC Address in format AA:BB:CC:DD:EE:FF or FALSE

    $in = getAlNumUC( $in );		// Convert to upper-case, remove all non-alphanumerics
    if( strlen( $in ) != 12 ) return false;

    return substr($in,0,2) .":". substr($in,2,2) .":". substr($in,4,2) .":". substr($in,6,2) .":". substr($in,8,2) .":". substr($in,10,2);

} // }}}
function getIP( $in ) // {{{
{
    // Returns a valid IP Address in format aaa.bbb.ccc.ddd or FALSE

    if( ip2long( $in ) === false ) return false;
    return long2ip( ip2long( $in ) );

} // }}}
function getEmail( $in, $log=false ) // {{{
{
    if( validEmail( $in, $log ) ) return $in;
    return null;
} // }}}
function getBaseEmailAddr( $in ) // {{{
{
    // Turns (and array of) "Kevin Traas <kevin@tra.as>" into "kevin@tra.as".
    // Returns nothing if email address is invalid.

    // If input is an array, then parse each element of the array (recursively) {{{
    if( is_array( $in ) )
    {
        $out = array();
        foreach( $in AS $x )
        {
            $y = getEmail( getBaseEmailAddr( $x ) );
            if( !empty( $y ) ) $out[] = $y;
        }
        return $out;
    } // }}}

    if( strpos( $in, "<" ) === FALSE ) return $in;

    $in = explode( "<", $in );
    $in = explode( ">", $in[1] );
    return $in[0];

} // }}}
function getPhone( $in, $style=1 ) // {{{
{
    // This only handles 10-digit numbers.  Returns original input (stripped of all non-numerics) otherwise...
    // style		: 1		--> (604) 858-5678	(default, unless cleansed input is not 10 chars)
    //				: 2		--> 604-858-5678
    //				: 3		--> 6048585678

    $val = preg_replace( '/[^0-9]/si' , '' , $in );
    if( strlen( $val ) == 11   &&  substr( $val, 0, 1 ) == "1" ) $val = substr( $val, 1, 10 );		// If starts with 1 (LD prefix), then strip it.

    if( strlen( $val ) != 10 ) return $val;

    switch( $style )
    {
        case 3:		return $val;
        case 2:		return substr( $val, 0, 3 ) . "-" . substr( $val, 3, 3 ) . "-" . substr( $val, 6, 4 );
        case 1:
        default:	return "(" . substr( $val, 0, 3 ) . ") " . substr( $val, 3, 3 ) . "-" . substr( $val, 6, 4 );
    }

    // should never get here
    return $val;

} //  }}}

function getSQLString( $in ) // {{{
{
    return preg_replace( '/[^a-zA-Z0-9_\- ()#,.:\/]/si', '', $in );
} // }}}

function getGTIN( $in, $asString=true, $type=12 ) // {{{
{
    /* Returns FALSE if $in is not a valid GTIN
         Supported formats:
            - GTIN-12 (UPC-A)
            - GTIN-14 (EAN-14, UCC-14, SCC-14)
        If $asString == true, then returns in format x-xxxx-xxxx-x (UPC-A) or x-xx-xxxxx-xxxxx-x (EAN-14)
        else returns as xxxxxxxxxxxx

        NOTES:
            - GTIN-12 can be converted to GTIN-14 by prefixing with 2 zeros
            - First digit of GTIN-12 indicates 'pack-type'
                - 0		= EACH
                - 1		= CASE
                - 2-8	= "other"
                - 9		= quantity of pack varies between containers
                - For more info, see http://www.swinglabels.com/b-gtin14.aspx

            - A Case UPC (GTIN-14) is *usually* the Item UPC (GTIN-12, UPC-A) with a 10 prefix (and new Check Digit suffix)
                - i.e. chars 3-13 of GTIN-14 === chars 1-11 of GTIN-12/UPC-A
    */

    // Initial validation {{{
    if( $type != 14 ) $type = 12;		// Only GTIN-12 and GTIN-14 are supported right now... Default to GTIN-12

    $in = preg_replace( '/[^0-9]/si' , '' , $in );

    if( strlen( $in ) == 12  &&  $type == 14 ) $in = "00$in";		// Convert GTIN-12 to GTIN-14 (if input was -12 and output is -14)

    if( strlen( $in ) != $type ) return false;
    // }}}

    // Check Digit Validation  {{{
    $even = $odd = 0;
    for( $i=0; $i<($type-1); $i++ ) ( $i % 2 ) ? $even += $in[$i] : $odd += $in[$i];

    $x = $even + ( $odd * 3 );
    $y = $x % 10;
    $z = $y ? 10 - $y : $y;

    if( $z != $in[($type-1)] ) { logger( "Invalid GTIN-$type ($in): even=$even, odd=$odd, y=$y, z=$z" ); return false; }
    // }}}

    // Return formatted GTIN {{{
    if( !$asString ) return $in;

    switch( $type )
    {
        default:	$out = $in; break;		// Unsupported format.  Leave as a full numeric strin

        case 12:
            $out = $in[0] . "-" . substr( $in, 1, 5 ) . "-" . substr( $in, 6, 5 ) . "-" . substr( $in, 11, 1 );
            break;

        case 14:
            $out = $in[0] . "-" . substr( $in, 1, 2 ) . "-" .substr( $in, 3, 5 ) . "-" . substr( $in, 8, 5 ) . "-" . substr( $in, 13, 1 );
            break;
    }

# 	logger( __FUNCTION__ . "( $in ): $out" );
    return $out;
    // }}}

} // }}}
// }}}

function jGrowl( $msg, $title="", $life=3000, $theme="info", $sticky=false, $position='top-right' ) // {{{
{
    // If life==0, sticky=true

    // Sanity checks {{{

    // First item in each row is the class name, remaining are synonyms (converted below)
    $themes = array(
        'info', 'information', 'notice', 'note',
        'good', 'success',
        'error', 'fail', 'failure',
        'warn', 'warning', 'alert'
    );
    if( !in_array( $theme, $themes ) ) $theme = $themes[0];

    if( $theme == "information" ) $theme = "info";
    if( $theme == "notice" ) $theme = "info";
    if( $theme == "note" ) $theme = "info";

    if( $theme == "success" ) $theme = "good";

    if( $theme == "fail" ) $theme = "error";
    if( $theme == "failure" ) $theme = "error";

    if( $theme == "warning" ) $theme = "warn";
    if( $theme == "alert" ) $theme = "warn";


    $positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'center' );
    if( !in_array( $position, $positions ) ) $position = $positions[0];


    if( $life == 0 ) $sticky = true;
    else
    {
        if( !is_numeric( $life ) ) $life = 3000;	// ms
        if( $life < 1000 ) $life = 1000;			// less is not readable....
        if( $life > 20000 ) $life = 20000;			// more is silly...
    }


    $sticky = ( $sticky ? 'true' : 'false' );
    // }}}

    // Make safe for Javascript (below)
    $msg = str_replace( "\n", "<br />", $msg );
    $msg = str_replace( "'", "&#39;", $msg );
# 	$msg = htmlentities( $msg, ENT_QUOTES );
    if( !runningOnPub() ) logger( __FUNCTION__ . "(): title='$title', msg='$msg'" );



    // Brayden August 2016 - replace $.jGrowl with jGrowl, the JS function this PHP function is emulating.
    //						Some

    return <<<EOF
<script type='text/javascript'>

tryJGrowl =	function() 
{
	if(typeof jGrowl === 'function') 
	{
	    jGrowl('$msg', '$title', $life, '$theme', $sticky, '$position');
	} 
	else if($ && typeof $.jGrowl === 'function') 
	{
		$.jGrowl(
			'$msg',
			{
				theme           : '$theme',
				header          : '$title',
				life            : $life,
				position        : '$position',
				sticky          : $sticky,
				openDuration    : 200,
				closeDuration   : 600
			}
		);
	} 
	else 
	{
		setTimeout(tryJGrowl, 250);	
	}
};

tryJGrowl();

</script>
EOF;

    // old method. Doesn't work for overriden jGrowl function... lets use devry's jGrowl() function (that this PHP function is emulating) instead.
    return <<<EOF

<SCRIPT type="text/javascript">
	$.jGrowl(
		'$msg',
		{
			theme			: '$theme',
			header			: '$title',
			life			: $life,
			position		: '$position',
			sticky			: $sticky,
			openDuration	: 200,
			closeDuration	: 600
		}
	);
</SCRIPT>

EOF;

} // }}}

function validEmail( $email, $log=false ) // {{{
{
    /*
        Returns true if the provided email address is in a valid format and the domain exists.
        Courtesy of http://www.linuxjournal.com/article/9585?page=0,3
    */

    $atIndex = strrpos( $email, "@" );

    if( is_bool( $atIndex )  &&  !$atIndex ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - no @ found" ); return false; }

    $domain = substr( $email, $atIndex + 1 );
    $domainLen = strlen( $domain );

    $local = substr( $email, 0, $atIndex );
    $localLen = strlen( $local );

    // if local part length exceeded
    if( $localLen < 1  ||  $localLen > 64 ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - name.length <1 or >64" ); return false; }

    // if domain part length exceeded
    if( $domainLen < 1  ||  $domainLen > 255 ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - domain.length <1 or >255" ); return false; }

    // if local part starts or ends with '.'
    if( $local[0] == '.'  ||  $local[$localLen-1] == '.' ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - name starts or ends with ." ); return false; }

    // if local part has two consecutive dots
    if( preg_match( '/\\.\\./', $local ) ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - name contains two consecutive dots" ); return false; }

    // if any character not valid in domain part
    if( !preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain ) ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - domain contains invalid character" ); return false; }

    // if domain part has two consecutive dots
    if( preg_match( '/\\.\\./', $domain ) ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - domain contains two consecutive dots" ); return false; }

    // if any character not valid in local part unless local part is quoted
    if( !preg_match( '/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace( "\\\\", "", $local ) ) )
    {
        if( !preg_match( '/^"(\\\\"|[^"])+"$/', str_replace( "\\\\", "", $local ) ) ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - name contains invalid character" ); return false; }
    }

    if( $log ) logger("checking DNS record for $domain");

    // if domain not found in DNS
    if( !checkdnsrr( $domain, "MX" )  &&  !checkdnsrr( $domain, "A" ) ) { if( $log ) logger( __FUNCTION__ . "($email):  Failed Test - domain doesn't resove via DNS" ); return false; }

    // if email account exists on remote server
    // TODO

    // No test failed, so... should be good!
    //if( $log ) logger( __FUNCTION__ . "($email):  Appears valid!" );
    return true;
} // }}}

function html2text( $html ) // {{{
{
    logger( __FUNCTION__ . "():   WARNING!!!!   THIS IS BROKEN!    e.g.  'onsite' is returned as 'o ite'           ********************* WARNING *****************" );
    $search = array (
        "'<script[^>]*?>.*?</script>'si",		// Strip out javascript
        "'<[/!]*?[^<>]*?>'si",					// Strip out HTML tags
        "'([rn])[s]+'",							// Strip out white space
        "'&(quot|#34);'i",						// Replace HTML entities
        "'&(amp|#38);'i",
        "'&(lt|#60);'i",
        "'&(gt|#62);'i",
        "'&(nbsp|#160);'i",
        "'&(iexcl|#161);'i",
        "'&(cent|#162);'i",
        "'&(pound|#163);'i",
        "'&(copy|#169);'i",
        "'&#(d+);'e"							// evaluate as php
    );

    $replace = array (
        "",
        "",
        "\1",
        "\"",
        "&",
        "<",
        ">",
        " ",
        chr(161),
        chr(162),
        chr(163),
        chr(169),
        "chr(\1)"
    );

    return preg_replace( $search, $replace, $html );

} // }}}

function getUserLink( $user_id ) // {{{
{
    // Returns an HTML-formatted string containing the user's info.
    if( (int)$user_id != $user_id ) return "Unknown";
    if( ( $r = runQ( "SELECT fullname, email FROM users WHERE user_id = $user_id" ) ) === FALSE ) return "Unknown";
    if( count( $r ) != 1 ) return "Unknown";

    $email = $r[0]['email'];
    $fullname = $r[0]['fullname'];

    if( empty( $email )  &&  !empty( $fullname ) ) return $fullname;		// If we don't have an email addr, but we do have a fullname....

    return '<a href="mailto:'.$email.'" title="Click to send an email...">'.$fullname.'</a>';
} // }}}

function websiteDown( $src=null ) // {{{
{
    Global $smarty;

    if( !empty( $_REQUEST['authCheck'] ) ) exit();			// Silently ignore the failed authcheck 'ping'

    // Don't display smarty errors, etc.
    ini_set( "display_errors", "Off" );

    if( is_object( $smarty )  &&  isset( $_SERVER['REQUEST_URI'] ) )
    {
        $smarty->assign( 'website_error', 'db' );
        $smarty->assign( 'uri', $_SERVER['REQUEST_URI'] );

        $smarty->display( "error_db_tpl.html" );
    }
    else echo "<br /><br /><br /><center><h3>Website is down for maintenance.  Please check back later.</h3></center>\n";
    exit();
} // }}}

function getGeoFence( $lat, $lon, $radiusInMeters ) // {{{
{
    // Returns the min/max lat/lon for the given radius.
    // NOTE:  This is a very rough calculation based on estimates....   Good enough for my purposes....
    // NOTE:  Calculations are relative to 50KM at 49th Parallel (Latitude)
    // With the help of:  http://www.csgnetwork.com/degreelenllavcalc.html

    $modifier = ( 0.0141 - ( ( 49 - $lat ) * 0.0008 ) );

    $lonDelta = ( 0.6833 + ( ( ( ( $modifier + 0.0141 ) / 2 ) * ( $lat - 49 ) ) * 0.975 ) ) / 50000 * $radiusInMeters;

    $latDelta = 0.4496 / 50000 * $radiusInMeters;


    return array(	'radius' => array( 'meters' => $radiusInMeters ),
        'lat' => array( 'in' => $lat, 'min' => round( ( $lat-$latDelta ), 4 ), 'max' => round( ( $lat+$latDelta ), 4 ) ),
        'lon' => array( 'in' => $lon, 'min' => round( ( $lon-$lonDelta ), 4 ), 'max' => round( ( $lon+$lonDelta ), 4 ) ),
    );

} // }}}

function getDistanceBetween( $lat1, $lon1, $lat2, $lon2 ) // return: meters {{{
{
    // Returns the "crow-flies" distance (in metres) between two GPS coordinates
    // XXX  This is a ROUGH estimate!!!!   It may be off by 10% or more.
    // XXX  It does not consider elevation differences between the two points.

    // This algorithm is courtesy of http://sgowtham.net/blog/2009/08/04/php-calculating-distance-between-two-locations-given-their-gps-coordinates/  {{{

    $earthRadius = 3960.00;			// Miles

    $d = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $lon2-$lon1 ) );
    $d = acos( $d );
    $d = rad2deg( $d );
    $d = $d * 60 * 1.1515;			// 60 nautical miles per degree separation of longitudes.  One nautical mile = 1.1515 statute mile.
    $d = round( $d, 4 );
    // }}}

    // Convert miles to KM's...
    $d = $d * 1.60934;

    // Convert KM's to M's...
    $d = $d * 1000;

    return ceil( $d );				// Round up to nearest whole meter

} // }}}

function queryGoogleMaps( $address ) // return array( 'lat' => ?, 'lon' => ? ) {{{
{
    // Query Google Maps to find the Latitude and Longitude of the given address.

    // Address should contain street, city, state, AND country.  I.e.  "5685 Thornhill St. Chilliwack BC Canada"
    // Commas, etc. are not necessary.  Try to exclude Box #'s, Suite #'s, etc.


    $url = "http://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode( $address ) . "&sensor=false";

    $json = file_get_contents( $url );
# 	logger( "json=\n$json" );
    if( empty( $json ) ) { logger( __FUNCTION__ . "():  Connection failed to Google Maps API." ); return false; }

    $info = json_decode( $json, true );
# 	logger( "info=" . print_r( $info, true ) );

    // Error detection {{{
    if( !array_key_exists( 'status', $info ) )
    {
        logger( __FUNCTION__ . "():  Invalid response from Google Maps API.  As follows:" );
        logger( __FUNCTION__ . "():  address: $address" );
        logger( __FUNCTION__ . "():  json:\n$json" );
        logger( __FUNCTION__ . "():  info: " . print_r( $info, true ) );
        return false;
    }

    if( empty( $info['results'] ) )
    {
        logger( __FUNCTION__ . "():  Google Maps API reports 'Address not found (or improperly formed, etc.)." );
        logger( __FUNCTION__ . "():  address: $address" );
        logger( __FUNCTION__ . "():  json:\n$json" );
        logger( __FUNCTION__ . "():  info: " . print_r( $info, true ) );
        return false;
    }

    if(			!array_key_exists( 'geometry', $info['results'][0] )
        ||	!array_key_exists( 'location', $info['results'][0]['geometry'] )
        ||	!array_key_exists( 'lat', $info['results'][0]['geometry']['location'] )
    )
    {
        logger( __FUNCTION__ . "():  Invalid response from Google Maps API.  As follows:" );
        logger( __FUNCTION__ . "():  address: $address" );
        logger( __FUNCTION__ . "():  json:\n$json" );
        logger( __FUNCTION__ . "():  info: " . print_r( $info, true ) );
        return false;
    }
    // }}}

    $loc = $info['results'][0]['geometry']['location'];

    $loc['lon'] = $loc['lng'];
    $loc['longitude'] = $loc['lng'];
    $loc['latitude'] = $loc['lat'];

# 	logger( __FUNCTION__ . "():  address: $address" );
# 	logger( __FUNCTION__ . "():  location: " . print_r( $loc, true ) );
    return $loc;


# 	// Google Maps API v2.0  - Deprecated
#
# 	// Google Maps returns 4 CSV values:  response-code, accuracy, latitude, longtitude
# 	// response code = 200 == OK
# 	//	0=unknown, 1=country-level, 2=region-level, 3=sub-region, 4=town, 5=postcode, 6=street, 7=intersection, 8=address, 9=premise
# 	// This uses Google Maps API, but does not require Google API Key.   Nice!
# 	// NOTE:  You are limited to 2500 queries per day.
#
# # 	logger( __FUNCTION__ . "( $address ):  Querying Google Maps..." );
#
# 	$url = "http://maps.google.com/maps/geo?q=" . urlencode( $address ) . "&output=csv&oe=utf8";
#
# 	$info = explode( ",", file_get_contents( $url ) );
#
# 	// Error detection {{{
# 	if( $info[0] != 200  ||  count( $info ) != 4 )
# 	{
# 		// 620=no-address-found
# 		if( $info[0] != 620 ) logger( __FUNCTION__ . "( $address ) Failed:  Results: " . print_r( $info, true ) );
# 		return false;
# 	}
#
# 	if( $info[1] <= 4 )
# 	{
# 		logger( __FUNCTION__ . "( $address ) Failed - Accuracy too low:  Results: " . print_r( $info, true ) );
# 		return false;
# 	}
#
# 	if( $info[2] < -90  ||  $info[2] > 90  ||  $info[3] < -180  ||  $info[3] > 180 )
# 	{
# 		logger( __FUNCTION__ . "( $address ) Failed - Results out-of-range:  Results: " . print_r( $info, true ) );
# 		return false;
# 	}
# 	// }}}
#
# 	return array( 'lat' => $info[2], 'latitude' => $info[2], 'lon' => $info[3], 'longitude' => $info[3] );

} // }}}
function getGoogleMapsLocation( $addr ) // return array( 'lat' => ?, 'lon' => ? ) {{{
{
    // Make various attempts to query Google Maps for Lat/Lon of the specified Address

    // $addr :  array( 'street' => ?, 'city' => ?, 'state' => ?, 'zip' => ? );

    // Assemble address info {{{
    $street = preg_replace( '/[^a-zA-Z0-9_\-\ ]/si', '', $addr['street'] );
    $street = preg_replace( '/Hwy/', 'Highway', $street );
    $street = preg_replace( '/ /', '+', $street );          // Google wants + instead of space

    $city = getAlNum( $addr['city'] );

    $state = $addr['state'];

    $zip = preg_replace( "/ /", "+", $addr['zip'] );

    $country = "USA";
    $provinces = array( "AB", "BC", "MB", "NT", "ON", "QC", "SK", "YT" );
    if( in_array( $addr['state'], $provinces ) ) $country = 'Canada';
    // }}}

    // Assemble various addresses to attempt to query Google with.  In order of priority {{{
    $addresses = array();
    if( !empty( $street )  &&  !empty( $city )  &&  !empty( $state )  && !empty( $country ) )
    {
        $addresses[] = "$street, $city, $state, $country";
        if( !empty( $zip ) )
        {
            $addresses[] = "$street, $city, $state $zip, $country";
            $addresses[] = "$city, $state $zip, $country";
            $addresses[] = "$zip";
        }
    }
    // }}}

    // Query Google Maps for each calculated address until a successful result is found or we run out of addresses to try
    $gm = FALSE;
    foreach( $addresses AS $attempt => $address ) if( ( $gm = queryGoogleMaps( $address ) ) !== FALSE ) break;

    return $gm;

} // }}}

function myTruncate( $string, $limit, $break=".", $pad="..." ) // {{{
{
    // Original PHP code by Chirp Internet: www.chirp.com.au
    // Please acknowledge use of this code by including this header.
    // http://www.the-art-of-web.com/php/truncate/

    // return with no change if string is shorter than $limit
    if( strlen( $string ) <= $limit ) return $string;

    // is $break present between $limit and the end of the string?
    if( !empty( $break )  &&  ( $breakpoint = strpos( $string, $break, $limit ) ) !== FALSE )
    {
        if( $breakpoint < strlen( $string ) - 1)
        {
            $string = substr( $string, 0, $breakpoint ) . $pad;
        }
    }
    else
    {
        // Otherwise just break at the length $limit (less length of pad)
        $string = trim( substr( $string, 0, ( $limit - strlen( $pad ) ) ) ) . $pad;
    }

    return $string;
} // }}}

function getTZOffsetFromAreaCode( $area_code ) // {{{
{
    // Return how many hours difference between our local area code (604) and what's specified.
    // NOTE:  SK does not use Daylight Savings Time
    // See http://en.wikipedia.org/wiki/List_of_British_Columbia_area_codes (and links to other states/provs from this page)

    // NOTE:  The area_codes index is the offset that gets returned....
    // NOTE:  If area code not found, default to '0'  (i.e. probably California or something....)

    $area_codes = array(
        0	=> array(  250, 604, 778, 236,   206, 253, 360, 425, 509, 564,   503, 541, 971, 458,   ),					# BC, WA, OR
        1	=> array(  403, 780, 587,   208,   406,  ),																	# AB, ID, MT
        2	=> array(  306, 639,   204, 431,  ),																		# SK, MB
        3   => array(  226, 249, 289, 343, 416, 519, 613, 647, 705, 807, 905,   418, 438, 450, 514, 581, 819, 873,  ),	# ON, QC
    );

    $found = false;
    foreach( $area_codes AS $offset => $codes ) if( in_array( $area_code, $codes ) ) { $found = true; break; }

    if( !$found ) $offset = 0;		# reset back to zero (because this is our biggest delivery area)

    // If SK, and we're currently in DST...
    if( ( $area_code == '306'  ||  $area_code == '639' )  &&  date( 'I' ) == 1 ) $offset -= 1;

    logger( __FUNCTION__ . "( $area_code ):  Offset = $offset" );
    return $offset;

} // }}}

$spinnerTime = $lastMessageTime = 0;		// How much time is spent running this function....
function spinnerMsg( $msg, $ccLog=false ) // {{{
{
    Global $spinnerTime, $lastMessageTime;
    $fStart = microtime( true );

    if($fStart - $lastMessageTime < (MIN_MESSAGE_UPDATE_MS/1000)) // If less than min 10ms between messages per thread
    {
        //echo $fStart . " - $lastMessageTime == ".($fStart-$lastMessageTime)." < " .(MIN_MESSAGE_UPDATE_MS/1000) . "<br>";
        $lastMessageTime = $fStart;
        return false;
    }

    $lastMessageTime = $fStart;
    // Send an update to the onscreen Spinner box to tell user what's happening....

    /* NOTE:  Typically, spinner box is displayed and page is loaded via location.href.
              As such, original context no longer exists, so ajax push doesn't work.
              Instead, you must...

              Convert location.href calls to:  showSpinner(); $.post( url, function( data ) { $('body').html( data ); } );
    */

    // If running from command line {{{
    if( empty( $_SERVER['REQUEST_URI'] ) )
    {
        logger( __FUNCTION__ . "():  msg=$msg" );

        $spinnerTime += ( microtime( true ) - $fStart );
        return true;
    } // }}}

    if( $ccLog ) logger( __FUNCTION__ . "(): $msg\n" );

    $msg = str_replace( '"', "&quot;", $msg );

    Session_Open();			// Must open (and then close) session to ensure messages are written and retrieved while parent thread is running.
    $_SESSION['status_msg'] = $msg;
    Session_Close();

    Global $auth;
    if($auth && is_object($auth)) {
        require_once 'lib/websocket/WebSocket.php';
        if(WebSocket\Server::active()) {
            $user = $auth->getUsername();
            $page = PAGE_ID;
            $action = "spinnermsg";
            $data = [];
            $data['message'] = $msg;
            WebSocket\Server::broadcast($user, $page, $action, $data, false);
        }

    }
    $spinnerTime += ( microtime( true ) - $fStart );
    return true;

} // }}}

function mssql_escape_string( $s=null ) // {{{
{
    // Functional equivalent to pg_escape_string() & mysql_escape_string(), but for Microsoft SQL Server

    // This is thanks to http://stackoverflow.com/questions/574805/how-to-escape-strings-in-sql-server-using-php
    $non_displayables = array(
        '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
        '/%1[0-9a-f]/',             // url encoded 16-31
        '/[\x00-\x08]/',            // 00-08
        '/\x0b/',                   // 11
        '/\x0c/',                   // 12
        '/[\x0e-\x1f]/'             // 14-31
    );
    foreach( $non_displayables as $regex ) $s = preg_replace( $regex, '', $s );


    $s = str_replace( "'", "''", $s );
    $s = str_replace( '"', '""', $s );
    return $s;
} // }}}

if( !function_exists( 'gzdecode' ) ) // {{{
{
    // NOTE:  Use base64_encode to ensure this is binary-safe
    function gzdecode( $string ) { return file_get_contents( 'compress.zlib://data:who/cares;base64,' . base64_encode( $string ) ); }
} // }}}

function runningOnPub() // {{{
{
    // Returns TRUE if this code is running on the published Intranet website

    // XXX See cli/test_runningOnPub.php for testing any changes to this function. XXX

    // If running via Apache...
    if( !empty( $_SERVER['REQUEST_URI'] ) ) $dir = basename( dirname( $_SERVER['SCRIPT_FILENAME'] ) );

    // If running via cmdline...
    else $dir = basename( getcwd() );

    if( $dir == 'intranet'  ||  $dir == 'intranet-pub' ) return true;
    return false;
} // }}}
function runningFromCron() // {{{
{
    // Returns TRUE if this code is running from /etc/crontab.
    // i.e. returns FALSE if running from cmdline.

    // If the SSH_CLIENT environment variable is defined, then we're running from cmdline...
    return !getenv('SSH_CLIENT');
}
function runningFromCrontab() { return runningFromCron(); }
// }}}

function version() // {{{
{
    $v1 = phpversion();
    $v2 = explode( "-", $v1 );
    $inf = explode( ".", $v2[0] );
    $php = array(
        'string_full' => $v1,
        'string' => $v2[0],
        'integer' => ( ( $inf[0] * 10000 ) + ( $inf[1] * 100 ) + $inf[2] ),
        'major' => $inf[0],
        'minor' => $inf[1],
        'build' => $inf[2]
    );

    $data = array( 'php' => $php );

# 	logger( $data );
    return $data;

} // }}}

function isHTML( $input ) // {{{
{
    // Check for a string containing '/>' with zero or more letters between the two symbols.
    return preg_match( "/\/[a-z]*>/i", $input ) != 0;
} // }}}

// Create GUID {{{
# USE:
#echo "Guid 1 = ".Guid()."\n";
#echo "Guid 2 = ". new Guid()."\n";
function getGuid( $opt=null )
{
    $guid = new Guid( $opt );
    return $guid->getGuid();
}
function Guid( $opt=null ) { return new Guid( $opt ); }
Class Guid
{
    private $guid = null;                   // NOTE:  Some uses (i.e. MS) require this value wrapped in curly braces {...}
    private $alnumOnly = false;             // Default GUID includes braces & dashes.  This returns alphanumeric only.
    public function __construct( $alnum=false )
    {
        $this->alnumOnly = $alnum;

        if( function_exists( 'com_create_guid' ) )
            $this->guid = com_create_guid();
        else
            $this->guid = sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535),
                mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) );
        return $this->guid;
    }
    public function __toString()
    {
        if( $this->alnumOnly ) return preg_replace( '/[^a-zA-Z0-9_]/si' , '' , $this->guid );
        else return $this->guid;
    }
    public function getGuid() { return $this->__toString(); }
}
// }}}

function getBaseURL() // {{{
{
    if( empty( $_SERVER ) ) return false;
# 	logger( $_SERVER );

    $host		= $_SERVER['SERVER_NAME'];
    $port		= $_SERVER['SERVER_PORT'];
    $protocol	= ( $_SERVER['HTTPS'] == 'on' ? 'https' : 'http' );

    $root		= dirname( $_SERVER['REQUEST_URI'] );

    $url = "$protocol://$host";

    if( ( $protocol == 'https' && $port != 443 )  ||  ( $protocol == 'http' && $port != 80 ) ) $url .= ":$port";

    $url .= $root;

# 	logger( $url );
    return $url;

} // }}}

function getClientIP() // {{{ chriswiegman.com
{
    //Just get the headers if we can or else use the SERVER global
    if ( function_exists( 'apache_request_headers' ) ) {
        $headers = apache_request_headers();
    } else {
        $headers = $_SERVER;
    }
    //Get the forwarded IP if it exists
    if ( array_key_exists( 'X-Forwarded-For', @$headers ) && filter_var( @$headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $the_ip = $headers['X-Forwarded-For'];
    } elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', @$headers ) && filter_var( @$headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
    ) {
        $the_ip = @$headers['HTTP_X_FORWARDED_FOR'];
    } else {

        $the_ip = filter_var( @$_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
    }
    return $the_ip;
} // }}}

/**
 * @param null $ip
 * @return bool
 */
function isLocalIP($ip = null) // {{{
{
    $ip = empty($ip) ? getClientIP() : getIP($ip);
    $internal_client = ( ( substr( $ip, 0, 7 ) != "192.168" && substr( $ip, 0, 3 ) != "10." ) ? false : true );
    return $internal_client;
} // }}}

/**
 * @return bool
 */
function isVPNIP($ip = null) // {{{
{
    $ip = empty($ip) ? getClientIP() : getIP($ip);
    if(!isLocalIP($ip)) return false;

    $ip_bytes = explode(".",$ip);

    // If no NAT (devry)
    if(	$ip_bytes[0] == 10 &&
        $ip_bytes[1] == 99 &&
        $ip_bytes[2] == 0) return true;

    // If behind VPN NAT (devry)
    if( $ip_bytes[0] == 10 &&
        $ip_bytes[1] == 0  &&
        $ip_bytes[2] == 1  &&
        $ip_bytes[3] == 55)
        return true;

    return false;
    /*
    $vpn_client = ( ( substr( $ip, 0, 7 ) != "10.0.99") ? false : true );
    return $vpn_client;*/
} // }}}

function arrayToTable($arr, $alert_empty=false) // {{{
{
    $html = "<table style='border: 1px solid;'>\n";
    if( is_array( $arr ) )
    {
        foreach( $arr AS $key => $val )
        {

            if($alert_empty && empty($val))             $val = "<i style='color: red'>No data!</i>";
            elseif(is_object($val))		$val = arrayToTable((array) $val);
            elseif(is_array($val))      $val = arrayToTable($val);

            $html .= "<tr>
					<td style='
							border-right: 1px solid gray;
							border-bottom: 1px solid gray;
					'><b>$key</b></td><td style='border-bottom: 1px solid gray;'>$val</td>
					</tr>
					";
        }
    }
    $html .= "</table>";
    return $html;
} // }}}

function get_normalized_FILES_array()  // {{{
{
    /*	Kevin Traas - 21 Nov 2016

        PHP is really screwy how it handles file uploads....
        A single file upload has 'name', 'type', etc. with actual values.
        A multi-file upload has 'name', 'type', etc. as arrays of the values....  Arrggh!

        This function normalizes either pattern into one model, so your code doesn't have to adapt to single vs multi uploads....

        This function is modified from:  http://php.net/manual/en/features.file-upload.post-method.php#118858

    */

    $normalized = array();

    foreach( $_FILES as $idx1 => $file )
    {

        if( !is_array( $file['name'] ) )
        {
            $normalized[$idx1][] = $file;
            continue;
        }

        foreach( $file['name'] as $idx2 => $name )
        {
            $normalized[$idx1][$idx2] = array(
                'name'		=> $name,
                'type'		=> $file['type'][$idx2],
                'tmp_name'	=> $file['tmp_name'][$idx2],
                'error'		=> $file['error'][$idx2],
                'size'		=> $file['size'][$idx2]
            );
        }
    }

    //logger( $normalized );
    return $normalized;

} // }}}

function get_file_mime_type( $file ) // {{{
{
    // You can't trust the mime-type sent with uploaded files.
    // This does a verification using locally-installed (i.e. hopefully more trustworthy) tools.

    $verbose = true;

    $file = escapeshellarg( $file );

    if( $verbose ) logger( __FUNCTION__ . "():  Analyzing $file..." );

    // Method #1 - using 'file' {{{
    // This works most of the time, but fails on some MS Office 'x'-type documents.
    $mime_type = trim( `file --mime-type -b $file 2>/dev/null` );

    if(			empty( $mime_type )
        ||	strtoupper( substr( $mime_type, 0, 5 ) ) == 'ERROR'
        ||	stripos( $mime_type, 'no summary info' ) !== FALSE
    )
    {
        $mime_type = false;
        if( $verbose ) logger( __FUNCTION__ . "():  Method #1: FAIL" );
    }

    if( $mime_type !== FALSE )
    {
        if( $verbose ) logger( __FUNCTION__ . "():  Method #1: $mime_type" );

        // octet-stream just means "wasn't able to figure out what this binary data is", so... try Method #2 below...
        if( $mime_type != 'application/octet-stream' ) return $mime_type;
    }
    // }}}

    // Method #2 - using 'xdg-mime' (if available) {{{
    // xdg-mime was installed via:  apt-get install xdg-utils
    // So far, this seems to work whenever 'file' doesn't.
    $mime_type = trim( `which xdg-mime >/dev/null 2>&1 && xdg-mime query filetype $file 2>/dev/null` );
    if( empty( $mime_type ) || $mime_type === FALSE )
    {
        if( $verbose ) logger( __FUNCTION__ . "():  Method #2: FAIL" );
        return false;
    }
    if( $verbose ) logger( __FUNCTION__ . "():  Method #2: $mime_type" );
    // }}}

    return $mime_type;

} // }}}


