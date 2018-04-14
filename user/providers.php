<?php
#Connect to openstack API
$openstack_cli="openstack --os-auth-url ".OS_AUTH_URL." --os-project-id ".OS_PROJECT_ID." --os-project-name ".OS_PROJECT_NAME." --os-user-domain-name ".OS_USER_DOMAIN_NAME." --os-username ".OS_USERNAME." --os-password ".OS_PASSWORD." --os-region-name ".OS_REGION_NAME." --os-interface ".OS_INTERFACE." --os-identity-api-version ".OS_IDENTITY_API_VERSION;
$vsphere_cli="/usr/bin/perl ".$_SERVER["DOCUMENT_ROOT"]."/perl/";
$cli_flag=false;

function fetch($entity,$provider)
{
	switch ($_POST['provider'])
	{
		case "openstack":
			switch ($entity)
			{
				case "vm":
				case "vms":
				case "snapshot": break;
			}
			break;
		case "vsphere":
			switch ($entity)
			{
				case "vm":
				case "vms":
				case "snapshot": break;
			}
			break;
		default: fetch ($entity,"openstack"); fetch($entity,"vsphere"); return;
	}
}


function get_snapshots($panel,$provider)
{
	
	function fetch_openstack_snapshots()
	{
		$snapshots=json_decode(shell_exec($GLOBALS['openstack_cli']." image list --property backup_type=spsnapshot -f json")); 
		foreach ($snapshots as $snapshot => $shot){
			$snapshots[$snapshot]=json_decode(shell_exec($GLOBALS['openstack_cli']." image show $shot->ID -f json")); 
			$snapshots[$snapshot]->createddate=$snapshots[$snapshot]->created_at;
		}
		if ($snapshots) $snapshots=set_value_in_whole_array($snapshots,"owner","undefined");
		return set_value_in_whole_array ($snapshots,"provider","OpenStack");
	}
	
	function fetch_vsphere_snapshots()
	{
		$snapshots=json_decode(shell_exec($GLOBALS['vsphere_cli']."snapshotmanager.pl --url ".VMW_SERVER."/sdk/webService --folder '".VMW_VM_FOLDER."' --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --operation list")); 
		return set_value_in_whole_array ($snapshots,"provider","vSphere");
	}
	
	$query="SELECT `snapshot_id` as ID, `vms`.`title` as vmname, `vms`.`id` as vmid, `snapshots`.`exp_date`, `providers`.`title` as provider, `snapshots`.`status`, `users`.`username` FROM `vms`,`snapshots`,`providers`,`users` WHERE `snapshots`.`cleared`=0 AND `users`.`user_id`=`vms`.`user_id` AND `snapshots`.`vm_id`=`vms`.`id` AND `snapshots`.`provider`=`providers`.`id`";
	if (!empty($provider))
	{
		$query.=" AND `snapshots`.`provider`=(SELECT `Id` from `providers` where LOWER(`title`) like LOWER('".$provider."'))";
	}
	if ($panel!=="admin") 
	{
		$query.=" AND `vms`.`user_id`='$_SESSION[user_id]'";
	}
	
	$snapshot_user_list=[];
	
	switch ($provider)
	{
		case "openstack": 
			$snapshots=fetch_openstack_snapshots();
			break;
		case "vsphere": 
			$snapshots=fetch_vsphere_snapshots();
			break;
		default: 
			$openstack_snapshots=fetch_openstack_snapshots();
			$vsphere_snapshots=fetch_vsphere_snapshots();
			$snapshots = (object) array_merge((array) $openstack_snapshots, (array) $vsphere_snapshots); 
			break;
	}
	
	$snapshot_in_db=server_db($query);	
	foreach ($snapshot_in_db as $item) {
		if (strpos($item['status'], 'TERMINATED') !== false) {
			$snapshot=new stdClass();
			$snapshot->id = $item['ID'];
			$snapshot->exp_date = $item['exp_date'];
			$snapshot->owner = $item['username'];
			$snapshot->provider = $item['provider'];
			$snapshot->status = $item['status'];
			$snapshot->vmname = $item['vmname'];
			$snapshot->vmid = $item['vmid'];
			$snapshot_user_list[]=$snapshot;
        }
    }
	
    if (!empty($snapshots)) foreach ($snapshots as $snapshot){
        $exist=false;
        foreach ($snapshot_in_db as $item) {
			if ($snapshot->{'id'} == $item['ID']) {
            	$exist=true;
				$snapshot->exp_date = $item['exp_date'];
				$snapshot->owner = $item['username'];
				$snapshot->provider = $item['provider'];
				$snapshot->status = $item['status'];
				$snapshot->vmname = $item['vmname'];
				$snapshot->vmid = $item['vmid'];
				$snapshot->extendlimit=DAYS_USER_CAN_EXTEND_SNAPSHOT;
				$snapshot_user_list[]=$snapshot;
        	}
    	}
        if ($panel == "admin" and !$exist) 
		{
			if (!isset($snapshot->vmid)) $snapshot->vmid=$snapshot->name;
			$snapshot_user_list[]=$snapshot;		
		}
    }
	echo json_encode($snapshot_user_list);
}

function set_value_in_whole_array ($where,$what,$with)
{
	array_walk($where,
				function (&$v, $k, $w) {   
					$what=$w[0];
        				$v->$what = $w[1];
        		}, array($what,$with)
			);
	return $where;
}

function get_vms($panel,$provider=null) {
	$statuses=[];
	$statuses_in_db=server_db("SELECT * FROM `vms_statuses`");
	while ($row=mysqli_fetch_assoc($statuses_in_db))
	{
		$statuses += [$row['title'] => $row['display_title']];
	}
	$query_vms = "SELECT `vms`.`title`,`vms`.`id` as id,INET_NTOA(`IP`) as IP,`username`,`exp_date`,`vms`.`user_id`,`email`,`vms_statuses`.`display_title` as vmstatus,`providers`.`title` as provider FROM `vms`,`users`,`vms_statuses`,`providers` WHERE `vms`.`user_id`=`users`.`user_id` and `vms`.`status`=`vms_statuses`.`id` AND `providers`.`id`=`vms`.`provider` AND `vms`.`cleared`=0";
	$query_tasks = "SELECT `tasks`.`title`,`tasks`.`id` as id,`username`,`exp_date`,`tasks`.`user_id`,`email`,`vms_statuses`.`display_title` as vmstatus,`providers`.`title` as provider FROM `tasks`,`users`,`vms_statuses`,`providers` WHERE `tasks`.`user_id`=`users`.`user_id` AND `providers`.`id`=`tasks`.`provider` and `tasks`.`status`=`vms_statuses`.`id` and cleared=0";
	if (!empty($provider))
	{
		$query_vms.=" AND `vms`.`provider`=(SELECT `Id` from `providers` where LOWER(`title`) like LOWER('".$provider."'))";
		$query_tasks.=" AND `tasks`.`provider`=(SELECT `Id` from `providers` where LOWER(`title`) like LOWER('".$provider."'))";
	}
	if ($panel!=="admin") 
	{
		$query_vms.=" AND `vms`.`user_id`='$_SESSION[user_id]'"; 
		$query_tasks.=" AND `tasks`.`user_id`='$_SESSION[user_id]'";
	}
    $vm_user_list=[];
	//echo $query_vms;
    switch ($provider)
	{
		case "openstack": 
			$vms=json_decode(shell_exec($GLOBALS['openstack_cli']." server list -f json")); 
			if ($vms) $vms=set_value_in_whole_array ($vms,"provider","OpenStack");
			break;
		case "vsphere": 
			$vms=json_decode(shell_exec($GLOBALS['vsphere_cli']."listvms.pl --url ".VMW_SERVER."/sdk/webService --folder '".VMW_VM_FOLDER."' --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --datacenter '".VMW_DATACENTER."'"));
			if ($vms) $vms=set_value_in_whole_array ($vms,"provider","vSphere");
			break;
		default: 
			$openstack_vms=json_decode(shell_exec($GLOBALS['openstack_cli']." server list -f json")); 
			if ($openstack_vms) $openstack_vms=set_value_in_whole_array ($openstack_vms,"provider","OpenStack");
			$vsphere_vms=json_decode(shell_exec($GLOBALS['vsphere_cli']."listvms.pl --url ".VMW_SERVER."/sdk/webService --folder '".VMW_VM_FOLDER."' --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --datacenter '".VMW_DATACENTER."'"));
			if ($vsphere_vms) $vsphere_vms=set_value_in_whole_array ($vsphere_vms,"provider","vSphere");
			$vms = (object) array_merge((array) $openstack_vms, (array) $vsphere_vms); break;
	}
	
	$vm_in_db=server_db($query_vms);	
	$task_in_db=server_db($query_tasks);
	
	foreach ($task_in_db as $task) {	
        $taskarray=new stdClass();
        $taskarray->ID = $task['id'];;
        $taskarray->date = $task['exp_date'];
        $taskarray->owner = $task['username'];
        $taskarray->extendlimit=DAYS_USER_CAN_EXTEND_VM;
        $taskarray->Status=$task['vmstatus'];
        $taskarray->Image="Deploying";
		$taskarray->provider=$task['provider'];
        $taskarray->Name=$task['title'];
        $vm_user_list[]=$taskarray;
    }
	
	foreach ($vm_in_db as $vm) {
		if (strpos($vm['vmstatus'], 'TERMINATED') !== false)
		{
			$vmarray=new stdClass();
			$vmarray->ID = $vm['id'];
			$vmarray->date = $vm['exp_date'];
			$vmarray->owner = $vm['username'];
			$vmarray->Status=$vm['vmstatus'];
			$vmarray->Name=$vm['title'];
			$vmarray->provider = $vm['provider'];
			$vm_user_list[]=$vmarray;
		}
    }
	
    if (!empty($vms)) foreach ($vms as $vm){
        $exist=false;
        foreach ($vm_in_db as $item) {
			if ($vm->{'ID'} == $item['id']) {
				if ($vm->{'Networks'})
				{
					$query_db="UPDATE `vms` set `ip`='".$vm->{'Networks'}."' where `id`='".$vm->{'ID'}."'";
					server_db($query_db);
				}
            	$exist=true;
				$vm->date = $item['exp_date'];
				$vm->owner = $item['username'];
				$vm->provider = $item['provider'];
				$vm->Status=$statuses[$vm->Status];
				$vm->extendlimit=DAYS_USER_CAN_EXTEND_VM;
				$vm_user_list[]=$vm;
        	}
    	}
        if ($panel == "admin" and !$exist) 
		{
			$vm->Status=$statuses[$vm->Status];
			$vm_user_list[]=$vm;
		}
    }
	echo json_encode($vm_user_list);
}

function get_free_ip(){
    $cli=$GLOBALS['openstack_cli']." floating ip list -f json";
    $ips=shell_exec($cli);
    $ip_array=json_decode($ips);
    foreach ($ip_array as $ip) {
        if ($ip->{'Port'} == null) {
            return $ip->{'Floating IP Address'};
        }
    }
    return allocate_ip();
    }
function allocate_ip(){
    $cli=$GLOBALS['openstack_cli']." floating ip create admin_floating_net -f json";
    $ips=shell_exec($cli);
    $ip_array=json_decode($ips);
    return  $ip_array->{'floating_ip_address'};
    }
function add_ip_to_server($server_id,$ip){
    if(!isset($server_id) or !isset($ip)) return "Can't get server_id or ip";
    $state=false;
    $count=0;
    $cli=$GLOBALS['openstack_cli']." server add floating ip $server_id $ip";
    while ($count<10){
    usleep(3000000);
    if (get_server_state($server_id) == "ACTIVE") {$state=true; break;}
    else $count++;
    }
    if ($state) shell_exec($cli);
    return $server_id;
}
function get_server_state($id) {
    $cli=$GLOBALS['openstack_cli']." server show $id -f json";
    $server=shell_exec($cli);
    $server_info=json_decode($server);
    return $server_info->{'status'};
}
function add_key_to_openstack($user_id,$title,$key){
    $folder="/tmp/";
    $keyname=$title."_".$user_id;
    $file = fopen($folder.$keyname, "w");
    fwrite($file,$key);
    fclose($file);
    $cli =$GLOBALS['openstack_cli']." keypair create --public-key ".$folder."$keyname $keyname -f json 2>&1";
    $key=shell_exec($cli);
    $key_json=json_decode($key);
    unlink($folder.$keyname);
    if (!isset($key_json->{'fingerprint'})) return $key;
}
function remove_key_from_openstack($id,$title){
    $keyname=$title."_".$id;
    $cli=$GLOBALS['openstack_cli']." keypair delete '$keyname'";
    shell_exec($cli);
}

function create_vsphere_vm ($image_id,$name,$owner,$owner_id){
    $cli=$GLOBALS['vsphere_cli']."createvm.pl --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --resourcepool '".VMW_RESOURCE_POOL."' --vmtemplate ".$image_id." --vmname '".$name."_".$owner."' --user '".$owner."' --user_id '".$owner_id."' --folder '".VMW_VM_FOLDER."' --datastore '".VMW_DATASTORE."' --action createvm";
    $task=shell_exec($cli);
    return $task;
}

function create_openstack_vm($image,$flavor,$keypair,$name,$owner,$owner_user){
	#OPENSTACK VM CREATION
    $cli=$GLOBALS['openstack_cli']." server create --image '$image' --flavor '$flavor' --security-group ".OS_SEC_GRP." --key-name '$keypair' --nic net-id=".OS_NET_ID." --property Owner_id='$owner' --property Owner_name='$owner_user' '$name' -f json 2>&1";
    $server=shell_exec($cli);
    $server_json=json_decode($server);
    if (isset($server_json->{'id'})) {
        return add_ip_to_server($server_json->{'id'},get_free_ip());
    }
    else return $server;
}
function server_db($query) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
    if ($conn->connect_error) {
        write_log(date('Y-m-d H:i:s')." [DB][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."', but DB connection error occured: ".$conn->connection_error);
        die("Connection failed: " . $conn->connect_error);
    } else {
        $result=mysqli_query($conn,$query);
            if (!$result)
            {
                write_log(date('Y-m-d H:i:s')." [DB][ERROR] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."', but DB connection error occured: ".mysqli_error($conn) . ". Query: $query");
                die("MySQL error: " . mysqli_error($conn) . "<hr>\nQuery: $query");
            }
    }
    write_log(date('Y-m-d H:i:s')." [DB][INFO] User ".$_SESSION['user']." (id ".$_SESSION['user_id'].") with access level ".$_SESSION['access']." tried to query DB: '".$query."' and suceeded.");
    $conn->close();
    return $result;

}

function restore_vm($backupid,$vmid,$provider)
{
	switch ($provider)
	{
		case "openstack":
			$cli=$GLOBALS['openstack_cli']." server rebuild --image '$backupid' '$vmid' -f json 2>&1";
			break;
		case "vsphere":
			$cli=$GLOBALS['vsphere_cli']."snapshotmanager.pl --url ".VMW_SERVER."/sdk/webService --username ".VMW_USERNAME." --password '".VMW_PASSWORD."' --vmname '".$vmid."' --folder '".VMW_VM_FOLDER."' --operation goto --snapshotname ".$backupid;
			break;
	}
	return shell_exec($cli);	
}