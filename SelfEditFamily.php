<?php
/*******************************************************************************
 *
 *  filename    : SelfEditFamily.php
 *  copyright   : Copyright 2016 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
include "Include/Config.php";
require "Include/UtilityFunctions.php";

error_reporting(-1);

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_id = 0; // will be registration id for current user

$errStr = "";

if (array_key_exists ("RegID", $_SESSION)) { // Make sure we have a valid login 
	$reg_id = intval ($_SESSION["RegID"]);
	
	$sSQL = "SELECT * FROM register_reg JOIN family_fam on reg_famid=fam_ID WHERE reg_id=$reg_id";
	$result = $link->query($sSQL);

	if ($result->num_rows != 1) {
		session_destroy ();
		header('Location: SelfRegisterHome.php');
		exit();
	}
			
	$line = $result->fetch_array(MYSQLI_ASSOC);
	extract ($line); // get $reg_firstname, $reg_lastname, fam_* etc.
} else {
	header('Location: SelfRegisterHome.php');
	exit();
}

if (isset($_POST["Cancel"])) {
	// bail out without saving
	header('Location: SelfRegisterHome.php');
	exit();
} else if (isset($_POST["Save"])) { // trying to save, use data from the form
	$fam_Name = $link->real_escape_string($_POST["Name"]);
	$fam_Address1 = $link->real_escape_string($_POST["Address1"]);
	$fam_Address2 = $link->real_escape_string($_POST["Address2"]);
	$fam_City = $link->real_escape_string($_POST["City"]);
	$fam_State = $link->real_escape_string($_POST["State"]);
	$fam_Zip = $link->real_escape_string($_POST["Zip"]);
	$fam_Country = $link->real_escape_string($_POST["Country"]);
	$fam_HomePhone = $link->real_escape_string($_POST["HomePhone"]);
	$fam_WorkPhone = $link->real_escape_string($_POST["WorkPhone"]);
	$fam_CellPhone = $link->real_escape_string($_POST["CellPhone"]);
	$fam_Email = $link->real_escape_string($_POST["Email"]);
	$fam_WeddingDate = $link->real_escape_string($_POST["WeddingDate"]);
	
	$errStr = "";
	if ($fam_Name == "") {
		$errStr .= "Please check Family Name.<br>\n";
	}
	if ($fam_Address1 == "") {
		$errStr .= "Please check Address.<br>\n";
	}
	if ($fam_City == "") {
		$errStr .= "Please check City.<br>\n";
	}
	if ($fam_State == "") {
		$errStr .= "Please check State.<br>\n";
	}
	if ($fam_Zip == "") {
		$errStr .= "Please check Zip.<br>\n";
	}
	if ($errStr == "") {
		// Ok to create or update
		
		$setValueSQL = "SET " .
			"fam_Name=\"$fam_Name\",".
			"fam_Address1=\"$fam_Address1\",".
			"fam_Address2=\"$fam_Address2\",".
			"fam_City=\"$fam_City\",".
			"fam_State=\"$fam_State\",".
			"fam_Zip=\"$fam_Zip\",".
			"fam_Country=\"$fam_Country\",".
			"fam_HomePhone=\"$fam_HomePhone\",".
			"fam_WorkPhone=\"$fam_WorkPhone\",".
			"fam_CellPhone=\"$fam_CellPhone\",".
			"fam_Email=\"$fam_Email\",".
			"fam_WeddingDate=\"$fam_WeddingDate\",".
			"fam_EditedBy=$reg_perid,".
			"fam_DateLastEdited=NOW()";
		
		if ($fam_ID == 0) { // creating a new record
			$sSQL = "INSERT INTO family_fam " . $setValueSQL;
			$result = $link->query($sSQL);
			
			$sSQL = "SELECT LAST_INSERT_ID();";
			$result = $link->query($sSQL);
			
			$line = $result->fetch_array(MYSQLI_ASSOC);
			$fam_ID = $line["LAST_INSERT_ID()"];
		} else {
			$sSQL = "UPDATE family_fam " . $setValueSQL . " WHERE fam_ID=".$fam_ID;
			$result = $link->query($sSQL);
		}
		header('Location: SelfRegisterHome.php');
		exit();
	}
}

// initialize everything if the form did not provide values OR the database record did not provide values
if (  (! isset($_POST["Submit"])) && $fam_ID == 0) {
	$fam_Name = "";
	$fam_Address1 =""; 
	$fam_Address2 = "";
	$fam_City = "";
	$fam_State = "";
	$fam_Zip = "";
	$fam_Country =""; 
	$fam_HomePhone = "";
	$fam_WorkPhone = "";
	$fam_CellPhone = "";
	$fam_Email = "";
	$fam_WeddingDate = "";
}
?>

<!DOCTYPE html>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css">

<?php require "Include/CalendarJava.php";?>

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<h1>
<?php echo "$reg_firstname $reg_lastname"; ?>
</h1>

<h2>
<?php echo "Update family information"; ?>
</h2>

<form method="post" action="SelfEditFamily.php" name="SelfEditFamily">

<table cellpadding="1" align="center">
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Family Name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Name" name="Name" value="<?php echo $fam_Name; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Address");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Address1" name="Address1" value="<?php echo $fam_Address1; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Address line 2");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Address2" name="Address2" value="<?php echo $fam_Address2; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("City");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="City" name="City" value="<?php echo $fam_City; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("State");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="State" name="State" value="<?php echo $fam_State; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Zip");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Zip" name="Zip" value="<?php echo $fam_Zip; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Country");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Country" name="Country" value="<?php echo $fam_Country; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Home Phone");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="HomePhone" name="HomePhone" value="<?php echo $fam_HomePhone; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Family Work Phone");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="WorkPhone" name="WorkPhone" value="<?php echo $fam_WorkPhone; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Family Cell Phone");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="CellPhone" name="CellPhone" value="<?php echo $fam_CellPhone; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Family Email");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Email" name="Email" value="<?php echo $fam_Email; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Family Wedding Date:"); ?></td>
		<td class="RegTextColumn"><input type="text" name="WeddingDate" id="WeddingDate" value="<?php echo $fam_WeddingDate; ?>" maxlength="10" id="Date" size="11">&nbsp;<input type="image" onclick="return showCalendar('WeddingDate', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

<?php if ($errStr != "") { ?>
	<tr>
		<td></td><td class="RegError" align="center"><?php echo $errStr; ?></td>
	</tr>

<?php } ?>

	<tr>
		<td></td><td align="center">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Save"); ?>" name="Save">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel">
		</td>
	</tr>

</table>
</form>

<?php
mysqli_close($link);
?>
