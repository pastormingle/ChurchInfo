<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterHome.php
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

error_reporting(-1);

$todayDate = new DateTime();
$backDate = new DateTime();
$backDate->sub(new DateInterval('P365D'));

$endDate = $todayDate->format ("Y-m-d");
$startDate = $backDate->format ("Y-m-d");

// Connecting, selecting database
$link = mysqli_connect($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE)
    or die('Could not connect: ' . mysqli_error());
    
$sSQL = "SELECT SUM(plg_Amount) AS totAmount, MIN(plg_date) AS firstDate, MAX(plg_date) AS lastDate, fun_Name FROM pledge_plg JOIN donationfund_fun ON fun_ID=plg_fundID WHERE plg_PledgeOrPayment='Payment' AND plg_Date>='$startDate' AND plg_Date<='$endDate' AND fun_Name IN ($sBroadcastFunds) GROUP BY fun_Name ORDER BY firstDate";
$rsDonationTotal = $link->query($sSQL);

printf ("<table>");
printf ("<tr>");
printf ("<th>Start</th><th>Most Recent</th><th>Outreach Fund</th><th>Total</th>");
printf ("</tr>");

while ($row = $rsDonationTotal->fetch_array(MYSQLI_ASSOC)) {
	$totThisFund = $row["totAmount"];
	$firstDate = date_create($row["firstDate"])->format ("M, Y");
	$lastDate = date_create($row["lastDate"])->format ("M, Y");
	$fun_name = $row['fun_Name'];
	
	printf ("<tr>");
	printf ("<td>$firstDate</td><td>$lastDate</td><td>$fun_name</td><td>$totThisFund</td>");
	printf ("<tr>");
}

printf ("</table>");

?>
