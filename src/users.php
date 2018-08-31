<?php

use DBA\QueryFilter;
use DBA\AccessGroupUser;
use DBA\AccessGroup;
use DBA\JoinFilter;
use DBA\Factory;

require_once(dirname(__FILE__) . "/inc/load.php");

if (!Login::getInstance()->isLoggedin()) {
  header("Location: index.php?err=4" . time() . "&fw=" . urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']));
  die();
}

AccessControl::getInstance()->checkPermission(DViewControl::USERS_VIEW_PERM);

Template::loadInstance("users/index");
Menu::get()->setActive("users_list");

//catch actions here...
if (isset($_POST['action']) && CSRF::check($_POST['csrf'])) {
  $usersHandler = new UsersHandler();
  $usersHandler->handle($_POST['action']);
  if (UI::getNumMessages() == 0) {
    Util::refresh();
  }
}

if (isset($_GET['new'])) {
  Template::loadInstance("users/new");
  Menu::get()->setActive("users_new");
  UI::add('groups', Factory::getRightGroupFactory()->filter([]));
  UI::add('pageTitle', "Create User");
}
else if (isset($_GET['id'])) {
  $user = Factory::getUserFactory()->get($_GET['id']);
  if ($user == null) {
    UI::printError("ERROR", "Invalid user!");
  }
  else {
    UI::add('user', $user);
    UI::add('groups', Factory::getRightGroupFactory()->filter([]));

    $qF = new QueryFilter(AccessGroupUser::USER_ID, $user->getId(), "=", Factory::getAccessGroupUserFactory());
    $jF = new JoinFilter(Factory::getAccessGroupUserFactory(), AccessGroup::ACCESS_GROUP_ID, AccessGroupUser::ACCESS_GROUP_ID);
    $joinedGroups = Factory::getAccessGroupFactory()->filter([Factory::FILTER => $qF, Factory::JOIN => $jF]);
    UI::add('accessGroups', $joinedGroups[Factory::getAccessGroupFactory()->getModelName()]);

    Template::loadInstance("users/detail");
    UI::add('pageTitle', "User details for " . $user->getUsername());
  }
}
else {
  $users = array();
  $res = Factory::getUserFactory()->filter([]);
  foreach ($res as $entry) {
    $set = new DataSet();
    $set->addValue('user', $entry);
    $set->addValue('group', Factory::getRightGroupFactory()->get($entry->getRightGroupId()));
    $users[] = $set;
  }

  UI::add('allUsers', $users);
  UI::add('numUsers', sizeof($users));
  UI::add('pageTitle', "Users");
}

echo Template::getInstance()->render(UI::getObjects());




