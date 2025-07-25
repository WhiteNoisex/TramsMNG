<?php
$APROV = array("Forbiden.php","Forbiden.html");
$Favicon = '
<head>
    <link rel="SHORTCUT ICON" type="image/x-icon" href="/htdocs/WEB/Favicon1.ico"/>
    <link rel="icon" type="image/x-icon" href="/htdocs/WEB/Favicon1.ico" />
</head>';




if(isset($_GET['A']))
{
    $ADDR = $_GET['A'];
}
else{
    $ADDR = null;
}

$Checked = true;
foreach($APROV as $ADR){
    //debug_to_console($ADR . " " . $ADDR);
    if($ADR == $ADDR)
    {
        $Checked = false;
        //GoToADDR($ADR); 
    }
}
//debug_to_console($Checked);
if($Checked == false)
{
    //GoToADDR("403 Forbidden");
    ob_start();
    require_once 'html/403-Forbiden.html';
    $newContent = ob_get_clean();
    echo $newContent;
    return;
}




GoToADDR($ADDR);














function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

function GoToADDR($Address)
{
    
    if($Address != null)
    {
        debug_to_console('html/' . $Address);
        if(file_exists('html/' . $Address))
        {
		/*
            $db = mysqli_connect('localhost', 'logger', 'H5foDNmDeao4$stMNBLsSsB&@3m5Rp&F@n7p35nx', 'resumewebsiteproject');
            $IP = getenv("REMOTE_ADDR");
            
            // Use prepared statement to insert values safely
            $query = "INSERT INTO `logs` (`IP`, `Site`) VALUES (?, ?)";
            $stmt = mysqli_prepare($db, $query);
            
            // Bind parameters and execute the statement
            mysqli_stmt_bind_param($stmt, 'ss', $IP, $Address);
            $result = mysqli_stmt_execute($stmt);
		*/

            //$content = file_get_contents($ADDR);
            ob_start();
            require_once 'html/' . $Address;
            $newContent = ob_get_clean();
            echo $newContent;
            
        }
        else
        {
            ob_start();
            require_once 'html/404-Error.html';
            $newContent = ob_get_clean();
            echo $newContent;
        }
    }
    else
    {
        ob_start();
        require_once 'html/landing.html';
        $newContent = ob_get_clean();
        echo $newContent;
    }
    
}



?>
