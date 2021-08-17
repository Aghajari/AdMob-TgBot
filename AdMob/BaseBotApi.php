<?php namespace TelegramBot;

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

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
        return null;
    } else {
        return json_decode($res);
    }
}

function getReadableName($chat_id) {
    $data = bot('getChat', ['chat_id' => $chat_id]);
    if (isset($data->result)){
        $data = $data->result;
    }
    
    if (isset($data->first_name)) {
        $name = $data->first_name;
        if (isset($data->last_name)) {
            $name .= " " . $data->last_name;
        }
        return $name;
    } else if (isset($data->last_name)) {
        return $data->last_name;
    } else if (isset($data->username)) {
        return $data->username;
    } else {
        return $chat_id;
    }
}

function answerCallbackQuery($callback_id){
    return bot('answerCallbackQuery', ['callback_query_id' => $callback_id]);
}

function sendMessage($chat_id, $text, $parse_mode = "Markdown") {
    return bot('sendMessage', ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode]);
}

function sendKeyboardMessage($chat_id, $text, &$keyboard, $parse_mode = "Markdown") {
    return bot('sendMessage', ['chat_id' => $chat_id, 'text' => $text, 'reply_markup'=>json_encode($keyboard), 'parse_mode' => $parse_mode]);
}

function deleteMessage($chat_id, $message_id) {
    return bot("deleteMessage",['chat_id' => $chat_id, 'message_id' => $message_id]);
}

?>