<?php

include_once "onedrive_classdef.php";

session_start();

$store=new MSOneDriveHelper();
$store->execute();

?>