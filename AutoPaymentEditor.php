<?php
/*******************************************************************************
 *
 *  filename    : AutoPaymentEditor.php
 *  copyright   : Copyright 2001, 2002, 2003, 2004 - 2014 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$linkBack = FilterInput($_GET["linkBack"]);
$iFamily = FilterInput($_GET["FamilyID"], 'int');
$iAutID = FilterInput($_GET["AutID"], 'int');

//Get Family name
if ($iFamily) {
	$sSQL = "SELECT * FROM family_fam where fam_ID = " . $iFamily;
	$rsFamily = RunQuery($sSQL);
	extract(mysqli_fetch_array($rsFamily));
} else {
	$fam_Name = "TBD";
}

if ($iAutID <= 0) {  // Need to create the record so there is a place to store the Vanco payment handle
	$dNextPayDate = date ("Y-m-d");
	$tFirstName="";
	$tLastName="";
	$tAddress1=$fam_Address1;
	$tAddress2=$fam_Address2;
	$tCity=$fam_City;
	$tState=$fam_State;
	$tZip=$fam_Zip;
	$tCountry=$fam_Country;
	$tPhone=$fam_HomePhone;
	$tEmail=$fam_Email;
	$iInterval = 1;
	$iFund = 1;
	
	$bEnableBankDraft=0;
	$bEnableCreditCard=0;

	// Default to the current fiscal year ID
	$FYID = CurrentFY ();
	$iFYID = $FYID;

	$tCreditCard="";
	$tCreditCardVanco="";
	$tExpMonth="";
	$tExpYear="";
	$tBankName="";
	$tRoute="";
	$tAccount="";
	$tAccountVanco="";
	
	$nAmount = 0;
	
	$sSQL = "INSERT INTO autopayment_aut (
	           aut_FamID,
				  aut_EnableBankDraft,
				  aut_EnableCreditCard,
				  aut_NextPayDate,
				  aut_FYID,
				  aut_Amount,
				  aut_Interval,
				  aut_Fund,
				  aut_FirstName,
				  aut_LastName,
				  aut_Address1,
				  aut_Address2,
				  aut_City,
				  aut_State,
				  aut_Zip,
				  aut_Country,
				  aut_Phone,
				  aut_Email,
				  aut_CreditCard,
				  aut_ExpMonth,
				  aut_ExpYear,
				  aut_BankName,
				  aut_Route,
				  aut_Account,
				  aut_Serial,
				  aut_DateLastEdited,
				  aut_EditedBy)
			   VALUES (" .
					$iFamily . "," .
					$bEnableBankDraft . "," .
					$bEnableCreditCard . "," .
					"'" . $dNextPayDate . "'," .
					"'" . $iFYID . "'," .
					"'" . $nAmount . "'," .
					"'" . $iInterval . "'," .
					"'" . $iFund . "'," .
					"'" . $tFirstName . "'," .
					"'" . $tLastName . "'," .
					"'" . $tAddress1 . "'," .
					"'" . $tAddress2 . "'," .
					"'" . $tCity . "'," .
					"'" . $tState . "'," .
					"'" . $tZip . "'," .
					"'" . $tCountry . "'," .
					"'" . $tPhone . "'," .
					"'" . $tEmail . "'," .
					"'" . $tCreditCard . "'," .
					"'" . $tExpMonth . "'," .
					"'" . $tExpYear . "'," .
					"'" . $tBankName . "'," .
					"'" . $tRoute . "'," .
					"'" . $tAccount . "'," .
					"'" . 1 . "'," .
					"'" . date ("YmdHis") . "'," .
					$_SESSION['iUserID'] .
					")";	
	RunQuery($sSQL);
	
	$sSQL = "SELECT MAX(aut_ID) AS iAutID FROM autopayment_aut";
	$rsAutID = RunQuery($sSQL);
	extract(mysqli_fetch_array($rsAutID));
}

$sPageTitle = gettext("Automatic payment configuration for the " . $fam_Name . " family");

//Is this the second pass?
if (isset($_POST["Submit"]))
{
	$iFamily  = FilterInput ($_POST["Family"]);

	$enableCode = FilterInput ($_POST["EnableButton"]);
	$bEnableBankDraft = ($enableCode == 1);
	if (! $bEnableBankDraft)
		$bEnableBankDraft = 0;
	$bEnableCreditCard = ($enableCode == 2);
	if (! $bEnableCreditCard)
		$bEnableCreditCard = 0;

	$dNextPayDate = FilterInput ($_POST["NextPayDate"]);
	$nAmount = FilterInput ($_POST["Amount"]);
	if (! $nAmount)
		$nAmount = 0;

	$iFYID = FilterInput ($_POST["FYID"]);

	$iInterval = FilterInput ($_POST["Interval"],'int');
	$iFund = FilterInput ($_POST["Fund"],'int');

	$tFirstName = FilterInput ($_POST["FirstName"]);
	$tLastName = FilterInput ($_POST["LastName"]);

	$tAddress1 = FilterInput ($_POST["Address1"]);
	$tAddress2 = FilterInput ($_POST["Address2"]);
	$tCity = FilterInput ($_POST["City"]);
	$tState = FilterInput ($_POST["State"]);
	$tZip = FilterInput ($_POST["Zip"]);
	$tCountry = FilterInput ($_POST["Country"]);
	$tPhone = FilterInput ($_POST["Phone"]);
	$tEmail = FilterInput ($_POST["Email"]);

	$tCreditCard = FilterInput ($_POST["CreditCard"]);
	$tExpMonth = FilterInput ($_POST["ExpMonth"]);
	$tExpYear = FilterInput ($_POST["ExpYear"]);
	
	$tBankName = FilterInput ($_POST["BankName"]);
	$tRoute = FilterInput ($_POST["Route"]);
	$tAccount = FilterInput ($_POST["Account"]);

	$sSQL = "UPDATE autopayment_aut SET " .
					"aut_FamID	=	" . $iFamily . "," .
					"aut_EnableBankDraft	=" . 	$bEnableBankDraft . "," .
					"aut_EnableCreditCard	=" . 	$bEnableCreditCard . "," .
					"aut_NextPayDate	='" . $dNextPayDate . "'," .
					"aut_Amount	='" . 	$nAmount . "'," .
					"aut_FYID	='" . 	$iFYID . "'," .
					"aut_Interval	='" . 	$iInterval . "'," .
					"aut_Fund	='" . 	$iFund . "'," .
					"aut_FirstName	='" . $tFirstName . "'," .
					"aut_LastName	='" . $tLastName . "'," .
					"aut_Address1	='" . $tAddress1 . "'," .
					"aut_Address2	='" . $tAddress2 . "'," .
					"aut_City	='" . $tCity . "'," .
					"aut_State	='" . $tState . "'," .
					"aut_Zip	='" . $tZip . "'," .
					"aut_Country	='" . $tCountry . "'," .
					"aut_Phone	='" . $tPhone . "'," .
					"aut_Email	='" . $tEmail . "'," .
					"aut_CreditCard	='" . $tCreditCard . "'," .
					"aut_ExpMonth	='" . $tExpMonth . "'," .
					"aut_ExpYear	='" . $tExpYear . "'," .
					"aut_BankName	='" . $tBankName . "'," .
					"aut_Route	='" . $tRoute . "'," .
					"aut_Account	='" . $tAccount . "'," .
					"aut_DateLastEdited	='" . date ("YmdHis") . "'," .
					"aut_EditedBy	=" . 	$_SESSION['iUserID'] .
				" WHERE aut_ID = " . $iAutID;
	RunQuery($sSQL);

	if (isset($_POST["Submit"]))
	{
		// Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
		if ($linkBack != "") {
			Redirect($linkBack);
		} else {
			//Send to the view of this pledge
			Redirect("AutoPaymentEditor.php?AutID=" . $iAutID . "&FamilyID=" . $iFamily . "&linkBack=", $linkBack);
		}
	}

} else { // not submitting, just get ready to build the page
	$sSQL = "SELECT * FROM autopayment_aut WHERE aut_ID = " . $iAutID;
	$rsAutopayment = RunQuery($sSQL);
	extract(mysqli_fetch_array($rsAutopayment));

	$iFamily=$aut_FamID;
	$bEnableBankDraft=$aut_EnableBankDraft;
	$bEnableCreditCard=$aut_EnableCreditCard;
	$dNextPayDate=$aut_NextPayDate;
	$iFYID = $aut_FYID;
	$nAmount=$aut_Amount;
	$iInterval=$aut_Interval;
	$iFund=$aut_Fund;
	$tFirstName=$aut_FirstName;
	$tLastName=$aut_LastName;
	$tAddress1=$aut_Address1;
	$tAddress2=$aut_Address2;
	$tCity=$aut_City;
	$tState=$aut_State;
	$tZip=$aut_Zip;
	$tCountry=$aut_Country;
	$tPhone=$aut_Phone;
	$tEmail=$aut_Email;
	$tCreditCard=$aut_CreditCard;
	$tCreditCardVanco=$aut_CreditCardVanco;
	$tExpMonth=$aut_ExpMonth;
	$tExpYear=$aut_ExpYear;
	$tBankName=$aut_BankName;
	$tRoute=$aut_Route;
	$tAccount=$aut_Account;
	$tAccountVanco=$aut_AccountVanco;
}

require "Include/Header.php";
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<?php

//Get Families for the drop-down
$sSQL = "SELECT * FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

if ($sElectronicTransactionProcessor == "Vanco") {
	include "Include/vancowebservices.php";
	include "Include/VancoConfig.php";
	include "Include/VancoChurchInfo.php";
}
?>

<form method="post" action="AutoPaymentEditor.php?<?php echo "AutID=" . $iAutID . "&FamilyID=" . $iFamily . "&linkBack=" . $linkBack; ?>" name="AutoPaymentEditor">

<table cellpadding="1" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="1" align="center">

			<tr>
				<td class="LabelColumn" <?php addToolTip("If a family member, select the appropriate family from the list. Otherwise, leave this as is."); ?>><?php echo gettext("Family:"); ?></td>
				<td class="TextColumn">
					<select name="Family" size="8">
						<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
						<option value="0">-----------------------</option>

						<?php
						while ($aRow = mysqli_fetch_array($rsFamilies))
						{
							extract($aRow);

							echo "<option value=\"" . $fam_ID . "\"";
							if ($iFamily == $fam_ID) { echo " selected"; }
							echo ">" . $fam_Name . "&nbsp;" . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
						}
						?>

					</select>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Automatic payment type"); ?></td>
				<td class="TextColumn"><input type="radio" Name="EnableButton" value="1" id="EnableBankDraft"<?php if ($bEnableBankDraft) echo " checked"; ?>>Bank Draft
				                       <input type="radio" Name="EnableButton" value="2" id="EnableCreditCard" <?php if ($bEnableCreditCard) echo " checked"; ?>>Credit Card
											  <input type="radio" Name="EnableButton" value="3"  id="Disable" <?php if ((!$bEnableBankDraft)&&(!$bEnableCreditCard)) echo " checked"; ?>>Disable</td>
			</tr>

			<tr>
				<td class="LabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="NextPayDate" value="<?php echo $dNextPayDate; ?>" maxlength="10" id="NextPayDate" size="11">&nbsp;<input type="image" onclick="return showCalendar('NextPayDate', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Fiscal Year:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<?php PrintFYIDSelect ($iFYID, "FYID") ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Payment amount");?></td>
				<td class="TextColumn"><input type="text" name="Amount" value="<?php echo $nAmount?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Payment interval (months)");?></td>
				<td class="TextColumn"><input type="text" name="Interval" value="<?php echo $iInterval?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Fund:"); ?></td>
				<td class="TextColumn">
					<select name="Fund">
					<option value="0"><?php echo gettext("None"); ?></option>
					<?php
					mysqli_data_seek($rsFunds, 0);
					while ($row = mysqli_fetch_array($rsFunds))
					{
						$fun_id = $row["fun_ID"];
						$fun_name = $row["fun_Name"];
						$fun_active = $row["fun_Active"];
						echo "<option value=\"$fun_id\" " ;
						if ($iFund == $fun_id)
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
				<td class="LabelColumn"><?php echo gettext("First name");?></td>
				<td class="TextColumn"><input type="text" id="FirstName" name="FirstName" value="<?php echo $tFirstName?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Last name");?></td>
				<td class="TextColumn"><input type="text" id="LastName" name="LastName" value="<?php echo $tLastName?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Address 1");?></td>
				<td class="TextColumn"><input type="text" id="Address1" name="Address1" value="<?php echo $tAddress1?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Address 2");?></td>
				<td class="TextColumn"><input type="text" id="Address2" name="Address2" value="<?php echo $tAddress2?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("City");?></td>
				<td class="TextColumn"><input type="text" id="City" name="City" value="<?php echo $tCity?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("State");?></td>
				<td class="TextColumn"><input type="text" id="State" name="State" value="<?php echo $tState?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Zip code");?></td>
				<td class="TextColumn"><input type="text" id="Zip" name="Zip" value="<?php echo $tZip?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Country");?></td>
				<td class="TextColumn"><input type="text" id="Country" name="Country" value="<?php echo $tCountry?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Phone");?></td>
				<td class="TextColumn"><input type="text" id="Phone" name="Phone" value="<?php echo $tPhone?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Email");?></td>
				<td class="TextColumn"><input type="text" id="Email" name="Email" value="<?php echo $tEmail?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Credit Card");?></td>
				<td class="TextColumn"><input type="text" id="CreditCard" name="CreditCard" value="<?php echo $tCreditCard?>"></td>
			</tr>
<?php 
if ($sElectronicTransactionProcessor == "Vanco") {
?>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Vanco Credit Card Method");?></td>
				<td class="TextColumn"><input type="text" id="CreditCardVanco" name="CreditCardVanco" value="<?php echo $tCreditCardVanco?>" readonly></td>
			</tr>
<?php 
}
?>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Expiration Month");?></td>
				<td class="TextColumn"><input type="text" id="ExpMonth" name="ExpMonth" value="<?php echo $tExpMonth?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Expiration Year");?></td>
				<td class="TextColumn"><input type="text" id="ExpYear" name="ExpYear" value="<?php echo $tExpYear?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Bank Name");?></td>
				<td class="TextColumn"><input type="text" id="BankName" name="BankName" value="<?php echo $tBankName?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Bank Route Number");?></td>
				<td class="TextColumn"><input type="text" id="Route" name="Route" value="<?php echo $tRoute?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Bank Account Number");?></td>
				<td class="TextColumn"><input type="text" id="Account" name="Account" value="<?php echo $tAccount?>"></td>
			</tr>		
<?php 
if ($sElectronicTransactionProcessor == "Vanco") {
?>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Vanco Bank Account Method");?></td>
				<td class="TextColumn"><input type="text" id="AccountVanco" name="AccountVanco" value="<?php echo $tAccountVanco?>" readonly></td>
			</tr>		
<?php 
	}
?>

<?php 
	if ($sElectronicTransactionProcessor == "Vanco") {
?>
			<tr>
				<td>
<?php 
	if ($iAutID > 0) {
?>
		<input type="button" id="PressToCreatePaymentMethod" value="Store Private Data at Vanco" onclick="CreatePaymentMethod();" />
<?php 
	} else {
?>
		<b>Save this record to enable storing private data at Vanco</b>
<?php 
	}
?>
				</td>
			</tr>
<?php 
	}
?>
		</table>
		</td>
		</tr>
</table>
</form>

<?php
require "Include/Footer.php";
?>
