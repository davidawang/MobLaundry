<?php

// Use simplehtmldom (open source on sourceforge) to easily parse html tables.
require 'simplehtmldom/simple_html_dom.php';

//include our magic sauce
require "queue.php";

// Never use global variables again.

$CampusPassword = "penn6389";
$LaundryBaseUrl = "";

function modifyTimeStamp($file)
{
	$TimeNow = strtotime('now');
	$fp = fopen($file, 'w');
	fwrite($fp, $TimeNow);
	fclose($fp);	
}

function getTimeStamp($file)
{
	if ($fp = fopen($file, 'r'))
	{
		$content = '';
		while($line = fgets($fp, 1024))
		{
			$content .= $line;
		}
	} else
		echo "Problem with reading time stamp";
	return $content;
}



function getAllHallsOfUniversity($UniversityPassword)
{
	//God Damn global variables. Must change to object oriented later.
	$MachinesOfAllHalls = array();
	

	$LaundryBaseUrl = "http://www.laundryalert.com/cgi-bin/" . $UniversityPassword . "/LMRoom?Halls=";
	
	// Later we want to dynamically generate the maximum number of halls
	$MaxNumHalls = 54;
	
	// Use multi curl function from http://www.rustyrazorblade.com/2008/02/20/curl_multi_exec/
	// In order to make downloading data faster (aka have more connections)
	$curl_arr = array();
	$master = curl_multi_init();

	for($i = 0; $i < $MaxNumHalls; $i++)
	{
		
		$LaundryFullUrl = "". $LaundryBaseUrl . $i;
		
		$curl_arr[$i] = curl_init("$LaundryFullUrl");
		curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
		curl_multi_add_handle($master, $curl_arr[$i]);
	}

	do {
	    curl_multi_exec($master,$running);
	} while($running > 0);

	
	//Stores all the curl'ed links into an array, $RawHtmlArray
	for($i = 0; $i < $MaxNumHalls; $i++)
	{
		$RawHtmlArray[$i] = curl_multi_getcontent($curl_arr[$i]);
	}
	
	
	// Put all halls into a huge array $MachinesOfAllHalls
	// Structure:
	// HallID | MachineID | Type | Status | Eta
	// Counter is just for each machine, regardless of in which hall
	$counter = 0;

	//
	foreach($RawHtmlArray as $HallIndex => $RawHtml)
	{
	
		$HtmlGarbage = DomIt($RawHtml);
		$OneMachine = ParseData($HtmlGarbage);
		//print_r($OneMachine);
		//die('');
		foreach($OneMachine as $MachineID => $MachineStuff)
		{
			$MachinesOfAllHalls[$counter] = 
					array("HallID" => $HallIndex,
					"MachineID" => $MachineStuff["MachineID"],
					"type" => $MachineStuff["MachineType"],
					"status" => $MachineStuff["MachineStatus"],
					"eta" => $MachineStuff["MachineETA"]);
			$counter ++;
		}
		
		
	} 
	
	return $MachinesOfAllHalls;

	
	
}
	
function DomIt($RawHtml)
{
	
	//Looks for data
	$oHTML = str_get_html($RawHtml);
    	// We want to match this pattern
    	// <table width=410 align="center" cellpadding=2 cellspacing=1 bgcolor=#FFFFFF>
    	$aData = array();
    	$oTRs = $oHTML->find('table[width=410]');
     
    	foreach($oTRs as $oTR) 
    	{
        	$aRow = array();
        	$oTDs = $oTR->find('td');

        	foreach($oTDs as $oTD) 
        	{
            		$aRow[] = trim($oTD->plaintext);
       		}

        	$aData[] = $aRow;
    	}
    	
    	return $aData;

}

function PreProcessData($aData)
{
	
	// $aData is the raw data after it's parsed.
	// It contains arrays of the rows of the tables,
	// but it still needs to be sorted into groups of 6

	// First get 2nd element of $aData, others are useless
	$prePreData = $aData[2];

	// Strip out first 8 elements of $preData, which are useless
	$preData = array_slice($prePreData, 8);
	
	// Now split into chunks of 6
	// Also drop the last element, since that just contains the buttons
	$Machines = array_chunk($preData, 6);
	array_pop($Machines);

	return $Machines;
}


function ParseData($GarbageData)
{
	// MachineList Contains a list of machines in a Hall
	$MachinesList = PreProcessData($GarbageData);

	$AllMachines = array();

	foreach($MachinesList as $MachineIndex => $MachineArray)
	{
		//Goes through information for each machine
		foreach($MachineArray as $id => $value)
		{
		
			switch ($id) 
			{
    			// Machine ID
   			case 0:
   				$AllMachines[$MachineIndex]["MachineID"] = $value;
       				break;
			
			// Machine Type
			case 1:
			
				if(preg_match("/washer/i", $value))
       					$AllMachines[$MachineIndex]["MachineType"] = "Washer";
       				else
       					$AllMachines[$MachineIndex]["MachineType"] = "Dryer";
        			break;
       	 
    			// Machine Status
    			case 2:
    				// Checking status, case insensitive check
    				if(preg_match("/available/i", $value))
       	 			$AllMachines[$MachineIndex]["MachineStatus"] = "Available";
       	 		else
       	 			$AllMachines[$MachineIndex]["MachineStatus"] = "In Use";
    	   	 		break;
   	 
    			// Machine Time Remaining (if any)
    			case 3:
    				if($AllMachines[$MachineIndex]["MachineStatus"] == "In Use")
    					$AllMachines[$MachineIndex]["MachineETA"] = $value;
    				else
    					$AllMachines[$MachineIndex]["MachineETA"] = 0;
    				break;
    		
    			default:
       			}	
		}
	}
	
	return $AllMachines;
}

function generateJSON($MachineID, $MachineType, $MachineStatus, $MachineETA)
{

	$jsonData=array();

	foreach($MachineID as $id => $value)
	{
		
			$jsonData[$id] = 
			array(
				id => $value, 
				type => $MachineType[$id], 
				status => $MachineStatus[$id], 
				eta => $MachineETA[$id]
			);

			//print_r($jsonData);
	}
	//header('Content-type: application/json');
	//echo "<pre>";
	echo json_encode($jsonData);
	//echo "</pre>";
}



?>
