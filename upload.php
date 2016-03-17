<?php
include "db.php";
include "drive_client.php";

loadOdbc();

$temp_dir='temp';
$img_dir='img';


function pdfExctractImg($pdf_name)
{
    $begin_tag='<</Subtype/Image/Length';
    $end_tag='\r\nendstream\rendobj';
    $file_length=0;
    $file_content='';
    $file_img_name='';
    
    $pdf_ext=".pdf";
    $pos=strpos($pdf_ext);

    if( $pos!=false && ($pos+strlen($pdf_ext))==strlen($pdf_name) )
    {
        $handle = fopen($temp_dir . '/' .  $pdf_name, "rb");
        if($handle!=false)
        {
            $chunk='';
            if(searchPattern($handle,$begin_tag))
            {
                $c='\0';
                do{
                    $c=readNbChar($handle,1);
                }while($c==' ');
                
                while(strlen($c)>0 && ctype_digit($c))
                {
                    $c=readNbChar($handle,1);
                    $file_length .= $c;
                }
                if(strlen($file_length)>0)
                {
                    $file_length=intval($file_length);
                }
                if(searchPattern($handle,'>>stream\r\n'))
                {
                    $file_content=readNbChars($handle,$file_length);
                    $file_img_name=$img_dir . '/' . substr( $pdf_name,0,$pos ) . time();
                    
                    $handle_out=fopen($file_img_name,'wb');
                    fwrite($handle_out,$file_content);
                    fclose($handle_out);
                }
                
            }
            fclose($handle);
        }
    }
    return $file_img_name;
}
function readNbChars($handle,$nb)
{
    $buffer='';
    $n=0;
    while(!feof($handle) && $n<$nb)
    {
        $buffer .= fread($handle, 1);
        $n++;
    }
    return $buffer;
}
function searchPattern($handle,$pattern)
{
    $buffer='';
    //index pattern string
    $ip=0;
    
    while(!feof($handle))
    {
        $c = fread($handle, 1);
        if($c==$pattern[$ip])
        {
            $ip++;
            if($ip>=strlen($pattern))
            {
                return true;
            }
        }
        else {
            $ip=0;
        }
    }
    return false;
}

function pdfGetImage($pdf_name)
{
    $pdf_ext=".pdf";
    $pos=strpos($pdf_name,$pdf_ext);
    $file_img_name='';
    
    //if( $pos!=false && ($pos+strlen($pdf_ext))==strlen($pdf_name) )
    {
        $img = new Imagik($temp_dir . '/' .$pdf_name);
        $im->setIteratorIndex(0);
        $im->setCompression(Imagick::COMPRESSION_JPEG);
        $im->setCompressionQuality(90);
        $file_img_name=$img_dir . '/' . substr( $pdf_name,0,$pos ) . time() . '.jpeg';
        $im->writeImage($file_img_name);
    } 
    return $file_img_name;
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

    if(!is_dir($temp_dir))
    {
        mkdir($temp_dir);
    }
    $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],$temp_dir.'/'.$_FILES['upfile']['name']);
    
    if(!is_dir($img_dir))
    {
        mkdir($img_dir);
    }
    
    $dbase = odbc_connectDatabase();
    if($dbase!=null)
    {
        echo 'database is connected<BR>';
        $title=$_POST['title'];$author=$_POST['author'];$pub=$_POST['pub'];$descr=$_POST['descr'];
        $size=$_FILES['upfile']['size'];$year=$_POST['year'];
        $hash=hash_file('md5',$temp_dir . '/' . $_FILES['upfile']['name']);
        $img=pdfGetImage($_FILES['upfile']['name']);
        if(strlen($img)==0)
        {
            $img=$img_dir . '/book250x250.png';
        }
        
        $res=$dbase->query("INSERT INTO BOOKS (TITLE,DESCR,AUTHORS,SIZE,YEAR,HASH,IMG_PATH) VALUES('$title','$descr','$author',$size,'$year','$hash','$img')");
        $dbase->close();
        if(!$res)
        {
            echo 'Book not inserted in DB: ' . mysql_error() . '<BR>';
            exit();
        }
    }
    else {
        # code...
        echo 'database is not connected<BR>';
    }
    
	//createDriveClient($_POST['store'],$_FILES['upfile']['name']);
    
   
}
if($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"),$request_body);
    strlen($request_body);
    foreach (getallheaders() as $name => $value) {
        echo "$name: $value\n";
    }
}
?>