<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterMatch.php
 *  last change : 2016-02-29
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2016 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$reg_id= FilterInput($_GET["reg_id"],'int');
$per_id= FilterInput($_GET["per_id"],'int');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (!$_SESSION['bEditRecords'])
{
	exit;
}

$sSQL = "SELECT `per_fam_id` FROM person_per WHERE per_id=$per_id";
$result = RunQuery($sSQL);
extract (mysqli_fetch_array($result));
$reg_famid = 0;
if (isset ($per_fam_id))
	$reg_famid = $per_fam_id;

$sSQL = "UPDATE `register_reg` SET reg_perid=$per_id, reg_famid=$reg_famid WHERE `reg_id`=" . $reg_id;
//Execute the SQL
RunQuery($sSQL);
?>
    