<?php

use DBA\File;

class UserAPIHashlist extends UserAPIBasic {
  public function execute($QUERY = array()) {
    try {
      switch ($QUERY[UQuery::REQUEST]) {
        case USectionHashlist::LIST_HASLISTS:
          $this->listHashlists($QUERY);
          break;
        case USectionHashlist::GET_HASHLIST:
          $this->getHashlist($QUERY);
          break;
        case USectionHashlist::CREATE_HASHLIST:
          $this->createHashlist($QUERY);
          break;
        case USectionHashlist::SET_HASHLIST_NAME:
          $this->setHashlistName($QUERY);
          break;
        case USectionHashlist::SET_SECRET:
          $this->setSecret($QUERY);
          break;
        case USectionHashlist::IMPORT_CRACKED:
          $this->importCracked($QUERY);
          break;
        case USectionHashlist::EXPORT_CRACKED:
          $this->exportCracked($QUERY);
          break;
        case USectionHashlist::GENERATE_WORDLIST:
          $this->generateWordlist($QUERY);
          break;
        case USectionHashlist::EXPORT_LEFT:
          $this->exportLeft($QUERY);
          break;
        case USectionHashlist::DELETE_HASHLIST:
          $this->deleteHashlist($QUERY);
          break;
        case USectionHashlist::GET_HASH:
          $this->getHash($QUERY);
        default:
          $this->sendErrorResponse($QUERY[UQuery::SECTION], "INV", "Invalid section request!");
      }
    }
    catch (HTException $e) {
      $this->sendErrorResponse($QUERY[UQueryTask::SECTION], $QUERY[UQueryTask::REQUEST], $e->getMessage());
    }
  }

  /**
   * @param array $QUERY 
   * @throws HTException 
   */
  private function getHash($QUERY){
    if (!isset($QUERY[UQueryHashlist::HASH])) {
      throw new HTException("Invalid query!");
    }
    $hash = HashlistUtils::getHash($QUERY[UQueryHashlist::HASH], $this->user);
    if($hash == null){
      throw new HTException("Hash was not found or is not cracked!");
    }
    else{
      $resonse = [
        UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
        UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
        UResponseHashlist::RESPONSE => UValues::OK,
        UResponseHashlist::HASH => $QUERY[UQueryHashlist::HASH],
        UResponseHashlist::PLAIN => $hash->getPlaintext()
      ];
      $this->sendResponse($resonse);
    }
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function deleteHashlist($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID])) {
      throw new HTException("Invalid query!");
    }
    HashlistUtils::delete($QUERY[UQueryHashlist::HASHLIST_ID], $this->user);
    $this->sendSuccessResponse($QUERY);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function exportLeft($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID])) {
      throw new HTException("Invalid query!");
    }
    $file = HashlistUtils::leftlist($QUERY[UQueryHashlist::HASHLIST_ID], $this->user);
    $response = [
      UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
      UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
      UResponseHashlist::RESPONSE => UValues::OK,
      UResponseHashlist::EXPORT_FILE_ID => (int)$file->getId(),
      UResponseHashlist::EXPORT_FILE_NAME => $file->getFilename()
    ];
    $this->sendResponse($response);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function generateWordlist($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID])) {
      throw new HTException("Invalid query!");
    }
    $arr = HashlistUtils::createWordlists($QUERY[UQueryHashlist::HASHLIST_ID], $this->user);
    /** @var $file File */
    $file = $arr[2];
    $response = [
      UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
      UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
      UResponseHashlist::RESPONSE => UValues::OK,
      UResponseHashlist::EXPORT_FILE_ID => (int)$file->getId(),
      UResponseHashlist::EXPORT_FILE_NAME => $file->getFilename()
    ];
    $this->sendResponse($response);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function exportCracked($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID])) {
      throw new HTException("Invalid query!");
    }
    $file = HashlistUtils::export($QUERY[UQueryHashlist::HASHLIST_ID], $this->user);
    $response = [
      UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
      UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
      UResponseHashlist::RESPONSE => UValues::OK,
      UResponseHashlist::EXPORT_FILE_ID => (int)$file->getId(),
      UResponseHashlist::EXPORT_FILE_NAME => $file->getFilename()
    ];
    $this->sendResponse($response);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function importCracked($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID]) || !isset($QUERY[UQueryHashlist::HASHLIST_SEPARATOR]) || !isset($QUERY[UQueryHashlist::HASHLIST_DATA])) {
      throw new HTException("Invalid query!");
    }
    $arr = HashlistUtils::processZap(
      $QUERY[UQueryHashlist::HASHLIST_ID],
      $QUERY[UQueryHashlist::HASHLIST_SEPARATOR],
      'paste',
      ['hashfield' => base64_decode($QUERY[UQueryHashlist::HASHLIST_DATA])],
      [],
      $this->user
    );
    $response = [
      UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
      UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
      UResponseHashlist::RESPONSE => UValues::OK,
      UResponseHashlist::ZAP_LINES_PROCESSED => (int)$arr[0],
      UResponseHashlist::ZAP_NEW_CRACKED => (int)$arr[1],
      UResponseHashlist::ZAP_ALREADY_CRACKED => (int)$arr[2],
      UResponseHashlist::ZAP_INVALID => (int)$arr[3],
      UResponseHashlist::ZAP_NOT_FOUND => (int)$arr[4],
      UResponseHashlist::ZAP_TIME_REQUIRED => (int)$arr[5],
      UResponseHashlist::ZAP_TOO_LONG => (int)$arr[6]
    ];
    $this->sendResponse($response);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function setSecret($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID]) || !isset($QUERY[UQueryHashlist::HASHLIST_IS_SECRET])) {
      throw new HTException("Invalid query!");
    }
    HashlistUtils::setSecret($QUERY[UQueryHashlist::HASHLIST_ID], $QUERY[UQueryHashlist::HASHLIST_IS_SECRET], $this->user);
    $this->sendSuccessResponse($QUERY);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function setHashlistName($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID]) || !isset($QUERY[UQueryHashlist::HASHLIST_NAME])) {
      throw new HTException("Invalid query!");
    }
    HashlistUtils::rename($QUERY[UQueryHashlist::HASHLIST_ID], $QUERY[UQueryHashlist::HASHLIST_NAME], $this->user);
    $this->sendSuccessResponse($QUERY);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function createHashlist($QUERY) {
    $toCheck = [
      UQueryHashlist::HASHLIST_NAME,
      UQueryHashlist::HASHLIST_IS_SALTED,
      UQueryHashlist::HASHLIST_IS_SECRET,
      UQueryHashlist::HASHLIST_HEX_SALTED,
      UQueryHashlist::HASHLIST_SEPARATOR,
      UQueryHashlist::HASHLIST_FORMAT,
      UQueryHashlist::HASHLIST_HASHTYPE_ID,
      UQueryHashlist::HASHLIST_ACCESS_GROUP_ID,
      UQueryHashlist::HASHLIST_DATA
    ];
    foreach ($toCheck as $input) {
      if (!isset($QUERY[$input])) {
        throw new HTException("Invalid query!");
      }
    }
    HashlistUtils::createHashlist(
      $QUERY[UQueryHashlist::HASHLIST_NAME],
      $QUERY[UQueryHashlist::HASHLIST_IS_SALTED],
      $QUERY[UQueryHashlist::HASHLIST_IS_SECRET],
      $QUERY[UQueryHashlist::HASHLIST_HEX_SALTED],
      $QUERY[UQueryHashlist::HASHLIST_SEPARATOR],
      $QUERY[UQueryHashlist::HASHLIST_FORMAT],
      $QUERY[UQueryHashlist::HASHLIST_HASHTYPE_ID],
      $QUERY[UQueryHashlist::HASHLIST_SEPARATOR],
      $QUERY[UQueryHashlist::HASHLIST_ACCESS_GROUP_ID],
      "paste",
      ['hashfield' => base64_decode($QUERY[UQueryHashlist::HASHLIST_DATA])],
      [],
      $this->user
    );
    $this->sendSuccessResponse($QUERY);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function getHashlist($QUERY) {
    if (!isset($QUERY[UQueryHashlist::HASHLIST_ID])) {
      throw new HTException("Invalid query!");
    }
    $hashlist = HashlistUtils::getHashlist($QUERY[UQueryHashlist::HASHLIST_ID]);
    if ($hashlist->getFormat() == DHashlistFormat::SUPERHASHLIST) {
      throw new HTException("This is not a single hashlist!");
    }
    $response = [
      UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
      UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
      UResponseHashlist::RESPONSE => UValues::OK,
      UResponseHashlist::HASHLIST_ID => (int)$hashlist->getId(),
      UResponseHashlist::HASHLIST_HASHTYPE_ID => (int)$hashlist->getHashTypeId(),
      UResponseHashlist::HASHLIST_NAME => $hashlist->getHashlistName(),
      UResponseHashlist::HASHLIST_FORMAT => (int)$hashlist->getFormat(),
      UResponseHashlist::HASHLIST_COUNT => (int)$hashlist->getHashCount(),
      UResponseHashlist::HASHLIST_CRACKED => (int)$hashlist->getCracked(),
      UResponseHashlist::HASHLIST_ACCESS_GROUP => (int)$hashlist->getAccessGroupId(),
      UResponseHashlist::HASHLIST_HEX_SALT => ($hashlist->getHexSalt() == 1) ? true : false,
      UResponseHashlist::HASHLIST_SALTED => ($hashlist->getIsSalted() == 1) ? true : false,
      UResponseHashlist::HASHLIST_SECRET => ($hashlist->getIsSecret() == 1) ? true : false,
      UResponseHashlist::HASHLIST_SALT_SEPARATOR => $hashlist->getSaltSeparator()
    ];
    $this->sendResponse($response);
  }

  /**
   * @param array $QUERY
   * @throws HTException
   */
  private function listHashlists($QUERY) {
    $hashlists = HashlistUtils::getHashlists($this->user);
    $lists = [];
    $response = [
      UResponseHashlist::SECTION => $QUERY[UQueryHashlist::SECTION],
      UResponseHashlist::REQUEST => $QUERY[UQueryHashlist::REQUEST],
      UResponseHashlist::RESPONSE => UValues::OK
    ];
    foreach ($hashlists as $hashlist) {
      $lists[] = [
        UResponseHashlist::HASHLISTS_ID => (int)$hashlist->getId(),
        UResponseHashlist::HASHLISTS_HASHTYPE_ID => (int)$hashlist->getHashTypeId(),
        UResponseHashlist::HASHLISTS_NAME => $hashlist->getHashlistName(),
        UResponseHashlist::HASHLISTS_FORMAT => (int)$hashlist->getFormat(),
        UResponseHashlist::HASHLISTS_COUNT => (int)$hashlist->getHashCount()
      ];
    }
    $response[UResponseHashlist::HASHLISTS] = $lists;
    $this->sendResponse($response);
  }
}