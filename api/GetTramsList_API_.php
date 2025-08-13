<?php

include('encriptionFunction.php');
include("configBackEnd.php");


$GUEST_MODE = true;
$ENCRKEY = null;
// Create connection
$connSQL = new mysqli($servername, $username, $password, $dbnameUsr);

function logRequest($request) {
    return;
    // Get current date and time
    $timestamp = date("Y-m-d H:i:s");
    
    // Log the request details to a file
    $logData = "[$timestamp] Message: " . json_encode($request) . "\n";
    file_put_contents('Log/0_GroupChatMessages.log', $logData, FILE_APPEND);
}

// Read raw request body
$requestBody = file_get_contents('php://input');

// Log the raw request body
logRequest($requestBody);

// Parse JSON request body if needed
$requestData = json_decode($requestBody, true);




if (isset($requestData['Username'])) {
    // Extract username and password from POST parameters
    $token = "";
    $username = $requestData['Username'];
    $userUID = GetUserUID($connSQL,$username);
    $timestamp = date("Y-m-d H:i:s");
    
    if($userUID === false){
        echo json_encode(['error' => 'The User Does not exist']);
        http_response_code(400); // Bad Request
        return;
    }

    if($userUID != 0)
    {
        $GUEST_MODE = false;
    }

    if($GUEST_MODE === false)
    {
        if(!isset($requestData['Token']))
        {
            // Required POST parameters are missing
            echo json_encode(['error' => 'Required Paramaters Missing']);
            http_response_code(400); // Bad Request
            return;
        }
        else
        {
            $token = $requestData['Token'];
        }
            
    }


    // Check if the credentials are correct (replace with your authentication logic)
    if (authenticateUser($connSQL,$username,$token) || $GUEST_MODE === true) {
            try
            {
                $messagesData = GetTramsFromDatabase($connSQL, $GUEST_MODE);
                http_response_code(200); // Unauthorized
                //logRequest($messagesData);
                echo json_encode($messagesData);
                
            }
            catch(error $error) {
                error_log($error);
                http_response_code(500); // Unauthorized
                echo json_encode(['Error' => 'Messages retivial Server Failure: ' . $error]);
            }
    } else {
        // Credentials are incorrect
        echo json_encode(['error' => 'User Not Authenticated']);
        http_response_code(401); // Unauthorized

    }
} else {
    // Required POST parameters are missing
    echo json_encode(['error' => 'Required Paramaters Missing']);
    http_response_code(400); // Bad Request
}



function GetUserName($mysqliUsers,$UserUID){
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE UserUID = ?";

    // Prepare the statement
    $stmt = $mysqliUsers->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("s", $UserUID);
    
    // Check if the parameter binding was successful
    if (!$bindResult) {
        // Log the error internally
        error_log("Failed to bind parameters: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Execute the statement
    $executeResult = $stmt->execute();
    
    // Check if the execution was successful
    if (!$executeResult) {
        // Log the error internally
        error_log("Failed to execute SQL statement: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Get the result
    $result = $stmt->get_result();
    
    // Check if there is any row returned
    if ($result->num_rows > 0) {
        // Fetch the row
        $row = $result->fetch_assoc();
        $username = $row['Username'];
        // Close the statement
        $stmt->close();
        
        // Authentication successful
        return $username;
    } else {
        // Close the statement
        $stmt->close();
        
        // Authentication failed
        return false;
    }
}


// Might Be vaurablity as the chatid isnt sanitised and is dirrectly from user however it has to exist to pass so idk

function GetTramsFromDatabase($mysqliDict, $lessDetail){

    $sql = "SELECT * FROM trams ORDER BY Tram_Name";


    $stmt = $mysqliDict->prepare($sql);

    if (!$stmt) { error_log("Failed to prepare SQL statement: " . $mysqliDict->error); return false;}

    
    
    // Execute the statement
    $executeResult = $stmt->execute();
    
    // Check if the execution was successful
    if (!$executeResult) {
        // Log the error internally
        error_log("Failed to execute SQL statement: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }

    $result = $stmt->get_result();

    



    if ($result->num_rows > 0) {

        if($lessDetail)
        {
            // Get the result
            $wanted_columns = ['ID', 'Tram_Name', 'Operating_Year_S', 'Operating_Year_E', 'Damage_History', 'Tram_Type', 'Service_State'];
            

            $rows = [];

            while ($row = $result->fetch_assoc()) {
                $filtered = array_intersect_key($row, array_flip($wanted_columns));
                $rows[] = $filtered;
            }
        }
        else{
            // Fetch the rows
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $rows = array_reverse($rows);
        }



        
        
        // Close the statement
        $stmt->close();
        

        return $rows;
    } else {
        // Close the statement
        $stmt->close();
        error_log("Failed To Authenticate");
        // Authentication failed
        return false;
    }
    
}

function CompareHashes($data1,$hash2){
    $jsonString = json_encode($data1);
    $serverHash = hash('sha256', $jsonString);
 
    // Compare the client's hash with the server's hash
    
    //logRequest($hash2);
    //logRequest($serverHash);
    if ($hash2 === $serverHash) {
        // Data is up to date, send 304 Not Modified
        return true;
    }
    else{
        return false;
    }
}

function authenticateUser($mysqliUsers, $username, $token) {
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE CONCAT(FirstName, LastName) = ? AND BINARY token = ? AND loggedin = 1";
    
    // Prepare the statement
    $stmt = $mysqliUsers->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("ss", $username, $token);
    
    // Check if the parameter binding was successful
    if (!$bindResult) {
        // Log the error internally
        error_log("Failed to bind parameters: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Execute the statement
    $executeResult = $stmt->execute();
    
    // Check if the execution was successful
    if (!$executeResult) {
        // Log the error internally
        error_log("Failed to execute SQL statement: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Get the result
    $result = $stmt->get_result();
    
    // Check if there is any row returned
    if ($result->num_rows > 0) {
        // Fetch the row
        $row = $result->fetch_assoc();
        // Close the statement
        $stmt->close();
        
        // Authentication successful
        return true;
    } else {
        // Close the statement
        $stmt->close();
        error_log("Failed To Authenticate");
        // Authentication failed
        return false;
    }
}



function GetUserUID($mysqliUsers,$username){
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE CONCAT(FirstName, LastName) = ?";

    // Prepare the statement
    $stmt = $mysqliUsers->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("s", $username);
    
    // Check if the parameter binding was successful
    if (!$bindResult) {
        // Log the error internally
        error_log("Failed to bind parameters: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Execute the statement
    $executeResult = $stmt->execute();
    
    // Check if the execution was successful
    if (!$executeResult) {
        // Log the error internally
        error_log("Failed to execute SQL statement: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Get the result
    $result = $stmt->get_result();
    
    // Check if there is any row returned
    if ($result->num_rows > 0) {
        // Fetch the row
        $row = $result->fetch_assoc();
        $userUID = $row['UserUID'];
        // Close the statement
        $stmt->close();
        
        // Authentication successful
        return $userUID;
    } else {
        // Close the statement
        $stmt->close();
        
        // Authentication failed
        return false;
    }
}



?>
