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
  if(CRM_Core_DAO::singleValueQuery("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'civicrm_mailing_bounce_type' AND COLUMN_NAME = 'name'")=='enum'){
    CRM_Core_DAO::singleValueQuery("ALTER TABLE civicrm_mailing_bounce_type CHANGE name name VARCHAR(24) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Type of bounce'");
  }
  
  //Add bounce type and pattern to database
  if(!CRM_Core_DAO::singleValueQuery("SELECT count(id) as 'COUNT' FROM civicrm_mailing_bounce_type WHERE `name` = 'SparkPost'")) {
    CRM_Core_DAO::singleValueQuery("INSERT INTO `civicrm_mailing_bounce_type` (`name`, `description`, `hold_threshold`) VALUES ('SparkPost', 'SparkPost supression list', 1)");
    $bounce_type_id = CRM_Core_DAO::singleValueQuery("SELECT `id` FROM `civicrm_mailing_bounce_type` WHERE `name` = 'SparkPost'");
    CRM_Core_DAO::singleValueQuery("INSERT INTO `civicrm_mailing_bounce_pattern` (`bounce_type_id`, `pattern`) VALUES ($bounce_type_id, 'recipient address suppressed due to customer policy')");
  }
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

function sparkpost_civicrm_alterMailParams(&$params, $context) {
  $path = $params['Return-Path'];
  $subject = $params['Subject'];
  $temp = explode('.', $path);
  $hash = substr($temp[3], 0, strpos($temp[3], '@'));
  $mailing = sparkpost_mailing($hash);
  $tags = array('metadata' => array('civi-rp' => $path),'campaign_id' => $mailing['name']);
  $json = json_encode($tags);
  $params['X-MSYS-API'] = $json."\n";
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
function civiapi_recent_sparkpost() {
  try {
    $result = civicrm_api3('JobLog', 'get', array(
      'sequential' => 1,
      'name' => "SparkPost Fetch Bounces",
      'description' => array('LIKE' => "%Finished execution of SparkPost Fetch Bounces with result: Success%"),
      'options' => array('sort' => "run_time DESC", 'limit' => 1),
      'return' => array("run_time"),
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
  return $result['values'][0]['run_time'];
}
/**
 * Perform CiviCRM API call to grab from addresses
 * @return string
 */
function getFromAddresses(){
  $result = civicrm_api3('OptionValue', 'get', array(
    'sequential' => 1,
    'return' => "label",
    'option_group_id' => "from_email_address",
  ));
  $first=TRUE;
  $froms = '';
  foreach($result['values'] as $item){
    $itemparts = preg_split("/<|>/",$item['label']);
    $froms.=$itemparts[1];
    if($first) $froms.=',';
    $first=FALSE;
  }
  return $froms;
}
