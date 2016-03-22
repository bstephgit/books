<?php
include "db.php";

session_start();

loadOdbc();

function sizeUnit($input)
{
    $size=0;
    $units=array('bytes','Kb','Mb','Gb','Tb');
    $formats=array(0,0,1,1,0);
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
            return number_format($size,$formats[$i]) . ' ' . $units[$i];
        }
        $size = $next;
    }
    return number_format(($size*$pace),$formats[count($units)-1]) . ' ' . $units[count($units)-1];
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
                    echo '<td><input type="checkbox" name="topic' . $rec->field_value('ID') . '">' . $rec->field_value('NAME') . '<td>';
                    $cols++;
                }
                while($cols<5){ echo '<td></td>'; $cols++; }
                echo '</tr>';
            }while($rec->is_valid());
        }
        echo '</table>';
    }
}

function print_book($rec,$subjects)
{
    if($rec && $rec->next())
    {
        $title=$rec->field_value('TITLE');
        $year=$rec->field_value('YEAR');
        $descr=$rec->field_value('DESCR');
        $author=$rec->field_value('AUTHORS');
        $size=$rec->field_value('SIZE');
        $img_src=$rec->field_value('IMG_PATH');
        echo "<div class='book_record'><table class='book_record'>";
        echo '<tr>';
        printf("<td width='210px' rowspan='2'><img src='%s' class='book'></td>",$img_src);
        printf("<td colspan='2'><div class='book'><span class='book_title'>%s</span></div></td>",$title);
        echo '</tr>';
        echo '<tr>';
        echo '<td>';
        printf("<div class='book'>Auteur: <span>%s</span></div>",$author);
        printf("<div class='book'>Parution: <span>%s</span></div>",$year);
        printf("<div class='book'>File size: <span>%s</span></div>",sizeUnit($size));
        printf("<div class='book'><span class='book_descr'>%s</span></div>",$descr);
        echo '</td>';
        echo '<td><div class="book"><ul class="book_tags"><LH>Tags</LH>';
        while($subjects->next())
        {
            printf('<li>%s</li>',$subjects->field_value('NAME'));
        }
        echo "</ul></div></td>";
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
        <div class='navtitle'><h3>Menu</h3></div>
        <div class="nav_elements">
            <a class="nav_element" href="home.php">home</a><br>
            <?php if(isset($_GET['bookid'])) {
                printf( '<a class="nav_element" href="upload.php?action=book_delete&bookid=%s">delete book</a><br>',$_GET['bookid']);
                printf( '<a class="nav_element" href="home.php?edit=%s">edit book</a><br>',$_GET['bookid']);
            } ?>
            <a class="nav_element" href="home.php?upload=1">uploader</a><br>
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
    
</div>

<div class="main">
    <div class="banner"><h1 class="title">BOOK STORE</h1></div>

        <div class='internal'>
        <div class="nav_elements" style="border-radius: 15px">
            <?php  if (isset($_GET['upload']) || isset($_GET['edit'])){   
                
                $upload = isset($_GET['upload']) && $_GET['upload']==='1';
                $edit = isset($_GET['edit']);
                $base=odbc_connectDatabase();
                
                if($edit)
                {
                    $id=$_GET['edit'];
                    $sql="SELECT * FROM BOOKS WHERE ID=$id";
                    $rec_book=$base->query($sql);
                    $sql="SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$id";
                    $rec_subjects=$base->query($sql);
                    $book=array();
                    $subjects=array();
                    if($rec_book->next())
                    {
                        $book['ID']=$rec_book->field_value('ID');
                        $book['TITLE']=$rec_book->field_value('TITLE');
                        $book['YEAR']=$rec_book->field_value('YEAR');
                        $book['AUTHORS']=$rec_book->field_value('AUTHORS');
                        $book['DESCR']=$rec_book->field_value('DESCR');
                    }
                    while($rec_subjects->next())
                    {
                        $subjects[ sprintf('topic%s',$rec_subjects->field_value('SUBJECT_ID')) ]=1;
                    }
                }
            ?>
            <form method="POST" action="upload.php" id="form_upload">
                <table class="nav_element" cellpadding="15">
                    <?php if ($upload) { ?>
                    <tr><td><input type="file" id="file_upload"></td><td colspan="2"><div class='upload-out'><div class='upload-in' id='upl-in1'><div></div></td></tr>
                    <?php } ?>
                    <tr><td>Titre<br> <input type="text" name="title"> </td>
                    <td>Auteur<br> <input type="text" name="author"> </td>
                    <td>Ann&eacute;e de parution<br> <input type="text" name="year"></td></tr> 
                    <tr><td colspan="3">Description<br><textarea rows="5" cols="90" name="descr"></textarea></td></tr>
                </table>
                <?php if($upload){ ?>
                <input type='hidden' name='file_name' id='fname'>
                <input type='hidden' name='file_size' id='fsize'>
                <input type='hidden' name='action' value='file_insert'>
                <?php } if($edit){ ?>
                    <input type='hidden' name='action' value='book_update'>
                    <input type='hidden' name='bookid' value=''>
                <?php } if($upload){ ?>
                <div class="nav_elements">
                        <p>Store<br>
                        <input type="radio" name="store" value="GOOG" checked> Google drive<br>
                        <input type="radio" name="store" value="MSFT"> MS OneDrive<br>
                        <input type="radio" name="store" value="AMZN"> Amazon<br>
                        <input type="radio" name="store" value="BOX"> Box.com<br>
                        <input type="radio" name="store" value="PCLD"> pCloud  </p>
                    </div>
                <?php } ?>
                <div class="nav_elements">
                    <?php  if($base){ print_subjects_tab($base); $base->close();}?>
                </div>
                <p><input type="submit" value='uploader' id='submit_btn'></p>
            </form>
            <?php
                if($edit)
                {
                    $script=sprintf(
                            "var upload_form = document.getElementById('form_upload');
                            upload_form.title.value='%s';
                            upload_form.author.value='%s';
                            upload_form.year.value='%s';
                            upload_form.descr.value='%s';
                            upload_form.bookid.value='%s';
                            upload_form.submit_btn.value='update';", 
                            $book['TITLE'], $book['AUTHORS'], $book['YEAR'], $book['DESCR'], $book['ID']);
                    
                    echo '<script type="text/javascript">';
                        echo $script;
                        foreach ($subjects as $key => $value) {
                            printf('upload_form.%s.checked=true;',$key);
                        }
                    echo '</script>';
                }
                $base->close();
             }  
            ?>
            
            <?php  
                    if (isset($_GET['bookid']))
                    { 
                        $id=$_GET['bookid'];
                        $dbase = odbc_connectDatabase();
                        if($dbase->is_connected())
                        {
                            $query_str="SELECT TITLE,YEAR,DESCR,AUTHORS,SIZE,IMG_PATH FROM BOOKS WHERE ID=$id";
                            $rec=$dbase->query($query_str);
                            $subjects=$dbase->query('SELECT NAME FROM IT_SUBJECT WHERE ID IN (SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID='.$id.')');
                            print_book($rec,$subjects);
                            $dbase->close();
                        }

                    } 
             ?>
             <?php 
                if(count($_GET)==0)
                {
                   $dbase = odbc_connectDatabase();
                   $sql='SELECT ID,TITLE,IMG_PATH FROM BOOKS';
                   $rec=$dbase->query($sql);
                   if($rec)
                   {
                       echo '<div class="nav_elements">';
                       while($rec->next())
                       {
                            printf('<table class="book_list"><tr><td><img src="%s" class="book"></td></tr><tr><td><a href="home.php?bookid=%s">%s</a></td></tr></table>',
                            $rec->field_value('IMG_PATH'),$rec->field_value('ID'),$rec->field_value('TITLE'));
                       }
                       echo '</div>';
                   }
                   $dbase->close();
                    
                }
             ?>
            
        </div>
    </div>
</div>
</div>
</body>
</html>
