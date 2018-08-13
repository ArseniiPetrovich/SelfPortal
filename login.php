<?php
require $_SERVER['DOCUMENT_ROOT'].'/config/config.php';
function check_ldap_user($username,$email,$department,$sid){
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
	// Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    else {
		//mysqli_query($conn,'SET MAMES utf8');
		$query="SELECT `id`,`table_name` from `user_types` where LOWER(`title`) like LOWER('ldap')";
        $usertype=mysqli_fetch_array(mysqli_query($conn,$query));
        $query="SELECT * FROM `users` WHERE `username`='$username' and `user_type`='$usertype[id]'";
        if ($result=mysqli_query($conn,$query)) {
        	if (mysqli_num_rows($result)== 0 ) {
				$query="SELECT `id` from departments where LOWER(`title`) like LOWER('$department')";
				if (mysqli_num_rows($dep=mysqli_query($conn,$query))===0) {
					mysqli_query($conn,"INSERT INTO `departments` values (NULL,'$department',0,0,0)") or die ("Can't insert to mysql");
					$dep=mysqli_insert_id($conn);
				}
				else $dep=mysqli_fetch_row($dep)[0];
            	$query="INSERT INTO `users` VALUES (NULL, '$username', '$email', '$dep',$usertype[id],1,NULL,NULL,NULL)";
                mysqli_query($conn,$query) or die ("Can't insert to mysql");
				$query="INSERT INTO `$usertype[table_name]` VALUES (NULL, '".mysqli_insert_id($conn)."', '$sid')";
                mysqli_query($conn,$query) or die ("Can't insert to mysql");
                $query="SELECT `id` FROM `users` WHERE `username`='$username' and `user_type`='$usertype[id]'";
                if($result=mysqli_query($conn,$query)){
                   $userid=mysqli_fetch_array($result);
                	return $userid['user_id'];
                }
            }
            else {
            	$userid=mysqli_fetch_array($result);
                return $userid['user_id'];
            }
        }
    }
    $conn->close();
}
?>
