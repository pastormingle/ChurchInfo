<?php
session_start();

$noBannerArg = "";
if (array_key_exists ("NoBanner", $_SESSION))
	$noBannerArg = "?NoBanner=1";

session_destroy();
header('Location: SelfRegisterHome.php'.$noBannerArg);
?>
