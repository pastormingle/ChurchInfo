<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterReset.php
 *  copyright   : Copyright 2015 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

include "Include/UtilityFunctions.php";
include "Include/Config.php";
include "SelfRegisterEmail.php";

$bNoBanner = array_key_exists ("NoBanner", $_GET);
if (array_key_exists ("NoBanner", $_SESSION))
	$bNoBanner = true;

error_reporting(-1);

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_randomtag = $link->real_escape_string($_GET['reg_randomtag']);

$sSQL = "SELECT * FROM register_reg WHERE reg_randomtag=\"$reg_randomtag\"";
$result = $link->query ($sSQL);
if ($result->num_rows != 1) {
	printf ("Unable to reset password.");
	mysqli_close($link);
	exit;
}
$line = $result->fetch_array(MYSQLI_ASSOC);
extract ($line);

if (isset($_POST["Submit"])) {
	$reg_password = $link->real_escape_string($_POST["Password"]);
	$reg_reenterpassword = $link->real_escape_string($_POST["ReEnterPassword"]);

	if ($reg_password != $reg_reenterpassword) {
		$errStr .= "Passwords do not match<br>\n";
	}

	if ($errStr == "") {
		$sPasswordHashSha256 = hash ("sha256", $reg_password);
		$sSQL = "UPDATE register_reg SET reg_password = '$sPasswordHashSha256', reg_randomtag='' WHERE reg_randomtag='".$reg_randomtag."'";
			
		$result = $link->query($sSQL);

		if (! $reg_confirmed) {
			SendConfirmMessage ($reg_id);
			
			printf ("Password has been updated, confirmation email has been re-sent.  Please use the link in the confirmation email to confirm your registration.");
			mysqli_close($link);
			exit;
		}
			
		header('Location: SelfRegisterHome.php');
		exit();
	}
}

// initialize everything if the form did not provide values OR the database record did not provide values
if (  (! isset($_POST["Submit"]))) {
	$reg_password = "";
	$reg_reenterpassword = "";	
}
?>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css">

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<h1>Choose a new password</h1>

<form method="post" action="SelfRegisterReset.php?reg_randomtag=<?php echo $reg_randomtag; ?>" name="SelfRegisterReset">

<table cellpadding="1" align="center">
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Password");?></td>
		<td class="RegTextColumn"><input type="password" class="RegEnterText" id="Password" name="Password" value="<?php echo $reg_password; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Re-Enter Password");?></td>
		<td class="RegTextColumn"><input type="password" class="RegEnterText" id="ReEnterPassword" name="ReEnterPassword" value="<?php echo $reg_reenterpassword; ?>"></td>
	</tr>
	<tr>
		<td></td><td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="Submit">
		</td>
	</tr>

</table>
</form>

<?php
mysqli_close($link);
?>
