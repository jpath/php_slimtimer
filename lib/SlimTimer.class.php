<?php
/*
 * Copyright 2008 James Hughes
 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.

 */
class SlimTimer {

  public $slimTimerUrl = "http://www.slimtimer.com";
  public $user_id = "";
  public $access_token = "";
  public $extra_tags = array();

  function __construct($email, $password, $api_key, $http = NULL) {
    if(!isset($http)) {
      $this->http = &new HTTP_Request($this->slimTimerUrl);
    }
    else $this->http = $http;
    $this->apiUserEmail = $email;
    $this->apiUserPassword = $password;
    $this->slimtimerApiKey = $api_key;
    $this->headers = array("Accept" => "application/xml", "Content-Type" => "application/xml");
  }

  public function authenticate($email = NULL, $password = NULL) {

    $post_data = array('name' => 'AuthData', 'value' => $this->getAuthenticationXml($email, $password));
    $this->setupHttp(HTTP_REQUEST_METHOD_POST, '/users/token', $post_data);
    $response = $this->makeRequest();

    if(strpos($response, "ERROR") === false) {
      $auth_pair = $this->getAuthenticationPair($response);
      $this->user_id = $auth_pair[0];
      $this->access_token = $auth_pair[1];
      return true;

    } else {
      echo $response;
      return false;
    }
  }
 

  /*
   * Task functions.
   */

  public function listTasks() {

    $path = "/users/".$this->user_id."/tasks";
    $this->setupHttp(HTTP_REQUEST_METHOD_GET, $path );

    return $this->makeRequest();
  }

  public function createTask($name, $tags=array(), $coworkers=array(), $reporters=array(), $completed_on="") {

    $post_data = array("name" => "Task", "value" => 
      $this->getTaskXml($name, $tags,$coworkers,$reporters,$completed_on));
    $path = "/users/".$this->user_id."/tasks";
    $this->setupHttp(HTTP_REQUEST_METHOD_POST, $path, $post_data );

    return $this->makeRequest();
  }

  public function updateTask($name, $task_id,$tags=array(), $coworkers=array(), $reporters=array(), $completed_on="") {

    $post_data = array("name" => "Task", "value" => 
      $this->getTaskXml($name, $tags,$coworkers,$reporters,$completed_on));
    $path = "/users/".$this->user_id."/tasks/$task_id";
    $this->setupHttp(HTTP_REQUEST_METHOD_PUT, $path, $post_data );
    return $this->makeRequest();

  }

  public function showTask($task_id) {

    $path = "/users/".$this->user_id."/tasks/$task_id";
    $this->setupHttp(HTTP_REQUEST_METHOD_GET,$path);
    return $this->makeRequest();

  }

  public function deleteTask($task_id) {
    $path = "/users/".$this->user_id."/tasks/$task_id";
    $this->setupHttp(HTTP_REQUEST_METHOD_DELETE, $path);
    return $this->makeRequest();

  }

  /*
   * Time entry functions.
   */
  public function listTimeEntries() {
    $path = "/users/".$this->user_id."/time_entries";
    $this->setupHttp(HTTP_REQUEST_METHOD_GET, $path );

    return $this->makeRequest();
  }

  public function createTimeEntry($task_id, $start_time, $duration_in_seconds, $end_time = NULL) {

    $path = "/users/".$this->user_id."/time_entries";
    $post_data = array("name" => "TimeEntry", "value" => 
      $this->getTimeEntryXml($task_id, $start_time, $duration_in_seconds, $end_time));
    $this->setupHttp(HTTP_REQUEST_METHOD_POST, $path, $post_data );
    return $this->makeRequest();
  }

  public function updateTimeEntry($task_id, $start_time, $duration_in_seconds, $end_time = NULL) {
    $path = "/users/".$this->user_id."/time_entries";
    $post_data = array("name" => "TimeEntry", "value" => 
      $this->getTimeEntryXml($task_id, $start_time, $duration_in_seconds, $end_time));
    $this->setupHttp(HTTP_REQUEST_METHOD_PUT, $path, $post_data );
    return $this->makeRequest();
  }

  public function deleteTimeEntry($time_entry_id) {
    $path = "/users/".$this->user_id."/time_entries/$time_entry_id";
    $this->setupHttp(HTTP_REQUEST_METHOD_DELETE, $path);

    return $this->makeRequest();
  }

  public function showTimeEntry($time_entry_id) {
    $path = "/users/".$this->user_id."/time_entries/$time_entry_id";
    $this->setupHttp(HTTP_REQUEST_METHOD_GET, $path);

    return $this->makeRequest();
  }

  private function makeRequest() {
    if (!PEAR::isError($this->http->sendRequest())) {
      $response_code = $this->http->getResponseCode(); 
      if( $response_code == 200 ) 
        return $this->http->getResponseBody();
      else {
        return "ERROR: Request failed with response code " . $response_code . 
          ":  " . $this->http->getResponseBody() . "\nRequest was: \n". $this->http->_postData['AuthData'];
      }
    } else {
      return "ERROR: pear";
    }
  }

  private function defaultQueryStringVars() {

    if (empty($this->access_token)) {
      echo "Access token is not set; did you forget to authenticate?";
      exit;
    }

    return array("api_key" => $this->getSlimTimerApiKey(), "access_token" => $this->access_token);

  }

  private function setupHttp($method,$path="",$post_data=NULL,$headers=NULL) {

//    $this->http = &new HTTP_Request($this->slimTimerUrl);

    $this->http->clearPostData();

    $this->http->setMethod($method);

    foreach($this->headers as $k => $v) {
      $this->http->addHeader($k, $v);
    }

    if(!empty($headers)) {
      foreach($headers as $k => $v) {
        $this->http->addHeader($k, $v);
      }
    }


    if(!empty($post_data)) {
      $this->http->addPostData($post_data['name'], $post_data['value'], true);
    } else {
      $this->http->clearPostData();
    }

    if(!empty($path)) {
      $this->http->setURL($this->slimTimerUrl.$path);
    } else {
      $this->http->setURL($this->slimTimerUrl);
    }

    // Don't add query string if we're trying to get access token.
    if( (strpos($path, "users/token") === false) )
      foreach ($this->defaultQueryStringVars() as $k => $v) {
        $this->http->addQueryString($k,$v);
      }
  }

  /*
   * Returns an array of the form [<user-id>,<access-token>]
   */
  private function getAuthenticationPair($response) {

    try {
      $xml = new SimpleXMLElement($response);
      return array($xml->{'user-id'},$xml->{'access-token'});
    } catch (Exception $e) {
      echo "Caught exception processing authentication response: ", $e->getMessage(), "\n";
      exit;
    }
  }

  private function getAuthenticationXml($email, $password) {
    $xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><request><user></user><api-key>";
    $xmlstr .= $this->slimtimerApiKey;
    $xmlstr .= "</api-key></request>"; 
    $xml = simplexml_load_string($xmlstr);

    if(isset($email)) {
      $xml->user->addChild('email', $email);
    } else {
      $xml->user->addChild('email', $this->apiUserEmail);
    }

    if(isset($password)) {
      $xml->user->addChild('password', $password);
    } else {
      $xml->user->addChild('password', $this->apiUserPassword);
    }

    return ($xml->asXML());
  }

  private function getTaskXml($taskname, $tags = NULL,
    $coworkers = NULL,$reporters = NULL,$completed_on = NULL) {

    global $DEBUG;

    $xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><task><name>$taskname</name></task>";
    $xml = simplexml_load_string($xmlstr);
    if(!empty($task_id)) {
      $xml->addChild('id', $task_id);
    }
    if(!empty($tags)) {
      $xml->addChild('tags', implode(',', $tags));
    }
    if(!empty($coworkers)) {
      $xml->addChild('coworker_emails', implode(',', $coworkers));
    }
    if(!empty($reporters)) {
      $xml->addChild('reporter_emails', implode(',', $reporters));
    }
    if(!empty($completed_on)) {
      $xml->addChild('completed_on', $completed_on);
    }
    if($DEBUG)
      return preg_replace('/\s+/', '', $xml->asXML());

    return $xml->asXML();
  }

  private function getTimeEntryXml($task_id, $start_time, $duration_in_seconds, $end_time = NULL) {

    global $DEBUG;

    $xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><time-entry><task-id>$task_id</task-id></time-entry>";
    $xml = simplexml_load_string($xmlstr);
    $xml->addChild('start-time', $start_time)->addAttribute("type", "datetime");
    if(!empty($end_time) ) {
      $xml->addChild('end-time', $end_time)->addAttribute("type", "datetime");
    }
    $xml->addChild('duration-in-seconds', $duration_in_seconds)->addAttribute("type", "integer");
    if ($DEBUG)
      return preg_replace('/\s+/', '', $xml->asXML());
    return $xml->asXML();
  }

  private function getSlimTimerApiKey() {
    return $this->slimtimerApiKey;
  }

  /*
   * The email for the User whose api key we are authenticating under.
   */
  private function getApiUserEmail() {
    return $this->apiUserEmail;
  }
  /*
   * The password for the User whose api key we are authenticating under.
   */
  private function getApiUserPassword() {
    return $this->apiUserPassword;
  }
}
?>
