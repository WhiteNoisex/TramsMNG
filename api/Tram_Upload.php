<?php
include("configBackEnd.php");

// Create DB connection
$conn = new mysqli($servername, $username, $password, $dbnameUsr);
$conn->set_charset("utf8mb4");

// Log requests
function logRequest($request){
    return;
    $timestamp = date("Y-m-d H:i:s");
    $logData = "[$timestamp] Request: ".json_encode($request)."\n";
    file_put_contents('Log/0_api_requests.log', $logData, FILE_APPEND);
}

// Read raw request body (optional JSON logging)
$requestBody = file_get_contents('php://input');
logRequest($requestBody);

// Validate credentials
$username = $_POST['Username'] ?? '';
$token = $_POST['Token'] ?? '';

if(!$username || !$token || !isset($_FILES['File'])){
    http_response_code(400);
    echo json_encode(['error'=>'Missing credentials or file']);
    exit();
}

// Authenticate
$userUID = GetUserUID($conn, $username);
if($userUID === false || !authenticateUser($conn, $userUID, $token, "Admin") && 
   !authenticateUser($conn, $userUID, $token, "Driver") && 
   !authenticateUser($conn, $userUID, $token, "Mant")){
    http_response_code(401);
    echo json_encode(['error'=>'User not authenticated']);
    exit();
}

// Determine file type
$uploadedFile = $_FILES['File']['tmp_name'];
$ext = strtolower(pathinfo($_FILES['File']['name'], PATHINFO_EXTENSION));
$trains = [];

try{
    if($ext === 'csv'){
        $handle = fopen($uploadedFile,'r');
        $header = fgetcsv($handle);
        while(($row=fgetcsv($handle)) !== false){
            $trains[] = array_combine($header, $row);
        }
        fclose($handle);
    } elseif($ext === 'json'){
        $json = file_get_contents($uploadedFile);
        $trains = json_decode($json,true);
        if(!is_array($trains)) throw new Exception("Invalid JSON");
    } elseif($ext === 'xml'){
        $xml = simplexml_load_file($uploadedFile,"SimpleXMLElement",LIBXML_NOCDATA);
        foreach($xml->Tram as $tram){
            $trains[] = json_decode(json_encode($tram), true);
        }
    } else throw new Exception("Unsupported file type");
} catch(Exception $e){
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
    exit();
}

// Fixed columns based on your schema
$columns = [
    "Tram_Name",
    "Operating_Year_S",
    "Operating_Year_E",
    "Service_State",
    "Historic",
    "EOL_GOAL",
    "Tram_Type",
    "Photo_Location",
    "Operating_City",
    "Power_Type",
    "Seat_Capacity",
    "Engine_Details",
    "Tram_Route",
    "Disablity_Compliance",
    "Normal_Driver",
    "Drives_History",
    "Damage_History",
    "Maintenance_History"
];

// Build static SQL with all 19 columns
$placeholders = rtrim(str_repeat('?,', count($columns)), ',');
$updates = [];
foreach ($columns as $col) {
    if ($col !== "ID") {
        $updates[] = "`$col` = VALUES(`$col`)";
    }
}
$sql = "INSERT INTO trams (`".implode("`,`",$columns)."`)
        VALUES ($placeholders)
        ON DUPLICATE KEY UPDATE ".implode(", ", $updates);

$stmt = $conn->prepare($sql);
if(!$stmt){
    die("Prepare failed: ".$conn->error);
}

$inserted = 0;
foreach($trains as $t){
    // Build ordered values array
    $dbVals = [];
    $types = '';

    foreach($columns as $col){
        $val = $t[$col] ?? null;   // NULL if missing
        $dbVals[] = $val;
        
        // Define types per column
        switch($col){
            case "ID":
            case "Seat_Capacity":
            case "Historic":
            case "Disablity_Compliance":
                $types .= 'i'; // integer
                break;
            default:
                $types .= 's'; // string/text/year
        }
    }

    $stmt->bind_param($types, ...$dbVals);
    if($stmt->execute()){
        $inserted++;
    }
}

$stmt->close();

echo json_encode(['inserted' => $inserted]);


// ----------------------
// Reuse existing functions
// ----------------------
function authenticateUser($mysqliUsers, $userUID, $token, $perm){
    $sql = "SELECT * FROM users WHERE BINARY UserUID=? AND BINARY token=? AND loggedin=1 AND Perms=?";
    $stmt = $mysqliUsers->prepare($sql);
    if(!$stmt) return false;
    $stmt->bind_param("sss",$userUID,$token,$perm);
    $stmt->execute();
    $res = $stmt->get_result();
    $auth = $res->num_rows>0;
    $stmt->close();
    return $auth;
}

function GetUserUID($mysqliUsers,$username){
    $sql = "SELECT * FROM users WHERE CONCAT(FirstName,LastName)=?";
    $stmt = $mysqliUsers->prepare($sql);
    if(!$stmt) return false;
    $stmt->bind_param("s",$username);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows>0){
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['UserUID'];
    }
    $stmt->close();
    return false;
}
?>
