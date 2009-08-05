<?php

/**
* Linking
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("scripts/lodd_utils.php");

// DrugBank -> DailyMed

	$table_drugbank_drugs = "drugs";
	$table_drugbank_dailymed = "drug_drug";
	
	mysql_query ("drop table ".$table_drugbank_dailymed.";");
	$sql_query = "CREATE TABLE IF NOT EXISTS $table_drugbank_dailymed (
	  `dailymed_drug` int(10) NOT NULL,
	  `drugbank_drug` varchar(15) NOT NULL,
	  PRIMARY KEY  (`dailymed_drug`,`drugbank_drug`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
	mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
	mysql_select_db ($database_dailymed);
	
	$drugbank_ids = array();
	$mysqlquery = 'SELECT id, drugbank_id FROM '.$table_drugbank_drugs.' where drugbank_id IS NOT NULL';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		mysql_select_db ("lodd");
		$mysqlquery1 = 'INSERT INTO '.$table_drugbank_dailymed.' (dailymed_drug, drugbank_id) VALUES ('.$row[0].', "'.$row[1].'")';
		if (!mysql_query($mysqlquery1)) {
			die ("drugbank -> dailymed: ". mysql_error());
		}
	}

// DailyMed <-> Diseasome

$table_drugbank_drugs = "drugs";

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_dailymed);

$drugbank_ids = array();
$mysqlquery = 'SELECT id, drugbank_id FROM '.$table_drugbank_drugs.' where drugbank_id IS NOT NULL';
$result = mysql_query($mysqlquery);
while ($row = mysql_fetch_row($result)) {
	mysql_select_db ("lodd");
	$mysqlquery1 = 'INSERT INTO drug_drug (dailymed_drug, drugbank_id) VALUES ('.$row[0].', "'.$row[1].'")';
	if (!mysql_query($mysqlquery1)) {
		die ("puh". mysql_error());
	}
}


?>