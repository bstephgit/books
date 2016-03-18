<?php
include "db.php";

session_start();

loadOdbc();

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

    <?php  if (isset($_GET) && isset($_GET['upload'])){   ?>
        <div class='internal'>
        <div class="nav_elements">
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
        </div>
    </div>
    <?php } ?>
</div>
</div>
</body>
</html>
