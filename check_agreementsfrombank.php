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

$lines = file($file);
// Skip first two lines and last two lines
for($i=2; $i < (count($lines)-2); $i++) {
	try {
		$lineNr = $i + 1; // line number in the file
		$kidNumber = trim(substr($lines[$i], 16, 25));
		$notificationToBank = substr($lines[$i], 41, 1);
		
		// check whether KID number exist in the system
		$kidExistsQuery = $cnxn->query("SELECT * FROM civicrm_kid_number WHERE kid_number = {$kidNumber}");
		if ($kidExistsQuery->execute()) {
			$kidExistsQuery = $kidExistsQuery->fetch();
		} else {
			$kidExistsQuery = false;
		}
		if ($kidExistsQuery) {
			// Check whether the contact exists and is not deleted nor deceased
			$contact_id = $kidExistsQuery['contact_id'];
			$contactQuery = $cnxn->query("SELECT * FROM civicrm_contact WHERE id = {$contact_id}");
			if ($contactQuery->execute()) {
				$contactQuery = $contactQuery->fetch();
			} else {
				$contactQuery = false;
			}
			if ($contactQuery && $contactQuery['is_deleted'] == 0 && $contactQuery['is_deceased'] == 0) {
				// Chech whether the notification to bank matches
				$notificationToBankQuery = $cnxn->query("select recur_off.* 
					from civicrm_kid_number kid  
					inner join civicrm_contribution ON kid.entity = 'Contribution'  and kid.entity_id = civicrm_contribution.id 
					inner join civicrm_contribution_recur_offline recur_off on civicrm_contribution.contribution_recur_id = recur_off.recur_id
					WHERE kid.kid_number = '{$kidNumber}'"
				);
				if ($notificationToBankQuery->execute()) {
					$notificationToBankQuery = $notificationToBankQuery->fetch();
				} else {
					$notificationToBankQuery = false;
				}
				if ($notificationToBankQuery) {
					if ($notificationToBankQuery['notification_for_bank'] == '0' && $notificationToBank == 'J') {
						echo "Line {$lineNr}: Notification to bank should be YES according to the bank for KID Number {$kidNumber} and Contact ID {$contact_id}\n";	
					} elseif ($notificationToBankQuery['notification_for_bank'] == '1' && $notificationToBank == 'N') {
						echo "Line {$lineNr}: Notification to bank should be NO according to the bank for KID Number {$kidNumber} and Contact ID {$contact_id}\n";	
					}
				}	else {
					echo "Line {$lineNr}: No recurring contribution found for KID Number {$kidNumber} and Contact ID {$contact_id}\n";	
				}
			} elseif ($contactQuery && $contactQuery['is_deleted'] == 1) {
				echo "Line {$lineNr}: Contact {$contact_id} with KID Number {$kidNumber} is marked as deleted\n";	
			} elseif ($contactQuery && $contactQuery['is_deceased'] == 1) {
				echo "Line {$lineNr}: Contact {$contact_id} with KID Number {$kidNumber} is marked as deceased\n";	
			} else {
				echo "Line {$lineNr}: Contact {$contact_id} with KID Number {$kidNumber} does not exist in system\n";
			}
		} else {
			echo "Line {$lineNr}: KID Number {$kidNumber} does not exist in system\n";
		}
	} catch (PDOException $exception) {
		echo $exception->getMessage()."\n";
	} 
}

