<?php 
/*


	functions for valdidation fo fields

	all functions return false if requirements are not met
	else they will return the value

*/
$do_validation = true; // enables validation

if (!isset($db)) $db = new mysql_db;

function checkmail($cemail) {
    if (eregi("^[0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.([a-z]{2}|com|edu|gov|int|mil|net|org|shop|aero|biz|coop|info|museum|name|pro)$", $cemail, $check))
        return $cemail;
    return false;
}

function checkenum($enum_value, $field) {
	$values = getenums($field);
	if (in_array($enum_value, $values)) return $enum_value;
	else return false;
}

function getenums($colum) {
	global $maintable;
    global $db;
    $sql = "SHOW COLUMNS FROM $maintable LIKE '$colum'";
    //$result = mysql_query($sql);
    $db->query($sql);
    if (mysql_num_rows($db->resid)>0)
    //if (mysql_num_rows($result))
    {
        $enums=$db->data();
        $values=explode("','",preg_replace("/(enum|set)\('(.+?)'\)/","\\2",$enums[1]));
        return $values;
    }
}

function check_url($url) {
	$url = str_replace("\\","/",$url);
	#echo $url;
	if (substr($url,0,1) == "/") return $url;
	elseif (substr($url,0,2) == "./") return $url;
	elseif (substr($url,0,3) == "../") return $url;
	else
	{
		if (!is_url($url))
		{
			#echo "<br>$url ist keine url<br>";
			$url = "http://" . $url;
			if (!is_url($url))
			{
				#echo "<br>$url ist immer noch keine url<br>";
				return false;
			}
			else return $url;
		}
		else return $url;
	}

}

function is_url( $url )
{

     if ( !( $parts = @parse_url( $url ) ) )
          return false;
     else {
     	#echo "<pre>";
     	#print_r ($parts);
     	#echo "</pre>";
     if ( @$parts['scheme'] != "http" && @$parts['scheme'] != "https" && @$parts['scheme'] != "ftp" && @$parts['scheme'] != "gopher" )
          return false;
     else if ( !@eregi( "^[0-9a-z]([-.]?[0-9a-z])*\.[a-z]{2,6}$", $parts['host'], $regs ) )
          return false;
     else if ( !@eregi( "^([0-9a-z-]|[\_])*$", $parts['user'], $regs ) )
          return false;
     else if ( !@eregi( "^([0-9a-z-]|[\_])*$", $parts['pass'], $regs ) )
          return false;
     else if ( !@eregi( "^[0-9a-z/_\.@~\-]*$", $parts['path'], $regs ) )
          return false;
     else if ( !@eregi( "^[0-9a-z?&=#\,]*$", $parts['query'], $regs ) )
          return false;
     }
     return true;
}


function checkfornull($field) {
	global $maintable;
    global $db;
	$sql = 'SHOW COLUMNS FROM '.$maintable.' LIKE "'.$field.'"';
    $db->query($sql);
    if (mysql_num_rows($db->resid)>0)
    {
        $line=$db->data();
        if (($line['Null']) == 'YES') return true;
        else return false;
    }
}

?>