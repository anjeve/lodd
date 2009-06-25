<?php

/**
* Create MySQL Dump from STITCH files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

$database_stitch = "lodd_stitch";

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_stitch);

$files = array (
//				"dataset/chemicals.v1.0.tsv", 
//				"dataset/chemical_chemical.links.v1.0.tsv",
//				"dataset/chemical.sources.v1.0.tsv",
//				"dataset/chemical.aliases.v1.0.tsv",
				"dataset/organisms.txt",
				"dataset/protein_chemical.links.v1.0.tsv"
				);

$tables = array(
//				"chemicals", 
//				"chemical_links",
//				"chemical_sources",
//				"chemical_aliases",
				"organisms",
				"protein_chemical_links",
				);

$blob_fields = array("SMILES_string", "name", "source");

$chemicalSources = array();

function countUpperCaseLetters($string) {
	preg_match_all("/[A-Z]/", $string, $your_match) ;
	return count($your_match[0]);
}

$date = date(DATE_RFC822);
echo "Starting import at $date\n";

// PROTEINS and SOURCES

$additional_tables = array("proteins" => array("id"));
foreach ($additional_tables as $name => $fields) {
	$sql_query = "DROP TABLE ".$name;
	mysql_query ($sql_query);
	
	$sql_query = "CREATE TABLE IF NOT EXISTS ".$name." (";
	foreach ($fields as $id => $column) {
		if (in_array(trim($column), $blob_fields)) {
			$sql_query .= $column." blob NOT NULL,";
		} else {
			$sql_query .= $column." varchar(500) NOT NULL,";
		}
	}
	$sql_query .= "
		UNIQUE KEY $fields[0] ($fields[0])
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
}

foreach ($files as $file_id => $file) {
	$date = date(DATE_RFC822);
	echo "Starting import of file $file at $date\n";
	
	$file_handle = fopen($file, "r");
	if (!$file_handle) {
		die ("File not found ".$file);
	}
	
	$sql_query = "DROP TABLE ".$tables[$file_id];
	mysql_query ($sql_query);
	
	// CREATE TABLE - 1. line: column definitions
	$line = fgets($file_handle);
	$columns = explode("\t", $line);
	if ($tables[$file_id] == "protein_chemical_links") {
		$columns[] = "organism";
		$columns[] = "protein_id";
	}
	if ($tables[$file_id] != "chemical_sources") {
		$sql_query = "CREATE TABLE IF NOT EXISTS ".$tables[$file_id]." (";
		foreach ($columns as $id => $column) {
			$columns[$id] = trim($column);
			if (in_array(trim($column), $blob_fields)) {
				$sql_query .= $column." blob NOT NULL,";
			} else {
				$sql_query .= $column." varchar(500) NOT NULL,";
			}
		}
		if ($tables[$file_id] == "chemical_links") {
			$sql_query .= "PRIMARY KEY  (chemical1,chemical2)";

		} else if ($tables[$file_id] == "protein_chemical_links") {
			$sql_query .= "PRIMARY KEY  (chemical,protein)";
		} else {
			$sql_query = substr($sql_query, 0, -1);
		}
		$sql_query .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1;";
		if (!mysql_query ($sql_query)) {
			die(mysql_error() . " - query: ".$sql_query);
		}
	}
		
	// $debug_counter = 0;
	while (!feof($file_handle)) {
	// while ($debug_counter < 100) {
		// $debug_counter = $debug_counter + 1;
		$line = fgets($file_handle);
		if ((string)strpos($line, "#") !== (string)0) {
			if ($tables[$file_id] == "protein_chemical_links") {
				$line_parts2 = explode("\t", $line);
				$line_parts1 = array();
				$protein = explode(".", $line_parts2[1]);
				$line_parts1[0] = $protein[0];
				$line_parts1[1] = substr($line_parts2[1], strlen($line_parts1[0])+1);
				$line_parts = array_merge($line_parts2, $line_parts1);

				$sql_query = "INSERT INTO proteins ( id ) 
					VALUES ('" . $line_parts1[1] . "');";
				if (!mysql_query ($sql_query)) {
					if (strpos(mysql_error(), "Duplicate entry") === false) {
						die("[DIE] ". mysql_error() . " - query: ".$sql_query);
					}
				}

			} else {
				$line_parts = explode("\t", $line);
			}
			if (sizeof($columns) == sizeof($line_parts)) {
				foreach ($line_parts as $id => $line_part) {
					if ((!in_array($columns[$id], $blob_fields)) && (strlen(trim($line_part))> 500)) {
						die("[DIE] too long (column: $columns[$id]): $line");
					}
					$line_parts[$id] = str_replace("\\\\'", "\'", str_replace("'", "\'", trim($line_part)));
				}
				if ($tables[$file_id] == "chemical_sources") {
					if (!in_array($line_parts[1], $chemicalSources))	{
						$sql_query = "CREATE TABLE IF NOT EXISTS ".$line_parts[1]." (";
						$sql_query .= "chemical varchar(500) NOT NULL,";
						$sql_query .= "id varchar(500) NOT NULL,";
						$sql_query .= "PRIMARY KEY (chemical,id)";
						$sql_query .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1;";
						if (!mysql_query ($sql_query)) {
							die(mysql_error() . " - query: ".$sql_query);
						}						
						$chemicalSources[] = $line_parts[1];
					}
					$sql_query = "INSERT INTO ".$line_parts[1]." ( chemical, id ) 
						VALUES ('" .$line_parts[0]. "', '" .$line_parts[2]. "');";
					if (!mysql_query ($sql_query)) {
						die("[DIE] ". mysql_error() . " - query: ".$sql_query);
					}
				} else {
					$sql_query = "INSERT INTO ".$tables[$file_id]." ( " .implode(", ", $columns). " ) 
						VALUES ('" . implode("', '", $line_parts). "');";
					if (!mysql_query ($sql_query)) {
						die("[DIE] ". mysql_error() . " - query: ".$sql_query);
					}
				}
			} else {
				echo "problem in line: $line\n";
			}
		}
	}
	fclose($file_handle);
	$date = date(DATE_RFC822);
	echo "Finished import of file $file at $date\n";
}
$date = date(DATE_RFC822);
echo "Starting import at $date\n";

?>