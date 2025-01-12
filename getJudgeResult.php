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
            'output' => "Too many requests, try again later",
            'status' => 0
        ]);
    }

}
if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
}
$ip_address = $_SERVER['REMOTE_ADDR'];
$sqlConn =  connectToSql();
$stmt = $sqlConn->prepare("SELECT * from submit_history WHERE ip = ?");
$stmt->bind_param('s', $ip_address);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
ajaxResult($row);

function ajaxResult($data){
    exit(json_encode($data));
}
?>