<?php

/**
* Linking
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");
mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_sider);


$database_sider_table_drugs = "drugs";
$database_sider_table_drugs_alternate_names = "drugs_alternate_names";


// SIDER -> DRUGBANK

$table_sider_drugbank = "sider_drugbank";
$database_drugbank_table_drugs = "drugs";
$database_drugbank_table_brandnames = "brandnames";
$database_drugbank_table_synonyms = "synonyms";

mysql_query ("drop table ".$table_sider_drugbank.";");
$sql_query = "CREATE TABLE IF NOT EXISTS $table_sider_drugbank (
  `sider_drug_id` varchar(15) NOT NULL,
  `drugbank_drug` varchar(15) NOT NULL,
  PRIMARY KEY  (`sider_drug_id`,`drugbank_drug`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$mysqlquery = 'SELECT sider_drug_id, name FROM '.$database_sider_table_drugs;
$result_sider = mysql_query($mysqlquery);
while ($row_sider = mysql_fetch_row($result_sider)) {
	$name = $row_sider[1];
	$sider_drug_id = $row_sider[0];
	mysql_select_db ($database_drugbank);
	$drugbank_drugs = array();
	$mysqlquery = 'SELECT id FROM '.$database_drugbank_table_drugs.' where genericName = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$mysqlquery = 'SELECT drug FROM '.$database_drugbank_table_brandnames.' where field = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$mysqlquery = 'SELECT drug FROM '.$database_drugbank_table_synonyms.' where field = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$drugbank_drugs = array_unique($drugbank_drugs);
	if (sizeof($drugbank_drugs) == 1)  {
		mysql_select_db ($database_sider);
		$mysqlquery1 = 'INSERT INTO '.$table_sider_drugbank.' (sider_drug_id, drugbank_drug) VALUES ("'.$sider_drug_id.'", "'.$drugbank_drugs[0].'")';
		if (!mysql_query($mysqlquery1)) {
			die ("sider -> drugbank: ". mysql_error());
		}
		// $sider_drug_id
	} else if (sizeof($intersected_drugbankids) > 1) {
		echo "more found: $sider_drug_id - $name\n"; 
	}
}

//Select * from backup_tbl Where id NOT IN(SELECT id FROM table2)

$mysqlquery = 'SELECT sider_drug_id, name FROM '.$database_sider_table_drugs_alternate_names.' WHERE sider_drug_id NOT IN (SELECT sider_drug_id FROM '.$table_sider_drugbank.')';
$result_sider = mysql_query($mysqlquery);
while ($row_sider = mysql_fetch_row($result_sider)) {
	$name = $row_sider[1];
	$sider_drug_id = $row_sider[0];
	mysql_select_db ($database_drugbank);
	$drugbank_drugs = array();
	$mysqlquery = 'SELECT id FROM '.$database_drugbank_table_drugs.' where genericName = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$mysqlquery = 'SELECT drug FROM '.$database_drugbank_table_brandnames.' where field = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$mysqlquery = 'SELECT drug FROM '.$database_drugbank_table_synonyms.' where field = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$drugbank_drugs = array_unique($drugbank_drugs);
	if (sizeof($drugbank_drugs) == 1)  {
		mysql_select_db ($database_sider);
		$mysqlquery1 = 'INSERT INTO '.$table_sider_drugbank.' (sider_drug_id, drugbank_drug) VALUES ("'.$sider_drug_id.'", "'.$drugbank_drugs[0].'")';
		if (!mysql_query($mysqlquery1)) {
			//die ("sider -> drugbank: ". mysql_error());
		}
		// $sider_drug_id
	} else if (sizeof($drugbank_drugs) > 1) {
		echo "more found: $sider_drug_id - $name\n"; 
	}
}


// SIDER -> DAILYMED

$table_sider_dailymed = "sider_dailymed";
$database_dailymed_table_drugs = "drugs";

mysql_query ("drop table ".$table_sider_dailymed.";");
$sql_query = "CREATE TABLE IF NOT EXISTS $table_sider_dailymed (
  `sider_drug_id` varchar(15) NOT NULL,
  `dailymed_drug` varchar(15) NOT NULL,
  PRIMARY KEY  (`sider_drug_id`,`dailymed_drug`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$mysqlquery = 'SELECT sider_drug_id, name FROM '.$database_sider_table_drugs;
$result_sider = mysql_query($mysqlquery);
while ($row_sider = mysql_fetch_row($result_sider)) {
	$name = $row_sider[1];
	$sider_drug_id = $row_sider[0];
	mysql_select_db ($database_dailymed);
	$drugbank_drugs = array();
	$mysqlquery = 'SELECT id FROM '.$database_dailymed_table_drugs.' where name = "'.$name.'" OR activeIngridient = "'.$name.'" OR activeMoiety = "'.$name.'" OR genericMedicine = "'.$name.'"';
	$result = mysql_query($mysqlquery);
	while ($row = mysql_fetch_row($result)) {
		$drugbank_drugs[] = $row[0];
	}
	$drugbank_drugs = array_unique($drugbank_drugs);
	if (sizeof($drugbank_drugs) == 1)  {
		mysql_select_db ($database_sider);
		$mysqlquery1 = 'INSERT INTO '.$table_sider_dailymed.' (sider_drug_id, dailymed_drug) VALUES ("'.$sider_drug_id.'", "'.$drugbank_drugs[0].'")';
		if (!mysql_query($mysqlquery1)) {
			die ("sider -> dailymed: ". mysql_error());
		}
		// $sider_drug_id
	} else if (sizeof($drugbank_drugs) > 1) {
		echo "more found (dailymed): $sider_drug_id - $name\n"; 
	}
}

?>