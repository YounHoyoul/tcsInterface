<?php
/* 
Author : Luis Youn
File : remoteCMS.php
Date : 2012.12.27
Desc :
	1. Get Current Event ID.	
		- GET
		- cmd : currentEventID.
	2. Update Current Event ID.
		- GET
		- cmd : updateEventID.
		- arge : eventID
	3. Sync Event Information.
		- POST
		- cmd : syncEvent
		- arge : id,name
*/
?>
<?php
define("DB_CONN_STRING","dbname=BenzWall_prod user=bzadmin password=Pass1Bzadmin2");
//define("DB_CONN_STRING","dbname=Vwall2_test user=postgres password=NewPassword1");
define("TB_WALLCONFIG","WallConfiguration");
define("TB_WALLEVENT","WallEvent");
define("TB_WALLITEM","WallIem");
define("TB_WALLSYNC","WallSync");
?>
<?php
$link = @pg_connect(DB_CONN_STRING);
if(!$link){
	echo "There is something wrong while connecting the database.";
	die();
}else{
	if($_GET["cmd"]=="cks"){
		echo "DB is connected!";
	}
}
?>
<?php
if($_GET["cmd"]=="currentEventID"){

	$currentEventId = 0;
	$sql = "select currenteventid from ".TB_WALLCONFIG;
	$result = pg_exec($link,$sql);
	if($result){
		$row = pg_fetch_assoc($result);
		$currentEventId = $row["currenteventid"];
	}
	
	echo $currentEventId;
}
?>

<?php
if($_GET["cmd"]=="updateCurrentEventID"){

	$currentEventId = 0;
	$sql = "update ".TB_WALLCONFIG." set currenteventid = '".$_GET["eventID"]."'";
	$result = pg_exec($link,$sql);
	if($result){
		echo "OK";
	}else{
		echo "ERR";
	}
}
?>

<?php
if($_POST["cmd"]=="syncEvent"){
	$sql="select count(*) cnt from ".TB_WALLEVENT." 
		   where rowtype='event'
			 and id = '".$_POST["id"]."' ";

	$result = pg_exec($link,$sql);
	if($result){
		$row = pg_fetch_assoc($result);
		$cnt = $row["cnt"];
		if($cnt == 1){
			$sql = "update ".TB_WALLEVENT." 
			           set name = '".$_POST["name"]."' 
					 where id= '".$_POST["id"]."' ";
			$result = pg_exec($link,$sql);
			if($result){
				echo "OK";
			}else{
				echo "ERR";
			}
		}else{
			try{
				$sql = "insert into ".TB_WALLEVENT."(id,name,isactive,rowtype,createdat,deletedat) values(".$_POST["id"].",'".$_POST["name"]."',TRUE,'event',now(),'9999-12-31 23:59:59')";
				if(pg_exec($link,$sql)){
					echo "OK";
				}
			}catch(Exception $e){
				echo $e;
			}
		}
	}	
}
?>

<?php 
pg_close($link);
?>