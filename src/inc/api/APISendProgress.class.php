<?php

use DBA\AgentZap;
use DBA\Assignment;
use DBA\Chunk;
use DBA\ComparisonFilter;
use DBA\ContainFilter;
use DBA\Hash;
use DBA\HashBinary;
use DBA\Hashlist;
use DBA\QueryFilter;
use DBA\Zap;
use DBA\QueryFilterWithNull;
use DBA\TaskDebugOutput;
use DBA\AgentStat;
use DBA\Factory;

class APISendProgress extends APIBasic {
  public function execute($QUERY = array()) {
    if (!PQuerySendProgress::isValid($QUERY)) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Invalid progress query!");
    }
    $this->checkToken(PActions::SEND_PROGRESS, $QUERY);
    $this->updateAgent(PActions::SEND_PROGRESS);

    // upload cracked hashes to server
    $keyspaceProgress = $QUERY[PQuerySendProgress::KEYSPACE_PROGRESS];
    $relativeProgress = intval($QUERY[PQuerySendProgress::RELATIVE_PROGRESS]);//Normalized between 1-10k
    $speed = intval($QUERY[PQuerySendProgress::SPEED]);
    $state = intval($QUERY[PQuerySendProgress::HASHCAT_STATE]);

    $chunk = Factory::getChunkFactory()->get(intval($QUERY[PQuerySendProgress::CHUNK_ID]));
    if ($chunk == null) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Invalid chunk id " . intval($QUERY[PQuerySendProgress::CHUNK_ID]));
    }
    else if ($this->agent->getIsActive() == 0) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Agent is marked inactive!");
    }
    else if ($chunk->getAgentId() != $this->agent->getId()) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "You are not assigned to this chunk");
    }

    $task = Factory::getTaskFactory()->get($chunk->getTaskId());
    if ($task == null) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "No task exists for the given chunk");
    }
    else if($task->getIsArchived() == 1){
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Task is archived, no work to do");
    }
    $taskWrapper = Factory::getTaskWrapperFactory()->get($task->getTaskWrapperId());
    if ($taskWrapper == null) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Inconsistency error on taskWrapper");
    }

    $hashlist = Factory::getHashlistFactory()->get($taskWrapper->getHashlistId());
    if ($hashlist == null) {
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "The given task does not have a corresponding hashlist!");
    }
    $totalHashlist = $hashlist;
    $hashlists = Util::checkSuperHashlist($hashlist);
    foreach ($hashlists as $hashlist) {
      if ($hashlist->getIsSecret() > $this->agent->getIsTrusted()) {
        $this->sendErrorResponse(PActions::SEND_PROGRESS, "Unknown Error. The API does not trust you with more information");
      }
    }

    $dataTime = time();
    if(isset($QUERY[PQuerySendProgress::GPU_TEMP])){
      for($i =0; $i < sizeof($QUERY[PQuerySendProgress::GPU_TEMP]); $i++){
        if(!is_numeric($QUERY[PQuerySendProgress::GPU_TEMP][$i]) || $QUERY[PQuerySendProgress::GPU_TEMP][$i] <= 0){
          unset($QUERY[PQuerySendProgress::GPU_TEMP][$i]);
        }
      }
      $data = implode(",", $QUERY[PQuerySendProgress::GPU_TEMP]);
      $agentStat = new AgentStat(null, $this->agent->getId(), DAgentStatsType::GPU_TEMP, $dataTime, $data);
      Factory::getAgentStatFactory()->save($agentStat);
    }
    if(isset($QUERY[PQuerySendProgress::GPU_UTIL])){
      for($i =0; $i < sizeof($QUERY[PQuerySendProgress::GPU_UTIL]); $i++){
        if(!is_numeric($QUERY[PQuerySendProgress::GPU_UTIL][$i]) || $QUERY[PQuerySendProgress::GPU_UTIL][$i] < 0){
          unset($QUERY[PQuerySendProgress::GPU_UTIL][$i]);
        }
      }
      $data = implode(",", $QUERY[PQuerySendProgress::GPU_UTIL]);
      $agentStat = new AgentStat(null, $this->agent->getId(), DAgentStatsType::GPU_UTIL, $dataTime, $data);
      Factory::getAgentStatFactory()->save($agentStat);
    }

    // agent is assigned to this chunk (not necessarily task!)
    // it can be already assigned to other task, but is still computing this chunk until it realizes it
    $skip = $chunk->getSkip();
    $length = $chunk->getLength();
    $taskID = $task->getId();

    //if by accident the number of the combinationProgress overshoots the limit
    if ($relativeProgress > 10000) {
      $relativeProgress = 10000;
    }
    if ($keyspaceProgress > $length + $skip) {
      $keyspaceProgress = $length + $skip;
    }

    /*
     * Save Debug output if provided
     */
    if(isset($QUERY[PQuerySendProgress::DEBUG_OUTPUT])){
      $lines = $QUERY[PQuerySendProgress::DEBUG_OUTPUT];
      $taskDebugOutputs = [];
      foreach($lines as $line){
        $taskDebugOutputs[] = new TaskDebugOutput(null, $chunk->getTaskId(), $line);
      }
      if(sizeof($taskDebugOutputs) > 0){
        Factory::getTaskDebugOutputFactory()->massSave($taskDebugOutputs);
      }
    }

    /*
     * Save chunk updates
     */
    $chunk->setProgress($relativeProgress);
    $chunk->setCheckpoint($keyspaceProgress);
    $chunk->setSolveTime(time());
    $aborting = false;
    if ($chunk->getState() == DHashcatStatus::ABORTED) {
      $aborting = true;
    }
    $chunk->setState($state);
    Factory::getChunkFactory()->update($chunk);
    $format = $hashlists[0]->getFormat();

    // reset values
    $skipped = 0;
    $cracked = array();
    foreach ($hashlists as $hashlist) {
      $cracked[$hashlist->getId()] = 0;
    }

    // process solved hashes, should there be any
    $crackedHashes = $QUERY[PQuerySendProgress::CRACKED_HASHES];
    Factory::getAgentFactory()->getDB()->beginTransaction();

    $plainUpdates = array();
    $crackHashes = array();
    $zaps = array();

    for ($i = 0; $i < sizeof($crackedHashes); $i++) {
      $crackedHash = $crackedHashes[$i];
      if ($crackedHash == "") {
        continue;
      }
      $splitLine = explode(SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR), $crackedHash);
      switch ($format) {
        case DHashlistFormat::PLAIN:
          $qF1 = new QueryFilter(Hash::HASH, $splitLine[0], "=");
          $qF2 = new ContainFilter(Hash::HASHLIST_ID, Util::arrayOfIds($hashlists));
          $qF3 = new QueryFilter(Hash::IS_CRACKED, 0, "=");
          $hashes = Factory::getHashFactory()->filter([Factory::FILTER => [$qF1, $qF2, $qF3]]);
          if (sizeof($hashes) == 0) {
            $skipped++;
            continue;
          }
          $salt = $hashes[0]->getSalt();
          if (strlen($salt) == 0) {
            // unsalted hashes
            $plain = str_ireplace($hashes[0]->getHash() . SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR), "", $crackedHash);
          }
          else {
            // salted hashes
            $plain = str_ireplace($hashes[0]->getHash() . SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR) . $hashes[0]->getSalt() . SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR), "", $crackedHash);
          }

          foreach ($hashes as $hash) {
            $cracked[$hash->getHashlistId()]++;
            $plainUpdates[] = new MassUpdateSet($hash->getId(), $plain);
            $crackHashes[] = $hash->getId();
            $zaps[] = new Zap(null, $hash->getHash(), time(), $this->agent->getId(), $totalHashlist->getId());
          }

          if (sizeof($plainUpdates) >= 1000) {
            $uS1 = new UpdateSet(Hash::CHUNK_ID, $chunk->getId());
            $uS2 = new UpdateSet(Hash::IS_CRACKED, 1);
            $qF = new ContainFilter(Hash::HASH_ID, $crackHashes);
            Factory::getHashFactory()->massSingleUpdate(Hash::HASH_ID, Hash::PLAINTEXT, $plainUpdates);
            Factory::getHashFactory()->massUpdate([Factory::UPDATE => $uS1, Factory::FILTER => $qF]);
            Factory::getHashFactory()->massUpdate([Factory::UPDATE => $uS2, Factory::FILTER => $qF]);
            Factory::getZapFactory()->massSave($zaps);
            Factory::getAgentFactory()->getDB()->commit();
            Factory::getAgentFactory()->getDB()->beginTransaction();
            $zaps = array();
            $plainUpdates = array();
            $crackHashes = array();
          }
          break;
        case DHashlistFormat::WPA:
          // save cracked wpa password
          // result sent: 408bc12965e7ce9987cf8fb61e62a90a:aef50f22801c:987bdcf9f950:8381533406003807685881523:hashcat!
          $mac_ap = $splitLine[1];
          $mac_cli = $splitLine[2];
          $essid = $splitLine[3];
          $plain = array();
          for ($t = 4; $t < sizeof($splitLine); $t++) {
            $plain[] = $splitLine[$t];
          }
          $plain = implode(SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR), $plain);
          //TODO: if we really want to be sure that not different wpas are cracked, we need to check here to which task the client is assigned. But not sure if this is still required if we check both MACs
          $qF1 = new QueryFilter(HashBinary::ESSID, $mac_ap . SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR) . $mac_cli . SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR) . $essid, "=");
          $qF2 = new QueryFilter(HashBinary::IS_CRACKED, 0, "=");
          $hashes = Factory::getHashBinaryFactory()->filter([Factory::FILTER => [$qF1, $qF2]]);
          if (sizeof($hashes) == 0) {
            $skipped++;
          }
          foreach ($hashes as $hash) {
            $cracked[$hash->getHashlistId()]++;
            $hash->setIsCracked(1);
            $hash->setChunkId($chunk->getId());
            $hash->setPlaintext($plain);
            Factory::getHashBinaryFactory()->update($hash);
          }
          break;
        case DHashlistFormat::BINARY:
          // save binary password
          $plain = implode(SConfig::getInstance()->getVal(DConfig::FIELD_SEPARATOR), $splitLine);
          $qF1 = new QueryFilter(HashBinary::HASHLIST_ID, $totalHashlist->getId(), "=");
          $qF2 = new QueryFilter(HashBinary::IS_CRACKED, 0, "=");
          $hashes = Factory::getHashBinaryFactory()->filter([Factory::FILTER => [$qF1, $qF2]]);
          if (sizeof($hashes) == 0) {
            $skipped++;
          }
          foreach ($hashes as $hash) {
            $cracked[$hash->getHashlistId()]++;
            $hash->setIsCracked(1);
            $hash->setChunkId($chunk->getId());
            $hash->setPlaintext($plain);
            Factory::getHashBinaryFactory()->update($hash);
          }
          break;
      }
    }
    if ($format == DHashlistFormat::PLAIN && sizeof($plainUpdates) > 0) {
      $uS1 = new UpdateSet(Hash::CHUNK_ID, $chunk->getId());
      $uS2 = new UpdateSet(Hash::IS_CRACKED, 1);
      $qF = new ContainFilter(Hash::HASH_ID, $crackHashes);
      Factory::getHashFactory()->massSingleUpdate(Hash::HASH_ID, Hash::PLAINTEXT, $plainUpdates);
      Factory::getHashFactory()->massUpdate([Factory::UPDATE => $uS1, Factory::FILTER => $qF]);
      Factory::getHashFactory()->massUpdate([Factory::UPDATE => $uS2, Factory::FILTER => $qF]);
      Factory::getZapFactory()->massSave($zaps);
    }

    Factory::getAgentFactory()->getDB()->commit();

    //insert #Cracked hashes and update in hashlist how many hashes were cracked
    Factory::getAgentFactory()->getDB()->beginTransaction();
    $sumCracked = 0;
    foreach ($cracked as $listId => $cracks) {
      $list = Factory::getHashlistFactory()->get($listId);
      $list->setCracked($cracks + $list->getCracked());
      Factory::getHashlistFactory()->update($list);
      $sumCracked += $cracks;
    }
    $chunk = Factory::getChunkFactory()->get($chunk->getId());
    $chunk->setCracked($chunk->getCracked() + $sumCracked);
    Factory::getChunkFactory()->update($chunk);
    Factory::getAgentFactory()->getDB()->commit();

    if ($chunk->getState() == DHashcatStatus::STATUS_ABORTED_RUNTIME) {
      // the chunk was manually interrupted
      $chunk->setState(DHashcatStatus::ABORTED);
      Factory::getChunkFactory()->update($chunk);
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Chunk was manually interrupted.");
    }
    /** Check if the task is done */
    $taskdone = false;
    if ($relativeProgress == 10000 && $task->getKeyspaceProgress() == $task->getKeyspace()) {
      // chunk is done and the task has been fully dispatched
      $incompleteFilter = new QueryFilter(Chunk::PROGRESS, 10000, "<");
      $taskFilter = new QueryFilter(Chunk::TASK_ID, $taskID, "=");
      $count = Factory::getChunkFactory()->countFilter([Factory::FILTER => [$incompleteFilter, $taskFilter]]);
      if ($count == 0) {
        // this was the last incomplete chunk!
        $taskdone = true;
      }
    }

    if ($taskdone) {
      // task is fully dispatched and this last chunk is done, deprioritize it
      $task->setPriority(0);
      Factory::getTaskFactory()->update($task);

      if ($taskWrapper->getTaskType() == DTaskTypes::SUPERTASK) {
        // check if the task wrapper is a supertask and is completed
        if (Util::checkTaskWrapperCompleted($taskWrapper)) {
          $taskWrapper->setPriority(0);
          Factory::getTaskWrapperFactory()->update($taskWrapper);
        }
      }
      else {
        $taskWrapper->setPriority(0);
        Factory::getTaskWrapperFactory()->update($taskWrapper);
      }

      $payload = new DataSet(array(DPayloadKeys::TASK => $task));
      NotificationHandler::checkNotifications(DNotificationType::TASK_COMPLETE, $payload);
    }

    $toZap = array();

    if ($sumCracked > 0) {
      $payload = new DataSet(array(DPayloadKeys::NUM_CRACKED => $sumCracked, DPayloadKeys::AGENT => $this->agent, DPayloadKeys::TASK => $task, DPayloadKeys::HASHLIST => $totalHashlist));
      NotificationHandler::checkNotifications(DNotificationType::HASHLIST_CRACKED_HASH, $payload);
    }

    if ($aborting) {
      $chunk->setSpeed(0);
      $chunk->setState(DHashcatStatus::ABORTED);
      Factory::getChunkFactory()->update($chunk);
      $this->sendErrorResponse(PActions::SEND_PROGRESS, "Chunk was aborted!");
    }

    switch ($state) {
      case DHashcatStatus::EXHAUSTED:
        // the chunk has finished (exhausted)
        $chunk->setSpeed(0);
        $chunk->setProgress(10000);
        $chunk->setCheckpoint($chunk->getSkip() + $chunk->getLength());
        Factory::getChunkFactory()->update($chunk);
        break;
      case DHashcatStatus::CRACKED:
        // the chunk has finished (cracked whole hashList)
        // de-prioritize all tasks and un-assign all agents
        $chunk->setCheckpoint($chunk->getLength());
        $chunk->setProgress(10000);
        $chunk->setSpeed(0);
        Factory::getChunkFactory()->update($chunk);

        TaskUtils::depriorizeAllTasks($hashlists);
        TaskUtils::unassignAllAgents($hashlists);

        $payload = new DataSet(array(DPayloadKeys::HASHLIST => $totalHashlist));
        NotificationHandler::checkNotifications(DNotificationType::HASHLIST_ALL_CRACKED, $payload);
        break;
      case DHashcatStatus::ABORTED:
      case DHashcatStatus::QUIT:
        // the chunk was aborted or quit
        $chunk->setSpeed(0);
        Factory::getChunkFactory()->update($chunk);
        $this->sendErrorResponse(PActions::SEND_PROGRESS, "Chunk was aborted!");
        break;
      case DHashcatStatus::RUNNING:
      default:
        // the chunk isn't finished yet, we will send zaps
        $qF1 = new ComparisonFilter(Hashlist::CRACKED, Hashlist::HASH_COUNT, "<");
        $qF2 = new ContainFilter(Hashlist::HASHLIST_ID, Util::arrayOfIds($hashlists));
        $count = Factory::getHashlistFactory()->countFilter([Factory::FILTER => [$qF1, $qF2]]);
        if ($count == 0) {
          $payload = new DataSet(array(DPayloadKeys::HASHLIST => $totalHashlist));
          NotificationHandler::checkNotifications(DNotificationType::HASHLIST_ALL_CRACKED, $payload);

          $task->setPriority(0);
          $chunk->setCheckpoint($chunk->getSkip() + $chunk->getLength());
          $chunk->setProgress(10000);
          $chunk->setSpeed(0);

          TaskUtils::depriorizeAllTasks($hashlists);

          $qF = new QueryFilter(Assignment::TASK_ID, $task->getId(), "=");
          Factory::getAssignmentFactory()->massDeletion([Factory::FILTER => $qF]);

          Factory::getChunkFactory()->update($chunk);
          Factory::getTaskFactory()->update($task);

          //stop agent
          $this->sendResponse(array(
              PResponseSendProgress::ACTION => PActions::SEND_PROGRESS,
              PResponseSendProgress::RESPONSE => PValues::SUCCESS,
              PResponseSendProgress::NUM_CRACKED => $sumCracked,
              PResponseSendProgress::NUM_SKIPPED => $skipped,
              PResponseSendProgress::AGENT_COMMAND => "stop"
            )
          );
        }
        $chunk->setSpeed($speed);
        Factory::getChunkFactory()->update($chunk);

        $qF = new QueryFilter(AgentZap::AGENT_ID, $this->agent->getId(), "=");
        $agentZap = Factory::getAgentZapFactory()->filter([Factory::FILTER => $qF], true);
        if ($agentZap == null) {
          $agentZap = new AgentZap(null, $this->agent->getId(), null);
          Factory::getAgentZapFactory()->save($agentZap);
        }

        $qF1 = new ContainFilter(Zap::HASHLIST_ID, Util::arrayOfIds($hashlists));
        $qF2 = new QueryFilter(Zap::ZAP_ID, ($agentZap->getLastZapId() == null) ? 0 : $agentZap->getLastZapId(), ">");
        $qF3 = new QueryFilterWithNull(Zap::AGENT_ID, $this->agent->getId(), "<>", true);
        $zaps = Factory::getZapFactory()->filter([Factory::FILTER => [$qF1, $qF2, $qF3]]);
        foreach ($zaps as $zap) {
          if ($zap->getId() > $agentZap->getId()) {
            $agentZap->setLastZapId($zap->getId());
          }
          $toZap[] = $zap->getHash();
        }
        $this->agent->setLastTime(time());
        Factory::getAgentFactory()->update($this->agent);

        if ($agentZap->getLastZapId() > 0) {
          Factory::getAgentZapFactory()->update($agentZap);
        }

        // update hashList age for agent to this task
        break;
    }
    Util::zapCleaning();
    Util::agentStatCleaning();
    $this->sendResponse(array(
        PResponseSendProgress::ACTION => PActions::SEND_PROGRESS,
        PResponseSendProgress::RESPONSE => PValues::SUCCESS,
        PResponseSendProgress::NUM_CRACKED => $sumCracked,
        PResponseSendProgress::NUM_SKIPPED => $skipped,
        PResponseSendProgress::HASH_ZAPS => $toZap
      )
    );
  }
}