<?php
/*******************************************************************************
*
*  filename    : Update1_2_14To1_3_0.php
*  description : auto-update script
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2010-2016 Michael Wilt
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
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

$sVersion = '1.3.0';

function BackUpTable ($tn)
{
    $sSQL = "DROP TABLE IF EXISTS $tn". "_backup";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    $sSQL = "CREATE TABLE $tn" . "_backup SELECT * FROM $tn";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    return (true);
}

function RestoreTableFromBackup ($tn)
{
    $sSQL = "DROP TABLE IF EXISTS $tn";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    $sSQL  = "RENAME TABLE `$tn"."_backup` TO `$tn`";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    return (true);
}

function DeleteTableBackup ($tn)
{
    $sSQL = "DROP TABLE IF EXISTS $tn"."_backup";
    if (!RunQuery($sSQL, FALSE))
        return (false);
    return (true);
}

for (; ; ) {    // This is not a loop but a section of code to be
                // executed once.  If an error occurs running a query the
                // remaining code section is skipped and all table
                // modifications are "un-done" at the end.
                // The idea here is that upon failure the users database
                // is restored to the previous version.

// **************************************************************************

// Need to back up tables we will be modifying- 

    $needToBackUp = array (
    "pledge_plg", "deposit_dep", "config_cfg", "menuconfig_mcf" );

    $bErr = false;
    foreach ($needToBackUp as $backUpName) {
        if (! BackUpTable ($backUpName)) {
            $bErr = true;
            break;
        }
    }
    if ($bErr)
        break;

// ********************************************************
// ********************************************************
// Begin modifying tables now that backups are available
// The $bStopOnError argument to RunQuery can now be changed from
// TRUE to FALSE now that backup copies of all tables are available
$sSQL = "ALTER TABLE pledge_plg ADD `plg_DateCleared` text NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "ALTER TABLE pledge_plg ADD `plg_TransactionRef` text NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "ALTER TABLE pledge_plg ADD `plg_TransactionFee` decimal(8,2) NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "ALTER TABLE deposit_dep MODIFY dep_Type ENUM ('Bank','CreditCard','BankDraft','eGive','SelfCreditCard','SelfBankDraft') NOT NULL default 'Bank'";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES (74, 'sSelfServiceFunds', '\"Pledge Recipts\"', 'text', '\"Pledge Receipts\"', 'Donation funds to present through self-service', 'General', NULL)";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "INSERT INTO `config_cfg` SET `cfg_id`=76, `cfg_name`='sBroadcastFunds', `cfg_value`='', `cfg_type`='text', `cfg_default`='', `cfg_tooltip`='Comma-separated list of funds to broadcast progress through the embedded web page', `cfg_section`='General', `cfg_category`=NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES (75, 'sSelfServiceAdminEmails', '\"\"', 'text', '\"\"', 'Comma-separated list of emails to distribute self-service notifications', 'General', NULL)";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "INSERT INTO `config_cfg` SET `cfg_id`=77, `cfg_name`='bSelfCreate', `cfg_value`='0', `cfg_type`='boolean', `cfg_default`='0', `cfg_tooltip`='Create person and family records through the self-service interface', `cfg_section`='General', `cfg_category`=NULL";
	if (!RunQuery($sSQL, FALSE))
	    break;

$sSQL = "CREATE TABLE `register_reg` (
	`reg_id` mediumint(9) unsigned NOT NULL auto_increment,
	`reg_perid` mediumint(9) unsigned,
	`reg_famid` mediumint(9) unsigned,
	`reg_firstname` text,
	`reg_middlename` text,
	`reg_lastname` text,
	`reg_famname` text,
	`reg_address1` text,
	`reg_address2` text,
	`reg_city` text,
	`reg_state` text,
	`reg_zip` text,
	`reg_country` text,
	`reg_phone` text,
	`reg_email` text,
	`reg_username` text,
	`reg_password` text,
	
	`reg_randomtag` text,
	`reg_confirmed` tinyint,
	`reg_changedate` datetime,
	PRIMARY KEY  (`reg_id`),
	UNIQUE KEY `reg_id` (`reg_id`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
	if (!RunQuery($sSQL, FALSE))
	    break;

   	$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (92, 'selfregisteradmin', 'admin', 0, 'Self-Registration Admin', 'Self-Registration Admin', 'SelfRegisterAdmin.php', '', 'bAdmin', NULL, 0, 0, NULL, 1, 13);";
	if (!RunQuery($sSQL, FALSE))
	    break;

	$sSQL = "INSERT INTO `menuconfig_mcf` VALUES (93, 'depositreconcile', 'deposit', 0, 'Reconcile Electronic Transactions', NULL, 'UpdateFromVanco.php', '', 'bFinance', NULL, 0, 0, NULL, 1, 4);";
	if (!RunQuery($sSQL, FALSE))
	    break;

	$sSQL = "UPDATE menuconfig_mcf SET sortorder=5	WHERE mid=39 AND sortorder=4";
	if (!RunQuery($sSQL, FALSE))
	    break;
	    
	$sSQL = "UPDATE menuconfig_mcf SET content=content_english;";
	if (!RunQuery($sSQL, FALSE))
	    break;
	
	$sSQL = "INSERT INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES (33, 
		'SELECT per.per_ID as AddToCart, 
		        CONCAT(per.per_FirstName,\' \',per.per_LastName) AS Name, 
		        Count(att.person_id) as Times_Attended, Max(evnt.event_start) as Last_attended 
		        FROM event_attend as att 
		        INNER JOIN events_event as evnt ON att.event_id = evnt.event_id 
		        INNER JOIN person_per as per ON att.person_id = per.per_id 
		        WHERE evnt.event_start >= \'~fromdate~\' AND evnt.event_start <= \'~todate~\' 
		        GROUP BY per.per_ID, CONCAT(per.per_FirstName,\' \',per.per_LastName) 
		        ORDER BY Last_attended, per.per_LastName, per.per_FirstName', 
		'Event Attendance Report', 'Summary of individual attendance data for a particular time period', 1)";
    RunQuery($sSQL, FALSE); // False means do not stop on error
	
	$sSQL = "INSERT INTO `queryparameters_qrp` (`qrp_ID`, `qrp_qry_ID`, `qrp_Type`, `qrp_OptionSQL`, `qrp_Name`, `qrp_Description`, `qrp_Alias`, `qrp_Default`, `qrp_Required`, `qrp_InputBoxSize`, `qrp_Validation`, `qrp_NumericMax`, `qrp_NumericMin`, `qrp_AlphaMinLength`, `qrp_AlphaMaxLength`) VALUES 
		(34, 33, 2, 'SELECT distinct event_start as Value, event_start as Display FROM events_event ORDER BY event_start', 'Starting Date', 'First event to report', 'fromdate', '1', 1, 0, '', 0, 0, 0, 0),
		(35, 33, 2, 'SELECT distinct event_start as Value, event_start as Display FROM events_event ORDER BY event_start', 'Ending Date', 'Last event to report', 'todate', '1', 1, 0, '', 0, 0, 0, 0)";
    RunQuery($sSQL, FALSE); // False means do not stop on error

	$sSQL = "INSERT INTO `version_ver` (`ver_version`, `ver_date`) VALUES ('".$sVersion."',NOW())";
    RunQuery($sSQL, FALSE); // False means do not stop on error
	    break;
}

$sError = MySQLError ();
$sSQL_Last = $sSQL;

// Let's check if mysql database is in sync with PHP code
$sSQL = 'SELECT * FROM version_ver ORDER BY ver_ID DESC';
$aRow = mysqli_fetch_array(RunQuery($sSQL));
extract($aRow);

if ($ver_version == $sVersion) {
    // We're good.  Clean up by dropping the
    // temporary tables
    foreach ($needToBackUp as $backUpName) {
        if (! DeleteTableBackup ($backUpName)) {
            break;
        }
    }
} else {
    // An error occured.  Clean up by restoring
    // tables to their original condition by using
    // the temporary tables.

    foreach ($needToBackUp as $backUpName) {
        if (! RestoreTableFromBackup ($backUpName)) {
            break;
        }
    }

    // Finally, Drop any tables that were created new
    $sSQL = "DROP TABLE IF EXISTS register_reg";
    if (!RunQuery($sSQL, FALSE))
        return (false);
}


$sSQL = $sSQL_Last;
?>
