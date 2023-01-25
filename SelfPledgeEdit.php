<?php
/*******************************************************************************
 *
 *  filename    : SelfPledgeEdit.php
 *  copyright   : Copyright 2015 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

if ($_GET["PledgeOrPayment"]=="Pledge") {
	$plg_PledgeOrPayment = "Pledge";
} else if ($_GET["PledgeOrPayment"]=="Payment") {
	$plg_PledgeOrPayment = "Payment";
} else {
	session_destroy ();
	header('Location: SelfRegisterHome.php');
	exit();
}

include "Include/Config.php";
require "Include/UtilityFunctions.php";

$bEnableElectronicDonation = ($sElectronicTransactionProcessor == "Vanco");

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
	
	$sSQL = "SELECT * FROM register_reg JOIN family_fam ON reg_famid=fam_ID WHERE reg_id=$reg_id";
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

$plg_plgID = 0; // id for current pledge record
if (array_key_exists ("PlgID", $_GET)) { // See if we are editing an existing record
	$plg_plgID = $_GET["PlgID"];
}

if (isset($_POST["Cancel"])) {
	// bail out without saving
	header('Location: SelfRegisterHome.php');
	exit();
} else if (isset($_POST["Save"]) || isset($_POST["Process"])) { // trying to save, use data from the form
	$plg_FYID = $link->real_escape_string($_POST["FYID"]);
	$plg_date = $link->real_escape_string($_POST["Date"]);
	$plg_amount = $link->real_escape_string($_POST["Amount"]);
	$plg_schedule = $link->real_escape_string($_POST["Schedule"]);
	$plg_method = $link->real_escape_string($_POST["Method"]);
	$plg_comment = $link->real_escape_string($_POST["Comment"]);
	$plg_fundID = $link->real_escape_string($_POST["FundID"]);
	$plg_aut_ID = $link->real_escape_string($_POST["AutoPay"]);
	
	if ((! isset($plg_aut_ID)) || $plg_aut_ID=="")
		$plg_aut_ID = 0;
	
	$errStr = "";
	if ($plg_amount <= 0.0) {
		$errStr .= "Please check amount.<br>\n";
	}

	if ($errStr == "") {
		// Ok to create or update
		
		//Get the selected autopayment record
		if ($plg_aut_ID > 0) {
			$sSQL = "SELECT * FROM autopayment_aut WHERE aut_ID=$plg_aut_ID";
			$rsAutoPayments = $link->query($sSQL);
			if ($rsAutoPayments->num_rows == 1) {
				$aRow = $rsAutoPayments->fetch_array(MYSQLI_ASSOC);
				extract($aRow);
				if ($aut_CreditCardVanco > 0) // if processing a payment the method is based on the autopayment record
					$plg_method = "CREDITCARD";
				else if ($aut_AccountVanco > 0)
					$plg_method = "BANKDRAFT";
			} else {
				header('Location: SelfRegisterHome.php');
				exit();
			}
		}
		$sGroupKey = genGroupKeyByMethod($plg_method, 0, $reg_famid, $plg_fundID, date("Y-m-d"), $plg_aut_ID);
		
		$setValueSQL = "SET " .
			"plg_FamID=$fam_ID,". 
			"plg_PledgeOrPayment=\"$plg_PledgeOrPayment\",". 
			"plg_FYID=$plg_FYID,".
			"plg_date=\"$plg_date\",".
			"plg_amount=$plg_amount,".
			"plg_schedule=\"$plg_schedule\",".
			"plg_method=\"$plg_method\",".
			"plg_comment=\"$plg_comment\",".
			"plg_fundID=$plg_fundID,".
			"plg_aut_ID=$plg_aut_ID,".
			"plg_EditedBy=$reg_perid,".
			"plg_GroupKey=\"$sGroupKey\",".
			"plg_DateLastEdited=NOW()";
		
		if ($plg_plgID == 0) { // creating a new record
			$sSQL = "INSERT INTO pledge_plg " . $setValueSQL;
			$result = $link->query($sSQL);
			
			$sSQL = "SELECT LAST_INSERT_ID();";
			$result = $link->query($sSQL);
			
			$line = $result->fetch_array(MYSQLI_ASSOC);
			$plg_plgID = $line["LAST_INSERT_ID()"];
		} else {
			$sSQL = "UPDATE pledge_plg " . $setValueSQL . " WHERE plg_plgID=".$plg_plgID;
			$result = $link->query($sSQL);
		}
		
		// If processing run the transaction now and remember whether it cleared
		if (isset($_POST["Process"])) {
			include "Include/vancowebservices.php";
			include "Include/VancoConfig.php";
			
			$customerid = "$plg_aut_ID";  // This is an optional value that can be used to indicate a unique customer ID that is used in your system
			// put aut_ID into the $customerid field
			// Create object to preform API calls
			
			$workingobj = new VancoTools($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);
			// Call Login API to receive a session ID to be used in future API calls
			$sessionid = $workingobj->vancoLoginRequest();
			// Create content to be passed in the nvpvar variable for a TransparentRedirect API call
			$nvpvarcontent = $workingobj->vancoEFTTransparentRedirectNVPGenerator($VancoUrltoredirect,$customerid,"","NO");

			$paymentmethodref = "";
			if ($plg_method == "CREDITCARD") {
				$paymentmethodref = $aut_CreditCardVanco;
			} else {
				$paymentmethodref = $aut_AccountVanco;
			}

			$addRet = $workingobj->vancoEFTAddCompleteTransactionRequest(
			    $sessionid, // $sessionid
			    $paymentmethodref,// $paymentmethodref
			    '0000-00-00',// $startdate
			    'O',// $frequencycode
			    $customerid,// $customerid
			    "",// $customerref
			    $aut_FirstName . " " . $aut_LastName,// $name
			    $aut_Address1,// $address1
			    $aut_Address2,// $address2
			    $aut_City,// $city
				$aut_State,// $state
				$aut_Zip,// $czip
				$aut_Phone,// $phone
				"No",// $isdebitcardonly
				"",// $enddate
				"",// $transactiontypecode
				"",// $funddict
				$plg_amount);// $amount

			$retArr = array();
			parse_str($addRet, $retArr);
			
			$errListStr = "";
			if (array_key_exists ("errorlist", $retArr))
				$errListStr = $retArr["errorlist"];
			
			$bApproved = false;
			
			// transactionref=None&paymentmethodref=16610755&customerref=None&requestid=201411222041237455&errorlist=167
			if ($retArr["transactionref"]!="None" && $errListStr == "")
				$bApproved = true;
				
			$errStr = "";
			if ($errListStr != "") {
				$errList = explode (",", $errListStr);
				foreach ($errList as $oneErr) {
					$errStr .= $workingobj->errorString ($oneErr . "<br>\n");
				}
			}
			if ($errStr == "")
				$errStr = "Success: Transaction reference number " . $retArr["transactionref"] . "<br>";
				
			$sSQL = "UPDATE pledge_plg SET plg_aut_Cleared='" . $bApproved . "', plg_TransactionRef='" .  $retArr["transactionref"] . "' WHERE plg_plgID=" . $plg_plgID;
			
			RunQuery($sSQL);
			
			if ($plg_aut_ResultID) {
				// Already have a result record, update it.
				
				$sSQL = "UPDATE result_res SET res_echotype2='" . EscapeString($errStr) . "' WHERE res_ID=" . $plg_aut_ResultID;
				RunQuery($sSQL);
			} else {
				// Need to make a new result record
				$sSQL = "INSERT INTO result_res (res_echotype2) VALUES ('" . EscapeString($errStr) . "')";
				RunQuery($sSQL);
	
				// Now get the ID for the newly created record
				$sSQL = "SELECT MAX(res_ID) AS iResID FROM result_res";
				$rsLastEntry = RunQuery($sSQL);
				extract(mysqli_fetch_array($rsLastEntry));
				$plg_aut_ResultID = $iResID;
	
				// Poke the ID of the new result record back into this pledge (payment) record
				$sSQL = "UPDATE pledge_plg SET plg_aut_ResultID=" . $plg_aut_ResultID . " WHERE plg_plgID=" . $plg_plgID;
				RunQuery($sSQL);
			}
			// show the result and provide a link back to the self-service home page
			if (! $bNoBanner)
				echo $sHeader; 
			echo "<h1>$reg_firstname $reg_lastname</h1>";
			echo gettext ("Process payment result:<br>");
			
			echo (EscapeString($errStr));
			echo "<br><a href=\"SelfRegisterHome.php\">Done</a>";
			exit();
		}
		
		header('Location: SelfRegisterHome.php');
		exit();
	}
} else if ($plg_plgID > 0) { // working on a pledge
	$query = "SELECT * FROM pledge_plg WHERE plg_plgID=$plg_plgID";
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 0) {
		$plg_plgID = 0;
	} else {
		while ($line = $result->fetch_array(MYSQLI_ASSOC)) {
			extract ($line);
		}
	}
	$result->free();
}

// initialize everything if the form did not provide values OR the database record did not provide values
if (  (! isset($_POST["Submit"])) && $plg_plgID == 0) {
	$plg_FYID = CurrentFY() + 1; // next fiscal year
	$plg_fundID = 1; // pledge receipts
	$plg_date = date ("Y-m-d");
	$plg_amount = 0.0;
	$plg_schedule = "Monthly";
	
	if ($bEnableElectronicDonation)
		$plg_method = "BANKDRAFT";
	else
		$plg_method = "CHECK";
	
	$plg_comment = "";
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
<?php echo gettext ($plg_PledgeOrPayment) . " " . gettext ("Form"); ?>
</h2>

<form method="post" action="SelfPledgeEdit.php?PledgeOrPayment=<?php echo $plg_PledgeOrPayment;?>&PlgID=<?php echo $plg_plgID; ?>" name="SelfPledgeEdit">

<table cellpadding="1" align="center">
	<tr>
		<td class="RegLabelColumn"><?php if ($plg_PledgeOrPayment == "Payment") echo gettext ("Payment amount"); else echo gettext("Annual Amount");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Amount" name="Amount" <?php if ($plg_aut_Cleared) echo "readonly=1";?> value="<?php echo $plg_amount; ?>"></td>
	</tr>

<?php if ($plg_PledgeOrPayment == "Pledge") { ?>
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Payment Schedule");?></td>
		<td class="RegTextColumn">
			<select class="RegEnterText" id="Schedule" name="Schedule">
				<option value="Monthly" <?php if ($plg_schedule=='Monthly') echo 'Selected'; ?>>Monthly</option>
			    <option value="Quarterly" <?php if ($plg_schedule=='Quarterly') echo 'Selected'; ?>>Quarterly</option>
			    <option value="Once" <?php if ($plg_schedule=='Once') echo 'Selected'; ?>>Once</option>
			</select>
		</td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Payment Method");?></td>
		<td class="RegTextColumn">
			<select class="RegEnterText" id="Method" name="Method">
<?php if ($bEnableElectronicDonation) { ?>
				<option value="BANKDRAFT">Bank Account ACH (preferred) <?php if ($plg_method=='BANKDRAFT') echo 'Selected'; ?></option>
			    <option value="CREDITCARD" <?php if ($plg_method=='CREDITCARD') echo 'Selected'; ?>>Credit Card</option>
<?php } ?>
			    <option value="Check" <?php if ($plg_method=='CHECK') echo 'Selected'; ?>>Check</option>
			    <option value="Cash" <?php if ($plg_method=='CASH') echo 'Selected'; ?>>Cash</option>
			</select>
		</td>
	</tr>
<?php  } // if pledge
	if ($plg_method=="CREDITCARD" || $plg_method=="BANKDRAFT") {?>
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
				if ($plg_aut_ID == $aut_ID)
					echo " selected";
				echo ">" . $showStr . "</option>\n";
			}
?>
			</select>
		</td>
	</tr>
<?php  }?>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Fiscal year");?></td>
		<td class="RegTextColumn">
			<select class="RegEnterText" id="FYID" name="FYID">
				<option value="<?php echo CurrentFY(); ?>"<?php if ($plg_FYID==CurrentFY()) echo ' Selected'; ?>>This fiscal year <?php echo MakeFYString (CurrentFY()); ?></option>
				<option value="<?php echo CurrentFY()+1; ?>"<?php if ($plg_FYID==CurrentFY()+1) echo ' Selected'; ?>>Next fiscal year <?php echo MakeFYString (CurrentFY()+1); ?></option>
			</select>
		</td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Fund");?></td>
		<td class="TextColumn">
			<select name="FundID">
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
		<td class="RegLabelColumn"><?php echo gettext("Comment");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Comment" name="Comment" value="<?php echo $plg_comment; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"<?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date:"); ?></td>
		<td class="RegTextColumn"><input type="text" name="Date" id="Date" value="<?php echo $plg_date; ?>" maxlength="10" id="Date" size="11">&nbsp;<input type="image" onclick="return showCalendar('Date', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>
	</tr>

<?php if ($errStr != "") { ?>
	<tr>
		<td></td><td class="RegError" align="center"><?php echo $errStr; ?></td>
	</tr>

<?php } ?>

	<tr>
		<td></td><td align="center">
<?php if ($plg_PledgeOrPayment == "Payment" && (! $plg_aut_Cleared)) {?>
			<input type="submit" class="regEditButton" value="<?php echo gettext("Process Payment"); ?>" name="Process">
<?php } else if ($plg_PledgeOrPayment == "Pledge") { ?>
			<input type="submit" class="regEditButton" value="<?php echo gettext("Save"); ?>" name="Save">
<?php }?>
			<input type="submit" class="regEditButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel">
			
			<input type="hidden" name="PledgeOrPayment" id="PledgeOrPayment" value="<?php echo $plg_PledgeOrPayment; ?>">

		</td>
	</tr>

</table>
</form>

<?php
mysqli_close($link);
?>
