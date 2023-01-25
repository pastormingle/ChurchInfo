<?php
/*******************************************************************************
*
*  filename    : Reports/FundRaiserReport.php
*  last change : 2016-03-15
*  description : Creates a PDF report about the auction
*  copyright   : Copyright 2016 Michael Wilt
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

//Get the paddlenum records for this fundraiser
if ($iPaddleNumID > 0) {
	$selectOneCrit = " AND pn_ID=" . $iPaddleNumID . " ";
} else {
	$selectOneCrit = "";
}

$sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                a.per_FirstName as paddleFirstName, a.per_LastName as paddleLastName, a.per_Email as paddleEmail,
				b.fam_ID, b.fam_Name, b.fam_Address1, b.fam_Address2, b.fam_City, b.fam_State, b.fam_Zip, b.fam_Country                
         FROM paddlenum_pn
         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
         LEFT JOIN family_fam b ON fam_ID = a.per_fam_ID 
         WHERE pn_FR_ID =" . $iFundRaiserID . $selectOneCrit . " ORDER BY pn_Num"; 
$rsPaddleNums = RunQuery($sSQL);

class PDF_FundRaiserReport extends ChurchInfoReport {
	// Private properties
	var $_Margin_Left = 0;         // Left Margin
	var $_Margin_Top  = 0;         // Top margin 
	var $_Char_Size   = 12;        // Character size
	var $_CurLine     = 0;
	var $_Column      = 0;
	var $_Font        = 'arial';
	
	// Sets the character size
	// This changes the line height too
	function Set_Char_Size($pt) {
		if ($pt > 3) {
			$this->_Char_Size = $pt;
			$this->SetFont($this->_Font,'',$this->_Char_Size);
		}
	}
	
	// Constructor
	function PDF_FundRaiserReport() {
		parent::__construct('P', 'mm', $this->paperFormat);
		$this->_Column      = 0;
		$this->_CurLine     = 2;
		$this->_Font        = 'arial';
		$this->SetMargins(0,0);

		$this->Set_Char_Size(12);
		$this->AddPage();
		$this->SetAutoPageBreak(false);

		$this->_Margin_Left = 12;
		$this->_Margin_Top  = 12;

		$this->Set_Char_Size(20);
		$this->Write (10, 'Fund Raiser Report');
		$this->Set_Char_Size(12);
	}

	function CellWithWrap ($curY, $curNewY, $ItemWid, $tableCellY, $txt, $bdr, $aligncode) {
		$curPage = $this->PageNo();
		$leftX = $this->GetX ();
		$this->SetXY ($leftX, $curY);
		$this->MultiCell ($ItemWid, $tableCellY, $txt, $bdr, $aligncode);
		$newY = $this->GetY ();
		$newPage = $this->PageNo ();
		$this->SetXY ($leftX+$ItemWid, $curY);
		if ($newPage > $curPage)
			return $newY;
		else
			return (max ($newY, $curNewY));
	}
}

// Read in report settings from database
$rsConfig = mysqli_query($cnChurchInfo, "SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_section='ChurchInfoReport'");
if ($rsConfig) {
	while (list($cfg_name, $cfg_value) = mysqli_fetch_row($rsConfig)) {
		$pdf->$cfg_name = $cfg_value;
	}
}

$totalAuctionItems = 0.0;
$totalSellToAll = array();

// Loop through result array
while ($row = mysqli_fetch_array($rsPaddleNums)) {
	extract ($row);

	// Get individual auction items first
	$sSQL = "SELECT di_item, di_title, di_donor_id, di_sellprice,
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName,
	                a.per_Email as donorEmail,
	                b.fam_homePhone as donorPhone
	                FROM donateditem_di LEFT JOIN person_per a on a.per_ID = di_donor_id
	                                    LEFT JOIN family_fam b on a.per_fam_id=b.fam_id
	                WHERE di_FR_ID = ".$iFundRaiserID." AND di_buyer_id = " . $pn_per_ID;
	$rsPurchasedItems = RunQuery($sSQL);
	
	while ($itemRow = mysqli_fetch_array($rsPurchasedItems)) {
		extract ($itemRow);
		$totalAuctionItems += $di_sellprice;
	}
	
	// Get multibuy items for this buyer
	$sqlMultiBuy = "SELECT mb_count, mb_item_ID, 
	                a.per_FirstName as donorFirstName,
	                a.per_LastName as donorLastName,
	                a.per_Email as donorEmail,
	                c.fam_HomePhone as donorPhone,
					b.di_item, b.di_title, b.di_donor_id, b.di_sellprice
					FROM multibuy_mb
					LEFT JOIN donateditem_di b ON mb_item_ID=b.di_ID
					LEFT JOIN person_per a ON b.di_donor_id=a.per_ID 
					LEFT JOIN family_fam c ON a.per_fam_id = c.fam_ID
					WHERE b.di_FR_ID=".$iFundRaiserID." AND mb_per_ID=" . $pn_per_ID;
	$rsMultiBuy = RunQuery($sqlMultiBuy);
	while ($mbRow = mysqli_fetch_array($rsMultiBuy)) {
		extract ($mbRow);
		$totalSellToAll[$di_title] += $mb_count * $di_sellprice;
	}
}
// Instantiate the directory class and build the report.
$pdf = new PDF_FundRaiserReport();

$pdf->leftX = $pdf->lMargin;
$curY = $pdf->tMargin + 25;

$pdf->WriteAt ($pdf->leftX, $curY, "Fund Raiser: $iFundRaiserID");
$curY += 10;

$pdf->WriteAt ($pdf->leftX, $curY, "Total item sales: $totalAuctionItems");
$curY += 10;

$grandTotal = $totalAuctionItems;
foreach ($totalSellToAll as $name => $value) {
	$pdf->WriteAt ($pdf->leftX, $curY, "$name: $value");
	$curY += 10;
	$grandTotal += $value;	
}
$pdf->WriteAt ($pdf->leftX, $curY, "Total money raised: $grandTotal");

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
$pdf->Output("FundRaiserReport" . date("Ymd") . ".pdf", "D");
?>
