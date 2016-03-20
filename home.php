<?php
include "db.php";

session_start();

loadOdbc();

function sizeUnit($input)
{
    $size=0;
    $units=array('bytes','Kb','Mb','Gb','Tb');
    if(is_string($input))
    {
        $size=intval($input);
    }
    if(is_int($size))
    {
        $size=$input;
    }
    
    $pace=1024;

    for($i=0; i < count($units); $i++)
    {
        $next =  $size/$pace;
        if(intval($next)==0)
        {
            return number_format($size,1) . ' ' . $units[$i];
        }
        $size = $next;
    }
    return number_format(($size*$pace),1) . ' ' . $units[count($units)-1];
}
function print_subjects_tab($base)
{
    if($base && $base->is_connected())
    {
        $rec=$base->query('SELECT ID,NAME FROM IT_SUBJECT');
        echo '<table class="nav_element">';
        if($rec)
        {
            do {
                $cols=0;
                echo '<tr>';
                while($rec->next() && $cols<5){
                    echo '<td><input type="checkbox" name="' . $rec->field_value('ID') . '">' . $rec->field_value('NAME') . '<td>';
                    $cols++;
                }
                while($cols<5){ echo '<td></td>'; $cols++; }
                echo '</tr>';
            }while($rec->is_valid());
        }
        echo '</table>';
    }
}

function print_book($rec)
{
    if($rec && $rec->next())
    {
        $title=$rec->field_value('TITLE');
        $year=$rec->field_value('YEAR');
        $descr=$rec->field_value('DESCR');
        $author=$rec->field_value('AUTHORS');
        $size=$rec->field_value('SIZE');
        $img_src=$rec->field_value('IMG_PATH');
        echo "<div class='book_record'><table class='book_record'><tr>";
        printf("<td width='210px'><img src='%s' class='book'></td>",$img_src);
        echo '<td>';
        printf("<div class='book'><span class='book_title'>%s</span></div>",$title);
        printf("<div class='book'>Auteur: <span>%s</span></div>",$author);
        printf("<div class='book'>Parution: <span>%s</span></div>",$year);
        printf("<div class='book'>File size: <span>%s</span></div>",sizeUnit($size));
        printf("<div class='book'><span class='book_descr'>%s</span></div>",$descr);
        echo '</td>';
        echo "</tr></table></div>";
    }
}
?>
<html>
<head>
    
    <link type="text/css" rel="stylesheet" href="books.css"/>
    <script type='text/javascript' src='script.js'></script>
</head>
<body>
<div class="container">

<div class="nav">
    <div class="banner"></div>
    <div class='internal'>
        <div class='navtitle'><h3>Home</h3></div>
        <div class="nav_elements">
            <a class="nav_element" href="home.php">click here</a>
        </div>
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Rechercher</h3></div>
        <div class="nav_elements">
            <form method="POST" action="">
                <p><input type='text'></p>  
                <p><input type='submit' value='rechercher'></p>
            </form>
        </div>
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Sujets</h3></div>
        <div class="nav_elements">
            <div>
            <?php 
                $base=odbc_connectDatabase();
                if($base && $base->is_connected())
                {
                    $rec = $base->query('SELECT ID,NAME FROM IT_SUBJECT');
                    if($rec)
                    {
                        $sep="";
                        while($rec->next()){
                            echo $sep . '<a class="nav_element" href="home.php?subject=' . $rec->field_value('ID') . '">' .  $rec->field_value('NAME') . "</a>";
                            $sep=", ";
                        }
                    }
                    else {
                        echo mysql_error();
                    }
                    $base->close();
                }
            ?>
            </div>
        </div>
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Uploader un fichier</h3></div>
        <div class="nav_elements">
                <p><button onclick="window.location='home.php?upload=1'">uploader un fichier</button></p>                
        </div>
    </div>
</div>

<div class="main">
    <div class="banner"><h1 class="title">BOOK STORE</h1></div>

        <div class='internal'>
        <div class="nav_elements" style="border-radius: 15px">
            <?php  if (isset($_GET) && isset($_GET['upload'])){   ?>
            <form method="POST" action="upload.php" id="form_upload">
                <table class="nav_element" cellpadding="15">
                    <tr><td><input type="file" id="file_upload"></td><td colspan="2"><div class='upload-out'><div class='upload-in' id='upl-in1'><div></div></td></tr>
                    <tr><td>Titre<br> <input type="text" name="title"> </td>
                    <td>Auteur<br> <input type="text" name="author"> </td>
                    <td>Ann&eacute;e de parution<br> <input type="text" name="year"></td></tr> 
                    <tr><td colspan="3">Description<br><textarea rows="5" cols="90" name="descr"></textarea></td></tr>
                </table>
                <input type='hidden' name='file_name' id='fname'>
                <input type='hidden' name='file_size' id='fsize'>
                <input type='hidden' name='file_action' value='file_insert'>
                <div class="nav_elements">
                        <p>Store<br>
                        <input type="radio" name="store" value="GOOG" checked> Google drive<br>
                        <input type="radio" name="store" value="MSFT"> MS OneDrive<br>
                        <input type="radio" name="store" value="AMZN"> Amazon<br>
                        <input type="radio" name="store" value="BOX"> Box.com<br>
                        <input type="radio" name="store" value="PCLD"> pCloud  </p>
                    </div>
                <div class="nav_elements">
                    <?php  $base=odbc_connectDatabase(); if($base){ print_subjects_tab($base); $base->close();}?>
                </div>
                <p><input type="submit" value='uploader' id='submit_btn'></p>
            </form>
            <?php } ?>
            
            <?php  if (isset($_GET) && isset($_GET['bookid'])){ 
                    $id=$_GET['bookid'];
                    $dbase = odbc_connectDatabase();
                    if($dbase->is_connected())
                    {
                        $query_str="SELECT TITLE,YEAR,DESCR,AUTHORS,SIZE,IMG_PATH FROM BOOKS WHERE ID=$id";
                        $rec=$dbase->query($query_str);
                        print_book($rec);
                        $dbase->close();
                    }

            ?>
                
            <?php } ?>
            
        </div>
    </div>
</div>
</div>
</body>
</html>
