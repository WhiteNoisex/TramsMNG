<?php



include("configBackEnd.php");



// Create connection
$conn = new mysqli($servername, $username, $password, $dbnameUsr);

function logRequest($request) {
    return;
    // Get current date and time
    $timestamp = date("Y-m-d H:i:s");
    
    // Log the request details to a file
    $logData = "[$timestamp] Request: " . json_encode($request) . "\n";
    file_put_contents('Log/0_api_requests.log', $logData, FILE_APPEND);
}

function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0C2f ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0x2Aff ), mt_rand( 0, 0xffD3 ), mt_rand( 0, 0xff4B )
    );

}

// Read raw request body
$requestBody = file_get_contents('php://input');

// Log the raw request body
logRequest($requestBody);

// Parse JSON request body if needed
$requestData = json_decode($requestBody, true);

$requestData = array_map(function($value) {
    return is_array($value) ? array_map('filter_var', $value) : filter_var($value);
}, $requestData);

// Check if the required POST parameters are set
if (isset($requestData['Username']) && isset($requestData['Token']) && isset($requestData['NewUserName'])) {
    // Extract username and password from POST parameters
    $username = $requestData['Username'];
    $newUsername = $requestData['NewUserName'];
    $token = $requestData['Token'];
    
    $userUID = GetUserUID($conn,$username);
    $timestamp = date("Y-m-d H:i:s");

    if (strlen($newUsername) > 15) {
        echo json_encode(['Error' => 'Invalid Username Length']);
        http_response_code(400); // Bad Request
        exit();
    }

    if (strlen($newUsername) < 5) {
        echo json_encode(['Error' => 'User Length Length']);
        http_response_code(400); // Bad Request
        exit();
    }


    if($userUID === false){
        echo json_encode(['Error' => 'Invalid UserUID']);
        http_response_code(401); // Bad Request
        exit();
    }

    // Check if the credentials are correct (replace with your authentication logic)
    if (authenticateUser($conn,$username,$token)) {

        if(CheckUsernameAllreadyExists($conn,$newUsername)){
            http_response_code(409); // Unauthorized
            echo json_encode(['Error' => 'Username Conflict']);
            exit();
        }

        $newUserUID = random_int(100000000, 999999999);

        if(CheckUserUIDAllreadyExists($conn,$newUserUID)){
            $newUserUID = random_int(100000000, 999999999);

            if(CheckUserUIDAllreadyExists($conn,$newUserUID)){
                http_response_code(500); // Unauthorized
                echo json_encode(['Error' => 'UserUID Conflict X2']);
                exit();
            }
        }








        if(CreateNewAccount($conn,$newUserUID,$newUsername)){
            http_response_code(200); // Unauthorized
            echo json_encode(['message' => 'User Created']);
        }
        else{
            http_response_code(500); // Unauthorized
            echo json_encode(['error' => 'Failed To Create New User']);
        }
    }
    else {
        // Credentials are incorrect
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'User Not Authenticated']);
    }
} else {
    // Required POST parameters are missing
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Bad Request']);
}

// Might Be vaurablity as the chatid isnt sanitised and is dirrectly from user however it has to exist to pass so idk



function authenticateUser($mysqliUsers, $username, $token) {
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE BINARY username = ? AND BINARY token = ? AND loggedin = 1";
    
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

function CheckUsernameAllreadyExists($mysqliUsers, $username) {
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE username = ?";
    
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
function CheckUserUIDAllreadyExists($mysqliUsers, $newuserUID) {
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE BINARY UserUID = ?";
    
    // Prepare the statement
    $stmt = $mysqliUsers->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("s", $newuserUID);
    
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
        error_log("Damn What Are The Chances Of 2 Identical UIDS");
        return true;
    } else {
        // Close the statement
        $stmt->close();
        error_log("Failed To Authenticate");
        // Authentication failed
        return false;
    }
}



function CreateNewAccount($mysqliUsers,$NewUserUID,$newUsername){
    // Prepare the SQL statement with placeholders
    $sql = "INSERT INTO `users` (`id`, `UserUID`, `Username`, `Password`, `token`, `loggedin`, `last_login`, `Friends`) VALUES (NULL, ?, ?, 'ChangeMe', '0', '0', CURRENT_TIMESTAMP, 'null');";

    // Prepare the statement
    $stmt = $mysqliUsers->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("ss", $NewUserUID,$newUsername);
    
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
    
    return true;

}


function GetUserUID($mysqliUsers,$username){
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE username = ?";

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



function GetChatName($mysqliMsg,$chatID){
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM groupchat_dict WHERE ID = ?";

    // Prepare the statement
    $stmt = $mysqliMsg->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliMsg->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("i", $chatID);
    
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
        $groupchat_name = $row['GroupChat_Name'];
        // Close the statement
        $stmt->close();
        
        // Authentication successful
        return $groupchat_name;
    } else {
        // Close the statement
        $stmt->close();
        
        // Authentication failed
        return false;
    }
}
?>
