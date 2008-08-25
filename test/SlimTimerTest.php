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
require_once('../../simpletest/unit_tester.php');
require_once('../../simpletest/reporter.php');
require_once('../../simpletest/mock_objects.php');

require_once('../library/slimtimer/SlimTimer.class.php');

require_once "HTTP/Request.php";

Mock::generate('SlimTimer');
Mock::generate('User');
Mock::generate('HTTP_Request');

$DEBUG = true;


class SlimTimerAuthenticationTest extends UnitTestCase {
  function setUp() {
    $this->requestXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><request><user><email>bob@bob.com</email><password>secret</password></user><api-key>c3a61cdd22facd6cce20fed458bad6</api-key></request>\n"; 

    $this->responseXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><response><access-token>1144f35ca89ab6470ced9e192db8fbc9711c1788</access-token><user-id type=\"integer\">36686</user-id></response>";
    $this->email = 'bob@bob.com';
    $this->password = 'secret';
    $this->http = &new MockHTTP_Request();
    $this->http->setReturnValue('getResponseCode', 200);
    $this->st = &new SlimTimer('bob@bob.com', 'secret',"asdfasdfasdf123456",$this->http);
    $this->user = &new MockUser();
    $this->user->expect('getEmail',array());
    $this->user->expect('getSlimTimerPassword',array());
    $this->user->setReturnValue('getEmail', 'bob@bob.com');
    $this->user->setReturnValue('getSlimTimerPassword', 'secret');
  }


  function testAuthentication() {
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_POST));
    $this->http->expectOnce("addPostData");
    $this->http->expectOnce("sendRequest");
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue("sendRequest", true);
    $this->http->setReturnValue("getResponseBody", $this->responseXml);
    $this->st->authenticate();
    $this->assertEqual("36686", $this->st->user_id);
    $this->assertEqual("1144f35ca89ab6470ced9e192db8fbc9711c1788", $this->st->access_token);
  }

}

class SlimTimerTimeEntryTest extends UnitTestCase {
  function setUp() {
    $this->http = &new MockHTTP_Request();
    $this->st = &new SlimTimer('bob@bob.com', 'secret', "asdfasdfasdf123456", $this->http);
    // Set up the slimtimer object to look like authentication has been done.
    $this->st->user_id = "36868";
    $this->st->access_token = "1144f35ca89ab6470ced9e192db8fbc9711c1788";
#    $this->st->slimtimerApiKey = "asdfasdfasdf123456";
    $this->taskname = "Example";
    $this->task_id = "4";
    $this->time_entry_id = "4";
  }

  function testListTimeEntries() {

    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_GET)); 
    $this->http->expectOnce("setURL", array("http://www.slimtimer.com/users/".$this->st->user_id."/time_entries")); 
    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 
    $this->http->expectOnce("sendRequest");
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseCode', 200);

    $this->st->listTimeEntries();
  }
  function testListTimeEntriesRespondsWithErrorMessageOnError() {
    $this->http->expectOnce("sendRequest");
//    $this->http->setReturnValue('sendRequest', new PEAR_Error());
    $this->http->expectOnce("getResponseCode");
    $this->http->setReturnValue('getResponseCode', 500);
    $this->assertPattern('/ERROR: Request failed with response code 500/', $this->st->listTimeEntries());
  }

  function testCreateTimeEntry() {
    $start_time = "2006-10-09T18:00:00Z";
    $end_time = "2006-10-09T19:00:00Z";
    $duration_in_seconds = "3600";

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
      <time-entry>
        <task-id>$this->task_id</task-id>
        <start-time type=\"datetime\">$start_time</start-time>
        <end-time type=\"datetime\">$end_time</end-time>
        <duration-in-seconds type=\"integer\">$duration_in_seconds</duration-in-seconds>
      </time-entry>";

    $response_xml ="XML";

    $expect_xml = preg_replace('/\s+/','',$xml);
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_POST)); 
    $this->http->expectOnce("setURL", array("http://www.slimtimer.com/users/".$this->st->user_id."/time_entries")); 

    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 

    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 

    $this->http->expectOnce("addPostData", array("TimeEntry", $expect_xml, true));

    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseBody', $response_xml);
    $this->http->setReturnValue('getResponseCode', 200);

    $create_result = $this->st->createTimeEntry($this->task_id, $start_time, $duration_in_seconds, $end_time);
    $this->assertEqual($response_xml, $create_result);
  }

  function testUpdateTimeEntry() {
    $start_time = "2006-10-09T18:00:00Z";
    $end_time = "2006-10-09T19:00:00Z";
    $duration_in_seconds = "3600";
    $task_id = 4;

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
      <time-entry>
        <task-id>$task_id</task-id>
        <start-time type=\"datetime\">$start_time</start-time>
        <end-time type=\"datetime\">$end_time</end-time>
        <duration-in-seconds type=\"integer\">$duration_in_seconds</duration-in-seconds>
      </time-entry>";
    $response_xml ="XML";
    $expect_xml = preg_replace('/\s+/','',$xml);
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_PUT)); 
    $this->http->expectOnce("setURL", array("http://www.slimtimer.com/users/".$this->st->user_id."/time_entries")); 

    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 

    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 

    $this->http->expectOnce("addPostData", array("TimeEntry", $expect_xml, true));

    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseBody', $response_xml);
    $this->http->setReturnValue('getResponseCode', 200);

    $update_result = $this->st->updateTimeEntry($this->task_id, $start_time, $duration_in_seconds, $end_time);
  }

  function testDeleteTimeEntry() {
    $this->http->expectOnce("setURL", 
      array("http://www.slimtimer.com/users/".$this->st->user_id."/time_entries/$this->time_entry_id")); 

    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_DELETE)); 

    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 

    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 

    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseCode', 200);

    $update_result = $this->st->deleteTimeEntry($this->time_entry_id);
  }

  function testShowTimeEntry() {
    $response_xml = "XML";
    $this->http->expectOnce("setURL", 
      array("http://www.slimtimer.com/users/".$this->st->user_id."/time_entries/$this->time_entry_id")); 
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_GET)); 

    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 
    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 
    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");

    $this->http->setReturnValue('getResponseBody', $response_xml);
    $this->http->setReturnValue('getResponseCode', 200);

    $show_result = $this->st->showTimeEntry($this->time_entry_id);
    $this->assertEqual($response_xml, $show_result);
  }
}

class SlimTimerTaskTest extends UnitTestCase {
  function setUp() {
    $this->http = &new MockHTTP_Request();
    $this->st = &new SlimTimer('bob@bob.com', 'secret',"asdfasdfasdf123456", $this->http);
    // Set up the slimtimer object to look like authentication has been done.
    $this->st->user_id = "36868";
    $this->st->access_token = "1144f35ca89ab6470ced9e192db8fbc9711c1788";
#    $this->st->slimtimerApiKey = "asdfasdfasdf123456";
    $this->taskname = "Example";
    $this->task_id = "4";
  }

  function testListTasks() {
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_GET)); 
    $this->http->expectOnce("setURL", array("http://www.slimtimer.com/users/".$this->st->user_id."/tasks")); 
    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 
    $this->http->expectOnce("sendRequest");
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseCode', 200);

    $this->st->listTasks();
  }

  function testListTasksRespondsWithErrorMessageOnError() {
    $this->http->expectOnce("sendRequest");
    //$this->http->setReturnValue('sendRequest', new PEAR_Error());
    $this->http->expectOnce("getResponseCode");
    $this->http->setReturnValue('getResponseCode', 500);
    $this->assertPattern('/ERROR: Request failed with response code 500/', $this->st->listTasks());
  }

  function testCreateTask() {
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
      <task>
        <name>$this->taskname</name>
        <tags>project-tag,milestone-tag</tags>
        <coworker_emails>bob@bob.com,sally@bob.com</coworker_emails>
        <reporter_emails>bill@bob.com,sue@bob.com</reporter_emails>
      </task>";
    $response_xml ="XML";
 
    $expect_xml = preg_replace('/\s+/','',$xml);
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_POST)); 
    $this->http->expectOnce("setURL", array("http://www.slimtimer.com/users/".$this->st->user_id."/tasks")); 

    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 

    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 

    $this->http->expectOnce("addPostData", array("Task", $expect_xml, true));
    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseBody', $response_xml);
    $this->http->setReturnValue('getResponseCode', 200);

    $create_result = $this->st->createTask($this->taskname, array("project-tag", "milestone-tag"), 
                          array("bob@bob.com", "sally@bob.com"),
                          array("bill@bob.com", "sue@bob.com"));
    $this->assertEqual($response_xml, $create_result);
  }

  function testUpdateTask() {
    $completed = "2008-07-29 00:00:00";
    $task_id = '4';
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
      <task>
        <name>$this->taskname</name>
        <completed_on>$completed</completed_on>
      </task>";
    $response_xml = "XML";
    $expect_xml = preg_replace('/\s+/','',$xml);
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_PUT)); 
    $this->http->expectOnce("setURL", 
      array("http://www.slimtimer.com/users/".$this->st->user_id."/tasks/$this->task_id")); 

    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 
    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 
    $this->http->expectOnce("addPostData", array("Task", $expect_xml, true));
    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseBody', $response_xml);
    $this->http->setReturnValue('getResponseCode', 200);

    $update_result = $this->st->updateTask($this->taskname, $this->task_id,NULL,NULL,NULL,$completed);
    $this->assertEqual($response_xml, $update_result);

  }
  function testDeleteTask() {
    $this->http->expectOnce("setURL", 
      array("http://www.slimtimer.com/users/".$this->st->user_id."/tasks/$this->task_id")); 
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_DELETE)); 
    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 
    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 
    $this->http->setReturnValue('getResponseCode', 200);
    $update_result = $this->st->deleteTask($this->task_id);
  }
  function testShowTask() {
    $response_xml = "XML";
    $this->http->expectOnce("setURL", 
      array("http://www.slimtimer.com/users/".$this->st->user_id."/tasks/$this->task_id")); 
    $this->http->expectOnce("setMethod", array(HTTP_REQUEST_METHOD_GET)); 
    $this->http->expectCallCount("addHeader", 2);
    $this->http->expectAt(0,"addHeader", array("Accept", "application/xml")); 
    $this->http->expectAt(1,"addHeader", array("Content-Type", "application/xml")); 
    $this->http->expectCallCount("addQueryString", 2);
    $this->http->expectAt(0, "addQueryString", array("api_key", $this->st->slimtimerApiKey));
    $this->http->expectAt(1,"addQueryString", array("access_token", $this->st->access_token)); 
    $this->http->expectOnce("sendRequest");
    $this->http->setReturnValue('sendRequest', true);
    $this->http->expectOnce("getResponseBody");
    $this->http->setReturnValue('getResponseBody', $response_xml);
    $this->http->setReturnValue('getResponseCode', 200);
    $show_result = $this->st->showTask($this->task_id);
    $this->assertEqual($response_xml, $show_result);
  }
}

class SlimTimerTaskLiveNetworkTest extends UnitTestCase {

  function setUp() {
    global $DEBUG;

    $DEBUG = false;
    // Change these values to your real values.
    $this->st = &new SlimTimer('bob@bob.com', 'secret', $this->http);
    $this->assertTrue($this->st->authenticate());
  }

  // Integration test, uses network.
  function testEverything() {

    // Create a task
    
    try {
      $result = $this->st->createTask('Example2', array('bogus','task'));
      $xml = new SimpleXMLElement($result);
    } catch (Exception $e) {
      $this->fail($e->getMessage());
    }
    $created_id =  $xml->id;

    // List the tasks and get the one we just created.
    $tasklist = $this->st->listTasks();
    try {
      $xml = new SimpleXMLElement($tasklist);
    } catch (Exception $e) {
      $this->fail($e->getMessage());
    }

    $found = false;
    $created_id = (string)$created_id;

    foreach($xml->task as $task) {
      $task_id = (string)$task->id; 
      if($task_id == $created_id) {
        $found =  true;
      }
      if($found)
        break;
    }

    $this->assertTrue($found, "Created task not in list.");

    // Update task we just created.
    try {
      $result = $this->st->updateTask('Example', $created_id, array('newtag'));
      new SimpleXMLElement($result);
    } catch (Exception $e) {
      $this->fail($e->getMessage());
    }

    // Retrieve task and check that tag is added.
    try {
      $result = $this->st->showTask($created_id);
      $xml = new SimpleXMLElement($result);
    } catch (Exception $e) {
      $this->fail($e->getMessage());
    }
    // TODO: check that tag is added. We get the task back but adding a tag doesn't work.

    // Delete the task.
    try {
      $result = $this->st->deleteTask($created_id);
    } catch (Exception $e) {
      $this->fail($e->getMessage());
    }
    // Make sure task is not in list.
    $tasklist = $this->st->listTasks();
    try {
      $xml = new SimpleXMLElement($tasklist);
    } catch (Exception $e) {
      $this->fail($e->getMessage());
    }

    $found = false;

    foreach($xml->task as $task) {
      $task_id = (string)$task->id; 
      if($task_id == $created_id) {
        $found =  true;
      }
      if($found)
        break;
    }
    $this->assertFalse($found);
  }

}

// TODO:
//class SlimTimerTimeEntryLiveNetworkTest extends UnitTestCase {
//
//  function setUp() {
//    global $DEBUG;
//
//    $DEBUG = false;
//    $this->st = &new SlimTimer();
//    $this->assertTrue($this->st->authenticate());
//  }
//
//  // Integration test, uses network.
//  function testEverything() {
//
//    // Create a task
//    // Create a time_entry
//    try {
//      $result = $this->st->createTimeEntry('Example2', array('bogus','time_entry'));
//      $xml = new SimpleXMLElement($result);
//    } catch (Exception $e) {
//      $this->fail($e->getMessage());
//    }
//    $created_id =  $xml->id;
//
//    // List the time_entrys and get the one we just created.
//    $time_entrylist = $this->st->listTimeEntrys();
//    try {
//      $xml = new SimpleXMLElement($time_entrylist);
//    } catch (Exception $e) {
//      $this->fail($e->getMessage());
//    }
//
//    $found = false;
//    $created_id = (string)$created_id;
//
//    foreach($xml->time_entry as $time_entry) {
//      $time_entry_id = (string)$time_entry->id; 
//      if($time_entry_id == $created_id) {
//        $found =  true;
//      }
//      if($found)
//        break;
//    }
//
//    $this->assertTrue($found, "Created time_entry not in list.");
//
//    // Update time_entry we just created.
//    try {
//      $result = $this->st->updateTimeEntry('Example', $created_id, array('newtag'));
//      new SimpleXMLElement($result);
//    } catch (Exception $e) {
//      $this->fail($e->getMessage());
//    }
//
//    // Retrieve time_entry and check that tag is added.
//    try {
//      $result = $this->st->showTimeEntry($created_id);
//      $xml = new SimpleXMLElement($result);
//    } catch (Exception $e) {
//      $this->fail($e->getMessage());
//    }
//    // TODO: check that tag is added. We get the time_entry back but adding a tag doesn't work.
//
//    // Delete the time_entry.
//    try {
//      $result = $this->st->deleteTimeEntry($created_id);
//    } catch (Exception $e) {
//      $this->fail($e->getMessage());
//    }
//    // Make sure time_entry is not in list.
//    $time_entrylist = $this->st->listTimeEntrys();
//    try {
//      $xml = new SimpleXMLElement($time_entrylist);
//    } catch (Exception $e) {
//      $this->fail($e->getMessage());
//    }
//
//    $found = false;
//
//    foreach($xml->time_entry as $time_entry) {
//      $time_entry_id = (string)$time_entry->id; 
//      if($time_entry_id == $created_id) {
//        $found =  true;
//      }
//      if($found)
//        break;
//    }
//    $this->assertFalse($found);
//  }
//
//}
function __autoload($class_name) {
  $directories = array(
    '../application/models/',
    '../application/models/users/',
    '../application/models/users/base/',
    '../environment/classes/dataaccess/'
  );

  foreach($directories as $directory) {
    $try1 = ($directory.$class_name.'.php');
    $try2 = ($directory.$class_name.'.class'.'.php');
    if(file_exists($try1)) {
      require_once($try1);
    } elseif(file_exists($try2)) {
      require_once($try2);
    }      
  }
}

$test = &new GroupTest('All file tests');
$test->addTestCase(new SlimTimerAuthenticationTest());
$test->addTestCase(new SlimTimerTaskTest());
$test->addTestCase(new SlimTimerTimeEntryTest());
// Always run last
//$test->addTestCase(new SlimTimerTaskLiveNetworkTest());
$test->run(new HtmlReporter());
?>
