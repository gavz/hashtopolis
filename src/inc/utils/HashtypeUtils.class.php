<?php

use DBA\HashType;
use DBA\User;
use DBA\Hashlist;
use DBA\QueryFilter;
use DBA\Factory;

class HashtypeUtils {
  /**
   * @param int $hashtypeId
   * @throws HTException
   */
  public static function deleteHashtype($hashtypeId) {
    $hashtype = Factory::getHashTypeFactory()->get($hashtypeId);
    if ($hashtype == null) {
      throw new HTException("Invalid hashtype!");
    }

    $qF = new QueryFilter(Hashlist::HASH_TYPE_ID, $hashtype->getId(), "=");
    $hashlists = Factory::getHashlistFactory()->filter([Factory::FILTER => $qF]);
    if (sizeof($hashlists) > 0) {
      throw new HTException("You cannot delete this hashtype! There are hashlists present which are of this type!");
    }

    Factory::getHashTypeFactory()->delete($hashtype);
  }

  /**
   * @param int $hashtypeId
   * @param string $description
   * @param int $isSalted
   * @param User $user
   * @throws HTException
   */
  public static function addHashtype($hashtypeId, $description, $isSalted, $user) {
    $hashtype = Factory::getHashTypeFactory()->get($hashtypeId);
    if ($hashtype != null) {
      throw new HTException("This hash number is already used!");
    }
    $desc = htmlentities($description, ENT_QUOTES, "UTF-8");
    if (strlen($desc) == 0 || $hashtypeId < 0) {
      throw new HTException("Invalid inputs!");
    }

    $salted = 0;
    if ($isSalted) {
      $salted = 1;
    }

    $hashtype = new HashType($hashtypeId, $desc, $salted);
    if (Factory::getHashTypeFactory()->save($hashtype) == null) {
      throw new HTException("Failed to add new hash type!");
    }
    Util::createLogEntry("User", $user->getId(), DLogEntry::INFO, "New Hashtype added: " . $hashtype->getDescription());
  }
}