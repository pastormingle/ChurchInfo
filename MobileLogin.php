<?php
$nChurchLatitude = 0.0; // initialize values that should be loaded from general settings if the login is successful
$nChurchLongitude = 0.0;

include "Include/Config.php";
include "Include/UtilityFunctions.php";

$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_id = 0;

$sResult = "";
$loginMsg = "";

$reg_username = $link->real_escape_string($_POST["sUsername"]);
$reg_password = $link->real_escape_string($_POST["sPassword"]);

$sPasswordHashSha256 = hash ("sha256", $reg_password);
$query = "SELECT * FROM register_reg WHERE reg_password='$sPasswordHashSha256' AND reg_confirmed=1 AND reg_username='$reg_username'";

$result = $link->query($query) or die('Query failed: ' . $link->error());
if ($result->num_rows == 1) {
	$line = $result->fetch_array(MYSQLI_ASSOC);
	extract ($line);
	$_SESSION["RegID"] = $reg_id;
	$fullURL = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$startURL = substr ($fullURL, 0, strlen($fullURL) - strlen("/MobileLogin.php"));
    $_SESSION['sURLPath'] = $startURL;
    $_SESSION['iUserID'] = $reg_perid;
    $_SESSION['LoginType'] = "SelfService";
    $loginMsg = "Login successful";
    $sResult = "Success";
} else {
	$query = "SELECT * FROM register_reg WHERE reg_password='$sPasswordHashSha256' AND reg_username='$reg_username'";
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 1) {
		// user is registered but email not confirmed yet
		$loginMsg = "Please use the link in your confirmation email to confirm your registration.";
	} else {
		$loginMsg = "Invalid User Name or Password";
	}
	$sResult = "Failure";
	session_destroy ();
	$reg_id = 0;
}
$result->free();

$resArr = array();
$resArr["Result"] = $sResult;
$resArr["Message"] = $loginMsg;
$resArr["ChurchLatitude"] = $nChurchLatitude;
$resArr["ChurchLongitude"] = $nChurchLongitude;

print json_encode ($resArr);
?>
