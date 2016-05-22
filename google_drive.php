<?php

include_once "google_classdef.php";

session_start();

 $gg_upload_helper = new GoogleDriveHelper();
 $gg_upload_helper->execute();


?>