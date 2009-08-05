<?php

/**
* Linking
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("scripts/lodd_utils.php");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");

// DrugBank -> DailyMed
	// PRECONDITION: dailymed.drugs.drugbank_id

	$table_drugbank_drugs = "drugs";
	$table_drugbank_dailymed = "drug_drug";

	mysql_select_db ($database_drugbank);
	
	mysql_query ("drop table ".$table_drugbank_dailymed.";");
	$sql_query = "CREATE TABLE IF NOT EXISTS $table_drugbank_dailymed (
	  `dailymed_drug` int(10) NOT NULL,
	  `drugbank_drug` varchar(15) NOT NULL,
	  PRIMARY KEY  (`dailymed_drug`,`drugbank_drug`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
	
	mysql_select_db ($database_dailymed);
	$drugbank_ids = array();
	$mysqlquery = 'SELECT id, drugbank_id FROM '.$table_drugbank_drugs.' where drugbank_id IS NOT NULL';
	$result = mysql_query($mysqlquery);
	mysql_select_db($database_drugbank);
	while ($row = mysql_fetch_row($result)) {
		$mysqlquery1 = 'INSERT INTO '.$table_drugbank_dailymed.' (dailymed_drug, drugbank_drug) VALUES ('.$row[0].', "'.$row[1].'")';
		if (!mysql_query($mysqlquery1)) {
			die ("drugbank -> dailymed: ". mysql_error());
		}
	}

// DailyMed <-> Diseasome (via DrugBank)
	// PRECONDITION: diseasome.drug_targets filled by import_diseasome script
	
	$table_drugbank_drugs = "drugs";
	$table_diseasome_drugbank = "drug_targets";
	$table_drugbank_dailymed = "drug_drug";
	$table_diseasome_dailymed = "disease_drug";
	$table_dailymed_diseasome = "drug_disease";

	mysql_select_db ($database_dailymed);
	mysql_query ("drop table ".$table_dailymed_diseasome.";");
	$sql_query = "CREATE TABLE IF NOT EXISTS $table_dailymed_diseasome (
	  `dailymed_drug` int(10) NOT NULL,
	  `diseasome_disease` int(10) NOT NULL,
	  PRIMARY KEY  (`dailymed_drug`,`diseasome_disease`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}

	$drugbank_ids = array();
	$mysqlquery = 'SELECT id, drugbank_id FROM '.$table_drugbank_drugs.' where drugbank_id IS NOT NULL';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_ids[$row[0]] = $row[1];
	}
	
	mysql_select_db ($database_diseasome);
	mysql_query ("drop table ".$table_diseasome_dailymed.";");
	$sql_query = "CREATE TABLE IF NOT EXISTS $table_diseasome_dailymed (
	  `dailymed_drug` int(10) NOT NULL,
	  `diseasome_disease` int(10) NOT NULL,
	  PRIMARY KEY  (`dailymed_drug`,`diseasome_disease`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
	
	$mysqlquery = 'SELECT disease, drug FROM '.$table_diseasome_drugbank;
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		if (in_array($row[1], $drugbank_ids)) {
			$dailymed_ids = array_keys($drugbank_ids, $row[1]);
			foreach ($dailymed_ids as $dailymed_id) {
				mysql_select_db ($database_diseasome);
				$mysqlquery1 = 'INSERT INTO '.$table_diseasome_dailymed.' (diseasome_disease, dailymed_drug) VALUES ('.$row[0].', "'.$dailymed_id.'")';
				if (!mysql_query($mysqlquery1)) {
					die ("diseasome -> dailymed: ". mysql_error());
				}
				mysql_select_db ($database_dailymed);
				$mysqlquery1 = 'INSERT INTO '.$table_dailymed_diseasome.' (diseasome_disease, dailymed_drug) VALUES ('.$row[0].', "'.$dailymed_id.'")';
				if (!mysql_query($mysqlquery1)) {
					die ("dailymed -> diseasome: ". mysql_error());
				}
			}
		}
		
	}
		
?>