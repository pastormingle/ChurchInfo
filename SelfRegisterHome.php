<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterHome.php
 *  copyright   : Copyright 2015 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

include "Include/Config.php";
include "Include/UtilityFunctions.php";

$bEnableElectronicDonation = ($sElectronicTransactionProcessor == "Vanco");

if (array_key_exists ("NoBanner", $_GET))
	$bNoBanner = intval ($_GET["NoBanner"]);
else if (array_key_exists ("NoBanner", $_SESSION)) {
	$bNoBanner = true;
}
$_SESSION['NoBanner'] = $bNoBanner;

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_id = 0;

$loginMsg = "";

if (isset($_POST["Forgot"])) {
	header('Location: SelfRegisterForgot.php');
	exit();
} else if (isset($_POST["Register"])) {
	header('Location: SelfRegister.php');
	exit();
} else if (isset($_POST["Login"])) { // log in using data from the form
	$reg_username = $link->real_escape_string($_POST["UserName"]);
	$reg_password = $link->real_escape_string($_POST["Password"]);
	
	$sPasswordHashSha256 = hash ("sha256", $reg_password);
	$query = "SELECT * FROM register_reg WHERE reg_password='$sPasswordHashSha256' AND reg_confirmed=1 AND reg_username='$reg_username'";
	
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 1) {
		$line = $result->fetch_array(MYSQLI_ASSOC);
		extract ($line);
		$_SESSION["RegID"] = $reg_id;
		$_SESSION['CaptchaPassed'] = 'true';
		$fullURL = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$startURL = substr ($fullURL, 0, strlen($fullURL) - strlen("/SelfRegisterHome.php"));
        $_SESSION['sURLPath'] = $startURL;
        $_SESSION['iUserID'] = $reg_perid;
        $_SESSION['LoginType'] = "SelfService";
		$_SESSION['NoBanner'] = $bNoBanner;
	} else {
		$query = "SELECT * FROM register_reg WHERE reg_password='$sPasswordHashSha256' AND reg_username='$reg_username'";
		$result = $link->query($query) or die('Query failed: ' . $link->error());
		if ($result->num_rows == 1) {
			// user is registered but email not confirmed yet
			$loginMsg = "Please use the link in your confirmation email to confirm your registration.";
		} else {
			$loginMsg = "Invalid User Name or Password";
		}
		session_destroy ();
		$reg_id = 0;
	}
	$result->free();
}

if (array_key_exists ("RegID", $_SESSION)) {
	$reg_id = intval ($_SESSION["RegID"]);
	// make sure this user actually exists
	$query = "SELECT * FROM register_reg WHERE reg_id='$reg_id'"; //reg_firstname, reg_lastname
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 1) {
		$line = $result->fetch_array(MYSQLI_ASSOC);
		extract ($line);
		
		$query = "SELECT * FROM family_fam WHERE fam_ID='$reg_famid'";
		$result = $link->query($query) or die('Query failed: ' . $link->error());
		$line = $result->fetch_array(MYSQLI_ASSOC);
		if ($result->num_rows == 1)
			extract ($line);
			
		$query = "SELECT * FROM person_per WHERE per_ID='$reg_perid'";
		$result = $link->query($query) or die('Query failed: ' . $link->error());
		$line = $result->fetch_array(MYSQLI_ASSOC);
		if ($result->num_rows == 1)
			extract ($line);
	}
	$result->free();
}

// initialize everything if the form did not provide values OR the database record did not provide values
if (  (! isset($_POST["Login"])) && $reg_id == 0) {
	$reg_username = "";
	$reg_password = "";
}

?>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css">

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<?php 
if ($reg_id == 0) {
?>
<form method="post" action="SelfRegisterHome.php<?php if ($bNoBanner)echo "?NoBanner=1"?>" name="SelfRegisterHome">

<table cellpadding="1" align="center">	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("User Name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="UserName" name="UserName" value="<?php echo $reg_username; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Password");?></td>
		<td class="RegTextColumn"><input type="password" class="RegEnterText" id="Password" name="Password" value="<?php echo $reg_password; ?>"></td>
	</tr>
<?php if ($loginMsg != "") {?>
	<tr>
		<td></td><td class="RegTextColumn"><?php echo $loginMsg;?></td>
	</tr>
<?php }?>

	<tr>
		<td></td>
		<td align="center">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Login"); ?>" name="Login">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Register"); ?>" name="Register">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Forgot User Name or Password"); ?>" name="Forgot">
		</td>
	</tr>

</table>
</form>

<?php 
} else {
?>

<?php 
$currentFYID = CurrentFY(); // self-service just focuses on this fiscal year and next fiscal year
$nextFYID = $currentFYID + 1;

if ($reg_famid > 0) {	// logged in and matched to a family, can show financial information
//Get the pledges for this family
$sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method,
         plg_comment, plg_DateLastEdited, plg_PledgeOrPayment, a.per_FirstName AS EnteredFirstName,
         a.Per_LastName AS EnteredLastName, b.fun_Name AS fundName, 
         plg_GroupKey
		 FROM pledge_plg
		 LEFT JOIN person_per a ON plg_EditedBy = a.per_ID
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		 WHERE plg_famID =$reg_famid AND (plg_fyid=$currentFYID OR plg_fyid=$nextFYID) ORDER BY pledge_plg.plg_date";
$rsPledges = $link->query($sSQL);

// Get all the people in this family
$sSQL = "SELECT per_ID, per_FirstName, per_LastName, per_BirthYear FROM person_per WHERE per_fam_id=$reg_famid ORDER BY per_BirthYear";
$rsFamilyPeople = $link->query($sSQL);

//Get the automatic payments for this family
$sSQL = "SELECT *, a.per_FirstName AS AutoEnteredFirstName, 
                   a.Per_LastName AS AutoEnteredLastName, 
                   b.fun_Name AS AutoFundName
		 FROM autopayment_aut
		 LEFT JOIN person_per a ON aut_EditedBy = a.per_ID
		 LEFT JOIN donationfund_fun b ON aut_Fund = b.fun_ID
		 WHERE aut_famID = " . $reg_famid . " ORDER BY autopayment_aut.aut_NextPayDate";
$rsAutoPayments = $link->query($sSQL);

}
?>

<h1><?php echo "$reg_firstname $reg_lastname"; ?></h1>

<h2><?php echo gettext("Self-Service Actions"); ?></h2>

<table>
<tbody>
<tr>

<?php  if ($reg_famid > 0) { // only give pledge and payment buttons if matched to a family ?>

<td><a class="regHomeButton" href="SelfPledgeEdit.php?PledgeOrPayment=Pledge">New Pledge</a></td>

<?php if ($bEnableElectronicDonation) { ?>

<td><a class="regHomeButton" href="SelfPaymentMethodEdit.php?AutID=0" >New Payment Method</a></td>
<td><a class="regHomeButton" href="SelfRepeatingPaymentEdit.php" >Setup Repeating Payment</a></td>
<?php if ($rsAutoPayments->num_rows == 0) 
	echo "<td>Please create at least one payment method to enable immediate donation option.</td>";
else
	echo "<td><a class=\"regHomeButton\" href=\"SelfPledgeEdit.php?PledgeOrPayment=Payment\">Donate Now</a></td>"
?>
<?php } //if ($bEnableElectronicDonation) { ?>

<?php } // if ($reg_famid > 0)?>

<td><a class="regHomeButton" href="SelfRegisterLogout.php" >Log Out</a></td>
</tr>
</tbody>
</table>

<?php  if ($reg_perid > 0) { // only give person information if matched to a person ?>

	<h2><?php echo gettext("Personal"); ?></h2>
	<?php echo gettext("Name: $per_FirstName $per_LastName<br>"); ?>
	<?php echo gettext("Birth date: Year $per_BirthYear, Month $per_BirthMonth, Day $per_BirthDay<br>"); ?>
	<?php echo gettext("Email: $per_Email<br>"); ?>
	<?php echo gettext("Cell Phone: $per_CellPhone<br>"); ?>
	
	<a href="SelfEditPerson.php?per_ID=<?php echo $reg_perid;?>"><?php echo gettext("Edit personal information"); ?></a>
	
	<?php  if ($reg_famid > 0) { // only family information if matched to a family ?>
	
		<h2><?php echo gettext("Family"); ?></h2>
		<?php echo gettext("Address: $fam_Address1 $fam_Address2 $fam_City, $fam_State $fam_Zip<br>"); ?>
		<?php echo gettext("Home Phone: $fam_HomePhone<br>"); ?>
		<?php echo gettext("Family Email: $fam_Email<br>"); ?>
		
		<?php if ($bSelfCreate) { // If self-create is enabled set up editing links for this family ?> 
			<table>
			<?php
			while ($aFamilyPerson = $rsFamilyPeople->fetch_array(MYSQLI_ASSOC)) {
				echo ("<tr>");
				echo ("<td>".$aFamilyPerson["per_FirstName"] . " " . $aFamilyPerson["per_LastName"]."</td>");
				echo ("<td><a href=\"SelfEditPerson.php?per_ID=".$aFamilyPerson["per_ID"]."\">". gettext("Edit")."</a></td>");
				echo ("</tr>");
			}
			?>
			</table>
			<a href="SelfEditPerson.php?per_ID=0"><?php echo gettext("Add a person to this family"); ?></a><br>
		
		<?php  } else { // else $bSelfCreate: self-create not enabled, just list the family members ?>		
			<?php echo gettext("Family Members: ");
			$bDidOne = false;
			while ($aFamilyPerson = $rsFamilyPeople->fetch_array(MYSQLI_ASSOC)) {
				if ($bDidOne)
					echo (", ");
				echo ($aFamilyPerson["per_FirstName"] . " " . $aFamilyPerson["per_LastName"]);
				$bDidOne = true;
			}
			echo ("<br>");
			?>	
		<?php  } // else $bSelfCreate ?>
		
		<a href="SelfEditFamily.php"><?php echo gettext("Edit family information"); ?></a>
	
	<?php } // if ($reg_famid > 0) { // only family information if matched to a family ?>
<?php } // if ($reg_perid > 0) { // only family information if matched to a family ?>

<h2><?php echo gettext("Online Registration"); ?></h2>
<?php echo gettext("Address: $reg_address1 $reg_address2 $reg_city, $reg_state $reg_zip<br>"); ?>
<?php echo gettext("Email: $reg_email<br>"); ?>
<a href="SelfRegister.php">Edit Registration</a><br>

<?php  if ($reg_famid > 0) { // only family information if matched to a family ?>

<?php if ($rsAutoPayments->num_rows > 0) { ?>

<h2><?php echo gettext("Electronic Payment Methods"); ?></h2>

<table cellpadding="4" cellspacing="0" width="100%">

<tr class="TableHeader" align="center">
	<td><?php echo gettext("Edit"); ?></td>
	<td><?php echo gettext("Method"); ?></td>
	<td><?php echo gettext("Date Updated"); ?></td>
	<td><?php echo gettext("Updated By"); ?></td>
</tr>

<?php

$numAutoPayments = 0;
$numAutoPaymentsWithAmount = 0;
//Loop through all payment methods
while ($aRow = $rsAutoPayments->fetch_array(MYSQLI_ASSOC))
{
	$numAutoPayments += 1;
	
	$tog = (! $tog);

	extract($aRow);
	
	if ($aut_Amount > 0)
		$numAutoPaymentsWithAmount += 1;

	$AutoPaymentMethod = "";
	if ($aut_EnableBankDraft)
		$AutoPaymentMethod = "Bank ACH " . $aut_BankName  . " " . $aut_Account;
	else if (aut_EnableCreditCard)
		$AutoPaymentMethod = "Credit Card " . $aut_CreditCard;
?>
	<tr class="<?php echo $sRowClass ?>" align="center">
		<td><a href=SelfPaymentMethodEdit.php?AutID=<?php echo $aut_ID ?>>Edit</a></td>
		<td><?php echo $AutoPaymentMethod ?>&nbsp;</td>
		<td><?php echo $aut_DateLastEdited; ?>&nbsp;</td>
		<td><?php echo $AutoEnteredFirstName . " " . $AutoEnteredLastName; ?>&nbsp;</td>
	</tr>
<?php
}
?>
</table>

<?php if ($numAutoPaymentsWithAmount > 0) { ?>


<h2><?php echo gettext("Automatic Electronic Payments"); ?></h2>

<table cellpadding="4" cellspacing="0" width="100%">

<tr class="TableHeader" align="center">
	<td><?php echo gettext("Edit"); ?></td>
	<td><?php echo gettext("Method"); ?></td>
	<td><?php echo gettext("Fund"); ?></td>
	<td><?php echo gettext("Amount"); ?></td>
	<td><?php echo gettext("Schedule"); ?></td>
	<td><?php echo gettext("Fiscal Year"); ?></td>
	<td><?php echo gettext("Next Payment Day"); ?></td>
	<td><?php echo gettext("Date Updated"); ?></td>
	<td><?php echo gettext("Updated By"); ?></td>
</tr>

<?php
$numAutoPayments = 0;
//Loop through all payment methods
$rsAutoPayments->data_seek(0);
while ($aRow = $rsAutoPayments->fetch_array(MYSQLI_ASSOC))
{
	$numAutoPayments += 1;
	
	extract($aRow);
	
	if ($aut_Amount > 0) {
			
		$AutoPaymentMethod = "";
		if ($aut_EnableBankDraft)
			$AutoPaymentMethod = "Bank ACH";
		else if (aut_EnableCreditCard)
			$AutoPaymentMethod = "Credit Card";
			
		$AutoSchedule = "";
		if ($aut_Interval == 1)
			$AutoSchedule = "Monthly";
		else if ($aut_Interval == 3)
			$AutoSchedule = "Quartely";
		else
			$AutoSchedule = "Other";
		?>
	  
		<tr class="<?php echo $sRowClass ?>" align="center">
			<td><a href=SelfRepeatingPaymentEdit.php?AutID=0<?php echo $aut_ID ?>>Edit</a></td>
			<td><?php echo $AutoPaymentMethod ?>&nbsp;</td>
			<td><?php echo $AutoFundName ?>&nbsp;</td>
			<td align=center><?php echo $aut_Amount ?>&nbsp;</td>
			<td><?php echo $AutoSchedule ?>&nbsp;</td>
			<td><?php echo MakeFYString ($aut_FYID) ?>&nbsp;</td>
			<td><?php echo $aut_NextPayDate ?>&nbsp;</td>
			<td><?php echo $aut_DateLastEdited; ?>&nbsp;</td>
			<td><?php echo $AutoEnteredFirstName . " " . $AutoEnteredLastName; ?>&nbsp;</td>
		</tr>
	<?php
	}
}
?>
</table>
<?php } //if ($numAutoPaymentsWithAmount > 0) { ?>
<?php } // if ($rsAutoPayments->num_rows > 0) { ?>

<?php if ($rsPledges->num_rows > 0) { ?>

<h2><?php echo gettext("Pledges for This Fiscal Year and Next Fiscal Year"); ?></h2>

<table cellpadding="4" cellspacing="0" width="100%">

<tr class="TableHeader" align="center">
	<td><?php echo gettext("Edit"); ?></td>
	<td><?php echo gettext("Fund"); ?></td>
	<td><?php echo gettext("Fiscal Year"); ?></td>
	<td><?php echo gettext("Date"); ?></td>
	<td><?php echo gettext("Amount"); ?></td>
	<td><?php echo gettext("Schedule"); ?></td>
	<td><?php echo gettext("Method"); ?></td>
	<td><?php echo gettext("Comment"); ?></td>
	<td><?php echo gettext("Date Updated"); ?></td>
	<td><?php echo gettext("Updated By"); ?></td>
</tr>

<?php
//Loop through all pledges
while ($aRow = $rsPledges->fetch_array(MYSQLI_ASSOC))
{
	extract($aRow);

	if ($plg_PledgeOrPayment == 'Pledge') {
	?>
		<tr class="<?php echo $sRowClass ?>" align="center">
		<?php if ($plg_PledgeOrPayment=="Pledge"||$plg_method=="CREDITCARD" || $plg_method=="BANKDRAFT") { ?>
			<td><a href=SelfPledgeEdit.php?PledgeOrPayment=<?php echo $plg_PledgeOrPayment?>&PlgID=<?php echo $plg_plgID ?>><?php echo gettext ("Edit");?></a></td>
		<?php } else { ?>
			<td></td>
		<?php } ?>		
			<td><?php echo $fundName ?>&nbsp;</td>
			<td><?php echo MakeFYString ($plg_FYID) ?>&nbsp;</td>
			<td><?php echo $plg_date ?>&nbsp;</td>
			<td align=center><?php echo $plg_amount ?>&nbsp;</td>
			<td><?php echo $plg_schedule ?>&nbsp;</td>
			<td><?php echo $plg_method; ?>&nbsp;</td>
			<td><?php echo $plg_comment; ?>&nbsp;</td>
			<td><?php echo $plg_DateLastEdited; ?>&nbsp;</td>
			<td><?php echo $EnteredFirstName . " " . $EnteredLastName; ?>&nbsp;</td>
		</tr>
<?php
	}
}
?>
</table>


<h2><?php echo gettext("Payments for This Fiscal Year and Next Fiscal Year"); ?></h2>

<table cellpadding="4" cellspacing="0" width="100%">

<tr class="TableHeader" align="center">
	<td><?php echo gettext("Edit"); ?></td>
	<td><?php echo gettext("Fund"); ?></td>
	<td><?php echo gettext("Fiscal Year"); ?></td>
	<td><?php echo gettext("Date"); ?></td>
	<td><?php echo gettext("Amount"); ?></td>
	<td><?php echo gettext("Schedule"); ?></td>
	<td><?php echo gettext("Method"); ?></td>
	<td><?php echo gettext("Comment"); ?></td>
	<td><?php echo gettext("Date Updated"); ?></td>
	<td><?php echo gettext("Updated By"); ?></td>
</tr>

<?php
//Loop through all pledges
$rsPledges->data_seek(0);
while ($aRow = $rsPledges->fetch_array(MYSQLI_ASSOC))
{
	extract($aRow);

	if ($plg_PledgeOrPayment == 'Payment') {
	?>
		<tr class="<?php echo $sRowClass ?>" align="center">
		<?php if ($plg_PledgeOrPayment=="Pledge"||$plg_method=="CREDITCARD" || $plg_method=="BANKDRAFT") { ?>
			<td><a href=SelfPledgeEdit.php?PledgeOrPayment=<?php echo $plg_PledgeOrPayment?>&PlgID=<?php echo $plg_plgID ?>><?php echo gettext ("Edit");?></a></td>
		<?php } else { ?>
			<td></td>
		<?php } ?>		
			<td><?php echo $fundName ?>&nbsp;</td>
			<td><?php echo MakeFYString ($plg_FYID) ?>&nbsp;</td>
			<td><?php echo $plg_date ?>&nbsp;</td>
			<td align=center><?php echo $plg_amount ?>&nbsp;</td>
			<td><?php echo $plg_schedule ?>&nbsp;</td>
			<td><?php echo $plg_method; ?>&nbsp;</td>
			<td><?php echo $plg_comment; ?>&nbsp;</td>
			<td><?php echo $plg_DateLastEdited; ?>&nbsp;</td>
			<td><?php echo $EnteredFirstName . " " . $EnteredLastName; ?>&nbsp;</td>
		</tr>
<?php
	}
}
?>
</table>

<?php } // if ($rsPledges->num_rows > 0) { ?>


<?php  } // all financial stuff is conditional on match to family ?>

<?php 
} // just logging in
?>

<?php
mysqli_close($link);
?>
