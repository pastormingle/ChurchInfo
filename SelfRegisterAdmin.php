<?php
/*******************************************************************************
 *
 *  filename    : SelfRegisterAdmin.php
 *  last change : 2016-02-28
 *  description : displays a list of all self-registration records
 *
 *  http://www.churchdb.org/
 *  Copyright 2016 Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

// Get all the self registration records
$sSQL = "SELECT * FROM register_reg ORDER BY reg_lastname";
$rsSelfRegistrations = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Self Registration Administration");
require "Include/Header.php";
?>

<script>
function ConfirmDeleteRegistration (RegID)
{
	var famName = document.getElementById("Name"+RegID).innerHTML;
	var r = confirm("Delete registration for "+famName);
	if (r == true) {
		DeleteSelfRegistration (RegID);
	} 
}

function DeleteSelfRegistration (RegID)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.uniqueid = RegID;

    var params="Delete=1"; // post with Delete already set so the page goes straight into the delete
    	    
    xmlhttp.open("POST","<?php echo RedirectURL("SelfRegisterDelete.php");?>?reg_id="+RegID,true);
    xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xmlhttp.setRequestHeader("Content-length", params.length);
    xmlhttp.setRequestHeader("Connection", "close");
    xmlhttp.RegID = RegID; // So we can see it when the request finishes
    
    xmlhttp.onreadystatechange=function() {
		if (this.readyState==4 && this.status==200) { // Hide them as the requests come back, deleting would mess up the outside loop
			 document.getElementById("RegisterRow"+this.RegID).style.display = 'none';
        }
    };
    xmlhttp.send(params);
}

function ManualMatch (RegID)
{
	var perID = document.getElementById("PersonID"+RegID).value;
	
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.uniqueid = RegID;

    var params="Delete=1";

    xmlhttp.open("POST","<?php echo RedirectURL("SelfRegisterMatch.php");?>?reg_id="+RegID+"&per_id="+perID,true);
    xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xmlhttp.setRequestHeader("Content-length", params.length);
    xmlhttp.setRequestHeader("Connection", "close");
    xmlhttp.RegID = RegID; // So we can see it when the request finishes
    
    xmlhttp.onreadystatechange=function() {
		if (this.readyState==4 && this.status==200) {
			 document.getElementById("MatchedTD"+this.RegID).innerHTML = 'Matched';
        }
    };
    xmlhttp.send(params);
}

</script>

<table id="SelfRegisterTable" cellpadding="4" align="center" cellspacing="0" width="100%">
	<tr class="TableHeader">
		<td align="center"><b><?php echo gettext("Name"); ?></b></td>
		<td align="center"><b><?php echo gettext("Username"); ?></b></td>
		<td align="center"><b><?php echo gettext("Email"); ?></b></td>
		<td align="center"><b><?php echo gettext("Confirmed"); ?></b></td>
		<td align="center"><b><?php echo gettext("Address"); ?></b></td>
		<td align="center"><b><?php echo gettext("Match"); ?></b></td>
		<td align="center"><b><?php echo gettext("Modified"); ?></b></td>
		<td><b><?php echo gettext("Delete"); ?></b></td>
	</tr>
<?php

//Loop through the registration records
while ($aRow = mysqli_fetch_array($rsSelfRegistrations)) {

	extract($aRow);

	//Display the row
?>
	<tr id="RegisterRow<?php echo $reg_id; ?>">
	<?php
		if ($reg_perid > 0) {
			echo "<td id=Name$reg_id><a href=PersonView.php?PersonID=$reg_perid>$reg_firstname $reg_lastname</a></td>";
		} else {
			echo "<td id=Name$reg_id>$reg_firstname $reg_lastname</td>";
		}
		?>
		<td><?php echo $reg_username;?></td>
		<td><a href="mailto:<?php echo $reg_email;?>"><?php echo $reg_email;?></a></td>		
		<td><?php if ($reg_confirmed==1) echo gettext ("Yes"); else echo gettext ("No");?></td>
		<td><?php echo $reg_address1 . ", ". $reg_city . ", ", $reg_state . " " . $reg_zip;?></td>
		
		<td id=MatchedTD<?php echo $reg_id;?>>
<?php 
	if ($reg_perid>0)
		echo gettext ("Matched"); 
	else {
		echo "<script language=\"javascript\" type=\"text/javascript\">\n";
		echo "$(document).ready(function() {\n";
		echo "	$(\"#PersonName$reg_id\").autocomplete({\n";
		echo "		source: \"AjaxFunctions.php?searchtype=person\",\n";
		echo "		minLength: 3,\n";
		echo "		select: function(event,ui) {\n";
		echo "			$('[name=PersonName$reg_id]').val(ui.item.value);\n";
//		echo "			$('[name=PersonID$reg_id]:eq(1)').val(ui.item.id);\n";
		echo "			$('[name=PersonID$reg_id]').val(ui.item.id);\n";
		echo "		}\n";
		echo "	});\n";
		echo "});\n";
		echo "</script>";
		echo "<input style='width:350px;' type=\"text\" id=\"PersonName$reg_id\" name=\"PersonName$reg_id\" value='$sPersonName' />\n";
		echo "<input type=\"hidden\" name=\"PersonID$reg_id\" id=\"PersonID$reg_id\" value='$iPerson'>\n";
		echo "<button onclick=\"ManualMatch($reg_id);\">".gettext("Set Match")."</button>";
	}
?>
		</td>
		
		<td><?php echo $reg_changedate;?></td>
		<td><button onclick="ConfirmDeleteRegistration(<?php echo $reg_id; ?>)"><?php echo gettext("Delete"); ?></button></td>
	</tr>
	<?php
}
?>
</table>

<?php
require "Include/Footer.php";
?>
