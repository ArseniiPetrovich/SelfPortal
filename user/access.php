<?php
#Check right
function access_level($resourse,$action,$provider=null,$resource_id=null) {
    if (access_level_internal($resourse,$action,$provider,$resource_id))
    {
        $log=date('Y-m-d H:i:s')." [ACCESS][INFO] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to ".$action." ".$resourse;
        if (isset($resource_id)) $log.=" (id ".$resource_id.")";
        write_log ($log.". Access granted.");
        return true;
    }
    else
    {
        $log=date('Y-m-d H:i:s')." [ACCESS][WARNING] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to ".$action." ".$resourse;
        if (isset($resource_id)) $log.=" (id ".$resource_id.")";
        write_log ($log.". Access denied!");
        return false;
    }
}

function access_level_internal($resource,$action,$provider,$resource_id) {
    $access=false;
    if (mysqli_num_rows(sql_query("SELECT * FROM `permissions` where `actions`=(SELECT `id` from `actions` where `resource`='all' AND `action`='all') and `rights`=".$_SESSION['access']))) { return $access=true;}
    $rights=mysqli_fetch_array(sql_query("SELECT MAX(`bypass_resource_check`) FROM `permissions` where `actions` IN (SELECT `id` FROM `actions` where (`resource`='$resource' or `resource`='all')  AND (`action`='$action' OR `action`='all'))  AND (`provider`='$provider' OR `provider` IS NULL) AND `rights`=".$_SESSION['access']));
	if ($rights[0]==0 && !is_null($rights[0])) { 
		return mysqli_num_rows(sql_query("SELECT * from `$resource` WHERE `user_id`='$_SESSION[user_id]' and `id`='$resource_id'")) > 0
			? true
			: false ;
	}
	else if ($rights[0]==1) return true;
	else return false;
}

function sql_query($query) {
    $owner=false;
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } else {
            $result=mysqli_query($conn,$query) or die("MySQL error: " . mysqli_error($conn) . "<hr>\nQuery: $query");
            //if (mysqli_num_rows($result)>0) { $owner=true; }
        }
    $conn->close();
    return $result;
}

function write_log($entry){
    $file = fopen(LOG_FOLDER."/selfportal.log", "a");
    $entry=preg_replace("/--os-username .* --os-password .* --os-region-name/","--os-username ******** --os-password ******* --os-region-name",$entry);
	$entry=preg_replace("/--username .* --password .* /","--username ******** --password *******",$entry);
    fwrite($file,$entry."\n");
    fclose($file);
}
