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

function manipulateJudgeResult($sqlConn, $ip_address, $result, $resultDescription,$ifDelete) {
    if($ifDelete){
        $deleteStmt = $sqlConn->prepare("DELETE FROM submit_history WHERE ip = ? AND time <> NOW()");
        $deleteStmt->bind_param('s', $ip_address);
        $deleteStmt->execute();
    }
    $stmt = $sqlConn->prepare("INSERT INTO submit_history (ip, time, result_description, result) VALUES (?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE result_description = ?, result = ?");
    $stmt->bind_param('sssss', $ip_address, $resultDescription, json_encode($result,JSON_UNESCAPED_SLASHES), $resultDescription, json_encode($result,JSON_UNESCAPED_SLASHES));
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $submit_timestamp = time();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $sqlConn = connectToSql();
    validateRequest($sqlConn, $ip_address);
    
    manipulateJudgeResult($sqlConn,$ip_address,[],"pending",[],true);

    $body = file_get_contents('php://input');
    $json = json_decode($body);
    $java_code = $json->{'code'};
    $java_input = $json->{'input'};
    //$problem_name = $json->{'problem'};
    $problem_name = "two_sum";
    
    manipulateJudgeResult($sqlConn,$ip_address,[],"pending_1",[],false);
    
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
    file_put_contents($temp_file_output_path, "");
    chmod($temp_source_file_path, 0777);
    chmod($temp_file_output_path, 0777);
    
    $class_name = 'Main'; 
    
    manipulateJudgeResult($sqlConn,$ip_address,[],"pending_2",[],false);
    
    $run_cmd = "/www/server/java/jdk-20.0.2/bin/javac 2>&1 ".$temp_source_file_path." -d ".$temp_file_path;
    exec($run_cmd,$output,$error);
    if($error!=0){//compile error
        $output[0] = preg_replace('/^.*\//', '', $output[0]);
        ajaxResult([
        'output' => implode("\n",$output),
        'status' => 1
        ]);
    }else{
        
         echo (json_encode([
        'output' => "Your code has been successfully compiled.\nYou can check your result by refreshing the page or by using the refresh button.\nYour code is saved locally and will not be deleted after refreshing the page.\nSubmitted at ".date('Y-m-d H:i:s'),
        'status' => 0
        ]));
    }
    
    $problem_name = $problem_name;
    $problem_path = "./problems_io/".$problem_name."/";
    $problem_config_path = $problem_path."config";

    manipulateJudgeResult($sqlConn,$ip_address,[],"pending_3",[],false);
    
    $config_lines = file($problem_config_path,FILE_IGNORE_NEW_LINES);
    $problem_testcase_total = (int)$config_lines[0];
    $problem_testcase_accepted = 0;
    $resultArr = array_fill(0, $problem_testcase_total, json_encode(['status' => -1]));
    for ($i = 0; $i < $problem_testcase_total; $i++) {
        manipulateJudgeResult($sqlConn, $ip_address,$resultArr, "judging".$i,$resultArr,false);
        $problem_input = $problem_path.$config_lines[1 + 2 * $i]; 
        $problem_output = $problem_path.$config_lines[2 + 2 * $i]; 
        $run_cmd = "ulimit -m 49152; /usr/bin/time --format=\"%e\n%M\" timeout 1 /www/server/java/jdk-20.0.2/bin/java 2>&1 -cp " .$temp_file_path." ".$class_name." > ".$temp_file_output_path . " < " . $problem_input;
        exec($run_cmd,$output,$error);

        $noError = 0;
        $compileError = 1;
        $runTimeError = 1;
        $timeLimit = 124;
        $memoryLimit = 139;
        // $resultArr[i] = json_encode(['next'=>1,'error'=>$error]);
        if($error!=$noError && $error!=$compileError && $error!=$runTimeError && $error!=$timeLimit && $error!=$memoryLimit){
            $resultArr[$i] = ['status'=>1];
            continue;
        }
        if($error==$runTimeError){
            $resultArr[$i] = ['status'=>2];
            continue;
        }
        if (!file_exists($temp_file_output_path)) {
            $resultArr[$i] = ['status'=>3];
            continue;
        }
        if (!file_exists($temp_file_output_path)) {
            $resultArr[$i] = ['status'=>4];
            continue;
        }
        if($error==$timeLimit){
            $resultArr[$i] = ['status'=>5];
            continue;
        }
        if($error==$memoryLimit){
            $resultArr[$i] = ['status'=>6];
            continue;
        }
        $output_content = file($temp_file_output_path);
        if($error == 0){//if/not error when running code
            $timeOutput = is_array($output) ? array_shift($output) : $output;
            $memoryOutput = is_array($output) ? array_shift($output) : $output;
        }
        $expected_output = file($problem_output);
        if ($output_content === $expected_output) {
            $resultArr[$i] = ['status' => 0,'timeOutput' => $timeOutput];//accepted
            $problem_testcase_accepted++;
            continue;
        } else {
            $resultArr[$i] = ['status' => 7];//wrong answer
            continue;
        }
    }    
    manipulateJudgeResult($sqlConn, $ip_address,$resultArr,$problem_testcase_accepted."/".$problem_testcase_total." testcases accepted",$resultArr,false);
}
//

    
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    echo "use POST";
}

function ajaxResult($data){
    echo(json_encode($data));
    exit();
}
?>