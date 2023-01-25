<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterForgot.php
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

require "SelfRegisterEmail.php";

$bNoBanner = array_key_exists ("NoBanner", $_GET);
if (array_key_exists ("NoBanner", $_SESSION))
	$bNoBanner = true;

error_reporting(-1);

$errMsg = "";

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_username = "";
$reg_email = "";

if (isset($_POST["Login"])) { // use data from the form to send a reset password email
	session_destroy ();
	$reg_id = 0;
	header('Location: SelfRegisterHome.php');
	return;
} else if (isset($_POST["Reset"])) { // use data from the form to send a reset password email
	$reg_username = $link->real_escape_string($_POST["UserName"]);
	$reg_email = $link->real_escape_string($_POST["Email"]);
	
	$query = "SELECT reg_id, reg_username, reg_email FROM register_reg WHERE reg_username='$reg_username' OR reg_email='$reg_email'";
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 0) {
		session_destroy ();
		$reg_id = 0;
		$errMsg = "Cannot find user name or email";
	} else {
		$line = $result->fetch_array(MYSQLI_ASSOC);
		$reg_id = $line['reg_id'];
		$reg_username = $line['reg_username'];
		$reg_email = $line['reg_email'];
		
		// change up the tag so this email will use a fresh one.
		$reg_randomtag = getGUID();
		$sSQL = "UPDATE register_reg SET reg_randomtag = '$reg_randomtag' WHERE reg_id=".$reg_id;
		$resultupdate = $link->query($sSQL);
		
		SendForgotMessage ($reg_id);
		
		$errMsg = "Please check your email for a link to help you reset your password.";
	}
	$result->free();
}
?>

<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css">

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<h1>Reset your password</h1>
<p>Enter your email or user name to receive a link that will reset your password.</p>
<form method="post" action="SelfRegisterForgot.php" name="SelfRegisterForgot">

<table cellpadding="1" align="center">	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("User Name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="UserName" name="UserName" value="<?php echo $reg_username; ?>"></td>
	</tr>
	
	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Email");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Email" name="Email" value="<?php echo $reg_email; ?>"></td>
	</tr>
<?php if ($errMsg != "") {?>
	<tr>
		<td></td><td class="RegTextColumn"><?php echo $errMsg;?></td>
	</tr>
<?php }?>
	<tr>
		<td></td><td align="center">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Submit"); ?>" name="Reset">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Login"); ?>" name="Login">
		</td>
	</tr>

</table>
</form>

<?php
mysqli_close($link);
?>
