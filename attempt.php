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
            'output' => "You submitted too frequently, try again later",
            'status' => 0
        ]);
    }

    $insertStmt = $sqlConn->prepare("INSERT INTO attempt_timestamp (ip, time) VALUES (?, NOW())");
    $insertStmt->bind_param('s', $ip_address);
    $insertStmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $sqlConn = connectToSql();
    validateRequest($sqlConn, $ip_address);

    $body = file_get_contents('php://input');
    $json = json_decode($body);
    $java_code = $json->{'code'};
    $java_input = $json->{'input'};
    
    
    $file_id = uniqid();
    $temp_dir = './userCode_test/'.$file_id;
    mkdir($temp_dir,0777);
    
    $temp_source_file_name = 'Main' . '.java';
    $temp_file_name = 'UserCode' . $file_id;
    //$temp_file_outpot_name = 'UserCode' . $file_id . '.java.output';
    $temp_file_output_name = $file_id.'output.txt';
    $temp_file_input_name = $file_id.'input.txt';
    $temp_source_file_path =  $temp_dir . '/' . $temp_source_file_name;
    $temp_file_path =  $temp_dir . '/' . $temp_file_name;
    $temp_file_output_path = $temp_dir . '/' . $temp_file_output_name;
    $temp_file_input_path =  $temp_dir . '/' . $temp_file_input_name;
    //$temp_file_outpot_path = '.' . $temp_dir . '/output2.txt';
    file_put_contents($temp_source_file_path, $java_code);
    file_put_contents($temp_file_input_path, $java_input);
    file_put_contents($temp_file_output_path, "");
    chmod($temp_source_file_path, 0777);
    chmod($temp_file_input_path, 0777);
    chmod($temp_file_output_path, 0777);
    
    $class_name = 'Main'; 
    
    $run_cmd = "/www/server/java/jdk-20.0.2/bin/javac 2>&1 ".$temp_source_file_path." -d ".$temp_file_path;
    exec($run_cmd,$output,$error);
    if($error!=0){//compile error
        $output[0] = preg_replace('/^.*\//', '', $output[0]);
        ajaxResult([
        'output' => implode("\n",$output),
        'status' => 1
        ]);
    }
    $run_cmd = "ulimit -m 49152; /usr/bin/time --format=\"%e\n%M\" timeout 1 /www/server/java/jdk-20.0.2/bin/java 2>&1 -cp " .$temp_file_path." ".$class_name." > ".$temp_file_output_path . " < " . $temp_file_input_path; //
    //echo($run_cmd);
    $startTime = time();
    exec($run_cmd,$output,$error);
    $endTime = time();
    
    $noError = 0;
    $compileError = 1;
    $runTimeError = 1;
    $timeLimit = 124;
    $memoryLimit = 139;
    if($error!=$noError && $error!=$compileError && $error!=$runTimeError && $error!=$timeLimit && $error!=$memoryLimit){
        
        ajaxResult([
        'output' => "error: 02 ".$error."\n".(is_array($output) ? implode("\n", $output) : $output),
        'status' => 2
        ]);
        
    }
    if (!file_exists($temp_file_output_path)) {
        ajaxResult([
        'output' => "error: 12 ".$error,
        'status' => $error
        ]);
    }
    if (!file_exists($temp_file_output_path)) {
        ajaxResult([
        'output' => "error: 13 ",
        'status' => $error
        ]);
    }
    if($error==$timeLimit){
        ajaxResult([
        'output' => "time limit exceeded! (1s)",
        'status' => $error
        ]);
    }
    if($error==$memoryLimit){
        ajaxResult([
        'output' => "memory limit exceeded! (48MB)",
        'status' => $error
        ]);
    }
    $output_content = file_get_contents($temp_file_output_path);
    if($error == 0){//if/not error when running code
        $timeOutput = is_array($output) ? array_shift($output) : $output;
        $memoryOutput = is_array($output) ? array_shift($output) : $output;
    }else{
        $timeOutput = "N/A";
        $memoryOutput = "N/A";
    }
    
    ajaxResult([
        'output' => 
        ($error == 0)?$output_content:(is_array($output)?implode("\n",$output):$output)
        ."\n-----\ntime elapsed:".$timeOutput
        ."\nmemory used:".$memoryOutput."KB"  ,
        'status' => $error . " ia" . is_array($output)
    ]);

    
}
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    echo "use POST";
}

function ajaxResult($data){
    echo(json_encode($data));
    exit();
}
?>