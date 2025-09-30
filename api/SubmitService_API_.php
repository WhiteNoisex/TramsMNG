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
if (isset($requestData['Username']) && isset($requestData['Token']) && isset($requestData["TramName"]) && isset($requestData["Overview"])  && isset($requestData["TramRep"]) && isset($requestData["Date"])) {
    // Extract username and password from POST parameters
    $username = $requestData['Username'];
    $tramReport = $requestData['TramRep'];
    $tramOverview = $requestData['Overview'];
    $token = $requestData['Token'];
    $tramName = $requestData['TramName'];
    $Date = $requestData['Date'];
    
    $userUID = GetUserUID($conn,$username);
    $timestamp = date("Y-m-d H:i:s");

    if($userUID === false){
        echo json_encode(['Error' => 'Invalid UserUID']);
        http_response_code(401); // Bad Request
        exit();
    }

    // Check if the credentials are correct (replace with your authentication logic)
    if (authenticateUser($conn,$userUID,$token, "Admin") || authenticateUser($conn,$userUID,$token, "Driver") || authenticateUser($conn,$userUID,$token, "Mant")) {
        submitService($conn, $tramName, $tramReport, $Date, $tramOverview);
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

function submitService($mysqliUsers, $tramName, $tramReport, $Date, $tramOverview)
{

    // New damage entry
    $damageEntry = [
        "Date" => $Date,
        "Tram Name" => $tramName,
        "Overview" => $tramOverview,
        "Report" => $tramReport
    ];

    // Encode as JSON
    $damageJson = json_encode($damageEntry);

    // SQL: append to existing JSON array safely
    $sql = "UPDATE trams
            SET Maintenance_History = JSON_ARRAY_APPEND(
                IF(Maintenance_History IS NULL OR JSON_LENGTH(Maintenance_History) = 0, JSON_ARRAY(), Damage_History),
                '$',
                JSON_EXTRACT(?, '$')
            )
            WHERE Tram_Name = ?";

    $stmt = $mysqliUsers->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false;
    }

    // Bind parameters
    $stmt->bind_param("ss", $damageJson, $tramName);
    $stmt->execute();


    
    // Check if the execution was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to execute SQL statement: " . $stmt->error);
        $stmt->close();
        return false; // Authentication failed
    }
    
    // Get the result
    $result = $stmt->get_result();
    
    return true;

}

function authenticateUser($mysqliUsers, $userUID, $token, $perm) {
    // Prepare the SQL statement with placeholders
    $sql = "SELECT * FROM users WHERE BINARY UserUID = ? AND BINARY token = ? AND loggedin = 1 AND Perms = ?";
    
    // Prepare the statement
    $stmt = $mysqliUsers->prepare($sql);
    
    // Check if the statement preparation was successful
    if (!$stmt) {
        // Log the error internally
        error_log("Failed to prepare SQL statement: " . $mysqliUsers->error);
        return false; // Authentication failed
    }
    
    // Bind parameters
    $bindResult = $stmt->bind_param("sss", $userUID, $token, $perm);
    
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
