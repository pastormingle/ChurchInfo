<?php

include "Include/Config.php";
require "Include/UtilityFunctions.php";

include "Include/vancowebservices.php";
include "Include/VancoConfig.php";

$aut_ID = 0; // id for current pledge record
if (array_key_exists ("AutID", $_GET)) { // See if we are editing an existing record
	$aut_ID = $_GET["AutID"];
	$iAutID = $aut_ID; // Include/VancoChurchInfo.php is looking for this. 
	$_SESSION["AutID"] = $aut_ID;
} else if (array_key_exists ("AutID", $_SESSION)) { // See if we are editing an existing record
	$aut_ID = $_SESSION["AutID"];
	$iAutID = $aut_ID; // Include/VancoChurchInfo.php is looking for this. 
}

	$customerid = "$iAutID"; // This is an optional value that can be used to indicate a unique customer ID that is used in your system
	// put aut_ID into the $customerid field
	// Create object to preform API calls
	
	$workingobj = new VancoTools($VancoUserid, $VancoPassword, $VancoClientid, $VancoEnc_key, $VancoTest);
	// Call Login API to receive a session ID to be used in future API calls
	$sessionid = $workingobj->vancoLoginRequest();
	// Create content to be passed in the nvpvar variable for a TransparentRedirect API call
	$nvpvarcontent = $workingobj->vancoEFTTransparentRedirectNVPGenerator(RedirectURL("CatchCreatePayment.php"),$customerid,"","NO");

$resultArr = array();
$resultArr["result"] = "Success";
$resultArr["errStr"] = "";
$resultArr["sessionid"] = $sessionid;
$resultArr["nvpvarcontent"] = $nvpvarcontent;

$json = json_encode($resultArr);
echo $json;
?>
