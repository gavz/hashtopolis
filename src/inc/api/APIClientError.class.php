<?php

use DBA\AgentError;
use DBA\Assignment;
use DBA\QueryFilter;
use DBA\Factory;

class APIClientError extends APIBasic {
  public function execute($QUERY = array()) {
    //check required values
    if (!PQueryClientError::isValid($QUERY)) {
      $this->sendErrorResponse(PActions::CLIENT_ERROR, "Invalid error query!");
    }
    $this->checkToken(PActions::CLIENT_ERROR, $QUERY);

    // load task wrapper
    $task = Factory::getTaskFactory()->get($QUERY[PQueryClientError::TASK_ID]);
    if ($task == null) {
      $this->sendErrorResponse(PActions::CLIENT_ERROR, "Invalid task!");
    }

    //check assignment
    $qF1 = new QueryFilter(Assignment::AGENT_ID, $this->agent->getId(), "=");
    $qF2 = new QueryFilter(Assignment::TASK_ID, $task->getId(), "=");
    $assignment = Factory::getAssignmentFactory()->filter([Factory::FILTER => [$qF1, $qF2]], true);
    if ($assignment == null) {
      $this->sendErrorResponse(PActions::CLIENT_ERROR, "Agent is not assigned to this task!");
    }

    if ($this->agent->getIgnoreErrors() <= DAgentIgnoreErrors::IGNORE_SAVE) {
      //save error message
      $error = new AgentError(null, $this->agent->getId(), $task->getId(), time(), $QUERY[PQueryClientError::MESSAGE]);
      Factory::getAgentErrorFactory()->save($error);

      $payload = new DataSet(array(DPayloadKeys::AGENT => $this->agent, DPayloadKeys::AGENT_ERROR => $QUERY[PQueryClientError::MESSAGE]));
      NotificationHandler::checkNotifications(DNotificationType::AGENT_ERROR, $payload);
      NotificationHandler::checkNotifications(DNotificationType::OWN_AGENT_ERROR, $payload);
    }

    if ($this->agent->getIgnoreErrors() == DAgentIgnoreErrors::NO) {
      //deactivate agent
      $this->agent->setIsActive(0);
    }

    $this->updateAgent(PActions::CLIENT_ERROR);
    $this->sendResponse(array(
        PQueryClientError::ACTION => PActions::CLIENT_ERROR,
        PResponseError::RESPONSE => PValues::SUCCESS
      )
    );
  }
}