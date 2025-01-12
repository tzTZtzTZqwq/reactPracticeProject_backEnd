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

function getTagIds($sqlConn,$tags){
    $tags_s = implode(',', array_fill(0, count($tags), '?'));
    $stmt = $sqlConn->prepare("SELECT * FROM tags WHERE tag_name IN ($tags_s)");
    $stmt->bind_param(str_repeat('s', count($tags)), ...$tags);
    $stmt->execute();
    $result = $stmt->get_result();
    $resultArr = [];
    for ($i = $result->num_rows - 1; $i >= 0; $i--) {
        $result->data_seek($i);
        $row = $result->fetch_assoc();
        array_push($resultArr,$row['tag_index']);
    }
    return $resultArr;
}

function getProblems($sqlConn, $count){
    //$placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
    $query = "SELECT * FROM problems";
    $stmt = $sqlConn->prepare($query);
    /*
    $conditions = [];
    foreach ($tag_ids as $id) {
        $conditions[] = "(tags & (1 << $id) = (1 << $id))";
    }
    $finalCondition = implode(' AND ', $conditions);
    $query = "SELECT * FROM problems WHERE $finalCondition";
    $stmt = $sqlConn->prepare($query);
    */
    $stmt->execute();
    $result = $stmt->get_result();
    $problems = $result->fetch_all(MYSQLI_ASSOC);
    return $problems; // Return the fetched problems
}

function ajaxResult($data){
    exit(json_encode($data));
}

$sqlConn = connectToSql();
$problems = getProblems($sqlConn,30);
print_r($problems);

//echo($tag_ids);