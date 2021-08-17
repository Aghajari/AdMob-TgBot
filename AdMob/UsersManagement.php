<?php namespace usersmanagement;

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

define('JSON_FILENAME', 'abc_users.dat');

class UsersManagement {

private function set(&$res){
    file_put_contents(JSON_FILENAME, json_encode($res));
}

private function get(){
    if (!file_exists(JSON_FILENAME) or filesize(JSON_FILENAME) == 0) {
        $res = ['users' => []];
        $this->set($res);
        return $res;
    } else {
        return json_decode(file_get_contents(JSON_FILENAME), true);
    }
}

function exists($chat_id){
    $res = $this->get();
    return array_key_exists($chat_id, $res['users']);
}

function addUser($chat_id) {
    $res = $this->get();
    if (!array_key_exists($chat_id, $res['users'])) {
        $res['users'][$chat_id] = [];
    }
    $this->set($res);
}

function removeUser($chat_id){
    $res = $this->get();
    unset($res['users'][$chat_id]);
    $this->set($res);
}

function getUsers(){
    return $this->get()['users'];
}

function addAppToUser($chat_id, $appID){
    $res = $this->get();
    if (!in_array($appID, $res['users'][$chat_id])) {
        $res['users'][$chat_id][] = $appID;
        $this->set($res);
    }
}

function countOfApps($chat_id) {
    $res = $this->get();
    if (!array_key_exists($chat_id, $res['users']))
        return 0;
    return count($res['users'][$chat_id]);
}

function countOfUsers() {
    $res = $this->get();
    return count($res['users']);
}

function removeAppFromUser($chat_id, $appID){
    $res = $this->get();
    if (in_array($appID, $res['users'][$chat_id])) {
        unset($res['users'][$chat_id][array_search($appID,$res['users'][$chat_id])]);
        $userApps = array();
        foreach ($res['users'][$chat_id] as $appID) {
            $userApps[] = $appID;
        }
        $res['users'][$chat_id] = $userApps;
        $this->set($res);
    }
}

function getUserApps($chat_id){
    $res = $this->get();
    return $res['users'][$chat_id];
}

}