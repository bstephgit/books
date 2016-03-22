<?php
include "db.php";
include "drive_client.php";

loadOdbc();

function temp_dir($subpath=null)
{
    $temp_dir='temp';
    if($subpath==null)
        return $temp_dir;
    return $temp_dir . '/' . $subpath;
}

function img_dir($subpath=null)
{
    $img_dir='img';
    if($subpath==null)
        return $img_dir;
    return $img_dir . '/' . $subpath;
}

if(!is_dir(temp_dir()))
{
    mkdir(temp_dir());
}
if(!is_dir(img_dir()))
{
    mkdir(img_dir());
}

function pdfGetImage($pdf_name)
{
    $pdf_ext=".pdf";
    $pos=strpos($pdf_name,$pdf_ext);
    $img_name='';
    
    if( $pos!=false && ($pos+strlen($pdf_ext))==strlen($pdf_name) )
    {
        $img_basename = basename($pdf_name,$pdf_ext);
        
        if(!is_dir(img_dir($img_basename)))
        {
            mkdir(img_dir($img_basename));
        }
        $hf = fopen(temp_dir($pdf_name),'rb');
        if($hf)
        {
            $count = 1;
            
            while(!feof($hf))
            {
                if(fread($hf,1)=='<')
                {
                    if(!feof($hf) && fread($hf,1)==='<')
                    {
                       $packet='';
                       while(!feof($hf) && ($c=fread($hf,1))!='>')
                       {
                           $packet .= $c;
                       }
                       $packet=str_replace('\n','',$packet);
                       $packet=str_replace('\r','',$packet);
                       $key='';
                       $headers=array();
                       foreach (explode('/',$packet) as $item) {
                           $pos = strpos($item,' ');
                           if($pos)
                           {
                               $item[$pos]='/';
                           }
                           foreach(explode('/',$item) as $item2)
                           {
                              if(strlen($key)==0)
                              {
                                  $key=$item2;
                              } 
                              else 
                              {
                                  $headers[$key]=$item2;
                                  $key='';
                              }
                           }
                       }
                       if(isset($headers['Subtype']) && $headers['Subtype']==='Image' && ($headers['Filter']==='DCTDecode' || $headers['Filter']==='JPXDecode')
                            && $headers['ColorSpace']==='DeviceRGB' && isset($headers[ImageName]))
                       {
                           $tag='stream';
                           $tgi=0;
                           
                           while($tgi<strlen($tag))
                           {
                               $tgi=1;
                               
                                while(!feof($hf) && fread($hf,1)!=$tag[0])
                                        ;
                                while(!feof($hf) && $tgi<strlen($tag) && ($c=fread($hf,1))===$tag[$tgi++])
                                        ;
                           }
                           if(!feof($hf)){ $c = fread($hf,1); }
                           while(!feof($hf) && (ord($c)===10 || ord($c)===13))
                           {
                               $c=fread($hf,1);
                           }
                           if(!feof($hf))
                           {
                                $len = intval($headers['Length']);
                                $img_content= $c . fread($hf,$len-1);
                                $img_name=img_dir($img_basename.'/'.$img_basename . '_' . $count++ . '.jpeg');
                                $hof = fopen($img_name,'wb');
                                fwrite($hof,$img_content,$len);
                                fclose($hof);
                           }
                       }
                       
                    }
                    
                }
            }
            fclose($hf);
        }
        
    } 
    return $img_name;
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

    $headers = getallheaders();
    $resultat=false;
    if(isset($headers['Content-Range']))
    {
        $range_start=0;
        $range_end=0;
        $file_size=0;
        $filename = $_FILES['upfile']['name'];
        $filepath=temp_dir($filename);
        
        sscanf($headers['Content-Range'],'bytes %d-%d/%d',$range_start,$range_end,$file_size);
        if($range_start==0 && is_file($filepath))
        {
            unlink($filepath);
        }
        $hfile = fopen($filepath,'ab');
        if($hfile)
        {
            fwrite($hfile,file_get_contents($_FILES['upfile']['tmp_name']));
            fclose($hfile);
        }
    }
    else 
    {
        $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],$temp_dir.'/'.$_FILES['upfile']['name']);
        $img=pdfGetImage($_FILES['upfile']['name']);
        
    }
}

if(isset($_POST['action']) && $_POST['action']==='file_insert')
{
    $dbase = odbc_connectDatabase();
    if($dbase!=null)
    {
        $title=$_POST['title'];$author=$_POST['author'];$pub=$_POST['pub'];$descr=$_POST['descr'];
        $size=$_POST['file_size'];$year=$_POST['year'];
        $hash=hash_file('md5',temp_dir($_POST['file_name']));
        $img=pdfGetImage($_POST['file_name']);
        if(strlen($img)==0)
        {
            $img=img_dir('book250x250.png');
        }
        
        $res=$dbase->query("INSERT INTO BOOKS (TITLE,DESCR,AUTHORS,SIZE,YEAR,HASH,IMG_PATH) VALUES('$title','$descr','$author',$size,'$year','$hash','$img')");
        if(!$res)
        {
            echo 'Book not inserted in DB: ' . mysql_error() . '<BR>';
            exit();
        }
        $id = $res;
        
        $res=$dbase->query('SELECT ID FROM IT_SUBJECT');
        while($res->next(true))
        {
            $subject=$res->field_value(0);
            $code=sprintf('topic%d',$subject);
            echo $code;
            if(isset($_POST[$code]) && $_POST[$code]==='on')
            {
                $dbase->query("INSERT INTO BOOKS_SUBJECTS_ASSOC (SUBJECT_ID,BOOK_ID) VALUES($subject,$id)");
            }
        }
        
        $dbase->close();
        
	    //createDriveClient($_POST['store'],$_FILES['upfile']['name']);
        header('Location: home.php?bookid='.$id);
        
    }
    else {
        # code...
        echo 'database is not connected<BR>';
    }
}
if(isset($_GET['action']) && $_GET['action']==='book_delete')
{
    $dbase = odbc_connectDatabase();
    
    $id=$_GET['bookid'];
    $sql_query="DELETE FROM BOOKS WHERE ID='$id'"; 
    $dbase->query($sql_query);
    $sql_query="DELETE FROM BOOKS_SUBJECTS_ASSOC WHERE ID='$id'";
    $dbase->query($sql_query);
    $sql_query="DELETE FROM BOOKS_LINKS WHERE ID='$id'"; 
    $dbase->query($sql_query);
    $dbase->close();
    header('Location: home.php');
}

if(isset($_POST['action']) && $_POST['action']==='book_update')
{
    $dbase = odbc_connectDatabase();
    
    $title=$_POST['title'];$author=$_POST['author'];$pub=$_POST['pub'];$descr=$_POST['descr'];
    $year=$_POST['year'];$id=$_POST['bookid'];
    
    $res=$dbase->query("UPDATE BOOKS SET TITLE='$title',DESCR='$descr',AUTHORS='$author',YEAR='$year' WHERE ID=$id");
    
    $sql_query="DELETE FROM BOOKS_SUBJECTS_ASSOC WHERE ID='$id'";
    
    $res=$dbase->query('SELECT ID FROM IT_SUBJECT');
    while($res->next(true))
    {
        $subject=$res->field_value(0);
        $code=sprintf('topic%d',$subject);
        echo $code;
        if(isset($_POST[$code]) && $_POST[$code]==='on')
        {
            $dbase->query("INSERT INTO BOOKS_SUBJECTS_ASSOC (SUBJECT_ID,BOOK_ID) VALUES($subject,$id)");
        }
    }
    $dbase->close();
    header('Location: home.php?bookid='.$id);
}
?>