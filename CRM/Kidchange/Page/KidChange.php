<?php
use CRM_Kidchange_ExtensionUtil as E;

class CRM_Kidchange_Page_KidChange extends CRM_Core_Page {

	protected $old_order_account = "70586360610";
	protected $new_order_account = "15039583302";
	protected $customer_unit_id = "00131936";
	
  public function run() {
  	$old_kids = array();
		$double_kids = array();
		
    $dao = CRM_Core_DAO::executeQuery("SELECT 
    	contribution_recur.contact_id, 
    	contribution_recur.campaign_id,
    	old_kid.identifier AS old_kid
    	FROM civicrm_contribution_recur contribution_recur
    	INNER JOIN (
    		SELECT max(used_since), entity_id, identifier  
    		FROM civicrm_value_contact_id_history 
    		WHERE identifier_type = 'KID' 
    		AND length(identifier) != 14
    		GROUP BY entity_id
    	) old_kid ON old_kid.entity_id = contribution_recur.contact_id
    	WHERE (contribution_recur.end_date IS NULL OR contribution_recur.end_date >= CURDATE())"
		);
		$lines = array();
		
		$today = new DateTime();
		$tomorrow = new DateTime();
		$tomorrow->modify("+1 day");
		
		$start_transmission = "NY000010";
		$start_transmission .= $this->customer_unit_id;
		$start_transmission .= $today->format("dm")."001";
		$start_transmission .= "00008080";
		$start_transmission .= str_pad("", 49, "0", STR_PAD_LEFT);
		$lines[] = $start_transmission;
		
		$start_record = "NY212720000000000";
		$start_record .= $tomorrow->format("dm")."001";
		$start_record .= $this->old_order_account;
		$start_record .= $this->new_order_account;
		$start_record .= str_pad("", 34, "0", STR_PAD_LEFT);
		$lines[] = $start_record;
		
		$i = 0;
		while($dao->fetch()) {
			if (in_array($dao->old_kid, $old_kids)) {
				$double_kids[] = "Contact ID: $dao->contact_id, old kid: $dao->old_kid";
			}
			$i++;	
			$new_kid = kidbanking_generate_kidnumber($dao->contact_id, $dao->campaign_id, FALSE);
			$line = "NY216926";
			$line .= str_pad($i, 7, "0", STR_PAD_LEFT);
			$line .= str_pad($dao->old_kid, 25, " ", STR_PAD_LEFT);
			$line .= str_pad($new_kid, 25, " ", STR_PAD_LEFT);
			$line .= "000000000000000";
			$lines[] = $line;
			
			$old_kids[] = $dao->old_kid;
		}
		$records = $i+2; 
		
		$end_record = "NY212788";
		$end_record .= str_pad($i, 8, "0", STR_PAD_LEFT);
		$end_record .= str_pad($records, 8, "0", STR_PAD_LEFT);
		$end_record .= str_pad("", 17, "0", STR_PAD_LEFT);
		$end_record .= str_pad("", 6, "0", STR_PAD_LEFT);
		$end_record .= str_pad("", 6, "0", STR_PAD_LEFT);
		$end_record .= str_pad("", 27, "0", STR_PAD_LEFT);
		$lines[] = $end_record;
		
		$records = $records + 2;
		$end_assignment = "NY000089";
		$end_assignment .= str_pad($i, 8, "0", STR_PAD_LEFT);
		$end_assignment .= str_pad($records, 8, "0", STR_PAD_LEFT);
		$end_assignment .= str_pad("", 17, "0", STR_PAD_LEFT);
		$end_assignment .= str_pad("", 6, "0", STR_PAD_LEFT);
		$end_assignment .= str_pad("", 33, "0", STR_PAD_LEFT);
		$lines[] = $end_assignment;
		
		$file = implode("\n", $lines);
		
		if (count($double_kids)) {
			CRM_Core_Error::fatal('Could not generate change file as OLD KIDs are double in file: '.implode(", ", $double_kids));
			parent::run();
			return;
		}
		
		header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=kidchange.txt');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($file)); //Remove
    echo $file; 
    exit();

    parent::run();
  }

}
