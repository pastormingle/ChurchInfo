<?php
/*******************************************************************************
 *
 *  filename    : SelfAutoPaymentEdit.php
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

error_reporting(-1);

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
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
	
	$enableCode = FilterInput ($_POST["EnableButton"]);
	$aut_EnableBankDraft = ($enableCode == 1);
	if (! $aut_EnableBankDraft)
		$aut_EnableBankDraft = 0;
	$aut_EnableCreditCard = ($enableCode == 2);
	if (! $aut_EnableCreditCard)
		$aut_EnableCreditCard = 0;

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
	$aut_FirstName=$link->real_escape_string($_POST["FirstName"]);
	$aut_LastName=$link->real_escape_string($_POST["LastName"]);
	$aut_Address1=$link->real_escape_string($_POST["Address1"]);
	$aut_Address2=$link->real_escape_string($_POST["Address2"]);
	$aut_City=$link->real_escape_string($_POST["City"]);
	$aut_State=$link->real_escape_string($_POST["State"]);
	$aut_Zip=$link->real_escape_string($_POST["Zip"]);
	$aut_Country=$link->real_escape_string($_POST["Country"]);
	$aut_Phone=$link->real_escape_string($_POST["Phone"]);
	$aut_Email=$link->real_escape_string($_POST["Email"]);
	
	$aut_CreditCard=$link->real_escape_string($_POST["CreditCard"]);
	$aut_ExpMonth=$link->real_escape_string($_POST["ExpMonth"]);
	$aut_ExpYear=$link->real_escape_string($_POST["ExpYear"]);
	$aut_BankName=$link->real_escape_string($_POST["BankName"]);
	$aut_Route=$link->real_escape_string($_POST["Route"]);
	$aut_Account=$link->real_escape_string($_POST["Account"]);
	$aut_CreditCardVanco=$link->real_escape_string($_POST["CreditCardVanco"]);
	$aut_AccountVanco=$link->real_escape_string($_POST["AccountVanco"]);
	
	$errStr = "";
	if ($aut_FirstName == "") {
		$errStr .= "Please check first name.<br>\n";
	}
	if ($aut_LastName == "") {
		$errStr .= "Please check last name.<br>\n";
	}
	if ($aut_Address1 == "") {
		$errStr .= "Please check address.<br>\n";
	}
	if ($aut_City == "") {
		$errStr .= "Please check city.<br>\n";
	}
	if ($aut_State == "") {
		$errStr .= "Please check state.<br>\n";
	}
	if ($aut_Zip == "") {
		$errStr .= "Please check zip.<br>\n";
	}
	
	if ($errStr == "") {
		// Ok to create or update

		$setValueSQL = "SET " .
			"aut_FamID=$fam_ID,". 
			"aut_EnableBankDraft=$aut_EnableBankDraft,".
			"aut_EnableCreditCard=$aut_EnableCreditCard,".
			"aut_NextPayDate=\"$aut_NextPayDate\",".
			"aut_FYID=$aut_FYID,".
			"aut_Amount=$aut_Amount,".
			"aut_Interval=$aut_Interval,".
			"aut_Fund=$aut_Fund,".
			"aut_FirstName=\"$aut_FirstName\",".
			"aut_LastName=\"$aut_LastName\",".
			"aut_Address1=\"$aut_Address1\",".
			"aut_Address2=\"$aut_Address2\",".
			"aut_City=\"$aut_City\",".
			"aut_State=\"$aut_State\",".
			"aut_Zip=\"$aut_Zip\",".
			"aut_Country=\"$aut_Country\",".
			"aut_Phone=\"$aut_Phone\",".
			"aut_Email=\"$aut_Email\",".
			"aut_EditedBy=$reg_perid,".
			"aut_CreditCard=\"$aut_CreditCard\",".
			"aut_ExpMonth=\"$aut_ExpMonth\",".
			"aut_ExpYear=\"$aut_ExpYear\",".
			"aut_BankName=\"$aut_BankName\",".
			"aut_Route=\"$aut_Route\",".
			"aut_Account=\"$aut_Account\",".
			"aut_CreditCardVanco=\"$aut_CreditCardVanco\",".
			"aut_AccountVanco=\"$aut_AccountVanco\",".
			"aut_DateLastEdited=NOW()";
		
		if ($aut_ID == 0) { // creating a new record
			$sSQL = "INSERT INTO autopayment_aut " . $setValueSQL;
			$result = $link->query($sSQL);
			
			$sSQL = "SELECT LAST_INSERT_ID();";
			$result = $link->query($sSQL);
			
			$line = $result->fetch_array(MYSQLI_ASSOC);
			$aut_ID = $line["LAST_INSERT_ID()"];
			$_SESSION["AutID"] = $aut_ID;
			header("Location: SelfAutoPaymentEdit.php");
		} else {
			$sSQL = "UPDATE autopayment_aut " . $setValueSQL . " WHERE aut_ID=".$aut_ID;
			$result = $link->query($sSQL);
			header('Location: SelfRegisterHome.php');
		}
		exit();
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
	$aut_EnableBankDraft=1;
	$aut_EnableCreditCard=0;
	$aut_NextPayDate=date ("Y-m-d");
	$aut_FYID=CurrentFY();
	$aut_Amount=0.0;
	$schedName = "Monthly";
	$aut_Fund=0;
	$aut_FirstName=$reg_firstname;
	$aut_LastName=$reg_lastname;
	$aut_Address1=$reg_address1;
	$aut_Address2=$reg_address2;
	$aut_City=$reg_city;
	$aut_State=$reg_state;
	$aut_Zip=$reg_zip;
	$aut_Country=$reg_country;
	$aut_Phone=$fam_HomePhone;
	$aut_Email=$reg_email;
}
?>

<!DOCTYPE html>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css?<?php echo "Screw=".time();?>">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script>
function ShowHidePaymentStuff() {
	if (document.getElementById("EnableBankDraft").checked) {
		document.getElementById("CreditCard").style.visibility = "hidden";
		document.getElementById("ExpMonth").style.visibility = "hidden";
		document.getElementById("ExpYear").style.visibility = "hidden";
		document.getElementById("CreditCardVanco").style.visibility = "hidden";
		
		document.getElementById("BankName").style.visibility = "visible";
		document.getElementById("Route").style.visibility = "visible";
		document.getElementById("Account").style.visibility = "visible";
		document.getElementById("AccountVanco").style.visibility = "visible";
	} else if (document.getElementById("EnableCreditCard").checked) {
		document.getElementById("CreditCard").style.visibility = "visible";
		document.getElementById("ExpMonth").style.visibility = "visible";
		document.getElementById("ExpYear").style.visibility = "visible";
		document.getElementById("CreditCardVanco").style.visibility = "visible";
		
		document.getElementById("BankName").style.visibility = "hidden";
		document.getElementById("Route").style.visibility = "hidden";
		document.getElementById("Account").style.visibility = "hidden";
		document.getElementById("AccountVanco").style.visibility = "hidden";
	} else {
		document.getElementById("CreditCard").style.visibility = "hidden";
		document.getElementById("ExpMonth").style.visibility = "hidden";
		document.getElementById("ExpYear").style.visibility = "hidden";
		document.getElementById("CreditCardVanco").style.visibility = "hidden";
		
		document.getElementById("BankName").style.visibility = "hidden";
		document.getElementById("Route").style.visibility = "hidden";
		document.getElementById("Account").style.visibility = "hidden";
		document.getElementById("AccountVanco").style.visibility = "hidden";
	}
}
</script>
<body onload="ShowHidePaymentStuff()">
<?php 
include "Include/vancowebservices.php";
include "Include/VancoConfig.php";
include "Include/VancoChurchInfo.php";
?>

<?php require "Include/CalendarJava.php";?>

<?php echo $sHeader; ?>

<h1>
<?php echo "$reg_firstname $reg_lastname"; ?>
</h1>

<h2>
<?php echo "Electronic Payment"; ?>
</h2>

<p>
<?php echo gettext ("This electronic payment method may be used for repeating donation payments or one-time donation payments.")?>
</p>

<form method="post" action="SelfAutoPaymentEdit.php?" name="SelfAutoPaymentEdit">

<table cellpadding="1" align="center">

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Automatic payment type"); ?></td>
		<td class="RegTextColumn"><input type="radio" onchange="ShowHidePaymentStuff()" Name="EnableButton" value="1" id="EnableBankDraft"<?php if ($aut_EnableBankDraft) echo " checked"; ?>>Bank Draft
		                       <input type="radio" onchange="ShowHidePaymentStuff()" Name="EnableButton" value="2" id="EnableCreditCard" <?php if ($aut_EnableCreditCard) echo " checked"; ?>>Credit Card
									  <input type="radio" onchange="ShowHidePaymentStuff()" Name="EnableButton" value="3"  id="Disable" <?php if ((!$aut_EnableBankDraft)&&(!$aut_EnableCreditCard)) echo " checked"; ?>>Disable</td>
	</tr>

	<tr>
		<td class="RegLabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
		<td class="RegTextColumn"><input type="text" name="NextPayDate" id="NextPayDate" value="<?php echo $aut_NextPayDate; ?>" maxlength="10" id="Date" size="11">&nbsp;<input type="image" onclick="return showCalendar('Date', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
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
				if ($plg_fundID == $fun_id)
					echo "selected" ;
				echo ">$fun_name";
				if ($fun_active != 'true') echo " (" . gettext("inactive") . ")";
				echo "</option>" ;
			}
			?>
			</select>
		</td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("FirstName");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="FirstName" name="FirstName" value="<?php echo $aut_FirstName; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("LastName");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="LastName" name="LastName" value="<?php echo $aut_LastName; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Address");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Address1" name="Address1" value="<?php echo $aut_Address1; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Address second line");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Address2" name="Address2" value="<?php echo $aut_Address2; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("City");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="City" name="City" value="<?php echo $aut_City; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("State");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="State" name="State" value="<?php echo $aut_State; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Zip");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Zip" name="Zip" value="<?php echo $aut_Zip; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Country");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Country" name="Country" value="<?php echo $aut_Country; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Phone");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Phone" name="Phone" value="<?php echo $aut_Phone; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Email");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Email" name="Email" value="<?php echo $aut_Email; ?>"></td>
	</tr>

	<tr><td class="WithBorder" colspan="2"><table>
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Credit Card");?></td>
			<td class="RegTextColumn"><input type="text" id="CreditCard" name="CreditCard" value="<?php echo $aut_CreditCard?>"></td>
		</tr>
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Expiration Month");?></td>
			<td class="RegTextColumn"> <select id="ExpMonth" name="ExpMonth">
  				<option value="01" <?php if (intval($aut_ExpMonth) == 1) echo " selected";?>>01</option>
  				<option value="02" <?php if (intval($aut_ExpMonth) == 2) echo " selected";?>>02</option>
  				<option value="03" <?php if (intval($aut_ExpMonth) == 3) echo " selected";?>>03</option>
  				<option value="04" <?php if (intval($aut_ExpMonth) == 4) echo " selected";?>>04</option>
  				<option value="05" <?php if (intval($aut_ExpMonth) == 5) echo " selected";?>>05</option>
  				<option value="06" <?php if (intval($aut_ExpMonth) == 6) echo " selected";?>>06</option>
  				<option value="07" <?php if (intval($aut_ExpMonth) == 7) echo " selected";?>>07</option>
  				<option value="08" <?php if (intval($aut_ExpMonth) == 8) echo " selected";?>>08</option>
  				<option value="09" <?php if (intval($aut_ExpMonth) == 9) echo " selected";?>>09</option>
  				<option value="10" <?php if (intval($aut_ExpMonth) == 10) echo " selected";?>>10</option>
  				<option value="11" <?php if (intval($aut_ExpMonth) == 11) echo " selected";?>>11</option>
  				<option value="12" <?php if (intval($aut_ExpMonth) == 12) echo " selected";?>>12</option>
				</select></td>
		</tr>
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Expiration Year");?></td>
			<td class="RegTextColumn"><input type="text" id="ExpYear" name="ExpYear" value="<?php echo $aut_ExpYear?>"></td>
		</tr>
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Vanco Credit Card Method");?></td>
			<td class="RegTextColumn"><input type="text" id="CreditCardVanco" name="CreditCardVanco" value="<?php echo $aut_CreditCardVanco?>" readonly></td>
		</tr>
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Bank Name");?></td>
			<td class="RegTextColumn"><input type="text" id="BankName" name="BankName" value="<?php echo $aut_BankName?>"></td>
		</tr>
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Bank Route Number");?></td>
			<td class="RegTextColumn"><input type="text" id="Route" name="Route" value="<?php echo $aut_Route?>"></td>
		</tr>
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Bank Account Number");?></td>
			<td class="RegTextColumn"><input type="text" id="Account" name="Account" value="<?php echo $aut_Account?>"></td>
		</tr>		
	
		<tr>
			<td class="RegLabelColumn"><?php echo gettext("Vanco Bank Account Method");?></td>
			<td class="RegTextColumn"><input type="text" id="AccountVanco" name="AccountVanco" value="<?php echo $aut_AccountVanco?>" readonly></td>
		</tr>
				
		</table></td></tr>
			
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
