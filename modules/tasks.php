<?php
include_once(dirname(__FILE__).'/../config/config.php');
include_once(dirname(__FILE__).'/../user/user.inc');
include_once(dirname(__FILE__).'/../user/access.php');
include_once(dirname(__FILE__).'/../plugins/phpmailer/PHPMailerAutoload.php');
#Connect to openstack API
$openstack_cli="openstack --os-auth-url ".OS_AUTH_URL." --os-project-id ".OS_PROJECT_ID." --os-project-name ".OS_PROJECT_NAME." --os-user-domain-name ".OS_USER_DOMAIN_NAME." --os-username ".OS_USERNAME." --os-password ".OS_PASSWORD." --os-region-name ".OS_REGION_NAME." --os-interface ".OS_INTERFACE." --os-identity-api-version ".OS_IDENTITY_API_VERSION;
$vsphere_cli="/usr/bin/perl ".dirname(__FILE__)."/../perl/controlvm.pl --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."'";
$shortopts  = "";
$shortopts .= "v::"; // Необязательное значение

$longopts  = array(
    "action:",     // Обязательное значение
    "optional::",    // Необязательное значение

);
$options = getopt($shortopts, $longopts);
switch ($options['action']){
    case "notify":
        list_notifications();
        break;
    case "disable":
        disable_sites();
        break;
    case "delete":
        delete_sites();
        break;
    case "shutdown_vm":
        shutdown_vm();
        break;
    case "terminate_vm":
        terminate_vm();
        break;
	case "vmupdate":
		vmupdate();
		break;
}

function update_info($task,$user)
{
	$cli="/usr/bin/perl ".dirname(__FILE__)."/../perl/createvm.pl --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --resourcepool '".VMW_RESOURCE_POOL."' --vmtemplate none --vmname ".$task." --user ".$user." --user ".$user_id." --folder '".VMW_VM_FOLDER."' --datastore '".VMW_DATASTORE."' --action updateinfo";
	$result=shell_exec($cli);
	if (!is_numeric($result))
	{
		$arr = json_decode($result);

		if (json_last_error() !== JSON_ERROR_NONE) {
    		$query="UPDATE `tasks` SET `status`=(SELECT `id` from `vms_statuses` where LOWER(`title`) like LOWER('FAILURE')),`comment`='$result'";
			db_query($query);
			send_notification (MAIL_ADMIN,'User with id '.$item['user_id'].' have tried to create VM (VSphere provider, i guess) with name '.$item['title'].', but error occured: '.$error);
			send_notification ($item['email'],'Hi! There was something strange, when we\'ve to create VM called "'.$item['title'].'" for you. Unfortunately, an error occured: '.$error.'. If you know how to fix it - good, otherwise - please, contact your system administrators via '.MAIL_ADMIN);
			return array(2);
		}
		else
		{
			return $arr;
		}
	}
	else return array($result);
}

function vmupdate()
{
	$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
	//$query="SELECT `vm_id`,`username`,`exp_date`,`vms`.`user_id`,`email`,`title` FROM `vms`,`users` WHERE `vms`.`user_id`=`users`.`user_id` and `vm_id` like '%task%' and `vm_id` not like '%FAILURE%' and `vm_id` not like '%TERMINATED%'";
	$query="SELECT * FROM `tasks`,`users` where `tasks`.`user_id`=`users`.`user_id` AND `tasks`.`status`!=(SELECT `id` FROM `vms_statuses` where LOWER(`title`) like LOWER('DISABLED'))";
	$vm_in_db=db_query($query);
	$success=true;
	foreach ($vm_in_db as $item) {
				$error = update_info($item['title'],$item['username']);
				switch ($error[0])
				{
					case -2:
						$query="INSERT INTO `vms` SELECT 'NULL','NULL','$item[user_id]','$error[1]',`exp_date`,`provider`,(SELECT id from `vms_statuses` where LOWER(`title`) LIKE LOWER('ENABLED')),'0' FROM `tasks` where `tasks`.`id`='$item[id]'";
						db_query($query);
						$query="UPDATE `tasks` SET `status`=(SELECT `id` from `vms_statuses` where LOWER(`title`) like LOWER('DISABLED')),`comment`='VM was successfully created.'";
						db_query($query);
						break;
					case -1: 
						send_notification (MAIL_ADMIN,'WARNING: SelfPortal was not able to find task for VM'.$item['title'].' created by user '.$item['username'].'. Please, assign this vm to user manually.');
						$query="UPDATE `tasks` SET `status`=(SELECT `id` from `vms_statuses` where LOWER(`title`) like LOWER('DISABLED')),`comment`='WARNING: SelfPortal was not able to find task for VM".$item['title']." created by user ".$item['username'].". Please, assign this vm to user manually.'";
						db_query($query);
						break;
					case 0: 
						$success=false;
						break;
					case 1:
						$returneddebug=vmdebug($item['id'],$item['title'],$item['username'],$item['user_id'],$item['email']);
						break;
					case 2: 
						break;
				}
	}
	if ($success) shell_exec("sudo crontab -l -u root | grep -v '/modules/tasks.php --action vmupdate' | sudo crontab -u root -");
}

function vmdebug($task,$vmname,$user,$user_id,$user_email)
{
	$cli="/usr/bin/perl ".dirname(__FILE__)."/../perl/listvms.pl --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --vmalias ".$vmname." --folder ".$user."_".$user_id." --datacenter '".VMW_DATACENTER."'";
	$result=shell_exec($cli);
	if (!empty($result))
	{
		$query="INSERT INTO `vms` SELECT 'NULL','NULL','$user_id','$vmname',`exp_date`,`provider`,(SELECT id from `vms_statuses` where LOWER(`title`) LIKE LOWER('ENABLED')),'0' FROM `tasks` where `tasks`.`id`='$task'";
		db_query($query);
		$query="UPDATE `tasks` SET `status`=(SELECT `id` from `vms_statuses` where LOWER(`title`) like LOWER('DISABLED')),`comment`='VM was successfully created.'";
		db_query($query);
		send_notification ($user_email,'Hi! Your VM called "'.$vmname.'" is ready.<br><hr>Sincerely yours, SelfPortal. In case of any errors - please, contact your system administrators via '.MAIL_ADMIN);
		return 0;
	}
	else {
		send_notification(MAIL_ADMIN,"Hello, Administrator! Something went wrong when user named ".$user." tried to create VM '".$vmname."' in VSphere. I was not able to find task '".$task."' or a vm by it's name. Please, check it.");
		$query="UPDATE `tasks` SET `status`=(SELECT `id` from `vms_statuses` where LOWER(`title`) like LOWER('FAILURE')),`comment`='VM was not created due to unknown reason.'";
		db_query($query);
		return 1;
	}
}

function db_query($query){

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
    // Check connection
    if ($conn->connect_error) {
        write_log(date('Y-m-d H:i:s')." [CRON][INFO] Query DB: '".$query."' but connection failed: ".mysqli_error($conn));
        die("Connection failed: " . $conn->connect_error);
    } else {
        $result=mysqli_query($conn,$query);
        if (!$result){
            write_log(date('Y-m-d H:i:s')." [CRON][INFO] Query DB: '".$query."' but error occured: ".mysqli_error($conn));
            die("MySQL error: " . mysqli_error($conn) . "<hr>\nQuery: $query");
        }
    }
    $conn->close();
    write_log(date('Y-m-d H:i:s')." [CRON][INFO] Query DB: '".$query."' and suceeded.");
    return $result;
}
//Check items status and send notification
function list_notifications(){
    $query="SELECT `site_name`,`domain`,`stop_date`,`email`,`days` FROM `users`,`proxysites`,`domains`, (SELECT `site_id`, DATEDIFF(stop_date,CURDATE()) as days FROM `proxysites`) as days WHERE (days BETWEEN ".DAYS_BEFORE_DELETE." AND ".DAYS_BEFORE_DISABLE.") AND  `proxysites`.`domain_id`=`domains`.`domain_id` AND `proxysites`.`user_id`=`users`.`user_id` AND `days`.`site_id`= `proxysites`.`site_id` ORDER by `stop_date`";
    $sites=db_query($query);
    $emails=[];
    while ($site=mysqli_fetch_array($sites)){
        $siteitem="$site[site_name]".".$site[domain]";
        if(!isset($emails[$site['email']]['site_disable']) and ($site['days']>=0) ) {$emails[$site['email']]['site_disable'] ="<br>This site(s) will be disabled:<br>";}
        if(!isset($emails[$site['email']]['site_delete']) and ($site['days']<0)) {$emails[$site['email']]['site_delete']="<br>This site(s) will be deleted:<br>";}
        if($site['days']>0) $emails[$site['email']]['site_disable'] .="<li><b>$site[stop_date]</b> - <a href="."\"http://$siteitem/\">$siteitem"."</a></li>";
        if($site['days']==0) $emails[$site['email']]['site_disable'] .="<li><b>TODAY AT 23.59</b> - <a href="."\"http://$siteitem/\">$siteitem"."</a></li>";
        if($site['days']<0 && abs($site['days'])<abs(DAYS_BEFORE_DELETE)) {
            $date= new DateTime($site['stop_date']);
            $date->add(new DateInterval('P'.abs(DAYS_BEFORE_DELETE).'D'));
            $site_date=$date->format('Y-m-d');
            $emails[$site['email']]['site_delete'] .="<li><b>$site_date</b> - <a href="."\"http://$siteitem/\">$siteitem"."</a></li>";
        }
        if ($site['days']<0 && abs($site['days'])>=abs(DAYS_BEFORE_DELETE))
        {
            $emails[$site['email']]['site_delete'] .="TODAY AT 23.59 - <a href="."\"http://$siteitem/\">$siteitem"."</a></li>";
        }

    }
    $query="SELECT `title`,`email`,`days`,`exp_date`,`vms_providers`.`title` as vmprovider FROM `vms`,`vms_providers`,`users`, (SELECT `vm_id`,DATEDIFF(exp_date,CURDATE()) as days FROM `vms`) as days WHERE (days BETWEEN ".DAYS_BEFORE_DELETE." AND ".DAYS_BEFORE_DISABLE.") AND `vms`.`user_id`=`users`.`user_id` AND `days`.`vm_id`= `vms`.`id` AND `vms_providers`.`id`=`vms`.`provider` ORDER by `exp_date`";
    $vms=db_query($query);
    while ($vm=mysqli_fetch_array($vms)){
        if(!isset($emails[$vm['email']]['vm_disable']) and ($vm['days']>=0)) {$emails[$vm['email']]['vm_disable'] = "<br>This Virtual machine(s) will be disabled:<br>";}
        if(!isset($emails[$vm['email']]['vm_delete']) and ($vm['days']<0)) {$emails[$vm['email']]['vm_delete'] = "<br>This Virtual machine(s) will be deleted:<br>";}
        if($vm['days']>0) $emails[$vm['email']]['vm_disable'] .="<li><b>$vm[vmprovider]</b> - <b>$vm[exp_date]</b> - $vm[title]</li>";
        if($vm['days']==0) $emails[$vm['email']]['vm_disable'] .="<li><b>$vm[vmprovider]</b> - <b>TODAY AT 23:59</b> - $vm[title]</li>";
        if($vm['days']<0 && abs($vm['days'])<abs(DAYS_BEFORE_DELETE))  {
            $date= new DateTime($vm['exp_date']);
            $date->add(new DateInterval('P'.abs(DAYS_BEFORE_DELETE).'D'));
            $vm_date=$date->format('Y-m-d');
            $emails[$vm['email']]['vm_delete'] .="<li><b>$vm_date </b> - $vm[title]</li>";};
        if($vm['days']<0 && abs($vm['days'])>=abs(DAYS_BEFORE_DELETE))
           {
               $emails[$vm['email']]['vm_delete'] .="<li><b>TODAY AT 23:59</b> - $vm[title] </li>";
           }
    }
    foreach ($emails as $email => $notification){
        $body="Notification from SELFPORTAL <br>".$notification['site_disable'].$notification['site_delete'].$notification['vm_disable'].$notification['vm_delete'];
        send_notification($email,$body);
    }
}

function send_notification($email,$body){

    $mail = new PHPMailer;
    //$mail->SMTPDebug = 3;                               // Enable verbose debug output

    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = MAIL_SERVER;  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = MAIL_USER;                 // SMTP username
    $mail->Password = MAIL_PASS;                           // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 587;                                    // TCP port to connect to

    $mail->setFrom(MAIL_USER, 'SELFPORTAL');

    $mail->isHTML(true);                                  // Set email format to HTML

    $mail->Subject = 'SELFPORTAL';

    $mail->addAddress($email);
    $mail->Body    = $body;
    if(!$mail->send()) {
        echo 'Message could not be sent.';
        echo 'Mailer Error: ' . $mail->ErrorInfo;
    } else {
   #     echo 'Message has been sent';
    }
}
//Check expired items and change new status
function disable_sites(){
    $query= "UPDATE `proxysites`SET status='Disabled' WHERE `stop_date` < CURDATE()";
    db_query($query);
    usleep(1000);
    update_nginx_config();
}
//Delete expired items and change new status
function delete_sites(){
    $query= "DELETE FROM `proxysites` WHERE DATEDIFF(stop_date,CURDATE()) < ".DAYS_BEFORE_DELETE;
    db_query($query);
    usleep(1000);
    update_nginx_config();
}
function shutdown_vm(){
    $vms=db_query("SELECT `id`,`providers`.`title` FROM `vms`,`providers` WHERE `providers`.`Id`=`vms`.`provider` and `exp_date` < CURDATE()");
    foreach ($vms as $vm) {
    	switch ($vm['title'])
		{
			case "openstack":
				$cli=$GLOBALS['openstack_cli']." server stop '".$vm['id']."' 2>&1";
				break;
			case "vsphere":
				$cli=$GLOBALS['vsphere_cli']."--vmname ".$vm['id']." --action Stop";       		
				break;
		}
		$cli_result=shell_exec($cli);
        if (isset($cli_result))
		{
			write_log(date('Y-m-d H:i:s')." [CRON][SHUTDOWN][ERROR] Cron tried to query ".$vm['title'].": '".$cli."', but error occured.");
		}
        else 
		{
			write_log(date('Y-m-d H:i:s')." [OPENSTACK][CRON][SHUTDOWN][INFO] Cron tried to query ".$vm['title'].": '".$cli."' and suceeded.");
        	db_query("UPDATE `vms` set `status`='Disabled' where `exp_date` < CURDATE()");
		}
    }
}
function terminate_vm(){
    $query= "SELECT `id`,`providers`.`title` FROM `vms`,`providers` WHERE `providers`.`Id`=`vms`.`provider` AND DATEDIFF(exp_date,CURDATE()) < ".DAYS_BEFORE_DELETE;
    $vms=db_query($query);
    usleep(1000);
    foreach ($vms as $vm) {
		switch ($vm['title'])
		{
			case "openstack":
				$cli=$GLOBALS['openstack_cli']." server delete ".$vm['id']." 2>&1";
				break;
			case "vsphere":
				$cli=$GLOBALS['vsphere_cli']."--vmname ".$vm['id']." --action Destroy";
				break;
		}
		$cli_result=shell_exec($cli);
        if (isset($cli_result))
		{
			write_log(date('Y-m-d H:i:s')." [CRON][TERMINATE][ERROR] Cron tried to query ".$vm['title'].": '".$cli."', but error occured.");
		}
        else 
		{
			write_log(date('Y-m-d H:i:s')." [OPENSTACK][CRON][TERMINATE][INFO] Cron tried to query ".$vm['title'].": '".$cli."' and suceeded.");
        	db_query("DELETE FROM `vms` WHERE `id`= '".$vm['id']."'");
		}
    }
}
