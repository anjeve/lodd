<?php

/**
* Create MySQL Dump from SIDER files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

$database_sider = "lodd_sider";

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_sider);

$file_label_mapping = "label_mapping.tsv/label_mapping.tsv";
$table_label_mapping = "label_mapping";
//TEST
//$file_label_mapping = "label_mapping.tsv/label_mapping_test.tsv";

$file_euphoria_adverse_effects = "euphoria_adverse_effects.tsv/euphoria_adverse_effects.tsv";
$table_euphoria_adverse_effects = "euphoria_adverse_effects";

$file_euphoria_adverse_effects_raw = "euphoria_adverse_effects_raw.tsv/euphoria_adverse_effects_raw.tsv";
$table_euphoria_adverse_effects_raw = "euphoria_adverse_effects_raw";

$file_euphoria_indications_raw = "euphoria_indications.txt";
$table_euphoria_indications_raw = "euphoria_indications_raw";

$file_costart_adverse_effects = "costart_adverse_effects.tsv/costart_adverse_effects.tsv";
$table_costart_adverse_effects = "costart_adverse_effects";

$file_costart_adverse_effects_raw = "costart_adverse_effects_raw.tsv/costart_adverse_effects_raw.tsv";
$table_costart_adverse_effects_raw = "costart_adverse_effects_raw";

$file_costart_indications_raw = "costart_indications_raw.tsv/costart_indications_raw.tsv";
$table_costart_indications_raw = "costart_indications_raw";

$table_sideeeffects = "side_effects";
$table_sideeeffects_alternate = "side_effects_alternate";
$side_effects = array();
$side_effects_alternate = array();

$table_drugs = "drugs";

$table_alternate_names = "drugs_alternate_names";

function countUpperCaseLetters($string) {
	preg_match_all("/[A-Z]/", $string, $your_match) ;
	return count($your_match[0]);
}

function getStitchId($sider_id) {
	if (strlen($sider_id)) {
		$id_length = strlen($sider_id);
		//CID000376131  12 - 3  
		$nullen = "";
		for ($i = 0; $i <= (9-$id_length); $i = $i+1) {
			$nullen .= "0";
		}
		return "CID".$nullen.$sider_id;
	}

}
//STITCH

/*
$file_stitch_chemical_aliases = "STITCH/chemical.aliases.v1.0.tsv";
$table_stitch_chemical_aliases = "stitch_chemical_aliases";

$file_handle = fopen($file_stitch_chemical_aliases, "r");
if (!$file_handle) {
	die ("File not found ".$file_stitch_chemical_aliases);
}

$sql_query = "TRUNCATE TABLE ".$table_stitch_chemical_aliases;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	if ($line_parts[0] != "chemical") {
		$data["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[0]));
		//trim(str_replace("\\'", "\'", str_replace("'", "\'",	, "\"")
		$data["alias"] = str_replace("\"", "", trim($line_parts[1]));
	
		$sql_query = "INSERT INTO ".$table_stitch_chemical_aliases." (sideeffect_id, alias) 
			VALUES ('".$data["sideeffect_id"]."', \"".$data["alias"]."\");";
		if (!mysql_query ($sql_query)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] ". mysql_error() . " - query: ".$sql_query);
			}
		}
	}
}

fclose($file_handle);

*/

$labels_for_sider_drug_id = array();

// LABEL MAPPING
$data = array();
$file_handle = fopen($file_label_mapping, "r");
if (!$file_handle) {
	die ("File not found ".$file_label_mapping);
}

while (!feof($file_handle)) {
	$line = fgets($file_handle);
	$line_parts = explode("\t", $line);
	$data[]["name"] = str_replace("'", "\'", trim($line_parts[0]));
	$data[sizeof($data)-1]["ingredients"] = str_replace("'", "\'", trim($line_parts[1]));
	
	//TODO: ok so?
	
	if ((strlen($data[sizeof($data)-1]["name"]) == 0) && (strlen($data[sizeof($data)-1]["ingredients"]) > 0)) {
		$data[sizeof($data)-1]["name"] = $data[sizeof($data)-1]["ingredients"];
		$data[sizeof($data)-1]["ingredients"] = "";
	}
	$data[sizeof($data)-1]["undefined"] = trim($line_parts[2]);
	$data[sizeof($data)-1]["sider_drug_id"] = trim($line_parts[3]);
	
	$data[sizeof($data)-1]["stitch_id"] = getStitchId($data[sizeof($data)-1]["sider_drug_id"]);
	
	$data[sizeof($data)-1]["original_file"] = trim($line_parts[4]);
	$data[sizeof($data)-1]["label"] = trim($line_parts[5]);
	
	if (strlen($data[sizeof($data)-1]["sider_drug_id"]) > 0) {
		$labels_for_sider_drug_id[$data[sizeof($data)-1]["label"]] = $data[sizeof($data)-1]["sider_drug_id"];
	}
}

$sql_query = "TRUNCATE TABLE ".$table_label_mapping;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_label_mapping." (name, ingredients, undefined, sider_drug_id, stitch_id, original_file, label) 
		VALUES ('".$db_entry["name"]."', '".$db_entry["ingredients"]."', '".$db_entry["undefined"]."', '".$db_entry["sider_drug_id"]."', '".$db_entry["stitch_id"]."', '".$db_entry["original_file"]."', '".$db_entry["label"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);

// EUPHORIA ADVERSE

$data = array();
$file_handle = fopen($file_euphoria_adverse_effects, "r");
if (!$file_handle) {
	die ("File not found ".$file_euphoria_adverse_effects);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$data[]["sider_drug_id"] = str_replace("-", "", str_replace("'", "\'", trim($line_parts[0])));

	$data[sizeof($data)-1]["stitch_id"] = getStitchId($data[sizeof($data)-1]["sider_drug_id"]);

	$data[sizeof($data)-1]["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[1]));
	$data[sizeof($data)-1]["drug_name"] = str_replace("'", "\'", trim($line_parts[2]));
	$data[sizeof($data)-1]["side_effect"] = trim($line_parts[3]);
	
	$sideeffect_existing = $side_effects[$data[sizeof($data)-1]["sideeffect_id"]];
	$sideeffect_new = $data[sizeof($data)-1]["side_effect"];
	if ($sideeffect_existing) {
		if (strtolower($sideeffect_existing) != strtolower($sideeffect_new)) {
			$side_effects_alternate[$data[sizeof($data)-1]["sideeffect_id"]][] = $sideeffect_new;
		} else if (countUpperCaseLetters($sideeffect_new) > countUpperCaseLetters($sideeffect_existing)) {
			$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
		}
	} else {
		$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
	}
}

$sql_query = "TRUNCATE TABLE ".$table_euphoria_adverse_effects;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_euphoria_adverse_effects." (sider_drug_id, stitch_id, sideeffect_id, drug_name, side_effect) 
		VALUES ('".$db_entry["sider_drug_id"]."', '".$db_entry["stitch_id"]."', '".$db_entry["sideeffect_id"]."', '".$db_entry["drug_name"]."', '".$db_entry["side_effect"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);


// COSTART ADVERSE

$data = array();
$file_handle = fopen($file_costart_adverse_effects, "r");
if (!$file_handle) {
	die ("File not found ".$file_costart_adverse_effects);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$data[]["sider_drug_id"] = str_replace("-", "", str_replace("'", "\'", trim($line_parts[0])));

	$data[sizeof($data)-1]["stitch_id"] = getStitchId($data[sizeof($data)-1]["sider_drug_id"]);

	$data[sizeof($data)-1]["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[1]));
	$data[sizeof($data)-1]["drug_name"] = str_replace("'", "\'", trim($line_parts[2]));
	$data[sizeof($data)-1]["side_effect"] = trim($line_parts[3]);
	
	$sideeffect_existing = $side_effects[$data[sizeof($data)-1]["sideeffect_id"]];
	$sideeffect_new = $data[sizeof($data)-1]["side_effect"];
	if ($sideeffect_existing) {
		if (strtolower($sideeffect_existing) != strtolower($sideeffect_new)) {
			$side_effects_alternate[$data[sizeof($data)-1]["sideeffect_id"]][] = $sideeffect_new;
		} else if (countUpperCaseLetters($sideeffect_new) > countUpperCaseLetters($sideeffect_existing)) {
			$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
		}
	} else {
		$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
	}
}

$sql_query = "TRUNCATE TABLE ".$table_costart_adverse_effects;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_costart_adverse_effects." (sider_drug_id, stitch_id, sideeffect_id, drug_name, side_effect) 
		VALUES ('".$db_entry["sider_drug_id"]."', '".$db_entry["stitch_id"]."', '".$db_entry["sideeffect_id"]."', '".$db_entry["drug_name"]."', '".$db_entry["side_effect"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);

// EUPHORIA ADVERSE RAW

$data = array();
$file_handle = fopen($file_euphoria_adverse_effects, "r");
if (!$file_handle) {
	die ("File not found ".$file_euphoria_adverse_effects_raw);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$data[]["label"] = trim($line_parts[0]);
	$data[sizeof($data)-1]["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[1]));
	$data[sizeof($data)-1]["drug_name"] = str_replace("'", "\'",trim($line_parts[2]));
}

$sql_query = "TRUNCATE TABLE ".$table_euphoria_adverse_effects_raw;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_euphoria_adverse_effects_raw." (label, sideeffect_id, drug_name) 
		VALUES ('".$db_entry["label"]."', '".$db_entry["sideeffect_id"]."', '".$db_entry["drug_name"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);

// COSTART ADVERSE RAW

$data = array();
$file_handle = fopen($file_costart_adverse_effects, "r");
if (!$file_handle) {
	die ("File not found ".$file_costart_adverse_effects_raw);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$data[]["label"] = trim($line_parts[0]);
	$data[sizeof($data)-1]["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[1]));
	$data[sizeof($data)-1]["drug_name"] = str_replace("'", "\'",trim($line_parts[2]));
}

$sql_query = "TRUNCATE TABLE ".$table_costart_adverse_effects_raw;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_costart_adverse_effects_raw." (label, sideeffect_id, drug_name) 
		VALUES ('".$db_entry["label"]."', '".$db_entry["sideeffect_id"]."', '".$db_entry["drug_name"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);

// EUPHORIA INDICATIONS RAW

$data = array();
$file_handle = fopen($file_euphoria_indications_raw, "r");
if (!$file_handle) {
	die ("File not found ".$file_euphoria_indications_raw);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$data[]["label"] = trim($line_parts[0]);
	$data[sizeof($data)-1]["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[1]));
	$data[sizeof($data)-1]["side_effect"] = trim($line_parts[2]);

	$sideeffect_existing = $side_effects[$data[sizeof($data)-1]["sideeffect_id"]];
	$sideeffect_new = $data[sizeof($data)-1]["side_effect"];
	if ($sideeffect_existing) {
		if (strtolower($sideeffect_existing) != strtolower($sideeffect_new)) {
			$side_effects_alternate[$data[sizeof($data)-1]["sideeffect_id"]][] = $sideeffect_new;
		} else if (countUpperCaseLetters($sideeffect_new) > countUpperCaseLetters($sideeffect_existing)) {
			$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
		}
	} else {
		$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
	}
}

$sql_query = "TRUNCATE TABLE ".$table_euphoria_indications_raw;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_euphoria_indications_raw." (sider_drug_id, label, sideeffect_id, side_effect) 
		VALUES ('".$labels_for_sider_drug_id[$db_entry["label"]]."', '".$db_entry["label"]."', '".$db_entry["sideeffect_id"]."', '".$db_entry["side_effect"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);

// COSTART INDICATIONS RAW

$data = array();
$file_handle = fopen($file_costart_indications_raw, "r");
if (!$file_handle) {
	die ("File not found ".$file_costart_indications_raw);
}

while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$data[]["label"] = trim($line_parts[0]);
	$data[sizeof($data)-1]["sideeffect_id"] = str_replace("'", "\'", trim($line_parts[1]));
	$data[sizeof($data)-1]["side_effect"] = trim($line_parts[2]);

	$sideeffect_existing = $side_effects[$data[sizeof($data)-1]["sideeffect_id"]];
	$sideeffect_new = $data[sizeof($data)-1]["side_effect"];
	if ($sideeffect_existing) {
		if (strtolower($sideeffect_existing) != strtolower($sideeffect_new)) {
			$side_effects_alternate[$data[sizeof($data)-1]["sideeffect_id"]][] = $sideeffect_new;
		} else if (countUpperCaseLetters($sideeffect_new) > countUpperCaseLetters($sideeffect_existing)) {
			$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
		}
	} else {
		$side_effects[$data[sizeof($data)-1]["sideeffect_id"]] = $sideeffect_new;
	}
}

$sql_query = "TRUNCATE TABLE ".$table_costart_indications_raw;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($data as $db_entry) {
	$sql_query = "INSERT INTO ".$table_costart_indications_raw." (sider_drug_id, label, sideeffect_id, side_effect) 
		VALUES ('".$labels_for_sider_drug_id[$db_entry["label"]]."', '".$db_entry["label"]."', '".$db_entry["sideeffect_id"]."', '".$db_entry["side_effect"]."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

fclose($file_handle);


// SIDE EFFECTS TABLE

$sql_query = "TRUNCATE TABLE ".$table_sideeeffects;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($side_effects as $id => $name) {
	$sql_query = "INSERT INTO ".$table_sideeeffects." (sideeffect_id, name) 
		VALUES ('".$id."', '".$name."');";
	if (!mysql_query ($sql_query)) {
		die("[DIE] ". mysql_error() . " - query: ".$sql_query);
	}
}

$sql_query = "TRUNCATE TABLE ".$table_sideeeffects_alternate;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

foreach ($side_effects_alternate as $id => $alternate) {
	foreach ($alternate as $id1 => $name) {
		$sql_query = "INSERT INTO ".$table_sideeeffects_alternate." (sideeffect_id, name) 
			VALUES ('".$id."', '".$name."');";
		if (!mysql_query ($sql_query)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] ". mysql_error() . " - query: ".$sql_query);
			}
		}
	}
}

// SIDER DRUGS

$sql_query = "TRUNCATE TABLE ".$table_drugs;
if (!mysql_query ($sql_query)) {
	die("[DIE] ". mysql_error() . " - query: ".$sql_query);
}

mysql_query ("DROP table ".$table_alternate_names.";");
$sql_query = "CREATE TABLE IF NOT EXISTS $table_alternate_names (
  `sider_drug_id` varchar(15) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY  (`sider_drug_id`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}


$mysqlquery = 'SELECT sider_drug_id, name FROM '.$table_label_mapping;
$result = mysql_query($mysqlquery);
$saved_drugs = array();
while ($row = mysql_fetch_row($result)) {
	$sider_drug_id = $row[0];
	$name = str_replace("'", "\'", $row[1]);
	if ((strlen($sider_drug_id) > 0) && (strlen($name) > 0)) {
		$sql_query = "INSERT INTO ".$table_drugs." (sider_drug_id, name) 
			VALUES ('".$sider_drug_id."', '".$name."');";
		if (!mysql_query ($sql_query)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] ". mysql_error() . " - query: ".$sql_query);
			} else {
				if ($name != $saved_drugs[$sider_drug_id]) {
					$sql_query = "INSERT INTO ".$table_alternate_names." (sider_drug_id, name) 
						VALUES ('".$sider_drug_id."', '".$name."');";
					if (!mysql_query ($sql_query)) {
						if (strpos(mysql_error(), "Duplicate entry") === false) {
							die("[DIE] ". mysql_error() . " - query: ".$sql_query);
						}
					}
				}
			}
		} else {
			$saved_drugs[$sider_drug_id] = $name;
		}
	}
}




?>