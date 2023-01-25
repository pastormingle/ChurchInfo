<?php
/*******************************************************************************
 *
 *  filename    : SelfRegister.php
 *  copyright   : Copyright 2015 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

include "Include/Captcha-1.1.1/PhraseBuilderInterface.php";
include "Include/Captcha-1.1.1/PhraseBuilder.php";
include "Include/Captcha-1.1.1/CaptchaBuilderInterface.php";
include "Include/Captcha-1.1.1/CaptchaBuilder.php";

include "Include/UtilityFunctions.php";
include "Include/Config.php";

require "SelfRegisterEmail.php";

$noBannerArg = "";
if (array_key_exists ("NoBanner", $_SESSION)) {
	$bNoBanner = true;
	$noBannerArg = "?NoBanner=1";
}

$bCaptchaPassed = false;

if ((array_key_exists ('CaptchaPassed', $_SESSION) && $_SESSION['CaptchaPassed'] == 'true') ||
	(array_key_exists ('phrase', $_SESSION) && array_key_exists ('CaptchaEntered', $_POST) && $_POST['CaptchaEntered'] == $_SESSION['phrase'])) {
	$bCaptchaPassed = true;
	$_SESSION['CaptchaPassed'] = 'true';
} else {
	$builder = new CaptchaBuilder;
	$builder->build();
	$_SESSION['phrase'] = $builder->getPhrase();
}

error_reporting(-1);

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());

$reg_id = 0;

$errStr = "";

if (isset($_POST["Cancel"])) { // bail out without saving
	header('Location: SelfRegisterHome.php'.$noBannerArg);
	exit();
}else if (isset($_POST["Submit"])) { // trying to save, use data from the form
	if (array_key_exists ("RegID", $_SESSION)) // editing an existing record
		$reg_id = $_SESSION["RegID"];

	$reg_firstname = $link->real_escape_string($_POST["FirstName"]);
	$reg_middlename = $link->real_escape_string($_POST["MiddleName"]);
	$reg_lastname = $link->real_escape_string($_POST["LastName"]);
	$reg_famname = $link->real_escape_string($_POST["FamName"]);
	$reg_address1 = $link->real_escape_string($_POST["Address1"]);
	$reg_address2 = $link->real_escape_string($_POST["Address2"]);
	$reg_city = $link->real_escape_string($_POST["City"]);
	$reg_state = $link->real_escape_string($_POST["State"]);
	$reg_zip = $link->real_escape_string($_POST["Zip"]);
	$reg_country = $link->real_escape_string($_POST["Country"]);
	$reg_phone = $link->real_escape_string($_POST["Phone"]);
	$reg_email = $link->real_escape_string($_POST["Email"]);
	$reg_username = $link->real_escape_string($_POST["UserName"]);
	$reg_password = $link->real_escape_string($_POST["Password"]);
	$reg_reenterpassword = $link->real_escape_string($_POST["ReEnterPassword"]);
	
	$reg_randomtag = $link->real_escape_string($_POST["RandomTag"]);
	$reg_confirmed = $link->real_escape_string($_POST["Confirmed"]);
	
	if ($reg_famname == "")
		$reg_famname = $reg_lastname;
	if ($reg_username == "")
		$reg_username = $reg_email;
		
	// If everything looks good save the record
	// If there is a problem make a note
	
	if (! $bCaptchaPassed) {
		$errStr .= "CAPTCHA string does not match<br>\n";
	}
	if ($reg_famname == "") {
		$errStr .= "Last name or family name is required<br>\n";
	}
	if ($reg_id == 0 && $reg_password != $reg_reenterpassword) { // note password can only be entered the first time through
		$errStr .= "Passwords do not match<br>\n";
	}
	if ($reg_username == "") {
		$errStr .= "Email or user name is required<br>\n";
	}
	if ($reg_id == 0) { // make sure this user name is valid and available
		$query = 'SELECT * FROM register_reg WHERE reg_username="' . $reg_username . '"';
		$result = $link->query($query) or die('Query failed: ' . $link->error);
		if ($result->num_rows > 0) {
			$errStr .= "User name is already taken (did you <a href=\"SelfRegisterForgot.php\">forget your password?</a>)<br>\n";
		}
		$result->free();
	}

	if ($errStr == "") {
		// Ok to create or update
		
		// try to figure out which family this is
		$per_fam_id = 0;
		$per_id = 0;
		$sSQL = "SELECT per_id, per_fam_id FROM person_per WHERE (per_firstname='$reg_firstname' AND per_lastname='$reg_lastname') OR (per_firstname='$reg_firstname' AND per_email='$reg_email')";
		$result = $link->query($sSQL);
		if ($result->num_rows == 1) { // got exactly one matching person
			$line = $result->fetch_array(MYSQLI_ASSOC);
			$per_id = $line["per_id"];	
			$per_fam_id = $line["per_fam_id"];
		} else if ($result->num_rows > 1) { // got multiple matching people
			for ($i = 0; $i < $result->num_rows; $i += 1) {
				$line = $result->fetch_array(MYSQLI_ASSOC);
				$per_id = $line["per_id"];
				$per_fam_id = $line["per_fam_id"];
				$sSQL = "SELECT fam_id, fam_address1, fam_city, fam_state FROM family_fam WHERE fam_id='$per_fam_id'";
				$resultfam = $link->query($sSQL);
				if ($resultfam->num_rows != 1)
					continue;
				$linefam = $resultfam->fetch_array(MYSQLI_ASSOC);
				if ($linefam['fam_address1'] == $reg_address1 &&
				    $linefam['fam_city'] == $fam_city &&
				    $linefam['fam_state'] == $fam_state)
					break; // break out and leave per_fam_id as set above
			}
		}
		
		$setValueSQL = "SET " .
				"reg_famid=\"$per_fam_id\",".
				"reg_perid=\"$per_id\",".
				"reg_firstname=\"$reg_firstname\",".
				"reg_middlename=\"$reg_middlename\",".
				"reg_lastname=\"$reg_lastname\",".
				"reg_famname=\"$reg_famname\",".
				"reg_address1=\"$reg_address1\",".
				"reg_city=\"$reg_city\",".
				"reg_state=\"$reg_state\",".
				"reg_zip=\"$reg_zip\",".
				"reg_country=\"$reg_country\",".
				"reg_phone=\"$reg_phone\",".
				"reg_email=\"$reg_email\",".
				"reg_username=\"$reg_username\",".
				"reg_randomtag=\"$reg_randomtag\",".
				"reg_changedate=NOW()";

		if ($reg_id == 0) { // creating a new record
			$sPasswordHashSha256 = hash ("sha256", $reg_password);
			$setPassWordStr = ", reg_password = \"$sPasswordHashSha256\"";
			
			$sSQL = "INSERT INTO register_reg " . $setValueSQL . $setPassWordStr;
			$result = $link->query($sSQL);
			
			$sSQL = "SELECT LAST_INSERT_ID();";
			$result = $link->query($sSQL);
			
			$line = $result->fetch_array(MYSQLI_ASSOC);
			$reg_id = $line["LAST_INSERT_ID()"];
			SendConfirmMessage ($reg_id);
			$errStr = gettext ("Please check your email for a confirmation message.");
			session_destroy();			
		} else {
			$errStr = gettext ("Registration information updated.");
			$sSQL = "UPDATE register_reg " . $setValueSQL . " WHERE reg_id=".$reg_id;
			$result = $link->query($sSQL);
		}

		SendSelfServiceAdminsEmail ($reg_id);
		
//		header('Location: SelfRegisterHome.php');
//		exit();
	}
	
} else if (array_key_exists ("RegID", $_SESSION)) { // already logged in, use the record for this session
	$query = 'SELECT * FROM register_reg WHERE reg_id=' . $_SESSION["RegID"];
	$result = $link->query($query) or die('Query failed: ' . $link->error());
	if ($result->num_rows == 0) {
		session_destroy ();
		$reg_id = 0;
	} else {
		while ($line = $result->fetch_array(MYSQLI_ASSOC)) {
			extract ($line);
		}
	}
	$result->free();
}

// initialize everything if the form did not provide values OR the database record did not provide values
if (  (! isset($_POST["Submit"])) && $reg_id == 0) {
	$reg_firstname = "";
	$reg_middlename = "";
	$reg_lastname = "";
	$reg_famname = "";
	$reg_address1 = "";
	$reg_address2 = "";
	$reg_city = "";
	$reg_state = "";
	$reg_zip = "";
	$reg_country = "";
	$reg_phone = "";
	$reg_email = "";
	$reg_username = "";
	$reg_password = "";
	$reg_reenterpassword = "";	

	$reg_randomtag = getGUID();
	$reg_confirmed = false;
}
?>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<link rel="stylesheet" type="text/css" href="Include/RegStyle.css">

<form method="post" action="SelfRegister.php" name="SelfRegister">

<?php 
if (! $bNoBanner)
	echo $sHeader; 
?>

<table cellpadding="1" align="center">

	<tr>
		<td><h2 style="display:table-cell;">Registration</h2></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("User Name");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="UserName" name="UserName" value="<?php echo $reg_username; ?>"></td>
	</tr>
	
	<tr>
	<?php if ($reg_id == 0) { ?>
		<td class="RegLabelColumn"><?php echo gettext("Password");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="password" class="RegEnterText" id="Password" name="Password" value="<?php echo $reg_password; ?>"></td>
	<?php  }?>
	</tr>
	
	<tr>
	<?php if ($reg_id == 0) { ?>
		<td class="RegLabelColumn"><?php echo gettext("Re-Enter Password");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="password" class="RegEnterText" id="ReEnterPassword" name="ReEnterPassword" value="<?php echo $reg_reenterpassword; ?>">
	<?php  }?>

		<input type="hidden" id="RandomTag" name="RandomTag" value="<?php echo $reg_randomtag; ?>" >
		<input type="hidden" id="Confirmed" name="Confirmed" value="<?php echo $reg_confirmed; ?>" >

		</td>
	</tr>

	<tr>
		<td><h2 style="display:table-cell;">Name/Address</h2></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("First name");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="FirstName" name="FirstName" value="<?php echo $reg_firstname; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Middle name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="MiddleName" name="MiddleName" value="<?php echo $reg_middlename; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Last name");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="LastName" name="LastName" value="<?php echo $reg_lastname; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Family name");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="LastName" name="FamName" value="<?php echo $reg_famname; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Address 1");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Address1" name="Address1" value="<?php echo $reg_address1; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Address 2");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Address2" name="Address2" value="<?php echo $reg_address2; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("City");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="City" name="City" value="<?php echo $reg_city; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("State");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="State" name="State" value="<?php echo $reg_state; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Zip code");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Zip" name="Zip" value="<?php echo $reg_zip; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Country");?></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Country" name="Country" value="<?php echo $reg_country; ?>"></td>
	</tr>

	<tr>
		<td colspan="2"><h2 style="display:table-cell;">Contact Information</h2></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Phone");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Phone" name="Phone" value="<?php echo $reg_phone; ?>"></td>
	</tr>

	<tr>
		<td class="RegLabelColumn"><?php echo gettext("Email");?><font color="red">*</font></td>
		<td class="RegTextColumn"><input type="text" class="RegEnterText" id="Email" name="Email" value="<?php echo $reg_email; ?>"></td>
	</tr>
	
<?php if (!	$bCaptchaPassed) { ?>
	<tr>
		<td></td><td class="RegTextColumn" align="center"><img src="<?php echo $builder->inline(); ?>" /><br><?php echo gettext ("Please enter string from CAPTCHA picture above");?><font color="red">*</font><br><input type="text" class="RegEnterText" name="CaptchaEntered" value=""></td>
	</tr>
<?php } ?>

<?php if ($errStr != "") { ?>
	<tr>
		<td></td><td class="RegError" align="center"><?php echo $errStr; ?></td>
	</tr>

<?php } ?>

	<tr>
		<td></td><td align="center">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Save"); ?>" name="Submit">
			<input type="submit" class="regEditButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel">
		</td>
	</tr>

</table>
</form>

<?php
mysqli_close($link);
?>
