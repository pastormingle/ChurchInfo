<?php
/*******************************************************************************
*
*  filename    : FixDates.php
*  description : auto-update script
*
*  http://www.churchdb.org/
*
*  Contributors:
*  2017 Michael Wilt
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

$sSQL = "SET SESSION sql_mode = '';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE user_usr SET usr_LastLogin='1970-01-01 00:00:00' WHERE usr_LastLogin='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE user_usr SET usr_ShowSince='1970-01-01' WHERE usr_ShowSince='0000-00-00';";
RunQuery($sSQL, FALSE);
$sSQL = "ALTER TABLE `user_usr` CHANGE `usr_LastLogin` `usr_LastLogin` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00', CHANGE `usr_showSince` `usr_showSince` DATE NOT NULL DEFAULT '1970-01-01';";

$sSQL = "ALTER TABLE `family_fam` CHANGE `fam_DateEntered` `fam_DateEntered` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE `family_fam` SET fam_DateEntered='1970-01-01 00:00:00' WHERE fam_DateEntered='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `pledge_plg` CHANGE `plg_DateLastEdited` `plg_DateLastEdited` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE pledge_plg SET plg_DateLastEdited='1970-01-01 00:00:00' WHERE plg_DateLastEdited='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `event_types` CHANGE `type_defrecurDOY` `type_defrecurDOY` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE event_types SET type_defrecurDOY='1970-01-01 00:00:00' WHERE type_defrecurDOY='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `istlookup_lu` CHANGE `lu_LookupDateTime` `lu_LookupDateTime` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE istlookup_lu SET lu_LookupDateTime='1970-01-01 00:00:00' WHERE lu_LookupDateTime='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `note_nte` CHANGE `nte_DateEntered` `nte_DateEntered` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE note_nte SET nte_DateEntered='1970-01-01 00:00:00' WHERE nte_DateEntered='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `person_per` CHANGE `per_DateEntered` `per_DateEntered` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE person_per SET per_DateEntered='1970-01-01 00:00:00' WHERE per_DateEntered='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);

$sSQL = "ALTER TABLE `events_event` CHANGE `event_start` `event_start` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00', CHANGE `event_end` `event_end` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE events_event SET event_start='1970-01-01 00:00:00' WHERE event_start='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);
$sSQL = "UPDATE events_event SET event_end='1970-01-01 00:00:00' WHERE event_end='0000-00-00 00:00:00';";
RunQuery($sSQL, FALSE);
?>
