<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterDelete.php
 *  last change : 2016-02-28
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

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (!$_SESSION['bEditRecords'])
{
	exit;
}

$sSQL = "DELETE FROM `register_reg` WHERE `reg_id` = '" . $reg_id . "' LIMIT 1;";
//Execute the SQL
RunQuery($sSQL);
?>
