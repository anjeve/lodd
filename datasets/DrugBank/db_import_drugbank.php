<?php

/**
* Create MySQL Dump from DrugBank DrugCard file
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

$file = "drugbank_drugcards.txt";

$foaf_pages = array("","");

$special_seperate_dbs = array("drugInteractions");

$seperate_dbs = array("brandNames", "foodInteractions", "synonyms", "atcCodes", "ahfsCodes", "brandMixtures", "organismsAffected", "drugReference", "secondaryAccessionNo", "drugType", "drugCategory", "dosageForms");
$string_fields = array("brandNames", "foodInteractions", "synonyms", "atcCodes", "ahfsCodes", "brandMixtures", "organismsAffected");

$targets_seperate_dbs = array("drugTargetCellularLocation", "drugTargetDrugReferences", "drugTargetTransmembraneRegions", "drugTargetCellularLocation", "drugTargetGeneralReferences", "drugTargetSynonyms", "drugTargetSignals", "drugTargetPfamDomainFunction", "drugTargetGoClassificationFunction", "drugTargetGoClassificationProcess", "drugTargetGoClassificationComponent");
$targets_string_fields = array("drugTargetDrugReferences", "drugTargetTransmembraneRegions", "drugTargetSignals", "drugTargetPfamDomainFunction", "drugTargetSynonyms", "drugTargetGoClassificationFunction", "drugTargetGoClassificationProcess", "drugTargetGoClassificationComponent");

$enzymes_seperate_dbs = array();

$drugbankLink_fields = array("contraindicationInsert", "interactionInsert", "patientInformationInsert", "msdsFiles");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ("lodd");

$database_name = "drugs";
$database_name_targets = "targets";
$database_name_enzymes = "enzymes";
$database_name_targets_links = "drug_targets";
$database_name_enzymes_links = "drug_enzymes";
$database_name_drug_interactions = "drug_interactions";


$dbpedia_link = "dbpediaResource";

$drug_target_links = array();
$drug_enzyme_links = array();

$file_handle = fopen($file, "r");

if (!$file_handle) {
	die ("File not found ".$file);
}

$liness = 0;
$fields = array();

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));

	// TODO: "Not Available"
	if (preg_match("/#BEGIN_DRUGCARD (DB[0-9]*)/i", $line, $match)) {
		$drugcard_nr = $match[1];
		echo $drugcard_nr."\n";
		if ($data[$drugcard_nr] !== null) {
			die ("ID already known");
		}
	} else if ((strpos($line, "# ") === 0) && (strpos($line, ":") == (strlen($line)-1))) {
		$old_field = $field;
		$field = substr($line, 2, -1);
		$field = camelCase($field);
		/*
		if (preg_match("/Drug_Target_([0-9]*)_/", $field, $match)) {
			$drug_target_id = (int) $match[1];
			$field = preg_replace("/Drug_Target_([0-9]*)_/", "Drug_Target_", $field);
		}
		if (preg_match("/Phase_1_Metabolizing_Enzyme_([0-9]*)/", $field, $match)) {
			$drug_enzyme_id = (int) $match[1];
			$field = preg_replace("/Phase_1_Metabolizing_Enzyme_([0-9]*)/", "Phase_1_Metabolizing_Enzyme_", $field);
		}
		*/
		//$field = preg_replace("/Drug_Target_([0-9]*)_/", "Drug_Target_", $field);
		//$field = preg_replace("/Phase_1_Metabolizing_Enzyme_([0-9]*)/", "Phase_1_Metabolizing_Enzyme_", $field);
		// Create DBpedia-Link
		if (($old_field == "wikipediaLink") && ($data[$drugcard_nr][$old_field]  != null)) {
			$dbpedia_identifier = str_replace("http://en.wikipedia.org/wiki/", "", $data[$drugcard_nr][$old_field]);
			if ((strpos($dbpedia_identifier, " ") === false) && (strpos($dbpedia_identifier, "\n") === false)) {
				$data[$drugcard_nr][$dbpedia_link] = $dbpedia_identifier;
				$fields[$dbpedia_link] = 1;
			}
		}
		if (preg_match("/drugTarget([0-9]*)/", $field, $match)) {
			if ($drugcard_nr == "DB01273") {
				echo "";
			}
			$dt_field = preg_replace("/drugTarget([0-9]*)/", "drugTarget", $field);
			if ($dt_field == "drugTargetGoClassification") {
				$drugtarget_fields["drugTargetGoClassificationFunction"] = 1;
				$drugtarget_fields["drugTargetGoClassificationProcess"] = 1;
				$drugtarget_fields["drugTargetGoClassificationComponent"] = 1;
 			} else {
				if ($drugtarget_fields[$dt_field] === null) {
					$drugtarget_fields[$dt_field] = 1;
				} else {
					$drugtarget_fields[$dt_field] = $drugtarget_fields[$dt_field] +1;
				}
 			}
		} else if (preg_match("/phase1MetabolizingEnzyme([0-9]*)/", $field, $match)) {
			$e_field = preg_replace("/phase1MetabolizingEnzyme([0-9]*)/", "phase1MetabolizingEnzyme", $field);
			if ($enzyme_fields[$e_field] === null) {
				$enzyme_fields[$e_field] = 1;
			} else {
				$enzyme_fields[$e_field] = $enzyme_fields[$e_field] +1;
			}
		} else {
			if ($fields[$field] === null) {
				$fields[$field] = 1;
			} else {
				$fields[$field] = $fields[$field] +1;
			}
		}
	} else if ((trim($line) !== "None") && (trim($line) !== "Not Available") && (trim($line) !== "") && (strpos($line, "#END_DRUGCARD") === false)) {
		if (preg_match("/drugTarget([0-9]*)GoClassification/", $field, $match)) { 
			$exploded = explode(":",$line,2);
			$go_mights = array("Function", "Process", "Component");
			if (in_array($exploded[0], $go_mights)) {
				if (trim($exploded[1]) != "Not Available") {
					if ($data[$drugcard_nr][$field.$exploded[0]]  === null) {
						$data[$drugcard_nr][$field.$exploded[0]] = trim($exploded[1]);
					} else {
						$data[$drugcard_nr][$field.$exploded[0]] .= "\n" . trim($exploded[1]);
					}
				}
			}
		} else {
			if ($data[$drugcard_nr][$field]  === null) {
				$data[$drugcard_nr][$field] = trim($line);
			} else {
				$data[$drugcard_nr][$field] .= "\n" . trim($line);
			}
		}
	}
}

echo "ANALYZATION DONE\n";


mysql_query ("drop table ".$database_name.";");
mysql_query ("drop table ".$database_name_targets.";");
mysql_query ("drop table ".$database_name_enzymes.";");
mysql_query ("drop table ".$database_name_targets_links.";");
mysql_query ("drop table ".$database_name_enzymes_links.";");
mysql_query ("drop table ".$database_name_drug_interactions.";");

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_name_targets_links." ( 
	drug varchar(15) NOT NULL,
	target int(10) NOT NULL,
 	PRIMARY KEY  (drug, target)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_name_enzymes_links." ( 
	drug varchar(15) NOT NULL,
	enzyme int(10) NOT NULL,
 	PRIMARY KEY  (drug, enzyme)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_name." (
	id varchar(15) NOT NULL, drugbankLink varchar(100) NOT NULL, ";
foreach ($fields as $field => $times) {
	if (!in_array($field, $seperate_dbs)) {
		if ($field == "genericName") {
			$sql_query .= $field." varchar(300) NULL,";
		} else {
			$sql_query .= $field." blob NULL,";
		}
	}
}
//$sql_query = substr($sql_query, 0, -1);
$sql_query .= "  PRIMARY KEY  (id)
 ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_name_targets." (
	id int(10) NOT NULL, ";
foreach ($drugtarget_fields as $field => $times) {
	$field = lcfirst(str_replace("drugTarget", "", $field));
	if ($field != "id") {
		$sql_query .= $field." blob NULL,";
	}
}
$sql_query .= "  PRIMARY KEY  (id)
 ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_name_enzymes." (
	id int(10) NOT NULL, ";
foreach ($enzyme_fields as $field => $times) {
	$field = lcfirst(str_replace("phase1MetabolizingEnzyme", "", $field));
	if ($field != "id") {
		$sql_query .= $field." blob NULL,";
	}
}
$sql_query .= "	PRIMARY KEY  (id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_name_drug_interactions." (
	drug1 varchar(15) NOT NULL,
	drug2 varchar(15) NOT NULL,
	text blob NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

foreach ($seperate_dbs as $seperate_db) {
	mysql_query ("drop table ".$seperate_db.";");
	$sql_query = "CREATE TABLE IF NOT EXISTS ".$seperate_db." (
		drug varchar(15) NOT NULL,
		field varchar(200) NOT NULL
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
}

foreach ($targets_seperate_dbs as $seperate_db) {
	mysql_query ("drop table ".$seperate_db.";");
	$sql_query = "CREATE TABLE IF NOT EXISTS ".$seperate_db." (
		target int(10) NOT NULL,
		field varchar(200) NOT NULL
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
}

echo "DB CREATION DONE\n";

foreach ($data as $drugcard_id => $drugcard) {
	$dt_id = null;
	$old_dt_id = null;
	$e_id = null;
	$sql_query1 = "INSERT INTO ".$database_name." (id, drugbankLink, ";
	$sql_query2 = "VALUES ('".$drugcard_id."', 'http://www.drugbank.ca/drugs/".$drugcard_id."', ";
	$dt_sql_query1 = "INSERT INTO ".$database_name_targets." (";
	$dt_sql_query2 = "VALUES (";
	$e_sql_query1 = "INSERT INTO ".$database_name_enzymes." (";
	$e_sql_query2 = "VALUES (";
	foreach ($drugcard as $id => $content) {
		if (strpos($id, "drugTarget") === 0) {
			preg_match("/drugTarget([0-9]*)/", $id, $match);
			$old_dt_id = $dt_id;
			$dt_id = $drugcard["drugTarget".$match[1]."Id"];
			if (($dt_id != $old_dt_id) && ($dt_id != null)) {
				if ($old_dt_id != null) {
					$dt_sql_query1 = substr($dt_sql_query1, 0, -2).") ";
					$dt_sql_query2 = substr($dt_sql_query2, 0, -2).") ";
					if (!mysql_query ($dt_sql_query1.$dt_sql_query2)) {
						if (strpos(mysql_error(), "Duplicate entry") === false) {
							die("[DIE] drug target ".$dt_id." at drugcard ".$drugcard_id." : ". mysql_error() . " - query: ".$dt_sql_query1.$dt_sql_query2);
						}
					}	
					$dt_sql_query1 = "INSERT INTO ".$database_name_targets." (";
					$dt_sql_query2 = "VALUES (";
				}

				$link_sql_query = "INSERT INTO ".$database_name_targets_links." (drug, target) VALUES ('".$drugcard_id."', ".$dt_id.");";
				if (!mysql_query ($link_sql_query)) {
					if (strpos(mysql_error(), "Duplicate entry") === false) {
						die("[DIE] linkage, drug target ".$dt_id." at drugcard ".$drugcard_id." : ". mysql_error() . " - query: ".$link_sql_query);
					}
				}	
			}
			$new_id = preg_replace("/drugTarget[0-9]*/", "", $id);
			$new_long_id = preg_replace("/drugTarget[0-9]*/", "drugTarget", $id);
			if (!in_array($new_long_id, $targets_seperate_dbs)) {
				$dt_sql_query1 .=  lcfirst($new_id). ", ";
				if ($new_id == "HgncId") {
					$content = str_replace("HGNC:", "", $content);
				}
				$dt_sql_query2 .= "'".str_replace("'", "\'", $content)."', ";
			}
		} else if (strpos($id, "phase1MetabolizingEnzyme") === 0) {
			$old_e_id = $e_id;
			preg_match("/phase1MetabolizingEnzyme([0-9]*)/", $id, $match);
			$e_id = $match[1];
			if (($e_id != $old_e_id) && ($old_e_id != null)) {
				$e_sql_query1 = substr($e_sql_query1, 0, -2).") ";
				$e_sql_query2 = substr($e_sql_query2, 0, -2).") ";
				if (!mysql_query ($e_sql_query1.$e_sql_query2)) {
					if (strpos(mysql_error(), "Duplicate entry") === false) {
						die("[DIE] enzyme : ". mysql_error() . " - query: ".$e_sql_query1.$e_sql_query2);
					}
				}				
				
				$link_sql_query = "INSERT INTO ".$database_name_enzymes_links." (drug, enzyme) VALUES ('".$drugcard_id."', ".$e_id.");";
				if (!mysql_query ($link_sql_query)) {
					if (strpos(mysql_error(), "Duplicate entry") === false) {
						die("[DIE] linkage, enzyme ".$e_id." at drugcard ".$drugcard_id." : ". mysql_error() . " - query: ".$link_sql_query);
					}
				}	

				$e_sql_query1 = "INSERT INTO ".$database_name_enzymes." (";
				$e_sql_query2 = "VALUES (";
			}
			$new_id = preg_replace("/phase1MetabolizingEnzyme[0-9]*/", "", $id);
			$e_sql_query1 .=  lcfirst($new_id) . ", ";
			$e_sql_query2 .= "'".str_replace("'", "\'", $content)."', ";
		} else {
			if ((!in_array($id, $seperate_dbs)) && (!in_array($id, $special_seperate_dbs))) {
				$sql_query1 .= $id . ", ";
				if (in_array($id, $drugbankLink_fields)) {
					// /drugs/1005/inserts/2305/full
					// http://129.128.185.122/drugbank2/drugs/DB01273/inserts/3820/full
					$content = preg_replace("/\/drugs\/[0-9]*(\/inserts\/[0-9]*\/full)/", "http://129.128.185.122/drugbank2/drugs/".$drugcard_id."$1", $content);
				}
				$sql_query2 .= "'".str_replace("'", "\'", $content)."', ";
			}
		}
	}
	$sql_query1 = substr($sql_query1, 0, -2).") ";
	$sql_query2 = substr($sql_query2, 0, -2).") ";
	if (!mysql_query ($sql_query1.$sql_query2)) {
		die(mysql_error() . " - query: ".$sql_query1.$sql_query2);
	}

	if ($dt_id != null) {
		$dt_sql_query1 = substr($dt_sql_query1, 0, -2).") ";
		$dt_sql_query2 = substr($dt_sql_query2, 0, -2).") ";
		if (!mysql_query ($dt_sql_query1.$dt_sql_query2)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] drug target ".$dt_id." at drugcard ".$drugcard_id." : ". mysql_error() . " - query: ".$dt_sql_query1.$dt_sql_query2);
			}
		}
		$link_sql_query = "INSERT INTO ".$database_name_targets_links." (drug, target) VALUES ('".$drugcard_id."', ".$dt_id.");";
		if (!mysql_query ($link_sql_query)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] linkage, drug target ".$dt_id." at drugcard ".$drugcard_id." : ". mysql_error() . " - query: ".$link_sql_query);
			}
		}	
	}
	
	if ($e_id != null) {
		$e_sql_query1 = substr($e_sql_query1, 0, -2).") ";
		$e_sql_query2 = substr($e_sql_query2, 0, -2).") ";
		if (!mysql_query ($e_sql_query1.$e_sql_query2)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] enzyme : ". mysql_error() . " - query: ".$e_sql_query1.$e_sql_query2);
			}
		}
		$link_sql_query = "INSERT INTO ".$database_name_enzymes_links." (drug, enzyme) VALUES ('".$drugcard_id."', ".$e_id.");";
		if (!mysql_query ($link_sql_query)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] linkage, enzyme ".$e_id." at drugcard ".$drugcard_id." : ". mysql_error() . " - query: ".$link_sql_query);
			}
		}	
	}
}

echo "DRUGS, TARGETS & ENZYMES DONE\n";

// drug interactions
foreach ($data as $drugcard_id => $drugcard) {
	$sql_query1 = "INSERT INTO ".$database_name_drug_interactions." (drug1, ";
	$sql_query2 = "VALUES ('".$drugcard_id."', ";
	if ($drugcard["drugInteractions"] != null) {
		$content = $drugcard["drugInteractions"];
		$drugInteractions = explode("\n", $content);
		foreach ($drugInteractions as $drugInteractionLine) {
			$drug2_id = null;
			$drug2_and_text = explode("\t", $drugInteractionLine);
			$drug2_name = $drug2_and_text[0];
			$drug2_text = str_replace("'", "\'", $drug2_and_text[1]);
			$result = mysql_query('SELECT id FROM '.$database_name.' where genericName="'.$drug2_name.'"');
			while ($row = mysql_fetch_row($result)) {
				$drug2_id = $row[0];
			}
			if ($drug2_id != null) {
				$sql_query1 .= "drug2, text) ";
				$sql_query2 .= "'".$drug2_id."', '".$drug2_text."') ";
				if (!mysql_query ($sql_query1.$sql_query2)) {
					die(mysql_error() . " - query: ".$sql_query1.$sql_query2);
				}
				$sql_query1 = "INSERT INTO ".$database_name_drug_interactions." (drug1, ";
				$sql_query2 = "VALUES ('".$drugcard_id."', ";
			}
		}
	}
}

echo "DRUG INTERACTIONS DONE\n";


// seperate dbs
foreach ($data as $drugcard_id => $drugcard) {
	foreach ($seperate_dbs as $seperate_db) {
		$sql_query1 = "INSERT INTO ".$seperate_db." (drug, ";
		$sql_query2 = "VALUES ('".$drugcard_id."', ";
		if ($drugcard[$seperate_db] != null) {
			$content = $drugcard[$seperate_db];
			$fields = explode("\n", $content);
			foreach ($fields as $field) {
				if ($seperate_db == "dosageForms") {
					$field = camelCase(str_replace("  ", " ",  str_replace(",", " ", str_replace("\t", " ", $field))));
				} else if ($seperate_db == "drugReference") {
					$exploded = explode("\t", " ", $field);
					if (sizeof($exploded  == 2)) {
						$field = $exploded[1];
					}
				} else if (!in_array($seperate_db, $string_fields)) {
					$field = camelCase($field);
				}
				$sql_query1 .= "field) ";
				$sql_query2 .= "'".str_replace("'", "\'", $field)."') ";
				if (!mysql_query ($sql_query1.$sql_query2)) {
					die(mysql_error() . " - query: ".$sql_query1.$sql_query2);
				}
				$sql_query1 = "INSERT INTO ".$seperate_db." (drug, ";
				$sql_query2 = "VALUES ('".$drugcard_id."', ";
			}
		}
	}
	foreach ($targets_seperate_dbs as $seperate_db) {
		$seperate_db_part2 = str_replace("drugTarget", "", $seperate_db);
		foreach ($drugcard as $id => $content) {
			preg_match("/drugTarget([0-9]*)/", $id, $match);
			$dt_id = $match[1];
			if ($id == "drugTarget".$dt_id.$seperate_db_part2) {
				$fields = explode("\n", $content);
				foreach ($fields as $field) {
					if ($seperate_db == "drugTargetDrugReference") {
						$exploded = explode("\t", " ", $field);
						if (sizeof($exploded  == 2)) {
							$field = $exploded[1];
						}
					} else if ($seperate_db == "drugTargetPfamDomainFunction") {
						if (preg_match("/(PF[0-9]*)/", $field, $match)) {
							$field = $match[1];
						}
					} else if (!in_array($seperate_db, $targets_string_fields)) {
						$field = camelCase($field);
					}
					$sql_query1 = "INSERT INTO ".$seperate_db." (target, ";
					$sql_query2 = "VALUES ('".$drugcard["drugTarget".$dt_id."Id"]."', ";
					$sql_query1 .= "field) ";
					$sql_query2 .= "'".str_replace("'", "\'", $field)."') ";
					if (!mysql_query ($sql_query1.$sql_query2)) {
						die(mysql_error() . " - query: ".$sql_query1.$sql_query2);
					}
				}
			}
		}

	}
}

fclose($file_handle);

// REPAIR REFERENCES

$reference_tables = array(/*"drugTargetDrugReferences",*/ "drugTargetGeneralReferences");

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