<?php

/**
* Create MySQL Dump from Diseasome files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

$database_sider_table_dbpedia = "dbpedia";

require_once("../scripts/lodd_utils.php");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_sider);

mysql_query ("drop table ".$database_sider_table_dbpedia.";");

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_sider_table_dbpedia." ( 
	sider_side_effect varchar(15) NOT NULL,
	dbpedia_disease varchar(255) NULL,
 	PRIMARY KEY  (sider_side_effect, dbpedia_disease)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}


$file = "sider_dbpedia_links.n3";

$file_handle = fopen($file, "r");
if (!$file_handle) {
	die ("File not found ".$file);
}
while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	if (preg_match("/<http:\/\/dbpedia\.org\/resource\/([^>]*)> owl:sameAs <[^C]*([^>]*)>/", $line, $match)) {
		$dbpedia = $match[1];
		$sider = $match[2];
	}
	
	$sql_query = "INSERT INTO ".$database_sider_table_dbpedia." (sider_side_effect, dbpedia_disease) VALUES ('".$sider."', '".$dbpedia."');";
	if (!mysql_query ($sql_query)) {
		if (strpos(mysql_error(), "Duplicate entry") === false) {
			die("[DIE] ". mysql_error() . " - query: ".$sql_query);
		}
	}


}
fclose($file_handle);

?>