<?php
include "db.php";

$base = new DB();
$base->connect("localhost","590_albertdupre","Isa150771?","590_albertdupre");

function print_subjects_tab($base)
{
    if($base->is_connected())
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

?>
<html>
<head>
    
    <link type="text/css" rel="stylesheet" href="books.css"/>
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
                if($base->is_connected())
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
    <?php  if (isset($_GET) && isset($_GET['upload'])){   ?>
        <div class='internal'>
        <div class="nav_elements">
            <form method="POST" action="upload.php" enctype="multipart/form-data">
                <table class="nav_element" cellpadding="15">
                    <tr><td colspan="3"><input type="file" name="upfile"></td></tr>
                    <tr><td>Titre<br> <input type="text" name="title"> </td>
                    <td>Auteur<br> <input type="text" name="author"> </td>
                    <td>Ann&eacute;e de parution<br> <input type="text" name="pub"></td></tr> 
                    <tr><td colspan="3">Description<br><textarea rows="5" cols="90" name="descr"></textarea></td></tr>
                </table>           
                <div class="nav_elements">
                        <p>Store<br>
                        <input type="radio" name="store" value="goog" checked> Google drive<br>
                        <input type="radio" name="store" value="msft"> MS OneDrive<br>
                        <input type="radio" name="store" value="amzn"> Amazon<br>
                        <input type="radio" name="store" value="box"> Box.com<br>
                        <input type="radio" name="store" value="pcld"> pCloud  </p>
                    </div>
                <div class="nav_elements">
                    <?php print_subjects_tab($base); ?>
                </div>
                <p><input type="submit" value='uploader'></p>
            </form>
        </div>
    </div>
    <?php } ?>
</div>
</div>
</body>
</html>
