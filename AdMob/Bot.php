<?php

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
 
 
require_once ('GoogleAdMobHelper.php');
use googleadmobhelper\GoogleAdmobHelper;

require_once ('BaseBotApi.php');
use function TelegramBot\answerCallbackQuery;
use function TelegramBot\sendMessage;
use function TelegramBot\sendKeyboardMessage;
use function TelegramBot\deleteMessage;
use function TelegramBot\getReadableName;

require_once ('UsersManagement.php');
use usersmanagement\UsersManagement;
$UsersManagement = new UsersManagement();

function limit($x, $length) {
    if (strlen($x) <= $length) {
        return $x;
    } else {
        $y = substr($x, 0, $length) . '...';
        return $y;
    }
}

function floorDec($val, $precision = 2){
    if ($precision < 0){
        $precision = 0;
    }
    $numPointPosition = intval(strpos($val, '.'));
    if ($numPointPosition === 0){ //$val is an integer
        return $val;
    }
    return floatval(substr($val, 0, $numPointPosition + $precision + 1));
}

function endsWith( $haystack, $needle) {
    $length = strlen($needle);
    if(!$length ) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}

function addToTotal(&$total, $key, $value){
    if (isset($total[$key])) {
        $total[$key] += $value;
    } else {
        $total[$key] = $value;
    }
}

function parseCountryCodeToFlag(string $code) {
    $exceptions = [
        'en' => 'gb',
        'uk' => 'gb',
    ];
    $code = str_replace(array_keys($exceptions), array_values($exceptions), $code);
    $emoji = [];
    foreach(str_split($code) as $c) {
        if(($o = ord($c)) > 64 && $o % 32 < 27) {
            $emoji[] = hex2bin("f09f87" . dechex($o % 32 + 165));
            continue;
        }
        $emoji[] = $c;
    }
    return join($emoji);
}

function report(GoogleAdmobHelper $helper, $chat_id, $start_date, $end_date, $dimensions = 'APP', $filterApp = null) {
    try{
        $sd = explode("/", $start_date);
        $ed = explode("/", $end_date);
    } catch (Exception $e){
        $sd = explode("/", date("Y/m/d"));
        $ed = $sd;
    }
                        
    $report_spec = ['report_spec' =>
        ['date_range' => [
            'start_date' => ['year' => (int) $sd[0], 'month' => (int) $sd[1], 'day' => (int) $sd[2]], 
            'end_date' => ['year' => (int) $ed[0], 'month' => (int) $ed[1], 'day' => (int) $ed[2]]
        ],
        'dimensions' => [$dimensions],
        'metrics' => ['ESTIMATED_EARNINGS', 'CLICKS', 'SHOW_RATE', 'MATCH_RATE', 
                    'AD_REQUESTS', 'IMPRESSIONS', 'IMPRESSION_CTR', 'IMPRESSION_RPM', 'MATCHED_REQUESTS'],
        'sortConditions' =>[
            ['metric'=>'ESTIMATED_EARNINGS', 'order'=> 'DESCENDING']
        ]
        ]
    ];
    
    $UsersManagement = new UsersManagement();     
    if (isset($filterApp)){
        $report_spec['report_spec']['dimensionFilters'] = [
            'dimension' => 'APP',
            'matchesAny' => ['values' => $filterApp]
        ];
    } else if ($UsersManagement->countOfApps($chat_id) > 0) {
        $report_spec['report_spec']['dimensionFilters'] = [
            'dimension' => 'APP',
            'matchesAny' => ['values' => $UsersManagement->getUserApps($chat_id)]
        ];
    }
    return $helper->networkReport(json_encode($report_spec));
}

function reportToTelegram(&$result, $chat_id, $start_date, $end_date, $dimension = 'APP', $filterApp = null){
    
    $header = (isset($filterApp) and $dimension != 'APP') ? "🆔 <code>$filterApp</code>\n" : "";
    if ($start_date == $end_date) {
        $header .= "📅 $start_date";
    } else {
        $sdt = new DateTime($start_date);
        $edt = new DateTime($end_date);
        $difference = $sdt->diff($edt);
        $header .= "📅 from $start_date to $end_date";
        if ($difference->days > 0) {
            $header .= " (". $difference->days ." days)";
        }
    }
    $header .= "\n\n";
                    
    $index = 0;
    $outputs = array();
    foreach ($result as $row_data) {
        if (isset($row_data['row'])) {
            $row = $row_data['row'];
            $index++;
    
            $income = (double) (trim($row['metricValues']['ESTIMATED_EARNINGS']['microsValue']) / 1000000); 
            $income = floorDec($income);
                    
            if ($dimension == "COUNTRY" and ($income == 0 or $index >= 11)){
                break;
            }
                     
            $eCPM = 0;
            if (isset($row['metricValues']['IMPRESSION_RPM']['doubleValue'])){       
                $eCPM = $row['metricValues']['IMPRESSION_RPM']['doubleValue'];
            }
            $eCPM = floorDec($eCPM, 3);
                      
            $CTR = 0;      
            if (isset($row['metricValues']['IMPRESSION_CTR']['doubleValue'])){
                $CTR = $row['metricValues']['IMPRESSION_CTR']['doubleValue'];
            }
            $CTR *= 100;
            $CTR = floorDec($CTR);

            $showRate = 0;
            if (isset($row['metricValues']['SHOW_RATE']['doubleValue'])){
                $showRate = $row['metricValues']['SHOW_RATE']['doubleValue'];
            }
            $showRate *= 100;
            $showRate = floorDec($showRate);

            $matchRate = 0;
            if (isset($row['metricValues']['MATCH_RATE']['doubleValue'])){
                $matchRate = $row['metricValues']['MATCH_RATE']['doubleValue'];
            }
            $matchRate *= 100;
            $matchRate = floorDec($matchRate);
                    
            if ($dimension == "COUNTRY"){
                $code = $row['dimensionValues'][$dimension]['value'];
                $res = parseCountryCodeToFlag($code) . " $code ($index) \n";
            } else {
                $res = ($index % 2 == 0 ? "🔶 " : "🔷 ") . $row['dimensionValues'][$dimension]['displayLabel'] . " ($index) \n";
                $res .= "🆔 <code>" . $row['dimensionValues'][$dimension]['value'] . "</code>\n";
            }
            $res .= "💰 $" . $income . "\n";
            $res .= "ℹ️ <b>eCPM:</b> $" . $eCPM . "\n";
            $res .= "ℹ️ <b>Clicks:</b> " . number_format($row['metricValues']['CLICKS']['integerValue']) . "\n";
            $res .= "ℹ️ <b>Requests:</b> " . number_format($row['metricValues']['AD_REQUESTS']['integerValue']) . "\n";
            $res .= "ℹ️ <b>MatchedRequests:</b> ". number_format($row['metricValues']['MATCHED_REQUESTS']['integerValue']) . "\n";
            $res .= "ℹ️ <b>Impr:</b> " . number_format($row['metricValues']['IMPRESSIONS']['integerValue']) . "\n";
            $res .= "ℹ️ <b>Impr. CTR:</b> " . $CTR . "%\n";
            $res .= "ℹ️ <b>ShowRate:</b> " . $showRate . "%\n";
            $res .= "ℹ️ <b>MatchRate:</b> " . $matchRate . "%\n";
            $res .= "\n";
            $outputs[] = $res;
        }
    }
                
    if (count($outputs) <= 10) {
        $res = $header;
        foreach ($outputs as $row_text) {
        $res .= $row_text;
        }
        sendMessage($chat_id, $res, "html");
    } else {
        $startIndex = 0;
        while (count($outputs) > $startIndex) {
            $res = $header;
            $diff = min(count($outputs) - $startIndex, 10);
            for ($i = $startIndex; $i < $startIndex + $diff; $i++) {
                $res .= $outputs[$i];
            }
            $header = "";
            $startIndex += $diff;
            if ($diff > 0) {
                sendMessage($chat_id, $res, "html");
            }
        }
    }
}

function doneAction($chat_id){
    file_put_contents("actions/$chat_id.admin.dat", "");
    unlink("actions/$chat_id.admin.dat");
}

//
$update = json_decode(file_get_contents('php://input'));
//

if (isset($update->callback_query)){
    $callback = $update->callback_query;
    
    if (isset($callback->message)){
        $message = $callback->message; 
        $chat_id = $message->chat->id;

        $callback_data = $callback->data;
        
        answerCallbackQuery($callback->id);
        
         if (!in_array($chat_id, ADMINS) and !$UsersManagement->exists($chat_id)) {
             sendMessage($chat_id, "You don't have access to this bot!");
             exit;
         }
        
        if ($callback_data == '/cancel'){
            doneAction($chat_id);
            deleteMessage($chat_id, $message->message_id);
            exit;
        } else if (substr($callback_data, 0, strlen("/removeuser")) === "/removeuser"){
            doneAction($chat_id);
            deleteMessage($chat_id, $message->message_id);
            $cmd_data = trim(substr($callback_data, strlen("/removeuser")));
            
            if ($UsersManagement->exists($cmd_data)) {
                $UsersManagement->removeUser($cmd_data);
                sendMessage($chat_id, getReadableName($cmd_data) . " doesn't have access to the bot anymore!");
            } else {
                sendMessage($chat_id, getReadableName($cmd_data) . " didn't had access to the bot!");
            }
            exit;
        } else if (substr($callback_data, 0, strlen("/addapptouser")) === "/addapptouser"){
            deleteMessage($chat_id, $message->message_id);
            $cmd_data = trim(substr($callback_data, strlen("/addapptouser")));
            if ($UsersManagement->exists($cmd_data)) {
                file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'add_app2', 'id' => $cmd_data]));
                $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                sendKeyboardMessage($chat_id, "Send the unique ID of the application...", $keyboard);
            } else {
                sendMessage($chat_id, getReadableName($cmd_data) . " isn't a member of the bot!");
            }
            exit;
        } else if (substr($callback_data, 0, strlen("/removeapp2")) === "/removeapp2"){
            deleteMessage($chat_id, $message->message_id);
            $cmd_data = trim(substr($callback_data, strlen("/removeapp2")));
            $actions = json_decode(file_get_contents("actions/$chat_id.admin.dat"), true);
            $UsersManagement->removeAppFromUser($actions['id'], $cmd_data);
            sendMessage($chat_id, "```$cmd_data``` Removed,\n". getReadableName($actions['id']) . " doesn't have access to this app anymore.");
            
            if ($UsersManagement->countOfApps($actions['id']) == 0) {
                sendMessage($chat_id, getReadableName($actions['id']) . " doesn't have access to any app!");
            }else {
                $userApps = array();
                foreach ($UsersManagement->getUserApps($actions['id']) as $appID) {
                    $userApps[] = [['text' => getReadableName($appID), 'callback_data' => "/removeapp2 $appID"]];
                }
                $userApps[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                $keyboard = ['inline_keyboard' => $userApps];
                sendKeyboardMessage($chat_id, "Select ID to remove Or just send the unique ID of the application ...", $keyboard);
            }
            exit;
        } else if (substr($callback_data, 0, strlen("/removeappfromuser")) === "/removeappfromuser"){
            deleteMessage($chat_id, $message->message_id);
            $cmd_data = trim(substr($callback_data, strlen("/removeappfromuser")));
            if ($UsersManagement->exists($cmd_data)) {
                if ($UsersManagement->countOfApps($cmd_data) == 0) {
                    sendMessage($chat_id, getReadableName($cmd_data) . " doesn't have access to any app!");
                } else {
                    file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'remove_app2', 'id' => $cmd_data]));
                    $userApps = array();
                    foreach ($UsersManagement->getUserApps($cmd_data) as $appID) {
                       $userApps[] = [['text' => getReadableName($appID), 'callback_data' => "/removeapp2 $appID"]];
                    }
                    $userApps[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                    $keyboard = ['inline_keyboard' => $userApps];
                    sendKeyboardMessage($chat_id, "Select ID to remove Or just send the unique ID of the application ...", $keyboard);
                }
            } else {
                sendMessage($chat_id, getReadableName($cmd_data) . " isn't a member of the bot!");
            }
            exit;
        }
        
        if (substr($callback_data, 0, strlen("/report")) === "/report"){
            $filterApp = null;
            $dimension = 'APP';
            $range_info = trim(substr($callback_data, strlen("/report")));
            
            if ($range_info == 'today'){
                $start_date = date("Y/m/d");
                $end_date = $start_date;
            } else if ($range_info == "yesterday"){
                $start_date = date('Y/m/d',strtotime("-1 days"));
                $end_date = $start_date;
            } else if ($range_info == "last month"){
                $start_date = date('Y/m/d', strtotime('first day of last month'));
                $end_date = date('Y/m/d', strtotime('last day of last month'));
            } else if ($range_info == "this month"){
                $start_date = date('Y/m/d', strtotime('first day of this month'));
                $end_date = date("Y/m/d");
            } else if ($range_info == "last week"){
                $start_date = date('Y/m/d',strtotime("last week"));
                $end_date = date("Y/m/d");
            }
        } else {
            $filterApp = $callback_data;
            
            deleteMessage($chat_id, $message->message_id);
                
            $out = file_get_contents("actions/$chat_id.dat");
            $local_result = json_decode($out, true);
            $start_date = $local_result['start_date'];
            $end_date = $local_result['end_date'];
        
            if ($local_result['cmd'] == "/reportbycountry") {
                $dimension = "COUNTRY";
            } else if ($local_result['cmd'] == "/reportbyadunit") {
                $dimension = "AD_UNIT";
            } else {
                $dimension = 'APP';
            }
        }
        
        $helper = new GoogleAdmobHelper();

        if ($helper->isTokenReady()) {
            $loading = sendmessage($chat_id, "Wait a moment...");
            if (isset($filterApp)) {
                $data = report($helper, $chat_id, $start_date, $end_date, $dimension, [$filterApp]);
            } else {
                $data = report($helper, $chat_id, $start_date, $end_date, $dimension);
            }
            $result = json_decode($data, true);
            //sendmessage($chat_id, json_encode($result));

            if (isset($loading)) {
                deleteMessage($chat_id, $loading->result->message_id);
            }
            reportToTelegram($result, $chat_id, $start_date, $end_date, $dimension, $filterApp);

        } else {
            sendMessage($chat_id, "You don't have access to token!");
        }
    }
    //END OF CALLBACK QUERY 
} else if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id;

    if (isset($message->text)) {
        $text = strtolower(trim($message->text));

        if (in_array($chat_id, ADMINS) or $UsersManagement->exists($chat_id)) {
            
            if (!in_array($chat_id, ADMINS) and $UsersManagement->countOfApps($chat_id) == 0) {
                sendMessage($chat_id, "There is no app for you that you can access!");
                exit;
            }
            
            $cmd = "/report";
            if ($text == '/start') {
                doneAction($chat_id);
                $cmds = "<b>Commands:</b> \n";
                $cmds .= "/admob (Est. AdMob Network)\n";
                $cmds .= "$cmd Today (Or Yesterday)\n";
                $cmds .= "$cmd Last Month (Or Year)\n";
                $cmds .= "$cmd This Month (Or Year)\n";
                $cmds .= "$cmd 7days (X days or months)\n";
                $cmds .= "$cmd 2020/2/20 to 2021/2/1\n";
                $cmds .= $cmd . "ByApp (Date optional)\n";
                $cmds .= $cmd . "ByAdUnit (Date optional)\n";
                $cmds .= $cmd . "ByCountry (Date optional)\n";
                $cmds .= "/help\n";
                                
                if (in_array($chat_id, ADMINS)){
                    $cmds .= "\n<b>Admin Commands:</b>\n";
                    $cmds .= "/token\n";
                    $cmds .= "/revoke_token\n";
                    $cmds .= "/addUser\n";
                    $cmds .= "/removeUser\n";
                    $cmds .= "/addAppToUser\n";
                    $cmds .= "/removeAppFromUser\n";
                }
                
                if ($UsersManagement->countOfApps($chat_id) > 0) {
                    $cmds .= "\n<b>Apps:</b> " . $UsersManagement->countOfApps($chat_id);
                }
                
                $keyboard = ['inline_keyboard' => [
                        [
                            ['text' => "Today", 'callback_data' => "/report today"],
                            ['text' => "Yesterday", 'callback_data' => "/report yesterday"]
                        ],
                        [
                            ['text' => "This Month", 'callback_data' => "/report this month"],
                            ['text' => "Last Month", 'callback_data' => "/report last month"]
                        ],
                        [
                            ['text' => "Report Last Week", 'callback_data' => "/report last week"]
                        ]
                    ]];
                
                sendKeyboardMessage($chat_id, "Hello\n\n$cmds", $keyboard, "html");
            }
            
            if (substr($text, 0, strlen("/reportbyadunit")) === "/reportbyadunit"){
                $usedCmd = "/reportbyadunit";
                $actions = true;
            } else if (substr($text, 0, strlen("/reportbycountry")) === "/reportbycountry"){
                $usedCmd = "/reportbycountry";
                $actions = true;
            } else if (substr($text, 0, strlen("/reportbyapp")) === "/reportbyapp"){
                $usedCmd = "/reportbyapp";
                $actions = true;
            } else {
                $usedCmd = $cmd;
                $actions = false;
            }
            
            if (substr($text, 0, strlen($usedCmd)) === $usedCmd) {
                doneAction($chat_id);
                $range_info = trim(substr($text, strlen($usedCmd)));
                
                $start_date = date("Y/m/d");
                $end_date = $start_date;
                
                if (strpos($range_info, ' to ') !== false) {
                    $ranges = explode("to", $range_info);
                    $start_date = trim($ranges[0]);
                    $end_date = trim($ranges[1]);
                } else if (strpos($range_info, '/') !== false) {
                    $start_date = trim($range_info);
                    $end_date = $start_date;
                } else if ($range_info == "yesterday"){
                    $start_date = date('Y/m/d',strtotime("-1 days"));
                    $end_date = $start_date;
                } else if ($range_info == "last month"){
                    $start_date = date('Y/m/d', strtotime('first day of last month'));
                    $end_date = date('Y/m/d', strtotime('last day of last month'));
                } else if ($range_info == "last year"){
                    $start_date = date('Y/m/d', strtotime('first day of january last year'));
                    $end_date = date('Y/m/d', strtotime('last day of december last year'));
                } else if ($range_info == "this month"){
                    $start_date = date('Y/m/d', strtotime('first day of this month'));
                } else if ($range_info == "this year"){
                    $start_date = date('Y/m/d', strtotime('first day of january this year'));
                } else if (endsWith($range_info,"days") && strlen($range_info) > 4){
                    $diff = (int) trim(substr($range_info, 0, -4));
                    $start_date = date('Y/m/d',strtotime("-$diff days"));
                } else if (endsWith($range_info,"months") && strlen($range_info) > 6){
                    $diff = (int) trim(substr($range_info, 0, -6));
                    $start_date = date('Y/m/d',strtotime("-$diff months"));
                } else if (endsWith($range_info,"years") && strlen($range_info) > 5){
                    $diff = (int) trim(substr($range_info, 0, -5));
                    $start_date = date('Y/m/d',strtotime("-$diff years"));
                } else if ($range_info == "last week"){
                    $start_date = date('Y/m/d',strtotime("last week"));
                } else if ($range_info == "today"){
                } else {
                    $start_date = date('Y/m/d',strtotime("-7 days"));
                }
                
                $helper = new GoogleAdmobHelper();

                if ($helper->isTokenReady()) {
                    $loading = sendmessage($chat_id, "Wait a moment...");
                    $data = report($helper, $chat_id, $start_date, $end_date);
                    $result = json_decode($data, true);
                    //sendmessage($chat_id, json_encode($result));

                    if (isset($loading)) {
                        deleteMessage($chat_id, $loading->result->message_id);
                    }
                    
                    if ($actions) {
                        file_put_contents("actions/$chat_id.dat", json_encode([
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'cmd' => $usedCmd
                            ]));
                            
                        $apps = array();
                        foreach ($result as $row_data) {
                            if (isset($row_data['row'])){
                                $row = $row_data['row'];
                                $apps[] = [['text' => $row['dimensionValues']['APP']['displayLabel'], 'callback_data' => $row['dimensionValues']['APP']['value']]];
                            }
                        }
                        $keyboard = ['inline_keyboard' => $apps];
                        sendKeyboardMessage($chat_id, "🔎 Select an app to report...", $keyboard);
                    } else {
                        reportToTelegram($result, $chat_id, $start_date, $end_date);
                    }
                } else {
                    sendMessage($chat_id, "You don't have access to token!");
                }
            }
            
            if ($text == "/admob") {
                doneAction($chat_id);
                $helper = new GoogleAdmobHelper();
                if ($helper->isTokenReady()) {
                    $loading = sendmessage($chat_id, "Wait a moment...");
                    $today_str = date("Y/m/d");
                    $yesterday_str = date('Y/m/d',strtotime("-1 days"));
                    
                    $load_info = [
                            [
                                'title' => 'Today so far',
                                'start_date' => $today_str,
                                'end_date' => $today_str,
                            ],
                            [
                                'title' => 'Yesterday',
                                'start_date' => $yesterday_str,
                                'end_date' => $yesterday_str,
                            ],
                            [
                                'title' => 'This month so far',
                                'start_date' => date('Y/m/d', strtotime('first day of this month')),
                                'end_date' => $today_str,
                            ],
                            [
                                'title' => 'Last month',
                                'start_date' => date('Y/m/d', strtotime('first day of last month')),
                                'end_date' => date('Y/m/d', strtotime('last day of last month')),
                            ],
                        ];
                        
                    $res = "Est. AdMob Network earnings\n\n";
                    
                    foreach ($load_info as $info) {
                        $res .= "<b>" . $info['title'] . ":</b>\n";
                        $data = report($helper, $chat_id, $info['start_date'], $info['end_date']);
                        $result = json_decode($data, true);
                        
                        $total = array();
                        $index = 0;
                        $indexCTR = 0;
                        $indexShowRate = 0;

                        foreach ($result as $row_data) {
                            if (isset($row_data['row'])) {
                                $row = $row_data['row'];
                                $index++;
                                
                                addToTotal($total,'ESTIMATED_EARNINGS',$row['metricValues']['ESTIMATED_EARNINGS']['microsValue']);
                                if (isset($row['metricValues']['IMPRESSION_CTR']['doubleValue'])) {
                                    $indexCTR++;
                                    addToTotal($total,'IMPRESSION_CTR',$row['metricValues']['IMPRESSION_CTR']['doubleValue']);   
                                } else {
                                    addToTotal($total,'IMPRESSION_CTR', 0);
                                }
                                if (isset($row['metricValues']['SHOW_RATE']['doubleValue'])) {
                                    $indexShowRate++;
                                    addToTotal($total,'SHOW_RATE',$row['metricValues']['SHOW_RATE']['doubleValue']);
                                } else {
                                    addToTotal($total,'SHOW_RATE', 0);
                                }
                                addToTotal($total,'MATCH_RATE',$row['metricValues']['MATCH_RATE']['doubleValue']);
                                addToTotal($total,'CLICKS',$row['metricValues']['CLICKS']['integerValue']);
                                addToTotal($total,'AD_REQUESTS',$row['metricValues']['AD_REQUESTS']['integerValue']);
                                addToTotal($total,'MATCHED_REQUESTS',$row['metricValues']['MATCHED_REQUESTS']['integerValue']);
                                addToTotal($total,'IMPRESSIONS',$row['metricValues']['IMPRESSIONS']['integerValue']);
                            }
                        }
                        
                        $income = (double) (trim($total['ESTIMATED_EARNINGS']) / 1000000);
                            
                        $eCPM = $income;
                        $eCPM /= $total['IMPRESSIONS'];
                        $eCPM *= 1000;
                        $income = floorDec($income);
                        $eCPM = floorDec($eCPM, 3);
            
                        $CTR = $total['IMPRESSION_CTR'];
                        $CTR /= $indexCTR;
                        $CTR *= 100;
                        $CTR = floorDec($CTR);

                        $showRate = $total['SHOW_RATE'];
                        $showRate /= $indexShowRate;
                        $showRate *= 100;
                        $showRate = floorDec($showRate);

                        $matchRate = $total['MATCH_RATE'];
                        $matchRate /= $index;
                        $matchRate *= 100;
                        $matchRate = floorDec($matchRate);
                        
                        $res .= "💰 $" . $income . "\n";
                        $res .= "ℹ️ <b>eCPM:</b> $" . $eCPM . "\n";
                        $res .= "ℹ️ <b>Clicks:</b> " . number_format($total['CLICKS']) . "\n";
                        $res .= "ℹ️ <b>Requests:</b> " . number_format($total['AD_REQUESTS']) . "\n";
                        $res .= "ℹ️ <b>MatchedRequests:</b> " . number_format($total['MATCHED_REQUESTS']) . "\n";
                        $res .= "ℹ️ <b>Impr:</b> " . number_format($total['IMPRESSIONS']) . "\n";
                        $res .= "ℹ️ <b>Impr. CTR:</b> " . $CTR . "%\n";
                        $res .= "ℹ️ <b>ShowRate:</b> " . $showRate . "%\n";
                        $res .= "ℹ️ <b>MatchRate:</b> " . $matchRate . "%\n";
                        $res .= "\n";
                    }
                    
                    sendMessage($chat_id, $res, "html");
                    
                    if (isset($loading)) {
                        deleteMessage($chat_id, $loading->result->message_id);
                    }
                } else {
                    sendMessage($chat_id, "You don't have access to token!");
                }
            }
            
            if ($text == '/help') {
                doneAction($chat_id);
                $help = "🆔 The unique ID\n";
                $help .= "💰 The estimated earnings.\n";
                $help .= "ℹ️ <b>eCPM:</b> The estimated earnings per thousand ad impressions.\n";
                $help .= "ℹ️ <b>Clicks:</b> The number of times a user clicks an ad.\n";
                $help .= "ℹ️ <b>Requests:</b> The number of ad requests.\n";
                $help .= "ℹ️ <b>MatchedRequests:</b> The number of times ads are returned in response to a request.\n";
                $help .= "ℹ️ <b>Impr:</b> The total number of ads shown to users.\n";
                $help .= "ℹ️ <b>Impr. CTR:</b> The ratio of clicks over impressions.\n";
                $help .= "ℹ️ <b>ShowRate:</b> The ratio of ads that are displayed over ads that are returned, defined as impressions / matched requests.\n";
                $help .= "ℹ️ <b>MatchRate:</b> The ratio of matched ad requests over the total ad requests.";
                sendMessage($chat_id, $help, "html");
            }

            if (in_array($chat_id, ADMINS)) {
                
                $usedCmd = "/adduser";
                if (substr($text, 0, strlen($usedCmd)) === $usedCmd){
                    $cmd_data = trim(substr($text, strlen($usedCmd)));
                    
                    if (strlen($cmd_data) > 4){
                        $UsersManagement->addUser($cmd_data);
                        sendMessage($chat_id, getReadableName($cmd_data) . " has access to the bot now!");
                    } else {
                        file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'add_user']));
                        
                        $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                        sendKeyboardMessage($chat_id, "Send a UserID...", $keyboard);
                    }
                    exit;
                }
                
                $usedCmd = "/removeuser";
                if (substr($text, 0, strlen($usedCmd)) === $usedCmd){
                    $cmd_data = trim(substr($text, strlen($usedCmd)));
                    
                    if ($UsersManagement->countOfUsers() == 0){
                        sendMessage($chat_id, "No one has access to the bot!");
                        exit;
                    }
                    
                    if (strlen($cmd_data) > 4){
                        if ($UsersManagement->exists($cmd_data)) {
                            $UsersManagement->removeUser($cmd_data);
                            sendMessage($chat_id, getReadableName($cmd_data) . " doesn't have access to the bot anymore!");
                        } else {
                            sendMessage($chat_id, getReadableName($cmd_data) . " didn't had access to the bot!");
                        }
                    } else {
                        file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'remove_user']));
                        $userNames = array();
                        foreach ($UsersManagement->getUsers() as $user => $userApps) {
                            $userNames[] = [['text' => getReadableName($user), 'callback_data' => "/removeuser $user"]];
                        }
                        $userNames[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                        $keyboard = ['inline_keyboard' => $userNames];
                        
                        sendKeyboardMessage($chat_id, "Select a user to remove Or just send the UserID...", $keyboard);
                    }
                    exit;
                }
                
                $usedCmd = "/addapptouser";
                if (substr($text, 0, strlen($usedCmd)) === $usedCmd){
                    $cmd_data = trim(substr($text, strlen($usedCmd)));
                    
                    if ($UsersManagement->countOfUsers() == 0){
                        sendMessage($chat_id, "No one has access to the bot!");
                        exit;
                    }
                    
                    if (strlen($cmd_data) > 4){
                        if ($UsersManagement->exists($cmd_data)) {
                            file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'add_app2', 'id' => $cmd_data]));
                            $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                            sendKeyboardMessage($chat_id, "Send the unique ID of the application...", $keyboard);
                        } else {
                            sendMessage($chat_id, getReadableName($cmd_data) . " isn't a member of the bot!");
                        }
                    } else {
                        file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'add_app']));
                        
                        $userNames = array();
                        foreach ($UsersManagement->getUsers() as $user => $userApps) {
                            $userNames[] = [['text' => getReadableName($user), 'callback_data' => "/addapptouser $user"]];
                        }
                        $userNames[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                        $keyboard = ['inline_keyboard' => $userNames];
                        
                        sendKeyboardMessage($chat_id, "Select a user Or just send the UserID...", $keyboard);
                    }
                    exit;
                }
                
                $usedCmd = "/removeappfromuser";
                if (substr($text, 0, strlen($usedCmd)) === $usedCmd){
                    $cmd_data = trim(substr($text, strlen($usedCmd)));
                    
                    if ($UsersManagement->countOfUsers() == 0){
                        sendMessage($chat_id, "No one has access to the bot!");
                        exit;
                    }
                    
                    if (strlen($cmd_data) > 4){
                        if ($UsersManagement->exists($cmd_data)) {
                            if ($UsersManagement->countOfApps($actions['id']) == 0) {
                                sendMessage($chat_id, getReadableName($cmd_data) . " doesn't have access to any app!");
                            } else {
                                file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'remove_app2', 'id' => $cmd_data]));
                                $userApps = array();
                                foreach ($UsersManagement->getUserApps($cmd_data) as $appID) {
                                   $userApps[] = [['text' => getReadableName($appID), 'callback_data' => "/removeapp2 $appID"]];
                                }
                                $userApps[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                                $keyboard = ['inline_keyboard' => $userApps];
                                sendKeyboardMessage($chat_id, "Select ID to remove Or just send the unique ID of the application ...", $keyboard);
                            }
                        } else {
                            sendMessage($chat_id, getReadableName($cmd_data) . " isn't a member of the bot!");
                        }
                    } else {
                        file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'remove_app']));
                        
                        $userNames = array();
                        foreach ($UsersManagement->getUsers() as $user => $userApps) {
                            $userNames[] = [['text' => getReadableName($user), 'callback_data' => "/removeappfromuser $user"]];
                        }
                        $userNames[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                        $keyboard = ['inline_keyboard' => $userNames];
                        
                        sendKeyboardMessage($chat_id, "Select a user to get apps Or just send the UserID...", $keyboard);
                    }
                    exit;
                }
                
                if ($text == '/revoke_token') {
                    doneAction($chat_id);
                    
                    $helper = new GoogleAdmobHelper();
                    $helper->revokeToken();
                    $authUrl = $helper->createAuthUrl();
                    sendMessage($chat_id, "Token revoked! [Login]($authUrl)");
                } else if ($text == '/token') {
                    doneAction($chat_id);
                    
                    $helper = new GoogleAdmobHelper();

                    if ($helper->isTokenReady()) {
                        sendMessage($chat_id, "Token: ```" . $helper->getSavedToken() . "```");
                    } else {
                        $authUrl = $helper->createAuthUrl();
                        sendMessage($chat_id, "No token found! [Login]($authUrl)");
                    }
                } else if (file_exists("actions/$chat_id.admin.dat") && filesize("actions/$chat_id.admin.dat") > 0) {
                    
                    $actions = json_decode(file_get_contents("actions/$chat_id.admin.dat"), true);
                    if ($actions['action'] == 'add_user' or $actions['action'] == 'remove_user' or 
                    $actions['action'] == 'add_app' or $actions['action'] == 'remove_app') {
                        if (strlen($text) > 4 and is_numeric($text)) {
                            doneAction($chat_id);
                            if ($actions['action'] == 'add_app') {
                                if ($UsersManagement->exists($text)) {
                                    file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'add_app2', 'id' => $text]));
                                    $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                                    sendKeyboardMessage($chat_id, "Send the unique ID of the application...", $keyboard);
                                } else {
                                    sendMessage($chat_id, getReadableName($text) . " isn't a member of the bot!");
                                }
                            } else if ($actions['action'] == 'remove_app') {
                                if ($UsersManagement->exists($text)) {
                                    if ($UsersManagement->countOfApps($text) == 0) {
                                        sendMessage($chat_id, getReadableName($text) . " doesn't have access to any app!");
                                    } else {
                                        file_put_contents("actions/$chat_id.admin.dat", json_encode(['action' => 'remove_app2', 'id' => $text]));
                                        $userApps = array();
                                        foreach ($UsersManagement->getUserApps($text) as $appID) {
                                           $userApps[] = [['text' => getReadableName($appID), 'callback_data' => "/removeapp2 $appID"]];
                                        }
                                        $userApps[] = [['text' => "Cancel", 'callback_data' => "/cancel"]];
                                        $keyboard = ['inline_keyboard' => $userApps];
                                        sendKeyboardMessage($chat_id, "Select ID to remove Or just send the unique ID of the application ...", $keyboard);
                                    }
                                } else {
                                    sendMessage($chat_id, getReadableName($text) . " isn't a member of the bot!");
                                }
                            } else if ($actions['action'] == 'add_user') {
                                $UsersManagement->addUser($text);
                                sendMessage($chat_id, getReadableName($text) . " has access to the bot now!");
                            } else if ($actions['action'] == 'add_user') {
                                if ($UsersManagement->exists($text)) {
                                    $UsersManagement->removeUser($text);
                                    sendMessage($chat_id, getReadableName($text) . " doesn't have access to the bot anymore!");
                                } else {
                                    sendMessage($chat_id, getReadableName($text) . " didn't had access to the bot!");
                                }
                            }
                        } else {
                            $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                            sendKeyboardMessage($chat_id, "The UserID isn't valid! Try again", $keyboard);
                        }
                    } else if ($actions['action'] == 'add_app2'){
                        $validCmd = "ca-app-pub-";
                        if (substr($text, 0, strlen($validCmd)) === $validCmd){
                            $UsersManagement->addAppToUser($actions['id'], $text);
                            sendMessage($chat_id, "```$text``` Imported,\n". getReadableName($actions['id']) . " has access to this app now.");
                            $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                            sendKeyboardMessage($chat_id, "Send more application IDs or click Cancel.", $keyboard);
                        } else {
                            $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                            sendKeyboardMessage($chat_id, "The ID isn't valid! Try again", $keyboard);
                        }
                    } else if ($actions['action'] == 'remove_app2'){
                        $validCmd = "ca-app-pub-";
                        if (substr($text, 0, strlen($validCmd)) === $validCmd){
                            $UsersManagement->removeAppFromUser($actions['id'], $text);
                            sendMessage($chat_id, "```$text``` Removed,\n". getReadableName($actions['id']) . " doesn't have access to this app anymore.");
                            $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                            sendKeyboardMessage($chat_id, "Send more application IDs to remove or click Cancel.", $keyboard);
                        } else {
                            $keyboard = ['inline_keyboard' => [[['text' => "Cancel", 'callback_data' => "/cancel"]]]];
                            sendKeyboardMessage($chat_id, "The ID isn't valid! Try again", $keyboard);
                        }
                    }
                }
            }

        } else {
            sendMessage($chat_id, "You don't have access to this bot!");
        }
    }
}
?>
