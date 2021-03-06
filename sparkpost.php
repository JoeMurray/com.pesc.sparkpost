<?php

require_once 'sparkpost.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function sparkpost_civicrm_config(&$config) {
  _sparkpost_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function sparkpost_civicrm_xmlMenu(&$files) {
  _sparkpost_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function sparkpost_civicrm_install() {
  _sparkpost_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function sparkpost_civicrm_uninstall() {
  _sparkpost_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function sparkpost_civicrm_enable() {
  _sparkpost_civix_civicrm_enable();
  sparkpost_job_create();

  //Check and fix bad field type on civicrm_mailing_bounce_type.name
  $field_type  = CRM_Core_DAO::singleValueQuery("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'civicrm_mailing_bounce_type' AND COLUMN_NAME = 'name'");
  $field_length  = CRM_Core_DAO::singleValueQuery("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'civicrm_mailing_bounce_type' AND COLUMN_NAME = 'name'");

  if($field_type != 'varchar' || $field_length < 24){
    CRM_Core_DAO::singleValueQuery("ALTER TABLE civicrm_mailing_bounce_type CHANGE name name VARCHAR(24) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Type of bounce'");
  }

  //Add bounce type and pattern to database
  if(!CRM_Core_DAO::singleValueQuery("SELECT count(id) as 'COUNT' FROM civicrm_mailing_bounce_type WHERE `name` = 'SparkPost'")) {
    CRM_Core_DAO::singleValueQuery("INSERT INTO `civicrm_mailing_bounce_type` (`name`, `description`, `hold_threshold`) VALUES ('SparkPost', 'SparkPost supression list', 1)");
    $bounce_type_id = CRM_Core_DAO::singleValueQuery("SELECT `id` FROM `civicrm_mailing_bounce_type` WHERE `name` = 'SparkPost' LIMIT 1");
    CRM_Core_DAO::singleValueQuery("INSERT INTO `civicrm_mailing_bounce_pattern` (`bounce_type_id`, `pattern`) VALUES ($bounce_type_id, 'recipient address suppressed due to customer policy')");
  }
  sparkpost_addOptionValues();
  sparkpost_mailingsCheck();

}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function sparkpost_civicrm_disable() {
  _sparkpost_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function sparkpost_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _sparkpost_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function sparkpost_civicrm_managed(&$entities) {
  _sparkpost_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function sparkpost_civicrm_caseTypes(&$caseTypes) {
  _sparkpost_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function sparkpost_civicrm_angularModules(&$angularModules) {
_sparkpost_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function sparkpost_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _sparkpost_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_postEmailSend( )
 * Update the status of activity created in sparkpost_civicrm_alterMailParams
 */
function sparkpost_civicrm_postEmailSend(&$params) {
  // check if an activityId was added in hook_civicrm_alterMailParams
  // if so, update the activity's status and add a target_contact_id

  $temparr = $params;
  unset($temparr['html']);
  unset($temparr['text']);
  $json = json_decode($temparr['headers']['X-MSYS-API']);
  $activityID = $json->metadata->civi_activityid;

  $result = civicrm_api3('Activity', 'create', array(
    'sequential' => 1,
    'id' => $activityID,
    'status_id' => 'Completed'
  ));
  return TRUE;
}

/**
 * Implementation of hook_civicrm_alterMailParams( )
 * Inserts smtp headers for use in SparkPost API and subsequent bounce processing
 */
function sparkpost_civicrm_alterMailParams(&$params, $context) {
  sparkpost_addOptionValues();
  sparkpost_mailingsCheck();
  if($context == 'civimail'){ // Do this on bulk mail

    $temphash = explode('.',$params['Return-Path']);
    $hash = substr($temphash[3], 0, strpos($temphash[3], '@'));

    $mailEventQueue = civicrm_api3('MailingEventQueue', 'get', array(
      'sequential' => 1,
      'hash' => $hash
    ));

    //Prep SparkPost metadata
    $mailing = sparkpost_mailing($hash);
    $campaign = mb_convert_encoding($mailing['name'], 'UTF-8', 'auto');

    if(mb_strlen($campaign, 'UTF-8') > 64) {
      $campaign = substr($campaign, 0, 64);
    }

    $tags = array(
      'campaign_id' => $campaign,
      'metadata' => array(
        'civi_type' => 'bulk',
        'civi_hash' => $hash,
        'civi_jobid' => $mailEventQueue['values'][0]['job_id'],
        'civi_queue' => $mailEventQueue['values'][0]['id']
      )
    );
  }else{ // Do this on transactional emails

    // GET source contact ID for activity
    $session = CRM_Core_Session::singleton();
    $sourceContactID = $session->get('userID');
    if (!$sourceContactID) {
      $sourceContactID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Domain', CRM_Core_Config::domainID(), 'contact_id');
    }

    // GET activity type ID
    $activityType = civicrm_api3('OptionValue', 'get', array('sequential' => 1,'option_group_id' => 'activity_type','name' => 'Transactional Email'));
    $activityTypeID = $activityType['values'][0]['value'];

    // GET activity status ID
    $activityStatus = civicrm_api3('OptionValue', 'get', array('sequential' => 1,'option_group_id' => 'activity_status','name' => 'Pending'));
    $activityStatusID = $activityStatus['values'][0]['value'];

    //Create activity
    $contactID = sparkpost_getTargetContactId($params);
    $activityParams = array( 
      'source_contact_id' => $sourceContactID,
      'activity_type_id' => $activityTypeID, 
      'subject' => $params['subject'],
      'activity_date_time' => date('YmdHis'),
      'status_id' => $activityStatusID,
      'priority_id' => 2,
      'details' => CRM_Utils_Array::value('html', $params, $params['text']),
      'target_contact_id' => $contactID[0]
    );
    $newActivity = civicrm_api3('activity', 'create', $activityParams);

    //Get email ID
    $emailID = NULL;
    $contactEmails = civicrm_api3('Email', 'get', array( //get all emails that match for this contact
      'sequential' => 1,
      'contact_id' => $contactID[0],
      'email' => $params['toEmail']
    ));
    if($contactEmails['count']==1){ //if only one result use that
      $emailID = $contactEmails['values'][0]['id'];
    }else{
      foreach ($contactEmails['values'] as $emailRecord) {
        if($emailRecord['is_primary']) $emailID = $emailRecord['id']; //elseif primary, use primary
      }
      if(empty($emailID)) $emailID = $contactEmails['values'][0]['id']; //ok... just use the first one
    }
    //Prep email queue vars
    $mailingJob = civicrm_api3('MailingJob', 'get', array(
      'sequential' => 1,
      'job_type' => 'SparkPost Transactional Emails',
      'options' => array('limit' => 1)
    ));
    //Crate mailing event queue
    $queueParams = array(
      'job_id' => $mailingJob['id'],
      'contact_id' => $contactID[0],
      'email_id' => $emailID
    );
    $eventQueue = CRM_Mailing_Event_BAO_Queue::create($queueParams);
    //Prep SparkPost metadata
    if(!empty($newActivity['id'])){
      $tags = array(
        'campaign_id' => 'Transactional Email',
        'metadata' => array(
          'civi_type' => 'transactional',
          'civi_hash' => $eventQueue->hash,
          'civi_jobid' => $mailingJob['id'],
          'civi_queue' => $eventQueue->id,
          'civi_activityid' => $newActivity['id']
        )
      );
    }
  }
  //Add SparkPost metadata to smtp headers
  $json = json_encode($tags);
  $params['headers']['X-MSYS-API'] = $json;

  return TRUE;
}

/**
 * Perform CiviCRM API call to grab event queue from hash
 * @param  string $h  hash value
 * @return array
 */
function sparkpost_queue($h) {
  $result = civicrm_api3('MailingEventQueue', 'get', array(
    'sequential' => 1,
    'hash' => $h,
  ));
  return $result['values'][0];
}

/**
 * Perform CiviCRM API call to grab mailing job from hash
 * @param  string $h  hash value
 * @return array
 */
function sparkpost_mailingjob($h) {
  $r1 = sparkpost_queue($h);
  $job_id = $r1['job_id'];

  $result = civicrm_api3('MailingJob', 'get', array(
    'sequential' => 1,
    'id' => $job_id,
  ));

  return $result['values'][0];
}

/**
 * Perform CiviCRM API call to grab mailing from hash
 * @param  string $h  hash value
 * @return array
 */
function sparkpost_mailing($h) {
  $r1 = sparkpost_mailingjob($h);
  $mailing_id = $r1['mailing_id'];

  $result = civicrm_api3('Mailing', 'get', array(
    'sequential' => 1,
    'id' => $mailing_id,
  ));

  return $result['values'][0];
}

/**
 * Perform CiviCRM API call to track a bounce in the database
 * @param  int $jid
 * @param  int $eqid
 * @param  string $hash
 * @param  string $body
 * @return boolean
 */
function sparkpost_addbounce($jid, $eqid, $hash, $body) {
  $result = civicrm_api3('Mailing', 'event_bounce', array(
    'sequential' => 1,
    'job_id' => $jid,
    'event_queue_id' => $eqid,
    'hash' => $hash,
    'body' => $body
  ));
  return $result;
}

/**
 * Create Hourly Scheduled Job for SparkPost.Fetchbounces
 * @return boolean success
 */
function sparkpost_job_create() {
  $currentDomainid=CRM_Core_Config::domainID();
  $result = civicrm_api3('Job', 'get', array('sequential' => 1, 'name' => 'SparkPost Fetch Bounces', 'domain_id' => $currentDomainid));
  if($result['count'] < 1) {
    $result = civicrm_api3('Job', 'create', array(
      'sequential' => 1,
      'run_frequency' => 'Hourly',
      'name' => 'SparkPost Fetch Bounces',
      'description' => 'Enables CiviCRM to communicate with SparkPost over a REST API to track bounces in CiviCRM',
      'is_active' => false,
      'api_entity' => 'SparkPost',
      'api_action' => 'Fetchbounces',
      'domain_id' => $currentDomainid,
      'parameters' => 'api_key=enterkeyhere - required
events=bounce,delay,policy_rejection,out_of_band,spam_complaint - optional
date_filter=1 - optional'
    ));
    return $result['is_error'];
  } else {
    return false;
  }
}

/**
 * Perform CiviCRM API call to grab most recent successful Sparkpost successful job
 * @return datetime
 */
function sparkpost_recentFetchSuccess() {
  try {
    $result = civicrm_api3('JobLog', 'get', array(
      'sequential' => 1,
      'name' => 'SparkPost Fetch Bounces',
      'description' => array('LIKE' => "%Finished execution of SparkPost Fetch Bounces with result: Success%"),
      'options' => array('sort' => 'run_time DESC', 'limit' => 1),
      'return' => array('run_time'),
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    $error = $e->getMessage();
  }
  if(!empty($error)){
      if (strpos($error, 'API (JobLog, get) does not exist') !== false) {
        return 0;
      }
    }
  if(!empty($result['values']))
    return $result['values'][0]['run_time'];
}

/**
 * Perform CiviCRM API call to grab from addresses
 * @return string
 */
function sparkpost_getFromAddresses(){
  $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'return' => 'label',
    'option_group_id' => 'from_email_address',
  ));

  foreach($result['values'] as $k => $value) {
    $split = preg_split("/<|>/",$value['label']);
    $froms .= $split[1];

    if(count($result['values']) != ($k + 1))
      $froms .= ',';
  }
  return $froms;
}

/**
 * Returns contact IDs for transational emails
 * @return string
 */
function sparkpost_getTargetContactId($params) {
  $emails = array_merge(
    explode(
      ',', 
      $params['toEmail']
    ), 
    explode(
      ',', 
      CRM_Utils_Array::value('cc', $params, '')
    ), 
    explode(
      ',', 
      CRM_Utils_Array::value('bcc', $params, '')
    )
  );
  $targetContactIds = array();
  foreach ($emails as $email) {
    preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $email, $matches);
    if (!empty($matches[0])) {
      $targetContacts = sparkpost_getEmailContactId(trim($matches[0]));
      $targetContactIds = array_merge(
        $targetContactIds, 
        CRM_Utils_Array::value('contactIds', $targetContacts, array())
      );
    }
  }
  return array_unique($targetContactIds);
}

function sparkpost_getEmailContactId($email, $checkUnique = FALSE) {
  if(!$email) {
    return FALSE;
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $matches = array();
    preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $email, $matches);
    if (!empty($matches)) {
      $email = $matches[0];
    }
  }
  
  $emails['email'] = null;
  $params = array( 
    'email' => $email,
    'return' => array('contact_id'),
    'api.Contact.get' => array('is_deleted' => 0, 'return' => array('id')),
  );
  $result = civicrm_api3('email', 'get', $params);
  
  //if contact not found then create new one
  if (!$result['count']) {
    $contactParams = array(
      'contact_type' => 'Individual',
      'email' => $email,
    );
    civicrm_api3('contact', 'create', $contactParams);
    $result = civicrm_api3('email', 'get', $params);      
  }
  
  // changes done for bad data, sometimes there are multiple emails but without contact id   
  foreach ($result['values'] as $emailId => $emailValue) {
    if (CRM_Utils_Array::value('contact_id', $emailValue)
      && $emailValue['api.Contact.get']['count']
    ) {
      if (!CRM_Utils_Array::value('email', $emails)) {
        $emails['email'] = $emailValue;
      }
      if (!$checkUnique) {
        $emails['contactIds'][] = $emailValue['contact_id'];
      }
      else {
        break;
      }
    }
  }
  return $emails;
}

/**
 * Perform CiviCRM API call to update transactional email activity with bounced status
 * @param  int    $activityID
 * @param  string $reason
 */
function sparkpost_updateBounceActivity($activityID,$reason) {
  $activity = civicrm_api3('Activity', 'get', array(
    'sequential' => 1,
    'id' => $activityID
  ));

  $activityStatus = civicrm_api3('OptionValue', 'get', array('sequential' => 1,'option_group_id' => 'activity_status','name' => 'Bounced'));
  $activityStatusID = $activityStatus['values'][0]['value'];

  $UpdatedActivity = civicrm_api3('Activity', 'create', array(
    'sequential' => 1,
    'id' => $activityID,
    'status_id' => $activityStatusID,
    'details' => '<p>BOUNCE REASON: ' . $reason . '</p><hr>' . $activity['values'][0]['details']
  ));
  return TRUE;
}

function sparkpost_addOptionValues() {
  // create activity_type -> transactional email
  $activity_type_values = CRM_Core_OptionGroup::values('activity_type');
  if (!in_array('Transactional Email', $activity_type_values)) {
    $params = array(
      'label' => 'Transactional Email',
      'is_active' => 1
    );
    $groupParams = array('name' => 'activity_type');
    $action = 1;
    $activityObject = CRM_Core_OptionValue::addOptionValue($params, $groupParams, $action, $optionValueID);
  }

  // create activity_status -> pending
  $activity_status_values = CRM_Core_OptionGroup::values('activity_status');
  if (!in_array('Pending', $activity_status_values)) {
    //create activity_status value
    $params = array(
      'label' => 'Pending',
      'is_active' => 1
    );
    $groupParams = array('name' => 'activity_status');
    $action = 1;
    $activityObject = CRM_Core_OptionValue::addOptionValue($params, $groupParams, $action, $optionValueID);
  }

  // create activity_status -> bounced
  $activity_status_values = CRM_Core_OptionGroup::values('activity_status');
  if (!in_array('Bounced', $activity_status_values)) {
    //create activity_status value
    $params = array(
      'label' => 'Bounced',
      'is_active' => 1
    );
    $groupParams = array('name' => 'activity_status');
    $action = 1;
    $activityObject = CRM_Core_OptionValue::addOptionValue($params, $groupParams, $action, $optionValueID);
  }
}

function sparkpost_mailingsCheck(){
  //Add mailing - holds all transactional emails for bounce processing
  //check for existing mailing 
  $mailingCheck = civicrm_api3('Mailing', 'get', array('name' => 'SparkPost Transactional Emails','domain_id' => CRM_Core_Config::domainID(),'options' => array('limit' => 1)));

  //add mailing if it does not exist 
  if($mailingCheck['count'] < 1){
    $mailingName = $mailingJobType = 'SparkPost Transactional Emails';
    $mailingParams = array(
      'subject' => $mailingName,
      'name' => $mailingName,
      'url_tracking' => TRUE,
      'forward_replies' => FALSE,
      'auto_responder' => FALSE,
      'open_tracking' => FALSE,
      'is_completed' => TRUE,
    );
    //create entry in civicrm_mailing
    $mailing = CRM_Mailing_BAO_Mailing::add($mailingParams, CRM_Core_DAO::$_nullArray);

    //create entry in civicrm_mailing_job
    $mailingJob = new CRM_Mailing_DAO_MailingJob();
    $mailingJob->start_date = $mailingJob->end_date = date('YmdHis');
    $mailingJob->status = 'Complete';
    $mailingJob->job_type = $mailingJobType;
    $mailingJob->mailing_id = $mailing->id;
    $mailingJob->save();
  }
}
