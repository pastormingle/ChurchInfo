<?php
/*******************************************************************************
 *
 *  filename    : SelfPaymentMethodSave.php
 *  copyright   : Copyright 2016 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 * Just handle saving to the database from the form.  The form is in 
 * SelfPaymentMethodEdit.php
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

if (array_key_exists ("AutID", $_SESSION)) { // See if we are editing an existing record
	$aut_ID = $_SESSION["AutID"];
	$iAutID = $aut_ID; // Include/VancoChurchInfo.php is looking for this. 
} else {
	header('Location: SelfRegisterHome.php');
	exit();
}

$enableCode = FilterInput ($_POST["EnableButton"]);
$aut_EnableBankDraft = ($enableCode == 1);
if (! $aut_EnableBankDraft)
	$aut_EnableBankDraft = 0;
$aut_EnableCreditCard = ($enableCode == 2);
if (! $aut_EnableCreditCard)
	$aut_EnableCreditCard = 0;

$aut_NextPayDate=$link->real_escape_string($_POST["NextPayDate"]);
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

$reportResult = "Failure";
if ($errStr == "") {
	// Ok to create or update

	$setValueSQL = "SET " .
		"aut_FamID=$fam_ID,". 
		"aut_EnableBankDraft=$aut_EnableBankDraft,".
		"aut_EnableCreditCard=$aut_EnableCreditCard,".
		"aut_NextPayDate=\"$aut_NextPayDate\",".
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
	} else {
		$sSQL = "UPDATE autopayment_aut " . $setValueSQL . " WHERE aut_ID=".$aut_ID;
		$result = $link->query($sSQL);
	}
	$reportResult = "Success";
}

mysqli_close($link);

$resultArr = array();
$resultArr["result"] = $reportResult;
$resultArr["errStr"] = $errStr;

$json = json_encode($resultArr);
echo $json;
?>
