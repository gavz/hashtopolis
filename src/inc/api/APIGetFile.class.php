<?php

use DBA\Assignment;
use DBA\File;
use DBA\FileTask;
use DBA\QueryFilter;
use DBA\Factory;

class APIGetFile extends APIBasic {
  public function execute($QUERY = array()) {
    //check required values
    if (!PQueryGetFile::isValid($QUERY)) {
      $this->sendErrorResponse(PActions::GET_FILE, "Invalid file query!");
    }
    $this->checkToken(PActions::GET_FILE, $QUERY);

    // let agent download adjacent files
    $task = Factory::getTaskFactory()->get($QUERY[PQueryGetFile::TASK_ID]);
    if ($task == null) {
      $this->sendErrorResponse(PActions::GET_FILE, "Invalid task!");
    }

    $qF1 = new QueryFilter(Assignment::TASK_ID, $task->getId(), "=");
    $qF2 = new QueryFilter(Assignment::AGENT_ID, $this->agent->getId(), "=");
    $assignment = Factory::getAssignmentFactory()->filter([Factory::FILTER => [$qF1, $qF2]], true);
    if ($assignment == null) {
      $this->sendErrorResponse(PActions::GET_FILE, "Client is not assigned to this task!");
    }

    $file = $QUERY[PQueryGetFile::FILENAME];
    $qF = new QueryFilter(File::FILENAME, $file, "=");
    $file = Factory::getFileFactory()->filter([Factory::FILTER => $qF], true);
    if ($file == null) {
      $this->sendErrorResponse(PActions::GET_FILE, "Invalid file!");
    }

    $qF1 = new QueryFilter(FileTask::TASK_ID, $task->getId(), "=");
    $qF2 = new QueryFilter(FileTask::FILE_ID, $file->getId(), "=");
    $taskFile = Factory::getFileTaskFactory()->filter([Factory::FILTER => [$qF1, $qF2]], true);
    if ($taskFile == null) {
      $this->sendErrorResponse(PActions::GET_FILE, "This file is not used for the specified task!");
    }

    if ($this->agent->getIsTrusted() < $file->getIsSecret()) {
      $this->sendErrorResponse(PActions::GET_FILE, "You have no access to get this file!");
    }
    $filename = $file->getFilename();
    $extension = explode(".", $filename)[sizeof(explode(".", $filename)) - 1];

    $this->updateAgent(PActions::GET_FILE);

    $this->sendResponse(array(
        PQueryGetFile::ACTION => PActions::GET_FILE,
        PResponseGetFile::FILENAME => $filename,
        PResponseGetFile::EXTENSION => $extension,
        PResponseGetFile::RESPONSE => PValues::SUCCESS,
        PResponseGetFile::URL => "getFile.php?file=" . $file->getId() . "&token=" . $this->agent->getToken(),
        PResponseGetFile::FILESIZE => (int)$file->getSize()
      )
    );
  }
}