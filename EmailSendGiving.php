<?php
/*******************************************************************************
*
*  filename    : EmailSendGiving.php
*  description : Sends Giving statements to email
*
*  http://www.churchdb.org/
*  Copyright 2001-2003 Lewis Franklin
*
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful, but
*  WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
*  General Public License for more details.
*
*  http://www.gnu.org/licenses
*
*  This file best viewed in a text editor with tabs stops set to 4 characters.
*  Please configure your editor to use soft tabs (4 spaces for a tab) instead
*  of hard tab characters.
*
******************************************************************************/

// The log files are useful when debugging email problems.  In particular, problems
// with SMTP servers.
$bEmailLog = FALSE;

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

$iUserID = $_SESSION['iUserID']; // Read into local variable for faster access
$sGreTable = 'giving_rpt_email_gre_'.$iUserID;
$sLogTable = 'email_job_log_'.$iUserID;

// Security: Both global and user permissions needed to send email.
// Otherwise, re-direct them to the main menu.
if (!($bEmailSend && $bSendPHPMail))
{
    Redirect('Menu.php');
    exit;
}

// Keep a detailed log of events in mysql.
function ClearEmailLog()
{
    global $iUserID;
    global $sLogTable;

    // Drop the table and create new empty table
    $sSQL = 'DROP TABLE IF EXISTS '.$sLogTable.";";
    RunQuery($sSQL);

    $sMessage = 'Log Created at '.date('Y-m-d H:i:s');

    $tSystem = gettimeofday();

    $tSec = $tSystem['sec'];
    $tUsec = str_pad($tSystem['usec'], 6, '0');

    $sSQL = "CREATE TABLE IF NOT EXISTS $sLogTable ( ".
            " ejl_id mediumint(9) unsigned NOT NULL auto_increment, ".
            " ejl_time varchar(20) NOT NULL DEFAULT '', ".
            " ejl_usec varchar(6) NOT NULL DEFAULT '', ".
            " ejl_text text NOT NULL DEFAULT '', PRIMARY KEY (ejl_id) ".
            ") ENGINE=MyISAM";
    RunQuery($sSQL);

    $sSQL = "INSERT INTO $sLogTable ". 
            "SET ejl_text='".EscapeString($sMessage)."', ". 
            "    ejl_time='$tSec', ".
            "    ejl_usec='$tUsec'";

    RunQuery($sSQL);
}

function AddToEmailLog($sMessage, $iUserID)
{
    global $sLogTable;
    $tSystem = gettimeofday();

    $tSec = $tSystem['sec'];
    $tUsec = str_pad($tSystem['usec'], 6, '0');

    $sSQL = "INSERT INTO $sLogTable ". 
            "SET ejl_text='".EscapeString($sMessage)."', ". 
            "    ejl_time='$tSec', ".
            "    ejl_usec='$tUsec'";

    RunQuery($sSQL);
}

function SendEmail($sSubject, $sMessage)
{
// TODO ALAN use these args or pitch them...

    global $iUserID;
    global $sGreTable;
    global $sEmailTaxState;
    global $sSendType;
    global $sFromEmailAddress;
    global $sFromName;
    global $sLangCode;
    global $sLanguagePath;
    global $sSMTPAuth;
    global $sSMTPUser;
    global $sSMTPPass;
    global $sSMTPHost;
    global $sSERVERNAME;
    global $sUSER;
    global $sPASSWORD;
    global $sDATABASE;
    global $sSQL_GRE;

    // Store these queries in variables. (called on every loop iteration)
    $sSQLGetEmail = 'SELECT * FROM '.$sGreTable.' '.
                    'ORDER BY gre_num_attempt, gre_FamID LIMIT 1';

    // Keep track of how long this script has been running.  To avoid server 
    // and browser timeouts break out of loop every $sLoopTimeout seconds and 
    // redirect back to EmailSendGiving.php with meta refresh until finished.
    $tStartTime = time();

    $mail = new PHPMailer();
    // Set the language for PHPMailer
    $mail->SetLanguage($sLangCode, $sLanguagePath);
    if($mail->IsError())
        echo 'PHPMailer Error with SetLanguage().  Other errors (if any) may not report.<br>';
    $mail->CharSet = 'utf-8';
    $mail->From = $sFromEmailAddress;   // From email address (User Settings)
    $mail->FromName = $sFromName;       // From name (User Settings)
    if (strtolower($sSendType)=='smtp') {
        $mail->IsSMTP();                    // tell the class to use SMTP
        $mail->SMTPKeepAlive = true;        // keep connection open until last email sent
        $mail->SMTPAuth = $sSMTPAuth;       // Server requires authentication
        if ($sSMTPAuth) {
            $mail->Username = $sSMTPUser;   // SMTP username
            $mail->Password = $sSMTPPass;   // SMTP password
        }
        $delimeter = strpos($sSMTPHost, ':');
        if ($delimeter === FALSE) {
            $sSMTPPort = 25;                // Default port number
        } else {
            $sSMTPPort = substr($sSMTPHost, $delimeter+1);
            $sSMTPHost = substr($sSMTPHost, 0, $delimeter);   
        }
        if (is_int($sSMTPPort))
            $mail->Port = $sSMTPPort;
        else
            $mail->Port = 25;

        $mail->Host = $sSMTPHost;           // SMTP server name
    } else {
        $mail->IsSendmail();                // tell the class to use Sendmail
    }

    $bContinue = TRUE;
    $sLoopTimeout = 30; // Break out of loop if this time is exceeded
    $iMaxAttempts = 3;  // Error out if an email address fails 3 times 
    while ($bContinue) 
    {   // Three ways to get out of this loop
        // 1.  We're finished sending email
        // 2.  Time exceeds $sLoopTimeout
        // 3.  Something strange happens 
        //        (maybe user tries to send from multiple sessions
        //         causing counts and timestamps to 'misbehave' )

        $tTimeStamp = date('Y-m-d H:i:s');
        $rsEmailAddress = RunQuery($sSQLGetEmail); // This query has limit one to pick up one job
        $aRow = mysqli_fetch_array($rsEmailAddress);
        extract($aRow);
        $mail->AddAddress($gre_Email);
        $mail->Subject = $sSubject;
        $mail->Body = $sMessage;
        // make current attachment the only one
        $mail->ClearAttachments ();
        if ($gre_Attach <> "")
            $mail->AddAttachment ("tmp_attach/".$gre_Attach);
        if(!$mail->Send()) {
            // failed- make a note in the log and the recipient record
            $sMsg = "Failed sending to: $gre_Email ";
            $sMsg .= $mail->ErrorInfo;
            echo "$sMsg<br>\n";
            AddToEmailLog($sMsg, $iUserID);

            // Increment the number of attempts for this message
            $gre_num_attempt++;
            $sSQL = 'UPDATE '.$sGreTable.' '.
                    "SET gre_num_attempt='$gre_num_attempt' ,".
                    "    gre_failed_time='$tTimeStamp' ".
                    "WHERE gre_FamID='$gre_FamID'";
            RunQuery($sSQL);

            // Check if we've maxed out retry attempts
            if ($gre_num_attempt < $iMaxAttempts) {
                echo "Pausing 15 seconds after failure<br>\n";
                AddToEmailLog('Pausing 15 seconds after failure', $iUserID);
                sleep(15);  // Delay 15 seconds on failure
                            // The mail server may be having a temporary problem
            } else {
                $sEmailTaxState = 'error';
                $bContinue = FALSE;
                $sMsg = 'Too many failures. Giving up. You may try to resume later.';
                AddToEmailLog($sMsg, $iUserID);
            }
        } else {
            echo "<b>$gre_Email</b> Sent! <br>\n";
            $sMsg = "Email sent to: $gre_Email";
            AddToEmailLog($sMsg, $iUserID);
            // Delete this record from the recipient list
            $sSQL = 'DELETE FROM '.$sGreTable.' '.
                    "WHERE gre_FamID='$gre_FamID'";
            RunQuery($sSQL);
            // remove the giving report file
            unlink ("tmp_attach/".$gre_Attach);
        }
        $mail->ClearAddresses();
        $mail->ClearBCCs();

        // Are we done?
        extract(mysqli_fetch_array(RunQuery($sSQL_GRE))); // this query counts remaining recipient records
        if ($countrecipients == 0) {
            $bContinue = FALSE;
            $sEmailTaxState = 'finish';
            AddToEmailLog('Job Finished', $iUserID);
        }

        // bail out of this loop if we've taken more than $sLoopTimeout seconds.
        // The meta refresh will reload this page so we can pick up where
        // we left off
        if ((time() - $tStartTime) > $sLoopTimeout) {
            $bContinue = FALSE;
        }
    }
    if (strtolower($sSendType) == 'smtp')
        $mail->SmtpClose();
} // end of function SendEmail()

#// TODO ALAN figure this bit out...
#if (array_key_exists ('resume', $_POST) && $_POST['resume'] == 'true') {
#    // If we are resuming skip the 'start' state and go straight to 'continue'
#    $_SESSION['sEmailTaxState'] = 'continue';
#
#    $sMsg = 'Email job resumed at '.date('Y-m-d H:i:s');
#    AddToEmailLog($sMsg, $iUserID);
#
#    // Clear the number of attempts, since we're retrying??
#    $sSQL = 'UPDATE '.$sGreTable.' '.
#            "SET gre_num_attempt='0' ";
#            // TODO ALAN was: "WHERE erp_usr_id='$iUserID'";
#
#    RunQuery($sSQL);
#}
#
#// TODO ALAN add abort functionality...
#if (array_key_exists ('abort', $_POST) && $_POST['abort'] == 'true') {
#    // If user chooses to abort the print job be sure to erase all evidence and
#    // Redirect to main menu
#
#    $sSQL = 'DROP TABLE IF EXISTS email_job_log_'.$iUserID;
#    RunQuery($sSQL);
#
#    // Delete message from emp
#    $sSQL = 'DROP TABLE '.$sGreTable;
#    RunQuery($sSQL);
#    Redirect('Menu.php?abortemail=true');
#}

// *****
// Force PHPMailer to the include path (this script only)
$sPHPMailerPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'Include'
.DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR;
$sIncludePath = '.'.PATH_SEPARATOR.$sPHPMailerPath;
ini_set('include_path',$sIncludePath);
// The include_path will automatically be restored upon completion of this script
// *****

$bHavePHPMailerClass = FALSE;
$bHaveSMTPClass = FALSE;
$bHavePHPMailerLanguage = FALSE;

$sLangCode = substr($sLanguage, 0, 2); // Strip the language code from the beginning of the language_country code

$sPHPMailerClass = $sPHPMailerPath.'class.phpmailer.php';
if (file_exists($sPHPMailerClass) && is_readable($sPHPMailerClass)) {
    require_once ($sPHPMailerClass);
    $bHavePHPMailerClass = TRUE;
    $sFoundPHPMailerClass = $sPHPMailerClass;
}

$sSMTPClass = $sPHPMailerPath.'class.smtp.php';
if (file_exists($sSMTPClass) && is_readable($sSMTPClass)) {
    require_once ($sSMTPClass);
    $bHaveSMTPClass = TRUE;
    $sFoundSMTPClass = $sSMTPClass;
}

$sTestLanguageFile = $sPHPMailerPath.'language'.DIRECTORY_SEPARATOR
.'phpmailer.lang-'.$sLangCode.'.php';
if (!strcmp($sLangCode, "en")) {
    $bHavePHPMailerLanguage = TRUE;
    $sFoundLanguageFile = "Not needed for English";
} elseif (file_exists($sTestLanguageFile) && is_readable($sTestLanguageFile)) {
    $sLanguagePath = $sPHPMailerPath.'language'.DIRECTORY_SEPARATOR;
    $bHavePHPMailerLanguage = TRUE;
    $sFoundLanguageFile = $sTestLanguageFile;
}

// This value is checked after the header is printed
$bPHPMAILER_Installed = $bHavePHPMailerClass && $bHaveSMTPClass && $bHavePHPMailerLanguage;

// if the table doesn't exist..but the log file does, show that.
// if the table exists..with no entries, we've just finished, so show the log file
$bLogTable_exists = True;
if(mysqli_num_rows(RunQuery("SHOW TABLES LIKE '".$sLogTable."';")) == 1 ) {
    $bLogTable_exists = True;
} else {
    $bLogTable_exists = False;
}
if(mysqli_num_rows(RunQuery("SHOW TABLES LIKE '".$sGreTable."';")) == 1 ) {
    $bGreTable_exists = True;
    $sSQL_GRE = "SELECT COUNT(gre_FamID) as countrecipients FROM $sGreTable ;";
    extract(mysqli_fetch_array(RunQuery($sSQL_GRE))); // this query counts remaining recipient records
} else {
    $bGreTable_exists = False;
    $countrecipients = 0;
}
// if there is nothing to do and nothing to show, bail out...
if( !$bLogTable_exists AND !$bGreTable_exists) {
    Redirect('Menu.php?noGreTable=true,noLogFile=true');
}

// If no log file, then we're just getting started..
if (!$bLogTable_exists) {
    // If no log, the email job has not started yet.  
    ClearEmailLog();  // Initialize Log
    AddToEmailLog('Job Started. Waiting on User to fill out Subject and Message', $iUserID);
    unset($_SESSION['EmailSubject']);
    unset($_SESSION['EmailMessage']);
}
if (array_key_exists ("EmailSubject", $_POST)) {
    $_SESSION['EmailSubject'] = stripslashes($_POST["EmailSubject"]);
}
if (array_key_exists ("EmailMessage", $_POST)) {
    $_SESSION['EmailMessage'] = stripslashes($_POST["EmailMessage"]);
}

$sEmailSubject = $_SESSION['EmailSubject'];
$sEmailMessage = $_SESSION['EmailMessage'];
if (($sEmailSubject == "") or ($sEmailMessage == "")) {
    // If no subject or message body, we just keep trying to get one in start state
    $sEmailTaxState = 'start';
} elseif (!$bGreTable_exists) {
    // If no gre file, then we've finished and want to show the log
    $sEmailTaxState = 'finish';
    $sMsg = 'Job finished after page reload at '.date('Y-m-d H:i:s');
    AddToEmailLog($sMsg, $iUserID);
} elseif ($countrecipients) {
    $sMsg = "Job running with .$countrecipients. recipients to go after page reload at ".date('Y-m-d H:i:s');
    AddToEmailLog($sMsg, $iUserID);
    $sEmailTaxState = 'continue';
} else {
    $sMsg = 'Job finished, showing log after page reload at '.date('Y-m-d H:i:s');
    AddToEmailLog($sMsg, $iUserID);
    $sEmailTaxState = 'finish';
}

$bMetaRefresh = FALSE; // Assume page does not need refreshing
// Decide if we want this page to reload again
if ($sEmailTaxState == 'continue') {
    $bMetaRefresh = TRUE;
} else {
    $bMetaRefresh = FALSE;
}
// Set a Meta Refresh in the header so this page automatically reloads
if ($bMetaRefresh) { 
    $sMetaRefresh = '<meta http-equiv="refresh" content="2;URL=EmailSendGiving.php">'."\n";
}

// Set the page title and include HTML header
$sPageTitle = gettext('Email Send Giving');
require 'Include/Header.php';

if(!$bPHPMAILER_Installed) {
    echo    '<br>' . gettext('ERROR: PHPMailer is not properly installed on this server.')
    .       '<br>' . gettext('PHPMailer is required in order to send emails from this server.');
    echo '<br><br>include_path = ' . ini_get('include_path');
    if ($bHavePHPMailerClass)
        echo '<br><br>Found: ' . $sFoundPHPMailerClass;
    else
        echo '<br><br>Unable to find file: class.phpmailer.php';
    if ($bHaveSMTPClass)
        echo '<br>Found: ' . $sFoundSMTPClass;
    else
        echo '<br>Unable to find file: class.smtp.php';
    if ($bHavePHPMailerLanguage)
        echo '<br>Found: ' . $sFoundLanguageFile;
    else
        echo "<br>Unable to find file: phpmailer.lang-$sLangCode.php";
    exit;
}

$tTimeStamp = date('m/d H:i:s');

if ($sEmailTaxState == 'start') {
    echo '<form method="post" action="EmailSendGiving.php" enctype="multipart/form-data">';
    echo '<table cellpadding="1" align="center">';
    echo '<tr>';
    echo '<td align="center">';
    echo '<input type="submit" class="icButton" value="'.gettext("Send").'" name="Submit">';
// TODO ALAN need to do something when cancelled... like stop and clean up?
// TODO ALAN need have a cancel/abort/pause button when in progress...
    echo '<input type="button" class="icButton" value="'.gettext("Cancel").'" name="Cancel">';
    echo '</td>';
    echo'</tr>';
    echo '</table>';

    echo gettext('Subject:');
    echo '<br><input type="text" name="EmailSubject" size="80" value="';
    echo htmlspecialchars($sEmailSubject) . '"></input>'."\n";
    echo '<br>' . gettext('Message:');
    echo '<br><textarea name="EmailMessage" rows="20" cols="72">';
    echo htmlspecialchars($sEmailMessage) . '</textarea>'."\n";

    echo '</form>';
    $iUserID = $_SESSION['iUserID']; // Read into local variable for faster access
    $sGreTable = 'giving_rpt_email_gre_'.$iUserID;
    if(mysqli_num_rows(RunQuery("SHOW TABLES LIKE '".$sGreTable."';")) <> 0 ) {
        $sSQL = 'SELECT gre_FamID, gre_FamName, gre_Email, gre_Attach FROM '.$sGreTable.' '.
                "ORDER BY gre_FamName";
        $rsGivingReports = RunQuery($sSQL);
        $bGivingReports = True;
        echo "<tr><td class=LabelColumn>".gettext("Giving Reports to Send:")."<br></td>";
        echo "<td class=TextColumnWithBottomBorder><div class=SmallText>"
            .gettext("Use Ctrl Key to select multiple")
            ."</div><select name=givingreports[] size=6 multiple>";
        echo "<option value=0 selected>".gettext("All Families")."</option>";
        echo "<option value=0>----------</option>";
        while ($aRow = mysqli_fetch_array($rsGivingReports)) {
            extract($aRow);
            echo "<option value=\"".$gre_FamID."\">".$gre_FamName."&nbsp;".$gre_Email;"&nbsp;</option>";
        }
        echo "</select></td></tr>";
    }
} elseif ($sEmailTaxState == 'continue') {
    // continue sending email
    // There must be more than one recipient
    echo '<br>Please be patient. Job is running, with '.$countrecipients.' yet to go.<br><br>';
    echo '<b>Please allow up to 60 seconds for page to reload.</b><br><br>';
    SendEmail($sEmailSubject, $sEmailMessage);
} elseif ($sEmailTaxState == 'finish') {
    echo "<br><b>The job is finished!</b><br>\n";
    echo '<br><br><div align="center"><table>';
    $sSQL = "SELECT * FROM email_job_log_$iUserID ".
            "ORDER BY ejl_id";
    $rsEJL = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsEJL)) {
        extract($aRow);
        $sTime = date('i:s', intval($ejl_time)).'.';
        $sTime .= substr($ejl_usec,0,3);
        echo "<tr><td>$sTime</td><td>$ejl_text</td></tr>\n";
    }
    echo '</table></div>';

    // Drop gre as it should now be empty..
    if($bGreTable_exists) {
        $sSQL = "DROP TABLE ".$sGreTable;
        RunQuery($sSQL);
    }
    // Drop log as it should have been displayed.
    if($bLogTable_exists) {
        $sSQL = "DROP TABLE ".$sLogTable;
        RunQuery($sSQL);
    }
} elseif ($sEmailTaxState == 'error') {
    echo "Job terminated due to error.  Please review log for further information.<br>\n";
    echo '<br><br><div align="center"><table>';
    $sSQL = "SELECT * FROM email_job_log_$iUserID ORDER BY ejl_id";
    $rsEJL = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsEJL)) {
        extract($aRow);
        $sTime = date('i:s', intval($ejl_time)).'.';
        $sTime .= substr($ejl_usec,0,3);
        echo "<tr><td>$sTime</td><td>$ejl_text</td></tr>\n";
    }
    echo '</table></div>';
}
require 'Include/Footer.php';
?>
