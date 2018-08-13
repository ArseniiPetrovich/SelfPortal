<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');
include ("login.php");
// Initialize session
ini_set('session.cookie_httponly', '1');
session_start();

function write_log($entry){
    $file = fopen(LOG_FOLDER."/access.log", "a");
    $entry=preg_replace("/--os-username .* --os-password .* --os-region-name/","--os-username ******** --os-password ******* --os-region-name",$entry);
	$entry=preg_replace("/--username .* --password .* /","--username ******** --password *******",$entry);
    fwrite($file,$entry."\n");
    fclose($file);
}

function SIDtoString($ADsid)
{
    $sid = "S-";
    //$ADguid = $info[0]['objectguid'][0];
    $sidinhex = str_split(bin2hex($ADsid), 2);
    // Byte 0 = Revision Level
    $sid = $sid.hexdec($sidinhex[0])."-";
    // Byte 1-7 = 48 Bit Authority
    $sid = $sid.hexdec($sidinhex[6].$sidinhex[5].$sidinhex[4].$sidinhex[3].$sidinhex[2].$sidinhex[1]);
    // Byte 8 count of sub authorities - Get number of sub-authorities
    $subauths = hexdec($sidinhex[7]);
    //Loop through Sub Authorities
    for($i = 0; $i < $subauths; $i++) {
        $start = 8 + (4 * $i);
        // X amount of 32Bit (4 Byte) Sub Authorities
        $sid = $sid."-".hexdec($sidinhex[$start+3].$sidinhex[$start+2].$sidinhex[$start+1].$sidinhex[$start]);
    }
    return $sid;
}

function authenticate($user, $password) {
    if(empty($user) || empty($password)) return false;
	
	$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
	// Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    else {
		$query="SELECT * from `ad_groups`";
        $groups=mysqli_query($conn,$query) or die ("Could not get any results from mysql");
		$query="SELECT `ldap_dn` from `ad_groups` GROUP BY `ldap_dn`";
        $dns=mysqli_query($conn,$query) or die ("Could not get any results from mysql");
    }

    // Active Directory server
    $ldap_host = LDAP_HOST;

    // Domain, for purposes of constructing $user
    $ldap_usr_dom = LDAP_USR_DOM;

    // connect to active directory
    $ldap = ldap_connect($ldap_host);

    // verify user and password
    if($bind = @ldap_bind($ldap, $user.$ldap_usr_dom, $password)) {
        // valid
        // check presence in groups
        $filter = "(sAMAccountName=".$user.")";
        $attr = array("memberof","mail","displayName","department","objectSid","pwdlastset");
		$result=array();
		foreach ($dns as $dn)
		{
			$result=array_merge($result,ldap_get_entries($ldap, ldap_search($ldap, $dn['ldap_dn'], $filter, $attr)));
		}
        ldap_unbind($ldap);
		$access=0;
        // check groups
        foreach($result[0]['memberof'] as $grps) {
			foreach ($groups as $group)
			{
				if(strpos($grps, $group['title'])) { if ($group['rights']>$access) $access=$group['rights']; }
			}

        }
        if($access !== 0) {
            $department="empty";
            $mail="empty";
            // establish session variables
            $_SESSION['user'] = $user;
            $_SESSION['access'] = $access;
            $_SESSION['displayname']=$result[0]['displayname'][0];
            $changedate=date("d-m-Y H:i:s",$result[0]['pwdlastset'][0]/10000000-11644473600);
            $_SESSION['pwdlastset']=date_diff(new DateTime(), new DateTime($changedate))->days;
            if (isset($result[0]['department'][0])) $department=$result[0]['department'][0];
            if (isset($result[0]['mail'][0])) $mail=$result[0]['mail'][0];

            $_SESSION['user_id']=check_ldap_user($user,$mail,$department,SIDtoString($result[0]['objectsid'][0]));
		}
    } else {
		$query="SELECT `table_name` from `user_types` where LOWER(`title`) like LOWER('internal')";
        $table_internal=mysqli_fetch_array(mysqli_query($conn,$query)) or die ("Could not get any results from mysql");
		$query="SELECT * from `".$table_internal['table_name']."`,`users` where `".$table_internal['table_name']."`.`global_uid`=`users`.`user_id` and `rights`>0";
        $int_users=mysqli_query($conn,$query) or die ("Could not get any results from mysql");
		$_SESSION['access']=0;
		foreach ($int_users as $int_user)
		{
			if ($int_user['username']===$user) 
				if (password_verify ($password , $int_user['passwd'])) 
				{
					if ($int_users['rights']>$_SESSION['access'])
					{
						$_SESSION['user'] = $user;
						$_SESSION['access']=$int_users['rights'];
						$_SESSION['displayname']=$result[0]['displayname'][0];
						$_SESSION['pwdlastset']="N/A";
						$_SESSION['user_id']=$int_users['global_uid'];
					}
				}
		}
        // invalid name or password
        return false;
    }
	$conn->close();
}
?>
