<?php 
class BaseConnection {
    
    protected $ChannelSecret = null; 
    protected $ChannelAccessToken = null; 
  
    public function __construct($ChannelSecret, $ChannelAccessToken) {
        $this->ChannelSecret = $ChannelSecret; 
        $this->ChannelAccessToken = $ChannelAccessToken; 
    }

    /**
     * 讀取資訊
     *
     */
    public function cleanRequest($HttpRequestBody,$HeaderSignature) 
    {
        //驗證來源是否是LINE官方伺服器 
        $Hash = hash_hmac('sha256', $HttpRequestBody,  $this->ChannelSecret, true); 
        $HashSignature = base64_encode($Hash); 
        if($HashSignature != $HeaderSignature) 
        { 
            die('hash error!'); 
        } 
        //解析 
        $DataBody =json_decode($HttpRequestBody, true); 
        return $DataBody;
    }

    /**
     * 產生response
     *
     * @param  $Payload
     */
    public function response($Payload) 
    {
        // 傳送訊息
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/bot/message/reply');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($Payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->ChannelAccessToken
        ]);
        curl_exec($ch);
        curl_close($ch);
    }



}
?>