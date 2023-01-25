<?php

include "Include/Config.php";
require "Include/Functions.php";
require "Include/VancoConfig.php";

if (array_key_exists ("FromDate", $_GET))
	$dFromDate = FilterInput($_GET["FromDate"], 'date');
else {
	// default behavior go back 2 months
	$d = new DateTime();
	$d->sub(new DateInterval('P2M'));
	$dFromDate = $d->format('Y-m-d');
}

if (array_key_exists ("ToDate", $_GET))
	$dToDate = FilterInput($_GET["ToDate"], 'date');
else
	$dToDate = date("Y-m-d");
	
function sendVancoXML ($xmlstr)
{
	//--- Open Connection ---
	$socket = fsockopen("ssl://myvanco.vancopayments.com",
	                 443, $errno, $errstr, 15);
//print ("Connected to ssl://myvanco.vancopayments.com on port 443, got socket $socket\n");

	if (!$socket) {
	
	    echo 'Fail<br>';
	    $Result['errno']=$errno;
	    $Result['errstr']=$errstr;
	    
	    printf ("Failed to open socket connection to Vanco, Error number $errno, Error description $errstr<br>");
	    
	    exit ();
	}
		
    //--- Create Header ---
    $ReqHeader  = "POST /cgi-bin/ws2.vps HTTP/1.1\r\n";
    $ReqHeader .= "Host: " . "myvanco.vancopayments.com" . "\r\n";
    $ReqHeader .= "User-Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\r\n";
    $ReqHeader .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $ReqHeader .= "Connection: close\r\n";
    $ReqHeader .= "Content-length: " . strlen($xmlstr) . "\r\n";
    $ReqHeader .= $xmlstr . "\r\n\r\n";

//print ("---------------- Sending this mesaage -------------\n");
//print ($ReqHeader);
//print ("---------------- End of the message ---------------\n");

    // --- Send XML ---
    fwrite($socket, $ReqHeader);

//print ("After calling fwrite to send the XML\n");
//sleep (1);
    // --- Retrieve XML ---
    $_return = "";
    while (!feof($socket)) {
        $_return .= fgets($socket, 4096);
    }

    fclose($socket);
    
//print ("---------------- Got this response -------------\n");
//    print ($_return);
//print ("---------------- End of response ---------------\n");
    
	$pos = strpos($_return, "<?xml");
	$xmlPart = substr ($_return, $pos, strlen ($_return)-$pos);

//print ("---------------- Extracted XML -------------\n");
//print ($xmlPart);
//print ("---------------- End of response ---------------\n");
	
	$xml=simplexml_load_string($xmlPart);
	return $xml;
}

$requestTime = date ("Y-m-d h:m:s");
//2008-11-24 12:27:52
$ReqBody="
<VancoWS>
	<Auth>
		<RequestType>Login</RequestType>
		<RequestID>123456</RequestID>
		<RequestTime>$requestTime</RequestTime>
		<Version>2</Version>
	</Auth>
	<Request>
		<RequestVars>
			<UserID>$VancoUserid</UserID>
			<Password>$VancoPassword</Password>
		</RequestVars>
	</Request>
</VancoWS>";

$regxml = sendVancoXML ($ReqBody);
$sessionID = (string) $regxml->Response->SessionID;
$requestTime = date ("Y-m-d h:m:s");

$ReqBody="
<VancoWS>
	<Auth>
		<RequestType>EFTTransactionFundHistory</RequestType>
		<RequestID>12345</RequestID>
		<RequestTime>$requestTime</RequestTime>
		<SessionID>$sessionID</SessionID>
		<Version>2</Version>
	</Auth>
	<Request>
		<RequestVars>
			<ClientID>$VancoClientid</ClientID>
			<FromDate>$dFromDate</FromDate>
			<ToDate>$dToDate</ToDate>
		</RequestVars>
	</Request>
</VancoWS>";

$sSQL = "SELECT `dep_ID` as CCDepId FROM  `deposit_dep` WHERE dep_type='SelfCreditCard' ORDER BY dep_date DESC LIMIT 1";
$rsCCDepInfo = RunQueryI($sSQL);
extract($rsCCDepInfo->fetch_array(MYSQLI_ASSOC));

$sSQL = "SELECT `dep_ID` as BDDepId FROM  `deposit_dep` WHERE dep_type='SelfBankDraft' ORDER BY dep_date DESC LIMIT 1";
$rsCCDepInfo = RunQueryI($sSQL);
extract($rsCCDepInfo->fetch_array(MYSQLI_ASSOC));

$transactionsxml = sendVancoXML ($ReqBody);

$cnt = (int) $transactionsxml->Response->TransactionCount;
$translist = $transactionsxml->Response->Transactions->children();

foreach ($translist as $onetrans) {
	$fam_Name = "";
	$plg_date = "";
	$plg_aut_Cleared = "";
	$plg_depID = 0;
	$dep_Date = "";
	$dep_Comment = "";
	$plg_plgID = 0;
	$plg_method = "";
	
	$sSQL = "SELECT * FROM pledge_plg JOIN family_fam ON plg_FamID=fam_id LEFT JOIN deposit_dep on plg_depID=dep_ID WHERE DATE_ADD(plg_date, INTERVAL 2 DAY)>=\"".$onetrans->ProcessDate."\" AND plg_date<=\"".$onetrans->ProcessDate."\" AND plg_PledgeOrPayment=\"Payment\" AND plg_aut_ID=". $onetrans->CustomerID .  " AND plg_Amount=". $onetrans->Amount;
	$rsDBInfo = RunQueryI($sSQL);
	if ($rsDBInfo->num_rows == 0)
		continue; // probably a failed transaction that got deleted from the database
	extract($rsDBInfo->fetch_array(MYSQLI_ASSOC));

	if ($onetrans->DepositDate != "" && $plg_plgID > 0 && $plg_depID == 0) {
		// this transaction has a record in pledge_plg and it cleared but it does not have a deposit slip.  
		// assign it to the latest deposit slip for internet donations
		if ($plg_method == 'CREDITCARD')
			$plg_depID = $CCDepId;
		else if ($plg_method == 'BANKDRAFT')	
			$plg_depID = $BDDepId;
		$sSQL = "UPDATE pledge_plg SET plg_depID=$plg_depID WHERE plg_plgID=$plg_plgID";		
		$rsUpdate = RunQueryI($sSQL);
	}
	
	if ($onetrans->DepositDate != "" && $plg_DateCleared != $onetrans->DepositDate) {
		$sSQL = "UPDATE pledge_plg SET plg_DateCleared='".$onetrans->DepositDate."', plg_TransactionFee='".$onetrans->TransactionFee."', plg_TransactionRef='".$onetrans->TransactionRef."' WHERE plg_PlgID=$plg_plgID";		
		$rsUpdate = RunQueryI($sSQL);
	}
}

$sSQL = "SELECT plg_plgID, plg_depID, plg_DateCleared, plg_amount, plg_TransactionFee, dep_Comment, dep_Type, b.fun_Name AS fundName
         FROM pledge_plg 
         JOIN deposit_dep ON plg_depID = dep_ID
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
         WHERE plg_date>='$dFromDate' AND plg_date<='$dToDate' AND plg_PledgeOrPayment=\"Payment\" AND (plg_Method='CREDITCARD' OR plg_Method='BANKDRAFT') 
         ORDER BY plg_depID, plg_DateCleared, plg_fundID";
$rsTransToReport = RunQueryI($sSQL);

$thisDeposit = 0;

$thisClearDate = "";
$thisClearTotalAmount = array();
$thisClearTotalFee = 0;

//Set the page title
$sPageTitle = gettext("Reconcile Credit Card and Bank Draft Deposits ") . $dFromDate . gettext(" To ") . $dToDate;
require "Include/Header.php";

while ($aRow = $rsTransToReport->fetch_array(MYSQLI_ASSOC))
{
	extract($aRow);

	if ($thisDeposit != $plg_depID) {
		// need to wrap up the previous deposit slip and start a new one
		if ($thisDeposit > 0) {
			// wrapping up the previous deposit
			printf ("<p style=\"margin-left: 40px\">Total amount donated ");
			foreach ($thisClearTotalAmount as $key => $value)
			    printf ("(Fund: $key $value) ");
			printf ("</p>");
			printf ("<p style=\"margin-left: 40px\">Total of fees $thisClearTotalFee</p>");
		}

		// starting a new deposit
		printf ("<p class=\"MediumLargeText\">$dep_Type $plg_depID $dep_Comment</p>");
		printf ("<p style=\"margin-left: 20px\">Clearing date $plg_DateCleared</p>");
		$thisDeposit = $plg_depID;
		$thisClearDate = $plg_DateCleared;
		$thisClearTotalAmount = array();
		$thisClearTotalAmount[$fundName] = $plg_amount;
		$thisClearTotalFee = $plg_TransactionFee;
	} else {
		// continuing with the same deposit
		if ($thisClearDate != $plg_DateCleared) {
			// need to wrap up the previous clear date and start a new one
			if ($thisClearDate != "") {
				// wrapping up the previous clear date
				printf ("<p style=\"margin-left: 40px\">Total amount donated");
				foreach ($thisClearTotalAmount as $key => $value)
			    	printf ("(Fund: $key $value) ");
				printf ("</p>");
				printf ("<p style=\"margin-left: 40px\">Total of fees $thisClearTotalFee</p>");
			}
			// starting a new clear date
			$thisClearDate = $plg_DateCleared;
			$thisClearTotalAmount = array();
			$thisClearTotalAmount[$fundName] = $plg_amount;
			$thisClearTotalFee = $plg_TransactionFee;
			printf ("<p style=\"margin-left: 20px\">Clearing date $thisClearDate</p>");
		} else {
			// continuing with the same clear date
			$thisClearTotalAmount[$fundName] += $plg_amount;
			$thisClearTotalFee += $plg_TransactionFee;
		}
	}	
}
printf ("<p style=\"margin-left: 40px\">Total amount donated");
foreach ($thisClearTotalAmount as $key => $value)
    printf ("(Fund: $key $value) ");
printf ("</p>");
printf ("<p style=\"margin-left: 40px\">Total of fees $thisClearTotalFee</p>");

require "Include/Footer.php";
?>
