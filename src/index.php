<?php

require_once(dirname(__FILE__) . "/inc/load.php");

if (!$INSTALL) {
  header("Location: install/");
  die("Forward to <a href='install'>Install</a>");
}

AccessControl::getInstance()->checkPermission(DViewControl::INDEX_VIEW_PERM);

Template::loadInstance("static/index");
UI::add('pageTitle', "Welcome");

// TODO: rewrite these messages to use the messages template
if (isset($_GET['err'])) {
  $err = $_GET['err'];
  $time = substr($err, 1);
  if (time() - $time < 10) {
    switch ($err[0]) {
      case '1':
        UI::addMessage(UI::ERROR, "Invalid form submission!");
        break;
      case '2':
        UI::addMessage(UI::ERROR, "You need to fill in both fields!");
        break;
      case '3':
        UI::addMessage(UI::ERROR, "Wrong username/password/OTP!");
        break;
      case '4':
        UI::addMessage(UI::ERROR, "You need to be logged in to view this! Please log in again.");
        break;
    }
  }
}
else if (isset($_GET['logout'])) {
  $logout = $_GET['logout'];
  $time = substr($logout, 1);
  if (time() - $time < 10) {
    UI::addMessage(UI::SUCCESS, "You logged out successfully!");
  }
}

$fw = "";
if (isset($_GET['fw'])) {
  $fw = $_GET['fw'];
}
UI::add('fw', htmlentities($fw, ENT_QUOTES, "UTF-8"));

echo Template::getInstance()->render(UI::getObjects());




