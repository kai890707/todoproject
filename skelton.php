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
            
        }   
        if ($params["action"] == "set") {
        }
    }else if($Event['type'] == 'message') { 
        $userMessage = $Event['message']['text'];
        
    }

    $Payload = [ 
        'replyToken' => $Event['replyToken'],
        'messages' => $reply
    ];

    $baseConnection->response($Payload);
}


?>