#!/usr/bin/php
<?php

$dbUser = $argv[1];
$dbPass = $argv[2];
$db = $argv[3];
$file = $argv[4];
$dsn = "mysql:dbname={$db};host=127.0.0.1";

$cnxn = new PDO($dsn, $dbUser, $dbPass);
if (!$cnxn) {
  echo 'Could not connect'; 
  exit();
}
$cnxn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Find all Active Avtale Giro agreements
$stmnt = $cnxn->query("SELECT sdd.reference, r.* FROM civicrm_contribution_recur r
inner join civicrm_sdd_mandate sdd ON sdd.entity_id = r.id and sdd.entity_table = 'civicrm_contribution_recur'
where sdd.is_enabled = 1 and (r.end_date is null or r.end_date >= now());");
$activeMandates = array();
while ($row = $stmnt->fetch(PDO::FETCH_ASSOC)) {
  $kid = substr($row['reference'], 0, 14);
  $activeMandates[$kid] = $row;
}

$lines = file($file);
// Skip first two lines and last two lines
for($i=2; $i < (count($lines)-2); $i++) {
  try {
    $lineNr = $i + 1; // line number in the file
    $kidNumber = trim(substr($lines[$i], 16, 25));
    $campaign_id = (int) substr($kidNumber, 7, 6);
    $contact_id = (int) substr($kidNumber, 0, 7);
    $notificationToBank = substr($lines[$i], 41, 1);
    
    $stmnt = $cnxn->prepare("SELECT sdd.reference, r.* FROM civicrm_contribution_recur r
inner join civicrm_sdd_mandate sdd ON sdd.entity_id = r.id and sdd.entity_table = 'civicrm_contribution_recur'
where sdd.is_enabled = 1 and (r.end_date is null or r.end_date >= now()) AND r.contact_id = :contact_id and r.campaign_id = :campaign_id");
    $stmnt->execute(array('contact_id' => $contact_id, 'campaign_id' => $campaign_id));
    
    
    if ($stmnt->rowCount() == 1) {
      // Found an active mandate.
      if (isset($activeMandates[$kidNumber])) {
        unset($activeMandates[$kidNumber]);
      }
    } else {
      echo "Line {$lineNr}: No active avtale giro found for KID Number {$kidNumber}\n";
    }
    
  } catch (Exception $e) {
    echo $exception->getMessage()."\n";
  }
}

foreach($activeMandates as $kidNumber => $row) {
  echo "{$kidNumber} is an active mandate but not in the file.\n";
}
