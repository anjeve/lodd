<?php

/**
* Create MySQL Dump from DrugBank DrugCard file
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

$reference_tables = array(/*"drugTargetDrugReferences",*/ "drugTargetGeneralReferences");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_drugbank);

foreach ($reference_tables as $reference_table) {
	$query = 'SELECT * FROM '.$reference_table;
	$result = mysql_query($query);
	while ($row = mysql_fetch_row($result)) {
		if (sizeof($row) == 3) {
			$id = $row[0];
			$target = $row[1];
			$field = $row[2];
		} else {
			$target = $row[0];
			$field = $row[1];
		}
		$new_field = preg_replace("/([0-9]*)\s.*/", '$1', $field);
		$query = "UPDATE ".$reference_table." SET field ='".$new_field."' WHERE target = '".$target."'";
		if (sizeof($row) == 3) {
			$query .= " AND id='".$id."';";
		} else {
			$query .= ";";
		}
		if (!mysql_query ($query)) {
			die("[DIE] ". mysql_error() . " - query: ".$sql_query);
		}				
	}
}

?>