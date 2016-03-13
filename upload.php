<?php
include "db.php";
include "drive_client.php";

if(isset($_SESSION['ODBC']))
{
    $odbc=new ODBC();
    if($odbc->load('odbc.xml'))
    {
        $_SESSION['ODBC']=$odbc;
    }
}
if(isset($_FILES) && isset($_FILES['upfile']))
{

    //$_FILES['upfile']['name']     //Le nom original du fichier, comme sur le disque du visiteur (exemple : mon_icone.png).
    //$_FILES['upfile']['type']     //Le type du fichier. Par exemple, cela peut être « image/png ».
    //$_FILES['upfile']['size']     //La taille du fichier en octets.
    //$_FILES['upfile']['tmp_name'] //L'adresse vers le fichier uploadé dans le répertoire temporaire.
    //$_FILES['upfile']['error']    //Le code d'erreur, qui permet de savoir si le fichier a bien été uploadé.

    if ($_FILES['upfile']['error'] > 0)
    {
         header('Location: home.php?erreur='.$_FILES['upfile']['error']);
         exit();
    }

    $temp_dir='temp';
    if(!is_dir($temp_dir))
    {
        mkdir($temp_dir);
    }
    $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],$temp_dir.'/'.$_FILES['upfile']['name']);
    
    if(isset($_SESSION['ODBC']))
    {
        $dbase = $_SESSION['ODBC']->connect();
        if($dbase!=null)
        {
            $title=$_POST['title'];$author=$_POST['author'];$pub=$_POST['pub'];$descr=$_POST['descr'];
            $size=$_FILES['upfile']['size'];$year=$_POST['year'];
            $dbase->query("INSERT INTO BOOKS (TITLE,PUB,DESCR,AUTHORS,SIZE) VALUES('$title','$pub','$descr','$author',$size,$year)");
        }
    }
    
	createDriveClient($_POST['store'],$_FILES['upfile']['name']);
}
?>