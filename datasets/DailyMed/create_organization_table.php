<?php

/**
* Create MySQL Dump from Diseasome files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

$database_dailymed = "lodd_dailymed";
$table_dailymed_drugs = "drugs";
$table_dailymed_organizations = "organizations";

require_once("../scripts/lodd_utils.php");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_dailymed);

$query = 'SELECT id, representedOrganization FROM '.$table_dailymed_drugs.' where representedOrganization IS NOT NULL';
$result = mysql_query($query);
while ($row = mysql_fetch_row($result)) {
	$query = 'SELECT id, name FROM '.$table_dailymed_organizations.' where name = "'.$row[1].'"';
	$result1 = mysql_query($query);
	if (mysql_num_rows($result1) > 1) {
		die("[DIE] duplicate entry - query: ".$query);
	}
	while ($row1 = mysql_fetch_row($result1)) {
		$query = 'UPDATE '.$table_dailymed_drugs.' SET representedOrganization = "'.$row1[0].'" where id = "'.$row[0].'"';
		$result2 = mysql_query($query);
		if (!$result2) {
			die("[DIE] query: ".$query);
		}
	}
}
?>