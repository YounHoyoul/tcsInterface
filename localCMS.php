<?php
/* 
Author : Luis Youn
File : localCMS.php
Date : 2012.12.27
Desc :
	V0. Setting up the environmental constant.
		- Local CMS URL.
		- Remote CMS URL.
		- Database Connect String.
	V1. Connecting to Postgresql.
	V2. Checking if there is the table; wallsync.
	V3. If there isn't the table, make it on the database.
	V4. Getting current event information.
		- Checking the current event id of remote cms.
	V5. Getting Event Items information and check if it is not sent.
	V6. Getting the detail information of Event Items.
		- email
		- mobile
		- hasEmailReq
		- hasSmsReq
		- hasFbReq
		- hasNewsReq
		- photo
	V7. Sending the form information to Remote CMS.
	V8. Getting the result.
	V9. if the result is successful, save it on wall sync.
	V10. After sleeping 60 seconds, run again.
*/
?>
<?php
define("URL_LOCAL_CMS","http://127.0.0.1");

define("URL_REMOTE_INTERFACE","http://54.252.103.156/tcsInterface/remoteCMS.php");
define("URL_REMOTE_CMS","http://54.252.103.156");

define("URL_WALLITEM","/wall/items.xml");
//define("URL_TMP","D:\\msysgit\\home\\tcsadmin\\BenzWall\\wwwroot\\tcsInterface\\bentzimage.jpg");
define("URL_TMP","C:\\Dev\\msysgit\\home\\royjung\\Vwall2\\wwwroot\\tcsInterface\\bentzimage.jpg");
define("URL_IMAGE",URL_LOCAL_CMS."/files/");

//define("DB_CONN_STRING","dbname=BenzWall_prod user=postgres password=NewPassword1");
define("DB_CONN_STRING","dbname=Vwall2_prod user=postgres password=NewPassword1");

define("TB_WALLCONFIG","WallConfiguration");
define("TB_WALLEVENT","WallEvent");
define("TB_WALLITEM","WallItem");
define("TB_WALLSYNC","WallSync");

?>

<?php
function curl_get($url){

	$ch=curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);
	
	return $response;
}

function curl_post($url,$data){

	$ch=curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);
	
	return $response;
}

function curl_download($url){
	$ch = curl_init($url);
	$fp = fopen(URL_TMP, 'wb');
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_exec($ch);
	curl_close($ch);
	fclose($fp);
}
?>

<?php

/*
status
dbcheck
run
*/
$action = "status"; 
if ( isset($_GET['action']) and strlen($_GET['action']) ) { 
	$action = addslashes($_GET['action']); 
} else if ( isset($argv) and isset($argv[1]) and strlen($argv[1]) ) { 
	$action = $argv[1]; 
}


if($action == "run" || $action == "dbcheck" || $action == "status"){
	echo "CHECK DATABASE...\n";

	while(1){
		$link = @pg_connect(DB_CONN_STRING);
		if(!$link){
			echo "There is something wrong while connecting the database.";
			//die();
			sleep(30);
			continue;
		}
		break;
	}

	try{
		$result = @pg_exec($link, "select count(*) from ".TB_WALLSYNC);
		if(!$result){
			
			echo "There is no the table; ".TB_WALLSYNC."\n";
			
			$result2 = pg_exec($link,"SELECT COUNT(relname) as a FROM pg_class WHERE relname = '".TB_WALLSYNC."'");
			$row = pg_fetch_assoc($result2);
			
			if($row["a"]==0){
			
				echo "Making the table...".TB_WALLSYNC."...\n";
			
				$sql = "CREATE TABLE WallSync (
							id        integer,
							cdate       timestamp,
							eventid         integer
						);";
				pg_exec($link,$sql);
			}
		}
	}catch(Exception $e){
		echo $e;
	}
}

if($action == "status"){
	$sql="select a.id,a.cdate,a.eventid,b.photoid 
	        from ".TB_WALLSYNC." a
			join ".TB_WALLITEM." b
			  on a.id = b.id
		   order by a.cdate desc
		   limit 100";
	
	$result = pg_exec($link,$sql);
	$arr = pg_fetch_all($result);
	
	echo "<table>";
	echo "<tr><td>id</td><td>cdate</td><td>eventid</td></tr>";
	foreach($arr as $sync){
		echo "<tr><td><a href=".URL_IMAGE.$sync['photoid'].">".$sync['id']."</a></td><td>".$sync['cdate']."</td><td>".$sync['eventid']."</td></tr>";
	}
	echo "</table>";
	
}else if($action == "run"){
	while(true){

		echo "START...\n";

		$currentEventId = 0;
		$sql = "select currenteventid from ".TB_WALLCONFIG;
		$result = pg_exec($link,$sql);
		if($result){
			$row = pg_fetch_assoc($result);
			$currentEventId = $row["currenteventid"];
		}

		$response = curl_get(URL_REMOTE_INTERFACE."?cmd=currentEventID");

		if(intval($currentEventId) != intval($response)){
			
			//Sync Event Information between Local CMS and Remote CMS.
			$sql="select id,name from ".TB_WALLEVENT." where rowtype='event'";
			$resultEvent = pg_exec($link,$sql);
			$arr = pg_fetch_all($resultEvent);

			foreach($arr as $event){
				echo "Sending Event : ".$event["id"]." ".$event["name"];
				
				$post_data = array(
					"cmd"=>"syncEvent",
					"id"=>$event["id"],
					"name"=>$event["name"],
				);
				$res = curl_post(URL_REMOTE_INTERFACE,$post_data);
				
				echo "...".trim($res)."\n";
			}
			
			$response = curl_get(URL_REMOTE_INTERFACE."?cmd=updateCurrentEventID&eventID=".$currentEventId);
		}

		$sql = "select 	a.id
						,a.email
						,a.mobile
						,a.hasEmailReq::int
						,a.hasSmsReq::int
						,a.hasFbReq::int
						,a.hasNewsReq::int
						,a.photoid
						,a.eventid
						,a.username
				  from ".TB_WALLITEM." a
				  left outer join ".TB_WALLSYNC." b
					on a.id = b.id
				 where b.id is null ";
			   
		$result = pg_exec($link,$sql);
		$arr = pg_fetch_all($result);
		
		if(!empty($arr)){
			foreach($arr as $row){
				echo "Sending Image[".$row["id"]."]...";
			
				curl_get(URL_REMOTE_INTERFACE."?cmd=updateCurrentEventID&eventID=".$row["eventid"]);
				curl_download(URL_IMAGE.$row["photoid"]);
				
				$post_data = array();
				if(!empty($row["email"])){
					$post_data["email"] = $row["email"];
				}else{	
					$post_data["email"] = "";
				}
				
				if(!empty($row["mobile"])){
					$post_data["mobile"] = $row["mobile"];
				}else{	
					$post_data["mobile"] = "";
				}
				
				if(!empty($row["hasemailreq"])){
					if($row["hasemailreq"]){
						$post_data["hasEmailReq"] = "on";
					}else{
						$post_data["hasEmailReq"] = "";
					}
				}else{
					$post_data["hasEmailReq"] = "";
				}
				
				if(!empty($row["hassmsreq"])){
					if($row["hassmsreq"]){
						$post_data["hasSmsReq"] = "on";
					}else{
						$post_data["hasSmsReq"] = "";
					}
				}else{
					$post_data["hasSmsReq"] = "";
				}
				
				if(!empty($row["hasfbreq"])){
					if($row["hasfbreq"]){
						$post_data["hasFbReq"] = "on";
					}else{
						$post_data["hasFbReq"] = "";
					}
				}else{	
					$post_data["hasfbreq"] = "";
				}
				
				if(!empty($row["hasnewsreq"])){
					if($row["hasnewsreq"]){
						$post_data["hasNewsReq"] = "on";
					}else{
						$post_data["hasNewsReq"] = "";
					}	
				}else{
					$post_data["hasNewsReq"] = "";
				}
				
				if(!empty($row["username"])){
					$post_data["userName"] = $row["username"];
				}else{	
					$post_data["userName"] = "";
				}
				
				$post_data["Filedata"] = "@".URL_TMP;
				
				$res = curl_post(URL_REMOTE_CMS.URL_WALLITEM,$post_data);

				try{
					$xml = new SimpleXMLElement($res);
					echo trim($xml->result->attributes()->status)."\n";
					
					if($xml->result->attributes()->status == "success"){
						$sql = "insert into ".TB_WALLSYNC."(id,eventid,cdate) values('".$row["id"]."','".$row["eventid"]."',now())";
						pg_exec($link,$sql);
					}
					
				}catch(Exception $e){
					echo $e;
				}
			} //foreach($arr as $row){
		} //if(!empty($arr)){

		curl_get(URL_REMOTE_INTERFACE."?cmd=updateCurrentEventID&eventID=".$currentEventId);
		
		echo "SLEEP...\n";
		sleep(60);
	}//while(true){
}//if(status == "run"){

if($action == "run" || $action == "dbcheck" || $action == "status"){
	pg_close($link);
}
?>