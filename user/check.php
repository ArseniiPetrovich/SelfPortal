<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');
ini_set('session.cookie_httponly', '1');
session_start();
if (!isset($_SESSION['user_id'])) die (http_response_code(401));
include_once $_SERVER['DOCUMENT_ROOT'].'/config/config.php';
include_once ("user.inc");
include_once ("providers.php");
include_once ("access.php");
    if (
		!@access_level(
			isset($_POST['subaction'])?$_POST['action']:$_POST['type'],
			isset($_POST['subaction'])?$_POST['subaction']:$_POST['action'],
			$_POST['provider'],
			$_POST['id'])
	   ) die (http_response_code(401));

    $flag=false;
    $multiquery=false;
	$cli_flag=false;
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
	if (!empty($_POST['provider']) && isset($_POST['provider']))
	{
		if (!server_db("SELECT `enabled` from `providers` where `title`='".$_POST['provider']."'")) {die ("DISABLED");}
		$provider_id=mysqli_fetch_assoc(server_db("SELECT `id` FROM `providers` where LOWER(`title`)=LOWER('".$_POST['provider']."')"));
	}
	else $_POST['provider']='';
    switch ($_POST['type']) {
        case "vm":
        case "vms":		
        switch ($_POST['provider']) {
            case "openstack":
                switch ($_POST['action']) {
                    case "createserver":
						$server_id=create_openstack_vm($_POST['name']['image'],$_POST['name']['flavor'],$_POST['name']['keypair'],$_POST['name']['name'],$_SESSION['user_id'],$_SESSION['user']);
                        if (isset($server_id)) {
                            if (!preg_match('/\s/',$server_id)){
                            $query="INSERT INTO `vms` (id,ip,user_id,title,exp_date,provider,status,cleared) SELECT '$server_id',NULL,'".$_SESSION['user_id']."','".$_POST['name']['name']."','".$_POST['name']['date']."',`providers`.`id`,(select `id` from `vms_statuses` where `title`='ENABLED'),0 from `providers` where `title`='OpenStack'";
                            }
                            else {
                                ob_start();
                                var_dump($_POST);
                                $postdump = ob_get_clean();
                                include_once $_SERVER['DOCUMENT_ROOT'].'/modules/tasks.php';
                                ob_start();
                                write_log(date('Y-m-d H:i:s')." [OPENSTACK][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to create VM in openstack. POST: ".$postdump.". OpenStack message: ".$server_id);
                                send_notification(MAIL_ADMIN,"Hello, Administrator! Something went wrong when user with id ".$_SESSION['user_id']." tried to create VM in OpenStack. Please, check it. Here is the details of his POST query:<pre> ".$postdump."</pre> And also here is the error, returned by OpenStack: ".$server_id);
                                ob_get_clean();
                                echo preg_replace("(\(.*\))","",$server_id);
								$query="INSERT INTO `vms` (id,ip,user_id,title,exp_date,provider,status,cleared) SELECT '$server_id',NULL,'".$_SESSION['user_id']."','".$_POST['name']['name']."','".$_POST['name']['date']."',`providers`.`id`,(select `id` from `vms_statuses` where `title`='FAILURE'),0 from `providers` where `title`='OpenStack'";
                            }
                        }
                        else {
							ob_start();
                            var_dump($_POST);
                            $postdump = ob_get_clean();
                            include_once $_SERVER['DOCUMENT_ROOT'].'/modules/tasks.php';
                            write_log(date('Y-m-d H:i:s')." [OPENSTACK][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to create VM in openstack. POST: ".$postdump);
                            send_notification(MAIL_ADMIN,"Hello, Administrator! Something went wrong when user with id ".$_SESSION['user_id']." tried to create VM in openstack. Please, check it. Here is the details of his POST query: ".$postdump);
                        }
                        break;
                    case "list": get_vms($_POST['panel'],$_POST['provider']);
                        break;
                    case "info": $openstack_cli .=" server show '$_POST[id]' -f json";
                        $cli_flag=true;
                        break;
					case "backupvm": if (mysqli_num_rows(server_db("SELECT * FROM `snapshots` where `vm_id` like '".$_POST['id']."' and `status` like 'ENABLED'"))>0) {
							echo "Snapshot for this VM already exists! Delete it first.";
							return;
						} 
						$backup_string=$openstack_cli. " server backup create --type spsnapshot '".$_POST['id']."' -f json 2>&1";
						$cli_result=shell_exec($backup_string);
						$json_cli_result=json_decode($cli_result);
						if ($json_cli_result)
						{
							$query="INSERT INTO `snapshots` VALUES ('".$json_cli_result->{'id'}."','".$_POST['id']."','".date('Y-m-d', strtotime("+".(defined(SNAPSHOT_DEFAULT_PERIOD)?SNAPSHOT_DEFAULT_PERIOD:1)." days"))."','".$provider_id['id']."','<span class=\"label label-success\">ENABLED</span>',0)";
							$openstack_cli.=" image unset --property sp ".$json_cli_result->{'id'};
							$cli_flag=true;
						}
						else { echo $cli_result; return; }
						break;
                    case "stopvm": $openstack_cli .=" server stop '$_POST[id]'";
                        $cli_flag=true;
						$multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='DISABLED');UPDATE `vms` SET `status`=@A where `vms`.`id`='$_POST[id]';";
                        break;
                    case "startvm": $openstack_cli .=" server start '$_POST[id]'";
						$multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='ENABLED');UPDATE `vms` SET `status`=@A where `vms`.`id`='$_POST[id]';";
                        $cli_flag=true;
                        break;
                    case "terminatevm": $openstack_cli .=" server delete '$_POST[id]'";
                        $cli_flag=true;
						$multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='TERMINATED');UPDATE `vms` set `status`=@A WHERE `id`= '$_POST[id]';";
                        break;
                    case "rebootvm": $openstack_cli .=" server reboot '$_POST[id]'";
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='ENABLED');UPDATE `vms` SET `status`=@A where `vms`.`id`='$_POST[id]';";
                        $cli_flag=true;
                        break;
                    case "vnc": $openstack_cli .=" console url show '$_POST[id]' -f json";
                        $cli_flag=true;
                        break;
                    case "images": $openstack_cli .=" image list --property sp=allow -f json";
                        $cli_flag=true;
                        break;
                    case "imagedetails": $openstack_cli .=" image show '$_POST[id]' -f json";
                        $cli_flag=true;
                        break;
                    case "flavor": $openstack_cli .=" flavor list -f json";
                        $cli_flag=true;
                        break;
                    case "flavordetails": $openstack_cli .=" flavor show '$_POST[id]' -f json";
                        $cli_flag=true;
                        break;
                    case "assignip":
                        add_ip_to_server($_POST['id'],get_free_ip());
                        break;
					case "assign":
                        $query="INSERT INTO `vms` (id,ip,user_id,title,exp_date,provider,status,cleared) SELECT '$_POST[vmid]',NULL,'$_POST[user]','$_POST[vmname]','".date('Y-m-d', strtotime("+1 days"))."',`providers`.`id`,(select `id` from `vms_statuses` where `title`='ENABLED'),0 from `providers` where `title`='OpenStack'";
                        break;
					case "dbinfo": 
						$query="SELECT `vms`.`id`,`vms`.`ip` as addresses,`vms`.`title` as name,`vms`.`comment` from `vms` WHERE `vms`.`id`= '".$_POST['id']."' UNION SELECT `tasks`.`id`,'',`tasks`.`title` as name,`tasks`.`comment` from `tasks` where `tasks`.`id`='".$_POST['id']."'";
                        break;
					case "clearvm":
						$query="UPDATE `vms` SET `cleared`=1 WHERE `id`= '".$_POST['id']."'";
                        break;	
					case "snapshots":
						switch ($_POST['subaction']){
						case "terminate":
							$query="UPDATE `snapshots` SET `status`='<span class=\"label label-danger\">TERMINATED</span>' WHERE `snapshot_id`= '".$_POST['id']."'";
							$openstack_cli .= "  image delete ".$_POST['id'];						
							$cli_flag=true;
							break;
						case "clear": 
							$query="UPDATE `snapshots` SET `cleared`=1 WHERE `snapshot_id`= '".$_POST['id']."'"; break;
						case "extend":
                			$query="UPDATE `snapshots` set `exp_date`=DATE_ADD(`snapshots`.`exp_date`, INTERVAL '$_POST[days]' DAY) WHERE id='$_POST[id]'";
                    		break;
						case "info": $openstack_cli .=" image show ".$_POST['id'];
                        	$cli_flag=true;
							break;
						case "restore":
							restore_vm($_POST['id'],$_POST['provider']);
							//$cli_flag=true;
							break;
						case "list":
                        	get_snapshots($_POST['panel'],$_POST['provider']);
							break;
						}
						break;
                }
				$cli=$openstack_cli;
				break;
			case "vsphere":
				switch ($_POST['action']) {
                    case "createserver":
						$server_id=create_vsphere_vm($_POST['name']['image'],$_POST['name']['name'],$_SESSION['user'],$_SESSION['user_id']);
                        if (isset($server_id)) {
                            if (!preg_match('/\s/',$server_id)){
                            $query="INSERT INTO `tasks` (id,user_id,title,exp_date,provider,status,cleared) SELECT '$server_id','".$_SESSION['user_id']."','".$_POST['name']['name']."','".$_POST['name']['date']."','".$provider_id['id']."',`vms_statuses`.`id`,0 from `vms_statuses` where LOWER(`title`) like LOWER('BUILDING')";
							shell_exec ('(sudo crontab -l -u root | grep -v "/modules/tasks.php --action vmupdate"; echo "* * * * * /usr/bin/php '.$_SERVER['DOCUMENT_ROOT'].'/modules/tasks.php --action vmupdate") | sudo crontab -u root -');
                            }
                            else {
                                ob_start();
                                var_dump($_POST);
                                $postdump = ob_get_clean();
                                include_once $_SERVER['DOCUMENT_ROOT'].'/modules/tasks.php';
                                write_log(date('Y-m-d H:i:s')." [VSphere][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to create VM in openstack. POST: ".$postdump.". VSphere message: ".$server_id);
                                send_notification(MAIL_ADMIN,"Hello, Administrator! Something went wrong when user with id ".$_SESSION['user_id']." tried to create VM in VSphere. Please, check it. Here is the details of his POST query:<pre> ".$postdump."</pre> And also here is the error, returned by VSphere: ".$server_id);
								echo "VM cannot be created. Reason: ".$server_id.". Admin team is already notified about your problem. Keep calm and wait for help.";
                                $query="INSERT INTO `tasks` (id,user_id,title,exp_date,provider,status,comment) SELECT 'NULL','".$_SESSION['user_id']."','".$_POST['name']['name']."','".$_POST['name']['date']."','".$provider_id['id']."',`vms_statuses`.`id`,'$server_id' from `vms_statuses` where LOWER(`title`) like LOWER('FAILURE')";
                            }
                        }
                        else {
							ob_start();
                            var_dump($_POST);
                            $postdump = ob_get_clean();
                            include_once $_SERVER['DOCUMENT_ROOT'].'/modules/tasks.php';
                            write_log(date('Y-m-d H:i:s')." [VSphere][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to create VM in VSphere. POST: ".$postdump);
                            send_notification(MAIL_ADMIN,"Hello, Administrator! Something went wrong when user with id ".$_SESSION['user_id']." tried to create VM in VSphere. Please, check it. Here is the details of his POST query: ".$postdump.". Most possible reason: name collision.");
							echo "VM cannot be created. Check if there is no VM with the same name created.";
                        }
                        break;
                    case "list": get_vms($_POST['panel'],$_POST['provider']);
                        break;
                    case "info": $vsphere_cli .="listvms.pl --vmname '$_POST[id]' --datacenter '".VMW_DATACENTER."'";
                        $cli_flag=true;
                        break;
                    case "stopvm": $vsphere_cli .="controlvm.pl --vmname '$_POST[id]' --action Stop";
                        $cli_flag=true;
                        $multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='DISABLED');UPDATE `vms` SET `status`=@A where `vms`.`id`='$_POST[id]';";
                        break;
                    case "startvm": $vsphere_cli .="controlvm.pl --vmname '$_POST[id]' --action Start";
                        $multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='ENABLED');UPDATE `vms` SET `status`=@A where `vms`.`id`='$_POST[id]';";
                        $cli_flag=true;
                        break;
                    case "terminatevm": $vsphere_cli .="controlvm.pl --vmname '$_POST[id]' --action Destroy";
                        $cli_flag=true;
                        $multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='TERMINATED');UPDATE `vms` set `status`=@A and `comment`='VM was terminated due to user request' WHERE `id`= '$_POST[id]';";
                        break;
                    case "rebootvm": $vsphere_cli .="controlvm.pl --vmname '$_POST[id]' --action Restart";
                        $multiquery=true;
                        $query="SET @A=(SELECT `vms_statuses`.`id` FROM `vms_statuses` where title='ENABLED');UPDATE `vms` SET `status`=@A where `vms`.`id`='$_POST[id]';";
                        $cli_flag=true;
                        break;
					case "backupvm": if (mysqli_num_rows(server_db("SELECT * FROM `snapshots` where `vm_id` like '".$_POST['id']."' and `status` like 'ENABLED'"))>0) {
							echo "Snapshot for this VM already exists! Delete it first.";
							return;
						} 
						$backup_string=$vsphere_cli. "snapshotmanager.pl --operation create --vmname '".$_POST['id']."' --snapshotname '".$_POST['id']."'  --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."'";
						$cli_result=shell_exec($backup_string);
						if (strpos($cli_result,"snapshot-")!==false)
						{
							$query="INSERT INTO `snapshots` VALUES ('".$cli_result."','".$_POST['id']."','".date('Y-m-d', strtotime("+".(defined(SNAPSHOT_DEFAULT_PERIOD)?SNAPSHOT_DEFAULT_PERIOD:1)." days"))."','".$provider_id['id']."','<span class=\"label label-success\">ENABLED</span>',0)";
						}
						else {
							echo "Error. Backup cannot be created: ".$cli_result;
							return;
						}
						break;
                    case "vnc": $vsphere_cli .="vnc.pl -vmname ".$_POST[id];
                        $cli_flag=true;
                        break;
                    case "images": $vsphere_cli .="listvms.pl --folder '".VMW_TEMPLATE_FOLDER."' --datacenter '".VMW_DATACENTER."'";
                        $cli_flag=true;
                        break;
                    case "flavor": echo json_encode(array(), JSON_FORCE_OBJECT); return;
                        break;
					case "assign":
                        $query="INSERT INTO `vms` (id,ip,user_id,title,exp_date,provider,status,cleared) SELECT '$_POST[vmid]',NULL,'$_POST[user]','$_POST[vmname]','".date('Y-m-d', strtotime("+1 days"))."',`providers`.`id`,(select `id` from `vms_statuses` where `title`='ENABLED'),0 from `providers` where `title`='vSphere'";
                        break;
					case "dbinfo": 
						$query="SELECT `vms`.`id`,`vms`.`ip` as addresses,`vms`.`title` as name,`vms`.`comment` from `vms` WHERE `vms`.`id`= '".$_POST['id']."' UNION SELECT `tasks`.`id`,'',`tasks`.`title` as name,`tasks`.`comment` from `tasks` where `tasks`.`id`='".$_POST['id']."'";
                        break;
					case "clearvm":
						if (strpos($_POST['id'], 'task-') !== false) {
							$query="UPDATE `tasks` SET `cleared`=1 WHERE `id`= '".$_POST['id']."'";
						}
						else $query="UPDATE `vms` SET `cleared`=1 WHERE `id`= '".$_POST['id']."'";
                        break;	
					case "snapshots":
						switch ($_POST['subaction']){
						case "list":
                        	get_snapshots($_POST['panel'],$_POST['provider']);
							return;
						case "terminate": 
							$query="UPDATE `snapshots` SET `status`='<span class=\"label label-danger\">TERMINATED</span>' WHERE `snapshot_id`= '".$_POST['id']."'";
							$vsphere_cli .= "snapshotmanager.pl --vmname '".$_POST['vmid']."' --snapshotname '".$_POST['id']."' --children 0 --folder '".VMW_VM_FOLDER."' --operation remove";
							$cli_flag=true;
							break;
						case "clear": $query="UPDATE `snapshots` SET `cleared`=1 WHERE `snapshot_id`= '".$_POST['id']."'"; break;
						case "extend":
                			$query="UPDATE `snapshots` set `exp_date`=DATE_ADD(`snapshots`.`exp_date`, INTERVAL '$_POST[days]' DAY) WHERE id='$_POST[id]'";
                    		break;
						case "info": break;
						case "restore":
							restore_vm($_POST['id'],$_POST['vmid'],$_POST['provider']);
							break;
						}
						break;
                }
				$cli=$vsphere_cli." --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."'";
				break;
			default:
				switch ($_POST['action']) {
					case "list": if (($_POST['panel']) == "user" || ($_POST['panel']) == "admin") get_vms($_POST['panel']);
                        break;
					case "count":
                        $query = "SELECT COUNT(id) FROM `vms` WHERE user_id='".$_SESSION['user_id']."'";
                        //$result=server_db($query);
                        break;
					case "extend":
                        $query="UPDATE vms set exp_date=DATE_ADD(vms.exp_date, INTERVAL '$_POST[days]' DAY) WHERE id='$_POST[id]'";
                        break;
					case "clearvm":
						$query="UPDATE `vms` SET `cleared`=1 WHERE `id`= '".$_POST['id']."'";
                        break;	
					case "dbinfo": 
						$query="SELECT `vms`.`id`,`vms`.`ip` as addresses,`vms`.`title` as name,`vms`.`comment` from `vms` WHERE `vms`.`id`= '".$_POST['id']."' UNION SELECT `tasks`.`id`,'',`tasks`.`title` as name,`tasks`.`comment` from `tasks` where `tasks`.`id`='".$_POST['id']."'";
                        break;
					case "snapshots":
						switch ($_POST['subaction']){
							case "list":
								//$vsphere_cli .="snapshots.pl --url ".VMW_SERVER."/sdk/webService --folder '".VMW_VM_FOLDER."' --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --datacenter '".VMW_DATACENTER."'";;
                        		if (($_POST['panel']) == "user" || ($_POST['panel']) == "admin") get_snapshots($_POST['panel'],$_POST['provider']);
								break;
							case "count":
								$query = "SELECT COUNT(`snapshot_id`) FROM `snapshots` WHERE `vm_id` in (SELECT `id` from `vms` where user_id='".$_SESSION['user_id']."') and `status` like '%ENABLED%'";
								break;
						}					
				}
        }
        break;
        case "keys":
            switch ($_POST['action']){
                case "list":
                    $query="SELECT `key_id`,`title`,`user_id` from `public_keys` ";
                    $query .= "WHERE `user_id`='".$_SESSION['user_id']."'";
                    break;
                case "add":
                    $query="INSERT INTO `public_keys` VALUES (NULL,'".$_SESSION['user_id']."','".$_POST['name']['title']."','".$_POST['name']['key']."')";
                    $openstack_query_result=add_key_to_openstack($_SESSION['user_id'],$_POST['name']['title'],$_POST['name']['key']);
                    if (isset($openstack_query_result)) {
                        echo preg_replace("(\(.*\))","",$openstack_query_result);
                        write_log(date('Y-m-d H:i:s')." [KEYS][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to add SSH key to his profile, but failed. Openstack message: ".$openstack_query_result);
                        return;
                            }
                    break;
                case "delete":
                    $query="DELETE FROM `public_keys` WHERE `key_id`='".$_POST['id']."'";
                    remove_key_from_openstack($_SESSION['user_id'],$_POST['title']);
                    break;
            }
            break;
        case "notifications":
            switch ($_POST['action']){
                case "list":
					/*UNION SELECT `title`,`exp_date`,`days`,`status`,'task' FROM `tasks`,`users`, (SELECT `tasks`.`id`,DATEDIFF(exp_date,CURDATE()) as days FROM `tasks`) as days WHERE (days BETWEEN ".DAYS_BEFORE_DELETE." AND ".DAYS_BEFORE_DISABLE.") AND `tasks`.`user_id`=`users`.`user_id` AND `days`.`id`= `tasks`.`id` AND `users`.`user_id`=".$_SESSION['user_id']." AND `tasks`.`status`!=(SELECT id from `vms_statuses` where title='FAILURE') --tasks notifications*/
                    $query="SELECT `title`,`exp_date`,`days`,`vms`.`status`,'VM' FROM `vms`,`users`, (SELECT `vms`.`id`,DATEDIFF(exp_date,CURDATE()) as days FROM `vms`) as days WHERE (days BETWEEN ".DAYS_BEFORE_DELETE." AND ".DAYS_BEFORE_DISABLE.") AND `vms`.`user_id`=`users`.`user_id` AND `days`.`id`= `vms`.`id` AND `users`.`user_id`=".$_SESSION['user_id']." AND `vms`.`status`!=(SELECT id from `vms_statuses` where title='FAILURE') AND `vms`.`status`!= (SELECT id from `vms_statuses` where title='TERMINATED') UNION SELECT concat(`site_name`,'.',`domain`),`exp_date`,`days`,`status`,'site' FROM `users`,`proxysites`,`domains`, (SELECT `site_id`, DATEDIFF(exp_date,CURDATE()) as days FROM `proxysites`) as days WHERE (days BETWEEN ".DAYS_BEFORE_DELETE." AND ".DAYS_BEFORE_DISABLE.") AND `proxysites`.`domain_id`=`domains`.`domain_id` AND `proxysites`.`user_id`=`users`.`user_id` AND `days`.`site_id`= `proxysites`.`site_id` AND `users`.`user_id`=".$_SESSION['user_id']." ORDER by `exp_date`";
                    break;
            }
        	break;
	    case "domains":
		switch ($_POST['action']) {
			case "delete":
                $query = " DELETE from `proxysites` where `domain_id`='".$_POST['id']."';DELETE FROM `domains` WHERE `domain_id`= '".$_POST['id']."';";
                $flag=true;
                $multiquery=true;
                break;
            case "add":
                if($_POST['checkbox']==="true") $check=1;
                    else $check=0;
                $query = "INSERT INTO `domains` VALUES (NULL,'".$_POST['name']['title']."',$check)";
				break;
            case "get":
                $query = "SELECT `domain`,`shared` FROM `domains` WHERE `domain_id`= '$_POST[id]' ";
                break;
            case "edit":
                if (isset($_POST['checkbox'])) {
                    if($_POST['checkbox']=="true") $check=1;
                        else $check=0;
                }
                else $check=0;
                $query = "UPDATE `domains` set `domain`='".$_POST['name']['name']."',`shared`='$check' WHERE `domain_id`= '$_POST[id]' ";
                $flag=true;
				break;
            case "list":
                $query = "SELECT * FROM `domains`";
                if (!empty($_POST['id'])) {
					if ($_POST['id']=="shared") $query.=" WHERE `shared`='1'";
				}
                break;
		}
          break;
	  case "user":
      case "users":
			if (!empty($_POST['provider'])) $users_table=mysqli_fetch_assoc(server_db("SELECT `table_name` from `user_types` where LOWER(`title`) like LOWER('".$_POST['provider']."')"))['table_name'];
			switch ($_POST['provider'])
			{
				case "ldap":
				switch ($_POST['action']){
            		case "list":
                		$query = "SELECT `user_id`,`username`,`email`,`departments`.`title` FROM `users`,`departments` where `user_type`=(SELECT id from `user_types` where LOWER(`title`) like LOWER('ldap')) and `departments`.`id`=`users`.`department`";
                		break;
				}
				break;
				case "internal":
                switch ($_POST['action']){
            		case "list":
                		$query = "SELECT `users`.`user_id`,`username`,`email`,`departments`.`title` as department,`rights`.`title` as rights FROM `users`,`departments`,`rights`,`$users_table` where `user_type`=(SELECT id from `user_types` where LOWER(`title`) like LOWER('internal')) and `departments`.`id`=`users`.`department` and `rights`.`id`=`$users_table`.`rights` and `users`.`user_id`=`$users_table`.`global_uid`";
                		break;
					case "add": 
						if (mysqli_num_rows(server_db("SELECT * from `users` where LOWER(`username`)=LOWER('".$_POST['name']['username']."')"))) return -1;
						$multiquery=true; 					
						$proc_quota=empty($_POST['name']['proc_quota'])?"null":$_POST['name']['proc_quota'];
						$ram_quota=empty($_POST['name']['ram_quota'])?"null":$_POST['name']['ram_quota'];
						$disk_quota=empty($_POST['name']['disk_quota'])?"null":$_POST['name']['disk_quota'];
						$query="SET @A=(SELECT id from `user_types` where LOWER(`title`) like LOWER('internal'));
						INSERT INTO `users` 
						VALUES(NULL,
						'".$_POST['name']['username']."',
						'".$_POST['name']['email']."',
						'".$_POST['name']['department']."',
						@A,
						".$_POST['checkbox'].",
						".$proc_quota.",
						".$ram_quota.",
						".$disk_quota.");
						SET @B=(SELECT user_id FROM `users` where `username`='".$_POST['name']['username']."' and `user_type`=@A);
						INSERT INTO `".$users_table."` VALUES 
						(NULL,
						@B,
						'".password_hash($_POST['name']['password'],PASSWORD_DEFAULT)."',
						'".$_POST['name']['rights']."');";
						break;
				}
				break;
				default:
				switch ($_POST['action']){
            		case "list":	
						$query = "SELECT `users`.`user_id`,`username`,`email`,`departments`.`title` as department FROM `users`,`departments` where `departments`.`id`=`users`.`department`";
                		break;
					case "delete":
						$query="DELETE FROM `users` where `user_id`='".$_POST['id']."'";
						break;
				}
				break;
			}
      break;
	  case "departments":
	  	switch ($_POST['action']){
        	case "list":
           		$query = "SELECT * FROM `departments`";
           		break;
			case "add":
           		$query = "INSERT INTO `departments` VALUES (NULL,'".$_POST['name']['title']."',".(!is_numeric($_POST['name']['proc_quota'])?"0":"ROUND(".$_POST['name']['proc_quota'].")").",".(!is_numeric($_POST['name']['ram_quota'])?"0":"ROUND(".$_POST['name']['ram_quota'].")").",".(!is_numeric($_POST['name']['disk_quota'])?"0":"ROUND(".$_POST['name']['disk_quota'].")").")";
           		break;
			case "update":
           		$query = "UPDATE `departments` SET WHERE `Id`='".$_POST['id']."'";
           		break;
			case "delete":
           		$query = "DELETE FROM `departments` WHERE `Id`='".$_POST['id']."'";
           		break;
		}
	  break;
	  case "adgroups":
	  	switch ($_POST['action']){
        	case "list":
           		$query = "SELECT `ad_groups`.`id` as id, `ad_groups`.`ldap_dn` as ldap_dn,`ad_groups`.`title` as title,`rights`.`title` as rights FROM `ad_groups`,`rights` where `ad_groups`.`rights`=`rights`.`id`";
           		break;
			case "add":
           		$query = "INSERT INTO `ad_groups` VALUES (NULL,'".$_POST['name']['ldap_dn']."','".$_POST['name']['title']."','".$_POST['name']['rights']."')";
           		break;
			#case "update":
           	#	$query = "UPDATE `departments` SET `ldap_dn`='".$_POST['name']['ldap_dn']."', `title`='".$_POST['name']['title']."', `rights`='".$_POST['name']['rights']."' WHERE `Id`='".$_POST['id']."'";
           #		break;
			case "delete":
           		$query = "DELETE FROM `ad_groups` WHERE `Id`='".$_POST['id']."'";
           		break;
		}
	  break;
	  case "rights":
	  	switch ($_POST['action']){
        	case "list":
           		$query = "SELECT * FROM `rights`";
           		break;
		}
	  break;
      case "blacklist":
            switch ($_POST['action']){
            case "list":
                $query = "SELECT `ip_id`,INET_NTOA(`IP`),`Mask` FROM `blacklist`";
                break;
            case "add":
                if (explode("/",$_POST['name'])[1]) $mask=explode("/",$_POST['name'])[1];
                else $mask=32;
                $query = "INSERT INTO `blacklist` VALUES (NULL,INET_ATON('".explode("/",$_POST['name'])[0]."'),'".$mask."')";
                break;
            case "delete":
                $query = "DELETE FROM `blacklist` WHERE `ip_id`= '$_POST[id]' ";
                break;
            case "check":
                $query="CALL `checkip`('".$_POST['proxy']."')";
                break;
            }
      break;
      case "site":
          switch ($_POST['action']){
              case "add":
                  $query ="CALL `addsite`('".$_POST['name']['name']."','".$_POST['name']['host']."','".$_POST['name']['port']."',".$_SESSION['user_id'].",".$_POST['name']['proxy'].",'".$_POST['name']['date']."')";
                  $flag=true;
                  break;
              case "list":
                  $query = "SELECT `site_id`, `site_name`, `domain`,`rhost`,`rport`,`exp_date`, `status`, `username`, `proxysites`.`domain_id` FROM `proxysites`, `domains`,`users` WHERE `proxysites`.`domain_id`=`domains`.`domain_id` AND `proxysites`.`user_id`=`users`.`user_id` ";
                  if ($_POST['id']!=null and $_POST['id'] == $_SESSION['user_id']) $query .= "AND `proxysites`.`user_id`='".$_POST['id']."'";
                  break;
              case "delete":
                  $query = "DELETE FROM `proxysites` WHERE `site_id`= '$_POST[id]'";
                  $flag=true;
                  break;
              case "get":
                  $query = "SELECT `site_id`, `site_name`, `domain`,`rhost`,`rport`,`stop_date`, `status`, `username`,  `proxysites`.`domain_id`  FROM `proxysites`, `domains`,`users` WHERE `proxysites`.`domain_id`=`domains`.`domain_id` AND `proxysites`.`user_id`=`users`.`user_id` AND `proxysites`.`site_id`=".$_POST['id'];
                  break;
              case "switch":
                  $query = "SET @A= (SELECT status from proxysites where site_id=" . $_POST['id'] . ");";
                  $query .= "UPDATE proxysites set status= IF (@A='Enabled','Disabled','Enabled') where site_id=" . $_POST['id'] . ";";
                  $flag=true;
                  $multiquery=true;
                  break;
              case "edit":
                  $query ="CALL `updatesite`('".$_POST['name']['name']."','".$_POST['name']['host']."','".$_POST['name']['port']."',".$_POST['name']['proxy'].",'".$_POST['name']['date']."','" . $_POST['id'] . "')";
                  $flag=true;
                  break;
              case "check":
                  $query = "SELECT `site_name` FROM `proxysites` WHERE `site_name` = '".$_POST['name']."' AND `domain_id`='".$_POST['proxy']."'";
                  break;
              case "count":
                  $query = "SELECT COUNT(site_id) FROM `proxysites` WHERE status='Enabled' AND user_id='".$_SESSION['user_id']."'";
                  break;
                       }
      break;
	}
	if (isset($query)) {
        write_log(date('Y-m-d H:i:s')." [DB][INFO] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." is trying to query DB: '".$query."'.");
        if ($conn->connect_error) {
            write_log(date('Y-m-d H:i:s')." [DB][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."', but DB connection error occured: ".$conn->connection_error);
            die("Connection failed: " . $conn->connect_error);
        } else {
        if ($multiquery)
        {
	    $result=mysqli_multi_query($conn,$query);
	    if (!$result)
            {
                echo "false";
                write_log(date('Y-m-d H:i:s')." [DB][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."', but DB connection error occured: ".mysqli_error($conn)); 
				die("MySQL error: " . mysqli_error($conn) . "<hr>\nmultiquery: $query");
            }
            else echo "true"; 
            usleep(1000);
        }
        else {
		$result=mysqli_query($conn,$query);
        if (!$result) {
            write_log(date('Y-m-d H:i:s')." [DB][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."', but DB connection error occured: ".mysqli_error($conn));
            die("MySQL error: " . mysqli_error($conn) . "<hr>\nQuery: $query");
        }
        if ($result!="FALSE") echo json_encode(mysqli_fetch_all($result,MYSQLI_ASSOC));
	     }
        }
        write_log(date('Y-m-d H:i:s')." [DB][INFO] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."' and suceeded.");
        $conn->close();
        if ($flag) $out=update_nginx_config();
    }
    if ($cli_flag) {
       $cli_result=shell_exec($cli);
        if (is_null(json_decode($cli_result)) && isset($cli_result)) write_log(date('Y-m-d H:i:s')." [".$_POST['provider']."][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query ".$_POST['provider'].": '".$cli."', but error occured: ".$cli_result);
        else write_log(date('Y-m-d H:i:s')." [".$_POST['provider']."][INFO] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query ".$_POST['provider'].": '".$cli."' and suceeded.");
        echo $cli_result;
    }

?>
