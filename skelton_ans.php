<?php 

require_once './function/DBConnectionHandler.php';
require_once './function/BaseConnection.php';



// 設定Token 
$ChannelSecret      = 'your secret'; 
$ChannelAccessToken = 'your access token'; 
$baseConnection     = new BaseConnection($ChannelSecret,$ChannelAccessToken);
$DbConnection       = DBConnectionHandler::getConnection();
$HttpRequestBody    = file_get_contents('php://input'); 
$HeaderSignature    = $_SERVER['HTTP_X_LINE_SIGNATURE']; 
$DataBody           = $baseConnection->cleanRequest($HttpRequestBody,$HeaderSignature);


foreach($DataBody['events'] as $Event) 
{ 
    if ($Event['type'] == 'postback') {
        // 初始化空的數組
        $params = [];
        parse_str($Event['postback']['data'], $params);

        if ($params["action"] == "helper") {
            switch($params["key"]){
                case "search":
                    break;
                case "create":
                    $reply = [
                        [
                            'type' => 'text',
                            'text' => "新增事件請遵循 動詞-任務名稱-任務描述 操作，例如: 新增-加入新事件-此處為事件描述"
                        ]
                    ];
                    break;
                case "update" : 
                    $reply = [
                        [
                            'type' => 'text',
                            'text' => "請輸入 修改 開啟修改選單"
                        ]
                    ];
                    break;
                case "delete":
                    $reply = [
                        [
                            'type' => 'text',
                            'text' => "刪除事件請遵循 動詞-事件id 操作，例如: 刪除-1"
                        ]
                    ];
                    break;
                case 'update_task_content':
                    $reply = [
                        [
                            'type' => 'text',
                            'text' => "修改事件名稱與描述請遵循 動詞-事件id-任務名稱-任務描述 操作，例如: 修改-1-修改任務名稱-修改任務描述"
                        ]
                    ];
                    break;
                case 'update_time':
                    $reply = [
                        [
                            'type' => 'text',
                            'text' => "修改事件名稱與描述請遵循 動詞-事件id-指令 操作，例如: 修改-1-修改時間"
                        ]
                    ];
                    break;
                default:
                    $reply = [
                        [
                            'type' => 'text',
                            'text' => "非法輸入"
                        ]
                    ];
            }
        }   
        if ($params["action"] == "set") {
             switch($params["key"]) {
                case "start_time":
                    $id = $params["task_id"];
                    $reply = updateStartTime($DbConnection,$id,$Event['postback']["params"]["datetime"]);
                    break;
                case 'end_time':
                    $id = $params["task_id"];
                    $reply = updateEndTime($DbConnection,$id,$Event['postback']["params"]["datetime"]);
                    break;
                
            }
        }
    }else if($Event['type'] == 'message') { 
        $userMessage = $Event['message']['text'];
        if ($userMessage == "呼叫") {
            $reply = [
                [
                    "type" => "template",
                    "altText" => "個人記事本 - Helper",
                    "template" => [
                        "type" => "buttons",
                        "thumbnailImageUrl" => "https://gcs.rimg.com.tw/g4/634/455/chipdaleji2022/d/64/c8/22411057842376_166.jpg",
                        "imageAspectRatio" => "rectangle",
                        "imageSize" => "cover",
                        "imageBackgroundColor" => "#FFFFFF",
                        "title" => "個人記事本 - Helper",
                        "text" => "點擊查看指令使用方法",
                        "defaultAction" => [
                            "type"=> "message",
                            "label"=> "呼叫",
                            "text"=>"呼叫"
                        ],
                        "actions" => [
                            [
                                "type" => "postback",
                                "label" => "查詢事件",
                                "data" => "action=helper&key=search"
                            ],
                            [
                                "type" => "postback",
                                "label" => "新增事件",
                                "data" => "action=helper&key=create"
                            ],
                            [
                                "type" => "postback",
                                "label" => "修改事件",
                                "data" => "action=helper&key=update"
                            ],
                            [
                                "type" => "postback",
                                "label" => "刪除事件",
                                "data" => "action=helper&key=delete"
                            ]
                        ]
                    ]
                ],
            ];
        }else{
            try {
                $parts = explode('-', $userMessage); // 切割指令字串
                switch($parts[0]){
                    case "查詢":
                        if (trim($parts[1]) == null || trim($parts[1])== '' || !isset($parts[1])) {
                            $reply = allTasks($DbConnection);
                        }else{
                            // 單一
                            $id = trim($parts[1]);
                            $sql = "SELECT * FROM tasks WHERE id = :id";
                            // 預備語句
                            $stmt = $DbConnection->prepare($sql);
                            // 綁定參數
                            $stmt->bindParam(':id', $id);
                            // 執行語句
                            $stmt->execute();
                            // 設置結果集為關聯數組
                            $stmt->setFetchMode(PDO::FETCH_ASSOC);
                            $result = $stmt->fetchAll();
                            if (!$result) {
                                $reply = [
                                    [
                                        'type' => 'text',
                                        'text' => "ID為 {$id} 的資料不存在",
                                    ]
                                ]; 
                            }else {
                                $msg = "ID: {$result[0]['id']} - 事件名稱 : {$result[0]['task_name']} \n".
                                "事件描述 : {$result[0]['task_description']} \n".
                                "開始時間 : {$result[0]["start_time"]} \n".
                                "結束時間 : {$result[0]['end_time']}";
                                $reply = [
                                    [
                                        'type' => 'text',
                                        'text' => $unsetTime . $msg,
                                    ]
                                ]; 
                            }

                        }
                        break;
                    case "新增":
                        $task_name = trim($parts[1]);
                        $task_description = trim($parts[2]);
                        if ($task_name == '' || $task_description == '') {
                            $reply =  [
                                [
                                    'type' => 'text',
                                    'text' => "錯誤! 請檢查事件標題與事件描述是否為空或未用-符號將字串隔開"
                                ]
                            ];
                            break;
                        }
                        try {
                            // 準備 SQL 語句
                            $sql = "INSERT INTO tasks (task_name, task_description) VALUES (:task_name, :task_description)";
                            // 預備語句
                            $stmt = $DbConnection->prepare($sql);
                            // 綁定參數
                            $stmt->bindParam(':task_name', $task_name);
                            $stmt->bindParam(':task_description', $task_description);
                            // 執行語句
                            $stmt->execute();
                            $inserted_id = $DbConnection->lastInsertId();
                            $reply = [
                                [
                                    'type' => 'text',
                                    'text' => "任務新增完成，請設定日期"
                                ],    
                                [
                                    "type" => "template",
                                    "altText" => "請選擇 {$task_name} 的開始和結束時間",
                                    "template" => [
                                        "type" => "buttons",
                                        "text" => "請選擇 {$task_name} 的開始和結束時間 ， 事件描述 - {$task_description}",
                                        "actions" => [
                                            [
                                                "type" => "datetimepicker",
                                                "label" => "開始時間",
                                                "data" => "action=set&key=start_time&task_id={$inserted_id}",
                                                "mode" => "datetime",
                                                "initial" => date("Y-m-d\TH:i"),
                                                "min" => date("Y-m-d\TH:i"),
                                                // "max" => date("Y-m-d\TH:i", strtotime("+1 week")),
                                            ],
                                            [
                                                "type" => "datetimepicker",
                                                "label" => "結束時間",
                                                "data" => "action=set&key=end_time&task_id={$inserted_id}",
                                                "mode" => "datetime",
                                                "initial" => date("Y-m-d\TH:i", strtotime("+2 hours")),
                                                "min" => date("Y-m-d\TH:i"),
                                                // "max" => date("Y-m-d\TH:i", strtotime("+1 week"))
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                        }catch (PDOException $pDOException){
                            $reply = [
                                [
                                    'type' => 'text',
                                    'text' => "新增任務發生錯誤"
                                ]
                            ];
                        }
                        break;
                    case "修改":
                        if (trim($parts[1]) == null || trim($parts[1])== '') {
                            $reply = [
                                [
                                    "type" => "template",
                                    "altText" => "事件修改",
                                    "template" => [
                                        "type" => "buttons",
                                        "thumbnailImageUrl" => "https://gcs.rimg.com.tw/g4/634/455/chipdaleji2022/d/64/c8/22411057842376_166.jpg",
                                        "imageAspectRatio" => "rectangle",
                                        "imageSize" => "cover",
                                        "imageBackgroundColor" => "#FFFFFF",
                                        "title" => "事件修改",
                                        "text" => "請選擇想修改的選項",
                                        "defaultAction" => [
                                            "type"=> "message",
                                            "label"=> "修改",
                                            "text"=>"修改"
                                        ],
                                        "actions" => [
                                            [
                                                "type" => "postback",
                                                "label" => "修改事件名稱與描述",
                                                "data" => "action=helper&key=update_task_content"
                                            ],
                                            [
                                                "type" => "postback",
                                                "label" => "修改事件開始與結束時間",
                                                "data" => "action=helper&key=update_time"
                                            ],
                                        ]
                                    ]
                                ],
                            ];
                        }else {
                            if (trim($parts[2])  == "修改時間") {
                                $id = trim($parts[1]);
                                $sql = "SELECT * FROM tasks WHERE id = :id";
                                // 預備語句
                                $stmt = $DbConnection->prepare($sql);
                                // 綁定參數
                                $stmt->bindParam(':id', $id);
                                // 執行語句
                                $stmt->execute();
                                // 設置結果集為關聯數組
                                $stmt->setFetchMode(PDO::FETCH_ASSOC);
                                
                                // 取得所有結果
                                $result =  $stmt->fetchAll();
                                if (!$result) {
                                    $reply = [
                                        [
                                            'type' => 'text',
                                            'text' => "ID為 {$id} 的資料不存在",
                                        ]
                                    ]; 
                                }else {
                                    $reply = [
                                        [
                                            "type" => "template",
                                            "altText" => "請選擇 {$result[0]["task_name"]} 的開始和結束時間",
                                            "template" => [
                                                "type" => "buttons",
                                                "text" => "請選擇 {$result[0]["task_name"]} 的開始和結束時間 ， 事件描述 {$result[0]["task_description"]}",
                                                "actions" => [
                                                    [
                                                        "type" => "datetimepicker",
                                                        "label" => "開始時間",
                                                        "data" => "action=set&key=start_time&task_id={$result[0]['id']}",
                                                        "mode" => "datetime",
                                                        "initial" => date("Y-m-d\TH:i"),
                                                        "min" => date("Y-m-d\TH:i"),
                                                        // "max" => date("Y-m-d\TH:i", strtotime("+1 week")),
                                                    ],
                                                    [
                                                        "type" => "datetimepicker",
                                                        "label" => "結束時間",
                                                        "data" => "action=set&key=end_time&task_id={$result[0]['id']}",
                                                        "mode" => "datetime",
                                                        "initial" => date("Y-m-d\TH:i", strtotime("+2 hours")),
                                                        "min" => date("Y-m-d\TH:i"),
                                                        // "max" => date("Y-m-d\TH:i", strtotime("+1 week"))
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ];
                                }
                            }else{
                                $id = trim($parts[1]);
                                $task_name = trim($parts[2]);
                                $task_description = trim($parts[3]);
                                $reply = updateTask($DbConnection,$id,$task_name,$task_description);
                            }
                        }
                        break;
                    case "刪除":
                        if (trim($parts[1]) == null || trim($parts[1])== '') {
                            $reply = [
                                [
                                    'type' => 'text',
                                    'text' => "刪除資料請遵循指令格式 刪除-事件id"
                                ]
                            ];
                        }else {
                            $id = trim($parts[1]);
                            $sql = "SELECT * FROM tasks WHERE id = :id";
                            // 預備語句
                            $stmt = $DbConnection->prepare($sql);
                            // 綁定參數
                            $stmt->bindParam(':id', $id);
                            // 執行語句
                            $stmt->execute();
                            // 設置結果集為關聯數組
                            $stmt->setFetchMode(PDO::FETCH_ASSOC);
                            
                            // 取得所有結果
                            $result = $stmt->fetchAll();
                            if (!$result) {
                                $reply = [
                                    [
                                        'type' => 'text',
                                        'text' => "ID為 {$id} 的資料不存在",
                                    ]
                                ]; 
                            }else {
                                $reply = deleteTask($DbConnection,$id);
                            }
                        }
                        break;
                    default:
                        $reply = [
                            [
                                'type' => 'text',
                                'text' => "錯誤! 指令錯誤"
                            ]
                        ];
                        break;

                }
            }catch(Exception $e) {
                $reply =  [
                    [
                        'type' => 'text',
                        'text' => "錯誤! 非法輸入"
                    ]
                ];
            }
        }
    }

    $Payload = [ 
        'replyToken' => $Event['replyToken'],
        'messages' => $reply
    ];

    $baseConnection->response($Payload);
}

/**
 * 查詢所有事件
 *
 * @param PDO $DbConnection
 * @return array
 */
function allTasks($DbConnection) {
    $sql = "
        SELECT *
        FROM tasks 
        ORDER BY DATE_FORMAT(start_time, '%Y-%m-%d') ASC;
    ";
    // 預備語句
    $stmt = $DbConnection->prepare($sql);
    // 執行語句
    $stmt->execute();
    // 設置結果集為關聯數組
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    // 取得所有結果
    $results = $stmt->fetchAll();
    if (!$results) {
        return [
            [
                'type' => 'text',
                'text' => "查無資料"
            ]
        ];
    }else {
        $msg = "";
        $unsetTime = "未設定時間" . "\n";
        foreach($results as $result) {
            if (is_null($result['start_time'])) {
                $unsetTime .= "ID: " . $result["id"] . " - 事件名稱 : " . $result["task_name"]. "\n";
                
            }else {
                $msg .= "開始時間: ". $result["start_time"] . "\n";
                $msg .= "ID: " . $result["id"] . " - 事件名稱 : " . $result["task_name"]. "\n";
            }
        }
        return [
            [
                'type' => 'text',
                'text' => $unsetTime . "--------------------------------------------"."\n" . $msg,
            ]
        ]; 
    }
}
/**
 * 確認事件是否存在
 *
 * @param PDO $DbConnection
 * @param string $id
 * @return array
 */
function checkTask($DbConnection,$id)
{
    $sql = "SELECT * FROM tasks WHERE id = :id";
    // 預備語句
    $stmt = $DbConnection->prepare($sql);
    // 綁定參數
    $stmt->bindParam(':id', $id);
    // 執行語句
    $stmt->execute();
    // 設置結果集為關聯數組
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    
    // 取得所有結果
    return $stmt->fetchAll();

}

/**
 * 新增任務
 *
 * @param PDO $DbConnection
 * @param string $task_name
 * @param string $task_description
 * @return array
 */
function insertTask($DbConnection,$task_name,$task_description)
{
    if ($task_name == '' || $task_description == '') {
        return [
            [
                'type' => 'text',
                'text' => "錯誤! 請檢查事件標題與事件描述是否為空或未用-符號將字串隔開"
            ]
        ];
    }
    try {
        // 準備 SQL 語句
        $sql = "INSERT INTO tasks (task_name, task_description) VALUES (:task_name, :task_description)";
        // 預備語句
        $stmt = $DbConnection->prepare($sql);
        // 綁定參數
        $stmt->bindParam(':task_name', $task_name);
        $stmt->bindParam(':task_description', $task_description);
        // 執行語句
        $stmt->execute();
        $inserted_id = $DbConnection->lastInsertId();
        return [
            [
                'type' => 'text',
                'text' => "任務新增完成，請設定日期"
            ],    
            [
                "type" => "template",
                "altText" => "請選擇 {$task_name} 的開始和結束時間",
                "template" => [
                    "type" => "buttons",
                    "text" => "請選擇 {$task_name} 的開始和結束時間 ， 事件描述 - {$task_description}",
                    "actions" => [
                        [
                            "type" => "datetimepicker",
                            "label" => "開始時間",
                            "data" => "action=set&key=start_time&task_id={$inserted_id}",
                            "mode" => "datetime",
                            "initial" => date("Y-m-d\TH:i"),
                            "min" => date("Y-m-d\TH:i"),
                            // "max" => date("Y-m-d\TH:i", strtotime("+1 week")),
                        ],
                        [
                            "type" => "datetimepicker",
                            "label" => "結束時間",
                            "data" => "action=set&key=end_time&task_id={$inserted_id}",
                            "mode" => "datetime",
                            "initial" => date("Y-m-d\TH:i", strtotime("+2 hours")),
                            "min" => date("Y-m-d\TH:i"),
                            // "max" => date("Y-m-d\TH:i", strtotime("+1 week"))
                        ]
                    ]
                ]
            ]
        ];
    }catch (PDOException $pDOException){
        return [
            [
                'type' => 'text',
                'text' => "新增任務發生錯誤"
            ]
        ];
    }

}

/**
 * 事件開始時間設定
 *
 * @param PDO $DbConnection
 * @param string $id
 * @param string $start_time
 * @return array
 */
function updateStartTime($DbConnection,$id,$start_time) 
{
    try {
        // 準備 SQL 語句
       $sql = "UPDATE tasks SET start_time = :start_time WHERE id = :task_id";
       // 預備語句
       $stmt = $DbConnection->prepare($sql);
       // 綁定參數
       $stmt->bindParam(':start_time', $start_time);
       $stmt->bindParam(':task_id', $id);
       // 執行語句
       $stmt->execute();
       return [
            [
                'type' => 'text',
                'text' => "開始時間設定完成 - " . date('Y-m-d H:i:s',strtotime($start_time))
            ]
        ];
   }catch (PDOException $pDOException){
       return [
           [
               'type' => 'text',
               'text' => "新增任務開始時間發生錯誤"
           ]
       ];
   }
}

/**
 * 事件結束時間設定
 *
 * @param PDO $DbConnection
 * @param string $id
 * @param string $end_time
 * @return array
 */
function updateEndTime($DbConnection,$id,$end_time)
{
    try {
        // 準備 SQL 語句
       $sql = "UPDATE tasks SET end_time = :end_time WHERE id = :task_id";
       // 預備語句
       $stmt = $DbConnection->prepare($sql);
       // 綁定參數
       $stmt->bindParam(':end_time', $end_time);
       $stmt->bindParam(':task_id', $id);
       // 執行語句
       $stmt->execute();
       return [
            [
                'type' => 'text',
                'text' => "結束時間設定完成 - " . date('Y-m-d H:i:s',strtotime($end_time) )
            ]
        ];
    }catch (PDOException $pDOException){
        return [
            [
                'type' => 'text',
                'text' => "新增任務開始時間發生錯誤"
            ]
        ];
    }
    
}

/**
 * 修改事件名稱與描述
 *
 * @param PDO $DbConnection
 * @param string $id
 * @param string $task_name
 * @param string $task_description
 * @return array
 */
function updateTask($DbConnection,$id,$task_name,$task_description)
{
    try {
        $sql = "UPDATE tasks SET task_name = :task_name,task_description=:task_description WHERE id = :task_id";
        // 預備語句
        $stmt = $DbConnection->prepare($sql);
        // 綁定參數
        $stmt->bindParam(':task_id', $id);
        $stmt->bindParam(':task_name', $task_name);
        $stmt->bindParam(':task_description', $task_description);
        // 執行語句
        $stmt->execute();
        return [
            [
                'type' => 'text',
                'text' => "修改成功"
            ]
        ];
    }catch(PDOException $e){
        return [
            [
                'type' => 'text',
                'text' => "更新失敗",
            ]
        ]; 
    }
    
}

/**
 * 刪除任務
 *
 * @param PDO $DbConnection
 * @param string $id
 * @return array
 */
function deleteTask($DbConnection,$id)
{
    try {
        $sql = "DELETE FROM tasks WHERE id = :task_id";
        // 預備語句
        $stmt = $DbConnection->prepare($sql);
        // 綁定參數
        $stmt->bindParam(':task_id', $id);
        // 執行語句
        $stmt->execute();
        return [
            [
                'type' => 'text',
                'text' => "刪除成功"
            ]
        ];
    } catch(PDOException $e) {
        return [
            [
                'type' => 'text',
                'text' => "失敗成功"
            ]
        ];
    }

}


?>