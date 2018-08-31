<?php

class SupertaskHandler implements Handler {
  public function __construct($supertaskId = null) {
    //nothing
  }

  public function handle($action) {
    try {
      switch ($action) {
        case DSupertaskAction::DELETE_SUPERTASK:
          AccessControl::getInstance()->checkPermission(DSupertaskAction::DELETE_SUPERTASK_PERM);
          SupertaskUtils::deleteSupertask($_POST['supertask']);
          break;
        case DSupertaskAction::CREATE_SUPERTASK:
          AccessControl::getInstance()->checkPermission(DSupertaskAction::CREATE_SUPERTASK_PERM);
          SupertaskUtils::createSupertask($_POST['name'], @$_POST['task']);
          break;
        case DSupertaskAction::APPLY_SUPERTASK:
          AccessControl::getInstance()->checkPermission(DSupertaskAction::APPLY_SUPERTASK_PERM);
          SupertaskUtils::runSupertask($_POST['supertask'], $_POST['hashlist'], $_POST['crackerBinaryVersionId']);
          break;
        case DSupertaskAction::IMPORT_SUPERTASK:
          AccessControl::getInstance()->checkPermission(DSupertaskAction::IMPORT_SUPERTASK_PERM);
          SupertaskUtils::importSupertask($_POST['name'], $_POST['isCpu'], $_POST['isSmall'], false, $_POST['crackerBinaryTypeId'], explode("\n", str_replace("\r\n", "\n", $_POST['masks'])));
          break;
        default:
          UI::addMessage(UI::ERROR, "Invalid action!");
          break;
      }
    }
    catch (HTException $e) {
      UI::addMessage(UI::ERROR, $e->getMessage());
    }
  }
}