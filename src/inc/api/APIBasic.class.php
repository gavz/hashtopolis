<?php

use DBA\Agent;
use DBA\QueryFilter;
use DBA\Factory;

abstract class APIBasic {
  /** @var Agent */
  protected $agent = null;

  /**
   * @param array $QUERY input query sent to the API
   */
  public abstract function execute($QUERY = array());

  protected function sendResponse($RESPONSE) {
    header("Content-Type: application/json");
    echo json_encode($RESPONSE);
    die();
  }

  protected function updateAgent($action) {
    $this->agent->setLastIp(Util::getIP());
    $this->agent->setLastAct($action);
    $this->agent->setLastTime(time());
    Factory::getAgentFactory()->update($this->agent);
  }

  public function sendErrorResponse($action, $msg) {
    $ANS = array();
    $ANS[PResponseErrorMessage::ACTION] = $action;
    $ANS[PResponseErrorMessage::RESPONSE] = PValues::ERROR;
    $ANS[PResponseErrorMessage::MESSAGE] = $msg;
    header("Content-Type: application/json");
    echo json_encode($ANS);
    die();
  }

  public function checkToken($action, $QUERY) {
    $qF = new QueryFilter(Agent::TOKEN, $QUERY[PQuery::TOKEN], "=");
    $agent = Factory::getAgentFactory()->filter([Factory::FILTER => array($qF)], true);
    if ($agent == null) {
      $this->sendErrorResponse($action, "Invalid token!");
    }
    $this->agent = $agent;
  }
}
