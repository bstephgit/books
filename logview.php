<?php
include "db.php";

if(count($_POST)>0)
{
    $dbase=\Database\odbc()->connect();
    if($dbase)
    {
        foreach($_POST as $key => $val)
        {
            if($_POST[$key]==='on')
            {
                $dbase->query("DELETE FROM LOGS WHERE ID=$key");
            }
        }
        $dbase->close();
    }
}

function printLogTable()
{
	echo '<table style="table-layout:fixed;width:100%">
             <thead>
                <tr>
                    <th style=\'width:1px\'>Delete</th>
                    <th style=\'width:1px\'>Level</th>
                    <th style=\'width:1px\'>Message</th>
                    <th style=\'width:1px\'>Function</th>
                    <th style=\'width:1px\'>Line</th>
                    <th style=\'width:1px\'>File</th>
                    <th style=\'width:1px\'>Time</th>
                </tr>
            </thead>
            <tbody>';
	
	 $dbase=\Database\odbc()->connect();
	if($dbase)
	{
			if($rec=$dbase->query('SELECT * FROM LOGS ORDER BY TIME'))
			{
					while($rec->next())
					{
							$msg=htmlspecialchars($rec->field_value('MSG'));
							$level=$rec->field_value('LEVEL');
							$file=$rec->field_value('FILE');
							$line=$rec->field_value('LINE');
							$function=$rec->field_value('FUNCTION');
							$time=$rec->field_value('TIME');
							$id=$rec->field_value('ID');
							$class='';
							if($msg==null) $msg='';							
							if($level==null) $level='';
							if($file==null) $file='';
							if($line==null) $line='';
							if($function==null) $function='';
							if($time==null) $time='';
							switch($level)
							{
									case 'ERR': $class='error';break;
									case 'WARN': $class='warning';break;
									case 'INFO': $class='info';break;
									case 'DEBUG': $class='debug';break;
							}
							echo sprintf("<tr class='%s'><td style='width:1px'><input type='checkbox' name='%s'></td><td style='width:1px'>%s</td><td style='white-space:normal;word-break:break-all'>%s</td><td style='width:1px'>%s</td><td style='width:1px'>%s</td><td style='width:1px'>%s</td><td style='width:1px'>%s</td></tr>",$class,$id,$level,$msg,$function,$line,$file,$time);
					}
			}
			$dbase->close();
	}
	echo '</tbody>
        </table>';
}
function printLogEntries()
{	
	echo '<table>';
	$dbase=\Database\odbc()->connect();
	if($dbase)
	{
			if($rec=$dbase->query('SELECT * FROM LOGS ORDER BY TIME'))
			{
					while($rec->next())
					{
							$msg=htmlspecialchars($rec->field_value('MSG'));
							$level=$rec->field_value('LEVEL');
							$file=$rec->field_value('FILE');
							$line=$rec->field_value('LINE');
							$function=$rec->field_value('FUNCTION');
							$time=$rec->field_value('TIME');
							$id=$rec->field_value('ID');
							$class='';
							if($msg==null) $msg='';							
							if($level==null) $level='';
							if($file==null) $file='';
							if($line==null) $line='';
							if($function==null) $function='';
							if($time==null) $time='';
							switch($level)
							{
									case 'ERR': $class='error';break;
									case 'WARN': $class='warning';break;
									case 'INFO': $class='info';break;
									case 'DEBUG': $class='debug';break;
							}
							echo sprintf("<tr class='%s' style='border: solid 0.5px'><td><input type='checkbox' name='%s'></td>
							<td width='25%%'>%s</td>
							<td>%s</td><td>%s</td>
							<td width='15%%'>%s</td><td width='15%%'>%s</td>
							<td>%s</td></tr>",$class,$id,$level,$function,$file,$line,$time,$msg);
					}
			}
			$dbase->close();
	}
	echo '</table>';
}
?>

<html>
<head>
    <title>logs viewer</title>
    <link type="text/css" rel="stylesheet" href="books.css" />
    <script type="text/javascript">
        function selectall(chbox)
        {
            if(chbox.id==='chtop'){
                document.getElementById('chbot').checked = chbox.checked;
            }
            if (chbox.id === 'chbot') {
                document.getElementById('chtop').checked = chbox.checked;
            }
            var form = document.getElementById('log_form');
            if (form === null)
            {
                alert('form is null');
                return;
            }
            for (var i = 0; i < form.elements.length; ++i)
            {
                var item = form.elements.item(i);
                if(item.type!=undefined && item.type==='checkbox')
                {
                    item.checked = chbox.checked;
                }
            }
        }
    </script>
</head>
<body class='logview'>
    <input type="checkbox" id="chtop" onchange="selectall(this)" />Select All<br />

    <form id="log_form" action="logview.php" method="POST">
        <input type="submit" value="delete" />
				<button onclick='window.location.reload();'> refresh	</button>
        
				<?php printLogEntries();	?>
           
        <input type="submit" value="delete"/>
			<button onclick='window.location.reload();'> refresh</button>
    </form>
    <input type="checkbox" id="chbot" onchange="selectall(this)" /> Select All
</body>
</html>
