<?php
/*******************************************************************************
 *
 *  filename    : SelfRepeatingPaymentEdit.php
 *  copyright   : Copyright 2015 Michael Wilt
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

error_reporting(-1);

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Name IN ($sSelfServiceFunds) AND fun_Active = 'true'";
$rsFunds = $link->query($sSQL);

$reg_id = 0; // will be registration id for current user

$errStr = "";

if (array_key_exists ("RegID", $_SESSION)) { // Make sure we have a valid login 
	$reg_id = intval ($_SESSION["RegID"]);	
	
	$sSQL = "SELECT * FROM register_reg JOIN family_fam ON reg_famid=fam_ID JOIN person_per on reg_perid=per_ID WHERE reg_id=$reg_id";
	$result = $link->query($sSQL);
	
	if ($result->num_rows != 1) {
		session_destroy ();
		header('Location: SelfRegisterHome.php');
		exit();
	}

	$line = $result->fetch_array(MYSQLI_ASSOC);
	extract ($line); // get $reg_firstname, $reg_lastname, etc.
} else {
	header('Location: SelfRegisterHome.php');
	exit();
}

$aut_ID = 0; // id for current pledge record
if (array_key_exists ("AutID", $_GET)) { // See if we are editing an existing record
	$aut_ID = $_GET["AutID"];
	$iAutID = $aut_ID; // Include/VancoChurchInfo.php is looking for this. 
	$_SESSION["AutID"] = $aut_ID;
}

if (array_key_exists ("AutID", $_SESSION)) { // See if we are editing an existing record
	$aut_ID = $_SESSION["AutID"];
	$iAutID = $aut_ID; // Include/VancoChurchInfo.php is looking for this. 
}

if (isset($_POST["Cancel"])) {
	// bail out without saving
	header('Location: SelfRegisterHome.php');
	exit();
} else if (isset($_POST["Save"])) { // trying to save, use data from the form
	
	$aut_ID=$link->real_escape_string($_POST["AutoPay"]);
	
	if ($aut_ID > 0) {
	
		$aut_NextPayDate=$link->real_escape_string($_POST["NextPayDate"]);
		$aut_FYID=$link->real_escape_string($_POST["FYID"]);
		$aut_Amount=$link->real_escape_string($_POST["Amount"]);
		$schedName = $link->real_escape_string($_POST["Schedule"]);
		if ($schedName == 'Monthly')
			$aut_Interval=1;
		else if ($schedName == 'Quarterly')
			$aut_Interval=3;
		else
			$aut_Interval=0;
		$aut_Fund=$link->real_escape_string($_POST["Fund"]);
		
		$errStr = "";
		if ($aut_Amount <= 0) {
			$errStr .= "Please check amount.<br>\n";
		}
		
		if ($errStr == "") {
			// Ok to create or update
	
			$setValueSQL = "SET " .
				"aut_NextPayDate=\"$aut_NextPayDate\",".
				"aut_FYID=$aut_FYID,".
				"aut_Amount=$aut_Amount,".
				"aut_Interval=$aut_Interval,".
				"aut_Fund=$aut_Fund,".
				"aut_EditedBy=$reg_perid,".
				"aut_DateLastEdited=NOW()";
	
			$sSQL = "UPDATE autopayment_aut " . $setValueSQL . " WHERE aut_ID=".$aut_ID;
			$result = $link->query($sSQL);
			header('Location: SelfRegisterHome.php');
			exit();
		}
	}
} else if ($aut_ID > 0) { // working on an exiting autopayment record
	$query = "SELECT * FROM autopayment_aut WHERE aut_ID=$aut_ID";
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 0) {
		$aut_ID = 0;
	} else {
		while ($line = $result->fetch_array(MYSQLI_ASSOC)) {
			extract ($line);
		}
	}
	$result->free();
}

// initialize everything if the form did not provide values OR the database record did not provide values
if (  (! isset($_POST["Submit"])) && $aut_ID == 0) {
	$aut_NextPayDate=date ("Y-m-d");
	$aut_FYID=CurrentFY();
	$aut_Amount=0.0;
	$schedName = "Monthly";
	$aut_Fund=1;
}
?>

<!DOCTYPE html>

<head>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css?<?php echo "Screw=".time();?>">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<?php require "Include/CalendarJava.php";?>
</head>

<body>

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<h1>
<?php echo "$reg_firstname $reg_lastname"; ?>
</h1>

<h2>
<?php echo "Automatic Repeating Donation Payment"; ?>
</h2>

<form method="post" action="SelfRepeatingPaymentEdit.php?" name="SelfRepeatingPaymentEdit">

<table cellpadding="1" align="center">

	<tr>
		<td <?php  echo "class=\"RegLabelColumn\">" . gettext("Choose online payment method");?></td>
		<td class="RegEnterText">
			<select name="AutoPay">
<?php
			echo "<option value=0";
			if ($plg_aut_ID == 0)
				echo " selected";
			echo ">" . gettext ("Select online payment record") . "</option>\n";
			$sSQLTmp = "SELECT aut_ID, aut_CreditCard, aut_BankName, aut_Route, aut_Account FROM autopayment_aut WHERE aut_FamID=" . $reg_famid;
			$rsFindAut = RunQuery($sSQLTmp);
			while ($aRow = mysqli_fetch_array($rsFindAut)) {
				extract($aRow);
				if ($aut_CreditCard <> "") {
					$showStr = gettext ("Credit card ...") . substr ($aut_CreditCard, strlen ($aut_CreditCard) - 4, 4);
				} else {
					$showStr = gettext ("Bank account ") . $aut_BankName . " " . $aut_Route . " " . $aut_Account;
				}
				echo "<option value=" . $aut_ID;
				if ($aut_ID == $aut_ID)
					echo " selected";
				echo ">" . $showStr . "</option>\n";
			}
?>
			</select>
		</td>
	</tr>

	<tr>
		<td class="RegLabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
		<td class="RegTextColumn"><input type="text" name="NextPayDate" id="NextPayDate" value="<?php echo $aut_NextPayDate; ?>" maxlength="10" id="Date" size="11">&nbsp;<input type="image" onclick="return showCalendar('NextPayDate', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Fiscal year");?></td>
		<td class="RegTextColumn">
			<select class="RegEnterText" id="FYID" name="FYID">
				<option value="<?php echo CurrentFY(); ?>"<?php if ($aut_FYID==CurrentFY()) echo ' Selected'; ?>>This fiscal year <?php echo MakeFYString (CurrentFY()); ?></option>
				<option value="<?php echo CurrentFY()+1; ?>"<?php if ($aut_FYID==CurrentFY()+1) echo ' Selected'; ?>>Next fiscal year <?php echo MakeFYString (CurrentFY()+1); ?></option>
			</select>
		</td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Repeating Amount");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Amount" name="Amount" value="<?php echo $aut_Amount; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Repeating Schedule");?></td>
		<td class="RegTextColumn">
			<select class="RegEnterText" id="Schedule" name="Schedule">
				<option value="Monthly" <?php if ($schedName=='Monthly') echo 'Selected'; ?>>Monthly</option>
			    <option value="Quarterly" <?php if ($schedName=='Quarterly') echo 'Selected'; ?>>Quarterly</option>
			    <option value="Once" <?php if ($schedName=='Once') echo 'Selected'; ?>>Once</option>
			</select>
		</td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Fund");?></td>
		<td class="TextColumn">
			<select name="Fund">
			<option value="0"><?php echo gettext("None"); ?></option>
			<?php
			mysqli_data_seek($rsFunds,0);
			while ($row = $rsFunds->fetch_array(MYSQLI_ASSOC)) {
				$fun_id = $row["fun_ID"];
				$fun_name = $row["fun_Name"];
				$fun_active = $row["fun_Active"];
				echo "<option value=\"$fun_id\" " ;
				if ($aut_Fund == $fun_id)
					echo "selected" ;
				echo ">$fun_name";
				if ($fun_active != 'true') echo " (" . gettext("inactive") . ")";
				echo "</option>" ;
			}
			?>
			</select>
		</td>
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
</body>

<?php
mysqli_close($link);
?>
