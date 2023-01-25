<?php
/*******************************************************************************
 *
 *  filename    : SelfPaymentMethodEdit.php
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

error_reporting(-1);

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

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
} else if (array_key_exists ("AutID", $_SESSION)) { // See if we are editing an existing record
	$aut_ID = $_SESSION["AutID"];
	$iAutID = $aut_ID; // Include/VancoChurchInfo.php is looking for this. 
}

if ($aut_ID != 0) {
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
} else {
	$aut_EnableBankDraft=1;
	$aut_EnableCreditCard=0;
	$aut_NextPayDate = date ("Y-m-d");
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
		document.getElementById("CreditCardRow").style.display = "none";
		document.getElementById("ExpMonthRow").style.display = "none";
		document.getElementById("ExpYearRow").style.display = "none";
		document.getElementById("CreditCardVancoRow").style.display = "none";
		
		document.getElementById("BankNameRow").style.display = "table-row";
		document.getElementById("RouteRow").style.display = "table-row";
		document.getElementById("AccountRow").style.display = "table-row";
		document.getElementById("AccountVancoRow").style.display = "table-row";
	} else if (document.getElementById("EnableCreditCard").checked) {
		document.getElementById("CreditCardRow").style.display = "table-row";
		document.getElementById("ExpMonthRow").style.display = "table-row";
		document.getElementById("ExpYearRow").style.display = "table-row";
		document.getElementById("CreditCardVancoRow").style.display = "table-row";
		
		document.getElementById("BankNameRow").style.display = "none";
		document.getElementById("RouteRow").style.display = "none";
		document.getElementById("AccountRow").style.display = "none";
		document.getElementById("AccountVancoRow").style.display = "none";
	} else {
		document.getElementById("CreditCardRow").style.display = "none";
		document.getElementById("ExpMonthRow").style.display = "none";
		document.getElementById("ExpYearRow").style.display = "none";
		document.getElementById("CreditCardVancoRow").style.display = "none";
		
		document.getElementById("BankNameRow").style.display = "none";
		document.getElementById("RouteRow").style.display = "none";
		document.getElementById("AccountRow").style.display = "none";
		document.getElementById("AccountVancoRow").style.display = "none";
	}
}

function CreatePaymentAndSave ()
{
	document.getElementById("SaveButton").enabled = false;
    $.ajax({
        type: "POST",
        url: "SelfPaymentMethodSave.php",
        data: $('#SelfPaymentMethodForm').serialize(),
        dataType: "json",
        async: true,
        traditional: false,
        success: function (saveformdata) {
        	if (saveformdata.result=="Success") {
        		document.getElementById('SaveButton').style.visibility='hidden';
        		UpdateNvpvar ();
        	} else {
	        	document.getElementById("ShowErrorStr").innerHTML = saveformdata.errStr;
        	}
        },
        error: function (jqXHR, textStatus, errorThrown, vancodata) {
            alert("Error saving: " + errorThrown);
        }
    });
}

function UpdateNvpvar ()
{
    $.ajax({
        type: "POST",
        url: "GetNewVancoNvpvar.php",
        dataType: "json",
        async: true,
        traditional: false,
        success: function (updatenvpdata) {
        	if (updatenvpdata.result=="Success") {
        		localStorage.setItem("sessionid", updatenvpdata.sessionid);
        		localStorage.setItem("nvpvarcontent", updatenvpdata.nvpvarcontent);
		    	CreatePaymentMethod ();
        	} else {
	        	document.getElementById("ShowErrorStr").innerHTML = updatenvpdata.errStr;
        	}
        },
        error: function (jqXHR, textStatus, errorThrown, vancodata) {
            alert("Error updating nvpvar: " + errorThrown);
        }
    });
}

function GoHome ()
{
	window.location="SelfRegisterHome.php";
}

function SaveAndHome () {
	document.getElementById("SaveButton").enabled = false;
    $.ajax({
        type: "POST",
        url: "SelfPaymentMethodSave.php",
        data: $('#SelfPaymentMethodForm').serialize(),
        async: true,
        traditional: false,
        success: function (saveformdata) {
	    	window.location="SelfRegisterHome.php";
        },
        error: function (jqXHR, textStatus, errorThrown, vancodata) {
        	window.location="SelfRegisterHome.php";
        }
    });

}

</script>
<body onload="ShowHidePaymentStuff()">
<?php 
include "Include/vancowebservices.php";
include "Include/VancoConfig.php";
include "Include/VancoChurchInfo.php";
?>

<?php require "Include/CalendarJava.php";?>

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<h1>
<?php echo "$reg_firstname $reg_lastname"; ?>
</h1>

<h2>
<?php echo "Electronic Payment"; ?>
</h2>

<form method="post" action="SelfPaymentMethodSave.php" name="SelfPaymentMethodForm" id="SelfPaymentMethodForm">

<table cellpadding="1" align="center">

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Automatic payment type"); ?></td>
		<td class="RegTextColumn"><input type="radio" onchange="ShowHidePaymentStuff()" Name="EnableButton" value="1" id="EnableBankDraft"<?php if ($aut_EnableBankDraft) echo " checked"; ?>>Bank Draft
		                       <input type="radio" onchange="ShowHidePaymentStuff()" Name="EnableButton" value="2" id="EnableCreditCard" <?php if ($aut_EnableCreditCard) echo " checked"; ?>>Credit Card
									  <input type="radio" onchange="ShowHidePaymentStuff()" Name="EnableButton" value="3"  id="Disable" <?php if ((!$aut_EnableBankDraft)&&(!$aut_EnableCreditCard)) echo " checked"; ?>>Disable</td>
	</tr>

	<tr>
		<td class="RegLabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
		<td class="RegTextColumn"><input type="text" name="NextPayDate" id="NextPayDate" value="<?php echo $aut_NextPayDate; ?>" maxlength="10" id="Date" size="11">&nbsp;<input type="image" onclick="return showCalendar('NextPayDate', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
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

	<tr id="CreditCardRow">
		<td class="RegLabelColumn"><?php echo gettext("Credit Card");?></td>
		<td class="RegTextColumn"><input type="text" id="CreditCard" name="CreditCard" value="<?php echo $aut_CreditCard?>"></td>
	</tr>

	<tr id="ExpMonthRow">
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

	<tr id="ExpYearRow">
		<td class="RegLabelColumn"><?php echo gettext("Expiration Year (20xx)");?></td>
		<td class="RegTextColumn"><input type="text" id="ExpYear" name="ExpYear" value="<?php echo $aut_ExpYear?>"></td>
	</tr>

	<tr id="CreditCardVancoRow">
		<td class="RegLabelColumn"><?php echo gettext("Vanco Credit Card Method");?></td>
		<td class="RegTextColumn"><input type="text" id="CreditCardVanco" name="CreditCardVanco" value="<?php echo $aut_CreditCardVanco?>" readonly></td>
	</tr>

	<tr id="BankNameRow" >
		<td class="RegLabelColumn"><?php echo gettext("Bank Name");?></td>
		<td class="RegTextColumn"><input type="text" id="BankName" name="BankName" value="<?php echo $aut_BankName?>"></td>
	</tr>

	<tr id="RouteRow">
		<td class="RegLabelColumn"><?php echo gettext("Bank Route Number");?></td>
		<td class="RegTextColumn"><input type="text" id="Route" name="Route" value="<?php echo $aut_Route?>"></td>
	</tr>

	<tr id="AccountRow" >
		<td class="RegLabelColumn"><?php echo gettext("Bank Account Number");?></td>
		<td class="RegTextColumn"><input type="text" id="Account" name="Account" value="<?php echo $aut_Account?>"></td>
	</tr>		

	<tr id="AccountVancoRow" >
		<td class="RegLabelColumn"><?php echo gettext("Vanco Bank Account Method");?></td>
		<td class="RegTextColumn"><input type="text" id="AccountVanco" name="AccountVanco" value="<?php echo $aut_AccountVanco?>" readonly></td>
	</tr>

	<tr>
		<td></td><td class="RegError" align="center" id="ShowErrorStr"><?php echo $errStr; ?></td>
	</tr>

	<tr>
		<td></td><td class="RegTextColumn" id="SaveStatus" align="center"></td>
	</tr>

	<tr>
		<td></td><td align="center">
			<input type="button" class="regEditButton" id="SaveButton" onclick="CreatePaymentAndSave();" value="<?php echo gettext("Save"); ?>" name="Save">
			<input type="button" class="regEditButton" id="HomeButton" onclick="SaveAndHome();" value="<?php echo gettext("Home"); ?>" name="Home" hidden>
			<input type="button" class="regEditButton" id="CancelButton" onclick="GoHome();" value="<?php echo gettext("Cancel"); ?>" name="Cancel">
		</td>
	</tr>

</table>
</form>
</body>

<?php
mysqli_close($link);
?>
