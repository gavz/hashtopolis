<?php

class TaskTest extends HashtopolisTest {
  protected $minVersion = "0.7.0";
  protected $maxVersion = "master";
  protected $runType = HashtopolisTest::RUN_FAST;

  public function init($version){
    HashtopolisTestFramework::log(HashtopolisTestFramework::LOG_INFO, "Initializing ".$this->getTestName()."...");
    parent::init($version);

    $status = true;
    // add some files
    $status &= $this->addFile("example.dict", 0);
    $status &= $this->addFile("best64.rule", 1);

    // add a hashlist
    $status &= $this->addHashlist();

    if(!$status){
      HashtopolisTestFramework::log(HashtopolisTestFramework::LOG_ERROR, "Some initialization failed, most likely tests will fail!");
    }
  }

  public function run(){
    HashtopolisTestFramework::log(HashtopolisTestFramework::LOG_INFO, "Running ".$this->getTestName()."...");
    $this->testListTasks();
    $this->testCreateTask();
    $this->testListTasks(['Test Task']);
    HashtopolisTestFramework::log(HashtopolisTestFramework::LOG_INFO, $this->getTestName()." completed");
  }

  private function testCreateTask(){
    $response = HashtopolisTestFramework::doRequest([
      "section" => "task",
			"request" => "createTask",
			"name" => "Test Task",
			"hashlistId" => 1,
			"attackCmd" => "#HL# -a 0 -r best64.rule example.dict",
			"chunksize" => 600,
			"statusTimer" => 5,
			"benchmarkType" => "speed",
			"color" => "5D5D5D",
			"isCpuOnly" => false,
			"isSmall" => false,
			"skip" => 0,
			"crackerVersionId" => 1,
			"files" => [1,2],
			"priority" => 1,
      "isPrince" => false,
      "accessKey" => "mykey"], HashtopolisTestFramework::REQUEST_UAPI);
    if($response === false){
      $this->testFailed("TaskTest:testCreateTask", "Empty response");
    }
    else if($response['response'] != 'OK'){
      $this->testFailed("TaskTest:testCreateTask", "Response not OK");
    }
    else{
      $this->testSuccess("TaskTest:testListTasks");
    }
  }

  private function testListTasks($assert = []){
    $response = HashtopolisTestFramework::doRequest([
      "section" => "task",
      "request" => "listTasks",
      "accessKey" => "mykey"], HashtopolisTestFramework::REQUEST_UAPI);
    if($response === false){
      $this->testFailed("TaskTest:testListTasks(" . implode(", ", $assert) . ")", "Empty response");
    }
    else if($response['response'] != 'OK'){
      $this->testFailed("TaskTest:testListTasks(" . implode(", ", $assert) . ")", "Response not OK");
    }
    else if(sizeof($assert) != sizeof($response['tasks'])){
      $this->testFailed("TaskTest:testListTasks(" . implode(", ", $assert) . ")", "Not matching number of tasks");
    }
    else{
      foreach($response['tasks'] as $task){
        if(!in_array($task['name'], $assert)){
          $this->testFailed("TaskTest:testListTasks(" . implode(", ", $assert) . ")", "Not matching task name");
          return;
        }
      }
      $this->testSuccess("TaskTest:testListTasks(" . implode(", ", $assert) . ")");
    }
  }

  private function addHashlist(){
    $data = base64_encode(file_get_contents(dirname(__FILE__)."/../files/example0.hash"));
    $hashtype = 0;
    $response = HashtopolisTestFramework::doRequest([
      "section" => "hashlist",
      "request" => "createHashlist",
		  "name" => "Test Hashlist",
		  "isSalted" => false,
		  "isSecret" => true,
		  "isHexSalt" => false,
		  "separator" => ":",
		  "format" => 0,
		  "hashtypeId" => $hashtype,
		  "accessGroupId" => 1,
      "data" => $data,
      "accessKey" => "mykey"], HashtopolisTestFramework::REQUEST_UAPI);
    if($response === false){
      return false;
    }
    else if($response['response'] != 'OK'){
      return false;
    }
    return true;
  }

  private function addFile($name, $type){
    $response = HashtopolisTestFramework::doRequest([
      "section" => "file",
      "request" => "addFile",
      "filename" => $name,
      "fileType" => $type,
      "source" => "inline",
      "data" => base64_encode(file_get_contents(dirname(__FILE__)."/../files/$name")),
      "accessGroupId" => 1,
      "accessKey" => "mykey"], HashtopolisTestFramework::REQUEST_UAPI);
    if($response === false){
      return false;
    }
    else if($response['response'] != 'OK'){
      return false;
    }
    return true;
  }

  public function getTestName(){
    return "Task Test";
  }
}

HashtopolisTestFramework::register(new TaskTest());