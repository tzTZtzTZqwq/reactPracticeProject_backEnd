<?php
function getConfig($path = "./config"){
    $file = fopen("./config/mysqlConfig", "r");
    $configData = fread($file, filesize("./config/mysqlConfig"));
    fclose($file);
    $config = json_decode($configData);
    return [
        'username' => $config->username,
        'password' => $config->password
    ];
}

function connectToSql(){
    $config = getConfig();
    $sqlConn = new mysqli("localhost", $config['username'], $config['password'], 'java_tonyz_top');
    if (!$sqlConn) 
    { 
        ajaxResult(["result"=>"failed","reason"=>"error:1"]);
    }
    return $sqlConn;
}

function validateRequest($sqlConn, $ip_address){
    $stmt = $sqlConn->prepare("SELECT * FROM attempt_timestamp WHERE ip = ?");
    $stmt->bind_param('s', $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();

    $validCount = 0;
    $currentTime = time();
    while ($row = $result->fetch_assoc()) {
        $timeDiff = $currentTime - strtotime($row['time']);
        if ($timeDiff > 60) {
            $deleteStmt = $sqlConn->prepare("DELETE FROM attempt_timestamp WHERE ip = ? AND time = ?");
            $deleteStmt->bind_param('ii', $row['ip'],$row['time']);
            $deleteStmt->execute();
        } else {
            $validCount++;
        }
    }

    if ($validCount >= 10) {
        ajaxResult([
            'description' => "Too many requests, please try again later.",
            'status' => 0
        ]);
    }

    $insertStmt = $sqlConn->prepare("INSERT INTO attempt_timestamp (ip, time) VALUES (?, NOW())");
    $insertStmt->bind_param('s', $ip_address);
    $insertStmt->execute();
}

function getProblem($sqlConn, $problem_id){
    $query = "SELECT * FROM problems WHERE problem_index = ?";
    $stmt = $sqlConn->prepare($query);
    $stmt->bind_param('i', $problem_id); 
    $stmt->execute();
    $result = $stmt->get_result();
    $result = $result->fetch_all(MYSQLI_ASSOC);
    return $result[0]; 
}

function ajaxResult($data){
    exit(json_encode($data));
}
if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
}
$sqlConn = connectToSql();
$ip_address = $_SERVER['REMOTE_ADDR'];
validateRequest($sqlConn, $ip_address);
$problem = getProblem($sqlConn,1);
$problem = json_encode($problem);
exit($problem);


//echo($tag_ids);