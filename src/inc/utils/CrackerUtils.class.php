<?php

use DBA\CrackerBinary;
use DBA\CrackerBinaryType;
use DBA\QueryFilter;
use DBA\Task;
use DBA\ContainFilter;
use DBA\Factory;

class CrackerUtils {
  /**
   * @param CrackerBinaryType $cracker
   * @return CrackerBinary[]
   */
  public static function getBinaries($cracker) {
    $qF = new QueryFilter(CrackerBinary::CRACKER_BINARY_TYPE_ID, $cracker->getId(), "=");
    return Factory::getCrackerBinaryFactory()->filter([Factory::FILTER => $qF]);
  }

  /**
   * @return CrackerBinaryType[]
   */
  public static function getBinaryTypes() {
    return Factory::getCrackerBinaryTypeFactory()->filter([]);
  }

  /**
   * @param string $typeName
   * @throws HTException
   */
  public static function createBinaryType($typeName) {
    $qF = new QueryFilter(CrackerBinaryType::TYPE_NAME, $typeName, "=");
    $check = Factory::getCrackerBinaryTypeFactory()->filter([Factory::FILTER => $qF], true);
    if ($check !== null) {
      throw new HTException("This binary type already exists!");
    }
    $binaryType = new CrackerBinaryType(null, $typeName, 1);
    Factory::getCrackerBinaryTypeFactory()->save($binaryType);
  }

  /**
   * @param string $version
   * @param string $name
   * @param string $url
   * @param int $binaryTypeId
   * @throws HTException
   * @return CrackerBinaryType
   */
  public static function createBinary($version, $name, $url, $binaryTypeId) {
    $binaryType = CrackerUtils::getBinaryType($binaryTypeId);
    if (strlen($version) == 0 || strlen($name) == 0 || strlen($url) == 0) {
      throw new HTException("Please provide all information!");
    }
    $binary = new CrackerBinary(null, $binaryType->getId(), $version, $url, $name);
    Factory::getCrackerBinaryFactory()->save($binary);
    return $binaryType;
  }

  /**
   * @param int $binaryId
   * @throws HTException
   */
  public static function deleteBinary($binaryId) {
    $binary = CrackerUtils::getBinary($binaryId);
    $qF = new QueryFilter(Task::CRACKER_BINARY_ID, $binary->getId(), "=");
    $check = Factory::getTaskFactory()->filter([Factory::FILTER => $qF]);
    if (sizeof($check) > 0) {
      throw new HTException("There are tasks which use this binary!");
    }
    Factory::getCrackerBinaryFactory()->delete($binary);
  }

  /**
   * @param int $binaryTypeId
   * @throws HTException
   */
  public static function deleteBinaryType($binaryTypeId) {
    $binaryType = CrackerUtils::getBinaryType($binaryTypeId);

    $qF = new QueryFilter(CrackerBinary::CRACKER_BINARY_TYPE_ID, $binaryType->getId(), "=");
    $binaries = Factory::getCrackerBinaryFactory()->filter([Factory::FILTER => $qF]);
    $versionIds = Util::arrayOfIds($binaries);

    $qF = new ContainFilter(Task::CRACKER_BINARY_ID, $versionIds);
    $check = Factory::getTaskFactory()->filter([Factory::FILTER => $qF]);
    if (sizeof($check) > 0) {
      throw new HTException("There are tasks which use binaries of this cracker!");
    }

    // delete
    Factory::getCrackerBinaryFactory()->massDeletion([Factory::FILTER => $qF]);
    Factory::getCrackerBinaryTypeFactory()->delete($binaryType);
  }

  /**
   * @param string $version
   * @param string $name
   * @param string $url
   * @param int $binaryId
   * @throws HTException
   * @return CrackerBinaryType
   */
  public static function updateBinary($version, $name, $url, $binaryId) {
    $binary = CrackerUtils::getBinary($binaryId);
    if (strlen($version) == 0 || strlen($name) == 0 || strlen($url) == 0) {
      throw new HTException("Please provide all information!");
    }
    $binary->setBinaryName(htmlentities($name, ENT_QUOTES, "UTF-8"));
    $binary->setDownloadUrl($url);
    $binary->setVersion($version);
    Factory::getCrackerBinaryFactory()->update($binary);
    return Factory::getCrackerBinaryTypeFactory()->get($binary->getCrackerBinaryTypeId());
  }

  /**
   * @param int $binaryTypeId
   * @throws HTException
   * @return CrackerBinaryType
   */
  public static function getBinaryType($binaryTypeId) {
    $binaryType = Factory::getCrackerBinaryTypeFactory()->get($binaryTypeId);
    if ($binaryType === null) {
      throw new HTException("Invalid binary type!");
    }
    return $binaryType;
  }

  /**
   * @param int $binaryId
   * @throws HTException
   * @return CrackerBinary
   */
  public static function getBinary($binaryId) {
    $binary = Factory::getCrackerBinaryFactory()->get($binaryId);
    if ($binary === null) {
      throw new HTException("Invalid cracker binary!");
    }
    return $binary;
  }
}