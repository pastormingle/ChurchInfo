<?php 
require 'Include/PHPMailer-5.2.14/PHPMailerAutoload.php';

// read the report settings to pick up sChurchName
$rsConfig = mysqli_query($cnChurchInfo, "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
    while (list($cfg_name, $cfg_value) = mysqli_fetch_row($rsConfig)) {
        $$cfg_name = $cfg_value;
    }
}

$CONFIRM_EMAIL_URL = URL_Origin() . $sRootPath . '/';
$CONFIRM_EMAIL_SUBJECT = $sChurchName . " " . gettext ("Registration Confirmation");
$RESET_EMAIL_SUBJECT = $sChurchName . " " . gettext ("Password Reset");

function getGUID(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(secure_random_string(32));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)// "{"
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12)
            .chr(125);// "}"
        return $uuid;
    }
}

function SendConfirmPledgeMessage ($rpg_id)
{
	
}

function SendSelfServiceAdminsEmail ($reg_id)
{
	global $link, $sSelfServiceAdminEmails;

	$query = "SELECT * FROM register_reg JOIN person_per ON reg_perid=per_id JOIN family_fam on reg_famid=fam_id WHERE reg_id=$reg_id";
	$result = $link->query($query) or die('Query failed: ' . $link->error);
	if ($result->num_rows == 0) {
		$query = "SELECT * FROM register_reg WHERE reg_id=$reg_id";
		$result = $link->query($query) or die('Query failed: ' . $link->error);
		$line = $result->fetch_array(MYSQLI_ASSOC);
		extract ($line);
	} else {
		$line = $result->fetch_array(MYSQLI_ASSOC);
		extract ($line);
	}
	$result->free();

	$bodyContents .= "<h1>$reg_firstname $reg_lastname</h1>";
	if ($reg_perid > 0) {
		$bodyContents .= ("<h2>" . gettext ("Matched to an existing person record") . "</h2>");
	} else {
		$bodyContents .= ("<h2>" . gettext ("Did not match any existing person record") . "</h2>");
	}
	$bodyContents .= "<h1>" . gettext ("Details from registration and matching records: ") . "</h1>";
	$bodyContents .= var_export($line, true);
	$bodyContents .= "</html>";

	$emailArray = explode(',', $sSelfServiceAdminEmails);
	foreach ($emailArray as $email)	{
		SendAMessage($reg_id, $bodyContents, $email, "ChurchInfo Registration Admin", "ChurchInfo Registration");
	}
}

function SendConfirmMessage ($reg_id)
{
	global $link, $CONFIRM_EMAIL_URL, $CONFIRM_EMAIL_SUBJECT;

	$query = "SELECT reg_email, reg_firstname, reg_lastname, reg_randomtag FROM register_reg WHERE reg_id=$reg_id";
	$result = $link->query($query) or die('Query failed: ' . $link->error);
	if ($result->num_rows == 0) {
		// Cannot get the email address
		return;
	} else {
		$line = $result->fetch_array(MYSQLI_ASSOC);
		$to_email = $line["reg_email"];
		$to_name = $line["reg_firstname"] . " " . $line["reg_lastname"];
		$reg_randomtag = $line["reg_randomtag"];
	}
	$result->free();
		
	$validateURL = $CONFIRM_EMAIL_URL . "SelfRegisterConfirm.php?reg_randomtag=" . $reg_randomtag;
	$bodyContents = "<html>To confirm your registration click this link or copy and paste it into a brower.<br>".
	    "<a href=\"$validateURL\">$validateURL</a></html>";
	SendAMessage($reg_id, $bodyContents, $to_email, $to_name, $CONFIRM_EMAIL_SUBJECT);
}

function SendForgotMessage ($reg_id)
{
	global $link, $CONFIRM_EMAIL_URL, $RESET_EMAIL_SUBJECT;

	$query = "SELECT reg_email, reg_firstname, reg_lastname, reg_randomtag FROM register_reg WHERE reg_id=$reg_id";
	$result = $link->query($query) or die('Query failed: ' . $link->error);
	if ($result->num_rows == 0) {
		// Cannot get the email address
		return;
	} else {
		$line = $result->fetch_array(MYSQLI_ASSOC);
		$to_email = $line["reg_email"];
		$to_name = $line["reg_firstname"] . " " . $line["reg_lastname"];
		$reg_randomtag = $line["reg_randomtag"];
	}
	$result->free();

	$resetURL = $CONFIRM_EMAIL_URL . "SelfRegisterReset.php?reg_randomtag=" . $reg_randomtag;
	$bodyContents = "<html>To reset your password click this link or copy and paste it into a brower.<br>".
	    "<a href=\"$resetURL\">$resetURL</a></html>";
	SendAMessage($reg_id, $bodyContents, $to_email, $to_name, $RESET_EMAIL_SUBJECT);
}
	
function SendAMessage ($reg_id, $bodyContents, $to_email, $to_name, $email_subject)
{
    global $sToEmailAddress; //Default account for receiving a copy of all emails
    global $sChurchName;
    $sFromName = $sChurchName.": ChurchInfo Administrator";
    global $sSMTPAuth;
    global $sSMTPUser;
    global $sSMTPPass;
    global $sSMTPHost;
    global $sSendType;

	$mail = new PHPMailer;
	
    if (strtolower($sSendType)=='smtp') {
		$mail->isSMTP();
	
		$delimeter = strpos($sSMTPHost, ':');
	    if ($delimeter === FALSE) {
	        $sSMTPPort = 25;                // Default port number
	    } else {
	        $sSMTPPort = intval(substr($sSMTPHost, $delimeter+1));
	        $sSMTPHost = substr($sSMTPHost, 0, $delimeter);   
	    }
	    if (is_int($sSMTPPort))
	        $mail->Port = $sSMTPPort;
	    else
	        $mail->Port = 25;
		
		//Enable SMTP debugging	// 0 = off (for production use)	// 1 = client messages	// 2 = client and server messages
		$mail->SMTPDebug = 0; // 2
		$mail->Debugoutput = 'html';
		$mail->Host = $sSMTPHost;
		$mail->SMTPAuth = $sSMTPAuth;
		$mail->SMTPAutoTLS = true;
		$mail->Username = $sSMTPUser;
		$mail->Password = $sSMTPPass;
    } else {
        $mail->IsSendmail();                // tell the class to use Sendmail
    }
	$mail->setFrom($sToEmailAddress, $sFromName);
	$mail->addReplyTo($sToEmailAddress, $sFromName);
	
	$mail->addAddress($to_email, $to_name);
	$mail->Subject = $email_subject; 
	//Read an HTML message body from an external file, convert referenced images to embedded,
	//convert HTML into a basic plain-text alternative body
	//$mail->msgHTML(file_get_contents('contents.html'), dirname(__FILE__));
	//Replace the plain text body with one created manually
	
	$mail->Body = $bodyContents; 
	
	$mail->isHTML(true);
	
	//Attach an image file
	//$mail->addAttachment('images/phpmailer_mini.png');
	
	//send the message, check for errors
	if (!$mail->send()) {
	    echo "Mailer Error: " . $mail->ErrorInfo;
	} else {
//	    echo "Message sent!";
	}
}

?>
