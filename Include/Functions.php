<?php
/*******************************************************************************
*
*  filename    : /Include/Functions.php
*  website     : http://www.churchdb.org
*  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
*                Copyright 2004-1012 Michael Wilt
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
// Initialization common to all ChurchInfo scripts

require "UtilityFunctions.php";

//
// Basic security checks:
//

if (empty($bSuppressSessionTests))  // This is used for the login page only.
{
    // Basic security: If the UserID isn't set (no session), redirect to the login page
    if (!isset($_SESSION['iUserID']))
    {
        Redirect("Default.php");
        exit;
    }

    // Basic security: If $sRootPath has changed we have changed databases without logging in 
    // redirect to the login page 
    if ($_SESSION['sRootPath'] !== $sRootPath )
    {
        Redirect("Default.php");
        exit;
    }

    // Check for login timeout.  If login has expired, redirect to login page
    if ($sSessionTimeout > 0)
    {
        if ((time() - $_SESSION['tLastOperation']) > $sSessionTimeout)
        {
            Redirect("Default.php?timeout");
            exit;
        }
        else {
            $_SESSION['tLastOperation'] = time();
        }
    }

    // If this user needs to change password, send to that page
    if ($_SESSION['bNeedPasswordChange'] && !isset($bNoPasswordRedirect))
    {
        Redirect("UserPasswordChange.php?PersonID=" . $_SESSION['iUserID']);
        exit;
    }

    // Check if https is required

    // Note: PHP has limited ability to access the address bar 
    // url.  PHP depends on Apache or other web server
    // to provide this information.  The web server
    // may or may not be configured to pass the address bar url
    // to PHP.  As a workaround this security check is now performed
    // by the browser using javascript.  The browser always has 
    // access to the address bar url.  Search for basic security checks
    // in Include/Header-functions.php

}
// End of basic security checks

// Are they adding an entire group to the cart?
if (isset($_GET["AddGroupToPeopleCart"])) {
    AddGroupToPeopleCart(FilterInput($_GET["AddGroupToPeopleCart"],'int'));
    $sGlobalMessage = gettext("Group successfully added to the Cart.");
}

// Are they removing an entire group from the Cart?
if (isset($_GET["RemoveGroupFromPeopleCart"])) {
    RemoveGroupFromPeopleCart(FilterInput($_GET["RemoveGroupFromPeopleCart"],'int'));
    $sGlobalMessage = gettext("Group successfully removed from the Cart.");
}

// Are they adding a person to the Cart?
if (isset($_GET["AddToPeopleCart"])) {
    AddToPeopleCart(FilterInput($_GET["AddToPeopleCart"],'int'));
    $sGlobalMessage = gettext("Selected record successfully added to the Cart.");
}

// Are they removing a person from the Cart?
if (isset($_GET["RemoveFromPeopleCart"])) {
    RemoveFromPeopleCart(FilterInput($_GET["RemoveFromPeopleCart"],'int'));
    $sGlobalMessage = gettext("Selected record successfully removed from the Cart.");
}

// Are they emptying their cart?
if (isset($_GET["Action"]) && ($_GET["Action"] == "EmptyCart")) {
    $_SESSION['aPeopleCart'] = array ();
    $sGlobalMessage = gettext("Your cart has been successfully emptied.");
}

if (isset($_POST["BulkAddToCart"])) {

    $aItemsToProcess = explode(",",$_POST["BulkAddToCart"]);

    if (isset($_POST["AndToCartSubmit"]))
    {
        if (isset($_SESSION['aPeopleCart']))
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'],$aItemsToProcess);
    }
    elseif (isset($_POST["NotToCartSubmit"]))
    {
        if (isset($_SESSION['aPeopleCart']))
            $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'],$aItemsToProcess);
    }
    else
    {
        for ($iCount = 0; $iCount < count($aItemsToProcess); $iCount++) {
            AddToPeopleCart(str_replace(",","",$aItemsToProcess[$iCount]));
        }
        $sGlobalMessage = $iCount . " " . gettext("item(s) added to the Cart.");
    }
}
?>
