<?php

include "Include/Config.php";

$bNoBanner = array_key_exists ("NoBanner", $_GET);
if (array_key_exists ("NoBanner", $_SESSION))
	$bNoBanner = true;

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_randomtag = $link->real_escape_string($_GET['reg_randomtag']);

$sSQL = "SELECT reg_confirmed FROM register_reg WHERE reg_confirmed=1 AND reg_randomtag=\"$reg_randomtag\"";
$result = $link->query ($sSQL);
if ($result->num_rows > 0) {
	printf ("Registration previously confirmed");
	mysqli_close($link);
	exit;
}

$sSQL = "SELECT * FROM register_reg WHERE reg_randomtag=\"$reg_randomtag\"";
$result = $link->query ($sSQL);
if ($result->num_rows != 1) {
	printf ("Unable to confirm registration");
	mysqli_close($link);
	exit;
}

$line = $result->fetch_array(MYSQLI_ASSOC);
extract ($line);

if ($reg_perid == 0 && $bSelfCreate) { // not matched to a person, create person and family records
	$sCreateFamilySQL = "INSERT INTO family_fam SET 
		fam_Name=\"$reg_famname\",
		fam_Address1=\"$reg_address1\",
		fam_Address2=\"$reg_address2\",
		fam_City=\"$reg_city\",
		fam_state=\"$reg_state\",
		fam_Zip=\"$reg_zip\",
		fam_Country=\"$reg_country\",
		fam_HomePhone=\"$reg_phone\",
		fam_Email=\"$reg_email\",
		fam_DateEntered=NOW(),
		fam_DateLastEdited=NOW()";
	$result = $link->query($sCreateFamilySQL);
	
	$sSQL = "SELECT LAST_INSERT_ID();";
	$result = $link->query($sSQL);
	$line = $result->fetch_array(MYSQLI_ASSOC);
	$per_fam_id = $line["LAST_INSERT_ID()"];
	$reg_famid = $per_fam_id;
	
	$sCreatePersonSQL = "INSERT INTO person_per SET
		per_FirstName=\"$reg_firstname\",
		per_LastName=\"$reg_lastname\",
		per_CellPhone=\"$reg_phone\",
		per_Email=\"$reg_email\",
		per_fmr_ID=1,
		per_fam_ID=$per_fam_id,
		per_DateLastEdited=NOW(),
		per_DateEntered=NOW(),
		per_FriendDate=NOW()";
	$result = $link->query($sCreatePersonSQL);
	
	$sSQL = "SELECT LAST_INSERT_ID();";
	$result = $link->query($sSQL);
	$line = $result->fetch_array(MYSQLI_ASSOC);
	$per_id = $line["LAST_INSERT_ID()"];
	$reg_perid = $per_id;
}

$sSQL = "UPDATE register_reg SET reg_confirmed=1, reg_perid=$reg_perid, reg_famid=$reg_famid, reg_randomtag='' WHERE reg_randomtag=\"$reg_randomtag\"";

if (! $bNoBanner)
	echo $sHeader; 

echo "<br>";

if ($link->query ($sSQL) && $link->affected_rows==1) {
	echo gettext ("Registration Confirmed");
} else {
	echo gettext ("Registration Failed");
}

mysqli_close($link);
?>
<br>
<a href="SelfRegisterHome.php">Log in</a>
