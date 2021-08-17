<?php namespace googleadmobhelper;

/*
 * Copyright (C) 2021 - Amir Hossein Aghajari
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
 
 
require_once ('AdMobOptions.php');

require_once ('BaseBotApi.php');
use function TelegramBot\sendMessage;

define('TOKEN_FILENAME', 'abc_tokens.dat');
define('REFRESH_TOKEN_FILENAME', 'abc_ref_token.dat');

class GoogleAdmobHelper {
    
function createAuthUrl() {
    $link = "https://accounts.google.com/o/oauth2/auth?";
    $link .= "access_type=offline&";
    $link .= "approval_prompt=force&";
    $link .= "scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fadmob." . "readonly" ."&";
    $link .= "response_type=code&";
    $link .= "client_id=" . urlencode(CLIENT_ID) . "&";
    $link .= "redirect_uri=" . urlencode(REDIRECT_URI);
    return $link;
}

function auth($code, $refresh = false) {
$curl = curl_init();

$fields = "client_id=" . urlencode(CLIENT_ID) .
  "&client_secret=" . urlencode(CLIENT_SECRET) .
  "&redirect_uri=". urlencode(REDIRECT_URI);
  
  if ($refresh){
      $fields .= "&grant_type=refresh_token&refresh_token=" . urlencode($code);
  } else {
      $fields .= "&grant_type=authorization_code&code=" . urlencode($code);
  }
  
curl_setopt_array($curl, array(
  CURLOPT_URL => "https://accounts.google.com/o/oauth2/token",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => $fields,
  CURLOPT_HTTPHEADER => array(
    "cache-control: no-cache",
    "content-type: application/x-www-form-urlencoded"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  //echo "cURL Error #:" . $err;
  return false;
} else {
    $this->saveToken($response);
    return true;
}
}

function refreshToken(){
    if (file_exists(REFRESH_TOKEN_FILENAME) && filesize(REFRESH_TOKEN_FILENAME) > 0) {
        return $this->auth(file_get_contents(REFRESH_TOKEN_FILENAME), true);
    } else {
        return false;
    }
}

function saveToken($token) {
    try{
    $time = (int)(microtime(true)*1000);
    $result = json_decode($token, true);
    
    $out = json_encode(array('startTime'=> $time, 
        'endTime' => ($time + ($result['expires_in'] * 1000)),
        'access_token'=> $result['access_token']));
    file_put_contents(TOKEN_FILENAME, $out);
    
    if (isset($result['refresh_token'])){
        file_put_contents(REFRESH_TOKEN_FILENAME, $result['refresh_token']);
    }
    
    }catch (Exception $e){
        $this->revokeToken();
    }
}

function revokeToken(){
    file_put_contents(TOKEN_FILENAME, "");
    file_put_contents(REFRESH_TOKEN_FILENAME, "");
}

function isTokenReady($try = true){
    if (file_exists(TOKEN_FILENAME) && filesize(TOKEN_FILENAME) > 0) {
        try {
            $time = (int)(microtime(true)*1000);
            $out = file_get_contents(TOKEN_FILENAME);
            $result = json_decode($out, true);
            
            // 10s delay.
            if ($result['endTime'] > ($time + 10000)) {
                return true;
            } else if ($try and $this->refreshToken()) {
                return $this->isTokenReady(false);
            }
        } catch (Exception $e){
            $this->revokeToken();
        }
    }
    return false;
}

function getSavedToken(){
    if (file_exists(TOKEN_FILENAME) && filesize(TOKEN_FILENAME) > 0) {
        $out = file_get_contents(TOKEN_FILENAME);
        $result = json_decode($out, true);
    
        return $result['access_token'];
    } else {
        return "null";
    }
}


function notifyNewToken(){
    foreach (ADMINS as $chat_id) {
        sendMessage($chat_id, "Logged In Sucessfully, Token: ```" . $this->getSavedToken() ."```");
    }
}

function post($link, $fields){
    $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $link,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => $fields,
  CURLOPT_HTTPHEADER => array(
    "authorization: Bearer " . $this->getSavedToken(),
    "cache-control: no-cache",
    "content-type: application/json"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  return "Error #:" . $err;
} else {
  return $response;
}
}

function get($link, $fields){
    $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "$link",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "authorization: Bearer " . $this->getSavedToken(),
    "cache-control: no-cache"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  return "Error #:" . $err;
} else {
  return $response;
}
}

function networkReport($json){
    return $this->post("https://admob.googleapis.com/v1/accounts/". PUBLICATION_ID ."/networkReport:generate", $json);
}

function getAppNames($appIDs) {
    $report_spec = ['report_spec' =>
        ['date_range' => [
            'start_date' => ['year' => 2010, 'month' => 1, 'day' => 1], 
            'end_date' => ['year' => 3010, 'month' => 1, 'day' => 1]
        ],
        'dimensions' => ['APP'],
        'metrics' => ['CLICKS'],
        'dimensionFilters' => [
            'dimension' => 'APP',
            'matchesAny' => ['values' => $appIDs]
        ]
        ]
    ];
    return $helper->networkReport(json_encode($report_spec));
}

}

?>