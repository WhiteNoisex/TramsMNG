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
//logRequest($requestBody);

// Parse JSON request body if needed
$requestData = json_decode($requestBody, true);

$requestData = array_map(function($value) {
    return is_array($value) ? array_map('filter_var', $value) : filter_var($value);
}, $requestData);

// Check if the required POST parameters are set
if (isset($requestData['username']) && isset($requestData['password'])) {
    // Extract username and password from POST parameters
    $username = $requestData['username'];
    $hashedpasscode = $requestData['password'];
    //$hashedpasscode = hash('sha256', $password);
    
    
    // Check if the credentials are correct (replace with your authentication logic)
    if (authenticateUser($conn,$username,$hashedpasscode,$password)) {
        // Credentials are correct
        $token = generate_uuid();
        if(SetLoginStatusExectute($conn, $username,$hashedpasscode,$password ,$token)){
            http_response_code(200); // OK
            echo json_encode(['message' => 'Credentials are correct'] + ['token' => $token]);
        }
        else{
            http_response_code(500); // Server Fault
            echo json_encode(['error' => 'Internal Fault']);
        }
        
    } else {
        // Credentials are incorrect
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Incorrect credentials']);
    }
} else {
    // Required POST parameters are missing
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing username or password']);
}














function authenticateUser($mysqli, $username, $passwordhashed,$password) {
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users 
        WHERE BINARY FirstName = ? 
        AND (BINARY password = ? OR (BINARY password = 'ChangeMe' AND BINARY ? =  'ChangeMe'));";
    
    // Prepare the statement
    $stmt = $mysqli->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqli->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("sss", $username, $passwordhashed ,$password);
    
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
        
        // Authentication failed
        return false;
    }
}




function SetLoginStatusExectute($mysqli, $username,$hashedpasscode ,$password ,$token){
    // Removed Required To Be logged Out to LogIn AND loggedin = 0
    //echo json_encode(['username'=> $username] + ['password' => $password] + ['token' => $token]);
    $sqlLog = "UPDATE users 
    SET token = ?, loggedin = 1 
    WHERE BINARY FirstName = ? 
    AND (BINARY Password = ? OR (BINARY Password = 'ChangeMe' AND ? = 'ChangeMe'))";
    
    // Prepare the statement
    $stmtLog = $mysqli->prepare($sqlLog);

    // $sql = "UPDATE `users` SET `token`=\'random\',`loggedin`=\'0\' WHERE Username = \'Test1\' AND loggedin = 1 AND token = \'bug\';";
    // UPDATE `users` SET `token`='random',`loggedin`='0' WHERE Username = 'Test1' AND loggedin = 1 AND token = 'bug'

    // Check if the statement preparation was successful
    if (!$stmtLog) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqli->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmtLog->bind_param("ssss", $token,$username,$hashedpasscode ,$password);
    
    // Check if the parameter binding was successful
    if (!$bindResult) {
        // Log the error internally
        error_log("Failed to bind parameters: " . $stmtLog->error);
        $stmtLog->close();
        return false; // Authentication failed
    }
    
    // Execute the statement
    $executeResult = $stmtLog->execute();
    
    // Check if the execution was successful
    if (!$executeResult) {
        // Log the error internally
        error_log("Failed to execute SQL statement: " . $stmtLog->error);
        $stmtLog->close();
        return false; // Authentication failed
    }
    
    // Get the result
    $result = $stmtLog->get_result();

    $stmtLog->close();
    // Authentication successful
    return true;

    echo json_encode(['result'=> $result]);
    // Check if there is any row returned
    // Close the statement
    
}
?>
