<?php
/*******************************************************************************
 *
 *  filename    : SelfEditPerson.php
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

$bNoBanner = array_key_exists ("NoBanner", $_GET);
if (array_key_exists ("NoBanner", $_SESSION))
	$bNoBanner = true;
	
$per_ID = FilterInput($_GET["per_ID"],'int'); // per_ID is passed as an argument.   
// per_ID could be 0 to create, current user editing self, or current user editing a family member

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_id = 0; // will be registration id for current user

$errStr = "";

if (array_key_exists ("RegID", $_SESSION)) { // Make sure we have a valid login 
	$reg_id = intval ($_SESSION["RegID"]);

	$sSQL = "SELECT * FROM  register_reg WHERE reg_id=$reg_id";
	$result = $link->query($sSQL);

	if ($result->num_rows != 1) {
		session_destroy ();
		header('Location: SelfRegisterHome.php');
		exit();
	}

	$line = $result->fetch_array(MYSQLI_ASSOC);
	extract ($line); // get $reg_firstname, $reg_lastname, $reg_famid
	
	if ($per_ID > 0) { // editing a specific person.  get the record and make sure it is the same family as the current user
		$sSQL = "SELECT * FROM person_per WHERE per_ID=$per_ID";
		$result = $link->query($sSQL);
		$line = $result->fetch_array(MYSQLI_ASSOC);
		if ($line['per_fam_ID'] != $reg_famid) {
			session_destroy ();
			header('Location: SelfRegisterHome.php');
			exit();			
		}
		extract ($line);
		
		$sSQL = "SELECT * FROM family_fam WHERE fam_id=$reg_famid";
		$result = $link->query($sSQL);
		$line = $result->fetch_array(MYSQLI_ASSOC);
		extract ($line); // get $fam_Name, etc.
		
	} else { // creating a person
		$per_FirstName = "";
		$per_MiddleName = "";
		$per_LastName = "";
		$per_BirthYear = "";
		$per_BirthMonth = "";
		$per_BirthDay = "";
		$per_Email = "";
		$per_CellPhone = "";		
	}
} else {
	header('Location: SelfRegisterHome.php');
	exit();
}

if (isset($_POST["Cancel"])) {
	// bail out without saving
	header('Location: SelfRegisterHome.php');
	exit();
} else if (isset($_POST["Save"])) { // trying to save, use data from the form
	$per_FirstName = $link->real_escape_string($_POST["FirstName"]);
	$per_MiddleName = $link->real_escape_string($_POST["MiddleName"]);
	$per_LastName = $link->real_escape_string($_POST["LastName"]);
	$per_BirthYear = $link->real_escape_string($_POST["BirthYear"]);
	$per_BirthMonth = $link->real_escape_string($_POST["BirthMonth"]);
	$per_BirthDay = $link->real_escape_string($_POST["BirthDay"]);
	$per_Email = $link->real_escape_string($_POST["Email"]);
	$per_CellPhone = $link->real_escape_string($_POST["CellPhone"]);
	
	$errStr = "";
	if ($per_FirstName == "") {
		$errStr .= "Please check First Name.<br>\n";
	}
	if ($per_LastName == "") {
		$errStr .= "Please check First Name.<br>\n";
	}
	if ($per_BirthYear == "") {
		$errStr .= "Please check birth year.<br>\n";
	}
	if ($per_BirthMonth == "") {
		$errStr .= "Please check birth month.<br>\n";
	}
	if ($per_BirthDay == "") {
		$errStr .= "Please check birth day.<br>\n";
	}
	if ($per_Email == "") {
		$errStr .= "Please check Email.<br>\n";
	}
	
	if ($errStr == "") {
		// Ok to create or update
		
		$setValueSQL = "SET " .
			"per_FirstName = \"$per_FirstName\",".
			"per_MiddleName = \"$per_MiddleName\",".
			"per_LastName = \"$per_LastName\",".
			"per_BirthYear = \"$per_BirthYear\",".
			"per_BirthMonth = \"$per_BirthMonth\",".
			"per_BirthDay = \"$per_BirthDay\",".
			"per_Email = \"$per_Email\",".
			"per_CellPhone = \"$per_CellPhone\",".
			"per_fam_id=$reg_famid,".
			"per_EditedBy=$reg_perid,".
			"per_DateLastEdited=NOW()";
		
		if ($per_ID == 0) { // creating a new record
			$sSQL = "INSERT INTO person_per " . $setValueSQL;
			$result = $link->query($sSQL);
			
			$sSQL = "SELECT LAST_INSERT_ID();";
			$result = $link->query($sSQL);
			
			$line = $result->fetch_array(MYSQLI_ASSOC);
			$per_ID = $line["LAST_INSERT_ID()"];
		} else {
			$sSQL = "UPDATE person_per " . $setValueSQL . " WHERE per_id=".$per_ID;
			$result = $link->query($sSQL);
		}
		header('Location: SelfRegisterHome.php');
		exit();
	}
}
?>

<!DOCTYPE html>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css">

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<h1>
<?php 
	if ($per_ID == 0) { // creating a new record
		echo gettext ("Adding a person to the $fam_Name Family"); 		
	} else {
		echo "$per_FirstName $per_LastName"; 		
	}
?>
</h1>

<h2>
<?php echo "Update personal information"; ?>
</h2>

<form method="post" action="SelfEditPerson.php?per_ID=<?php echo $per_ID; ?>" name="SelfEditPerson">

<table cellpadding="1" align="center">
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("First Name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="FirstName" name="FirstName" value="<?php echo $per_FirstName; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Middle Name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="MiddleName" name="MiddleName" value="<?php echo $per_MiddleName; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Last Name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="LastName" name="LastName" value="<?php echo $per_LastName; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Birth Year");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="BirthYear" name="BirthYear" value="<?php echo $per_BirthYear; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Birth Month");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="BirthMonth" name="BirthMonth" value="<?php echo $per_BirthMonth; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Birth Day");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="BirthDay" name="BirthDay" value="<?php echo $per_BirthDay; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Email");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Email" name="Email" value="<?php echo $per_Email; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Cell Phone");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="CellPhone" name="CellPhone" value="<?php echo $per_CellPhone; ?>"></td>
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
