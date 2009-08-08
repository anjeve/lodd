<?php

/**
* Create MySQL Dump from Dailymed XML files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

// get start and end index
if(is_numeric($argv[1])) {
	$start = (int) $argv[1];
}
if(is_numeric($argv[2])) {
	$end = (int) $argv[2];
}

function repairXml() {
	$path = "dataset/";
	$path_repaired = "dataset/repaired/";
	if ($dir=opendir($path)) {
		if (!opendir($path_repaired)) {
			mkdir($path_repaired);
		}
		while($file=readdir($dir)) {
			if (!is_dir($file) && (strpos($file,".xml") !== false)) {
				$file_handle = fopen($path.$file, "r");
				$file_handle1 = fopen($path_repaired.$file, "w");
				if (!$file_handle) {
					die ("File not found ".$file);
				}
				$lines = "";
				$line_nr = 0;
				while (!feof($file_handle)) {
					$line_nr = $line_nr + 1;
					$line = trim(fgets($file_handle));
					
					$line = str_replace("&#160;", " ", $line);
					$line = str_replace("&#174;", "'", $line);
					$line = str_replace("&#8217;", "'", $line);
					$line = str_replace("<sup>'</sup>", "", $line);
					
					if (preg_match_all("/\(.*?\)/", $line, $match)) {
						foreach ($match[0] as $match_id => $matched) {
							$str1 = strpos($matched, "(", 1);
							$str2 = strpos($matched, ")");
							if (substr_count($matched, "(") - substr_count($matched, ")") != 0 || ($str1 > $str2)) {
								unset($match[0][$match_id]);
							}
						}					
						foreach ($match[0] as $matched) {
							if (strpos($matched, "<linkHtml") !== false) {
								$countWords = count(explode(" ", $matched)); 
								if ($countWords >= 2) {
									$matched_temp = $matched;
									$matched_temp = preg_replace("/<content[^>]*>/", "", $matched_temp);
									$matched_temp = preg_replace("/<\/content>/", "", $matched_temp);
									$matched_temp = preg_replace("/<linkHtml[^>]*>/", "", $matched_temp);
									$matched_temp = preg_replace("/<\/linkHtml>/", "", $matched_temp);
	
									preg_match_all("/[A-Z]/", $matched_temp, $your_match);
									$total_upper_case_count = count($your_match[0]);
									if ($total_upper_case_count > ($countWords+1)) {
										$line = str_replace($matched, "", $line);
									}
								}
							}
						}
					}				
					
				//2. content-tags in paragraph tags entfernen
					$line = preg_replace("/<content[^>]*>/", "", $line);
					$line = preg_replace("/<\/content>/", "", $line);
					$line = preg_replace("/<linkHtml[^>]*>/", "", $line);
					$line = preg_replace("/<\/linkHtml>/", "", $line);

					$lines .= $line."\n";
				}
				if ($lines != "") {
					if (!fwrite($file_handle1,$lines)) {
						die ($file);
					}
				} else {
					echo " error: $file\n";
				}
			}
		}
		closedir($dir);
	} else {
		die ("Couldn't find DailyMed dump path at: $path");
	}
}

if (!$start || ($start <= 1)) {
	repairXml();
}

$database_table_drugs = "drugs";
$seperate_tables = array(
	"side_effects", "contraindications", "adverse_reactions", "overdosage", "inactiveIngredient", "dosage_and_administration", "indications_and_usage", "warnings", "precautions", "how_supplied", "description", "clinical_pharmacology", "supplemental_patient_material", "boxed_warning");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_dailymed);

$database_drugbank_table_drugs = "drugs";
$database_drugbank_table_brandnames = "brandnames";
$database_drugbank_table_synonyms = "synonyms";

$path = "dataset/repaired";
if ($dir = opendir($path)) {
	while($file=readdir($dir)) {
		if (!is_dir($file) && (strpos($file,".xml") !== false)) {
			$files[] = $file;
		}
	}
	if (sizeof($files) == 0) {
		die ("No DailyMed files found.\nPlease download DailyMed dumps from ftp://public.nlm.nih.gov/nlmdata/.dailymed/dm_spl_release.zip and extract the zip file at: $path");
	}
	closedir($dir);
} else {
	die ("Couldn't find DailyMed dump path at: $path");
}

if ($start == 0) {
	mysql_query ("drop table ".$database_table_drugs.";");
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_table_drugs." ( 
	id int(10) NOT NULL,
	name varchar(400) NOT NULL,
	fullName varchar(500) NULL,
	activeIngridient varchar(500) NULL,
	activeMoiety varchar(500) NULL,
	routeOfAdministration  varchar(500) NULL,
	drugbank_id varchar(15) NULL,
	genericMedicine varchar(500) NULL,
	representedOrganization varchar(500) NULL,
 	PRIMARY KEY  (id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

foreach ($seperate_tables as $name => $seperate_db) {
	if (!is_array($seperate_db)) {
		$name = $seperate_db;
	}
	if ($start == 0) {
		mysql_query ("drop table ".$name.";");
	}
	$sql_query = "CREATE TABLE IF NOT EXISTS ".$name." (
		drug int(10) NOT NULL,";
	if (!is_array($seperate_db)) {
		$sql_query .= "field blob NOT NULL";
	} else {
		foreach ($seperate_db as $row_name) {
			$sql_query .= $row_name." blob NOT NULL,";
		}
		$sql_query = substr($sql_query, 0, -1);
	}
	$sql_query .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
}

$drug_id = 0;
$identified_drugs = 0;
$identified_drugs_generic = 0;

$found_inactive_ingredients = 0;
$found_indications = 0;

$found_text = array();

foreach ($files as $file) {
	unset($xmlDrugFile);
	$drug_id = $drug_id+1;
//	if (($drug_id == 11) || ($drug_id == 6)) {
	if (($drug_id >= $start) && ($drug_id < $end)) {
//		echo $file;
		$xmlDrugFile = new XMLParser($path."/".$file);
		
		$sql1 = "";
		$sql2 = "";
		if (array_searchRecursive("GENERICMEDICINE", $xmlDrugFile->data[0]) !== false ) {
			$genericMedicine = null;
			for ($i = 0; $i < sizeof($parent_subtree["child"]); $i = $i+1) {
				if (($parent_subtree["child"][$i]["name"] == "NAME") && (strlen($parent_subtree["child"][$i]["content"]) > 0)) {
					if ($genericMedicine != null) {
						print_r($parent_subtree);
						die("DIED 2x generic @ ".$file);
					}
					$genericMedicine = $parent_subtree["child"][$i]["content"];
					$sql1 .= ", genericMedicine";
					$sql2 .= ", '".$genericMedicine."'";


					mysql_select_db ($database_drugbank);
					
					$drugbank_id_generic = array();
					$mysqlquery = 'SELECT id FROM '.$database_drugbank_table_drugs.' where genericName = "'.$genericMedicine.'"';
					$result = mysql_query($mysqlquery);
					while ($row = mysql_fetch_row($result)) {
						$drugbank_id_generic[] = $row[0];
					}
					if (sizeof($drugbank_id_generic) == 1)  {
						$identified_drugs_generic = $identified_drugs_generic+1;
						$drugbank_id = array_pop($drugbank_id_generic);
						$sql1 .= ", drugbank_id";
						$sql2 .= ", '".$drugbank_id."'";
						//echo "identified generic: ".$identified_drugs_generic."/".$drug_id."\n";
					} else if (sizeof($intersected_drugbankids) > 1) {
						echo "more generic ids found: ".sizeof($drugbank_id_generic)."/".$drug_id."\n"; 
					}
						
					mysql_select_db ($database_dailymed);
						
											
				}
			}
			if ($genericMedicine == null) {
				print_r($parent_subtree);
				die("DIED no generic name ".$file);
			}
		} else {
			die("DIED no generic name ".$file);
		}
		

		if (array_searchRecursive("MANUFACTUREDMEDICINE", $xmlDrugFile->data[0]) !== false ) {
			if ($parent_subtree["child"][1]["name"] == "NAME") {
				$drugName = str_replace("'", "\'", $parent_subtree["child"][1]["content"]);
				if ($parent_subtree["child"][2]["attributes"]["DISPLAYNAME"]) {
					$temp = $parent_subtree["child"][2]["attributes"]["DISPLAYNAME"];
					$temp = ucwords(strtolower($temp));
					$fullDrugName = $drugName . " (" . $temp . ")";
				}
			}
		}

		if (array_searchRecursive("REPRESENTEDORGANIZATION", $xmlDrugFile->data[0]) !== false ) {
			if ($parent_subtree["child"][0]["name"] == "NAME") {
				$representedOrganization = str_replace("'", "\'", $parent_subtree["child"][0]["content"]);
				$sql1 .= ", representedOrganization";
				$sql2 .= ", '".$representedOrganization."'";
			}
		}

		// ACTIVE INGRIDIENTS			
		if (array_searchRecursive("ACTIVEINGREDIENTSUBSTANCE", $xmlDrugFile->data[0]) !== false ) {
			if ($parent_subtree["child"][1]["name"] == "NAME") {
				$activeIngridient = str_replace("'", "\'", $parent_subtree["child"][1]["content"]);
				$sql1 .= ", activeIngridient";
				$sql2 .= ", '".$activeIngridient."'";
				if ($parent_subtree["child"][2]["name"] == "ACTIVEMOIETY") {
					if ($parent_subtree["child"][2]["child"][0]["child"][1]["name"] == "NAME") {
						$activeMOIETY = str_replace("'", "\'", $parent_subtree["child"][2]["child"][0]["child"][1]["content"]);
						$sql1 .= ", activeMoiety";
						$sql2 .= ", '".$activeMOIETY."'";
					}
				}
			}
		}

		if (array_searchRecursive("SUBSTANCEADMINISTRATION", $xmlDrugFile->data[0]) !== false ) {
			// substanceAdministration
			if (strlen($parent_subtree["child"][0]["attributes"]["DISPLAYNAME"]) > 0) {
				$routeOfAdministration = ucwords(strtolower(str_replace("'", "\'", $parent_subtree["child"][0]["attributes"]["DISPLAYNAME"])));
				$sql1 .= ", routeOfAdministration";
				$sql2 .= ", '".$routeOfAdministration."'";
			}
		}

// INSERT DRUG INFO				
		$sql_query = "INSERT INTO ".$database_table_drugs." (id, name, fullName".$sql1.")  VALUES (".$drug_id.", '".$drugName."', '".$fullDrugName."'".$sql2.")";
		if (!mysql_query ($sql_query)) {
			die(mysql_error() . " - query: ".$sql_query);
		}		
		
		foreach ($seperate_tables as $seperate_table => $seperate_table_array) {
			if (!is_array($seperate_table_array)) {
				$seperate_table = $seperate_table_array;
			}
			$fields = array();
			
// INACTIVEINGREDIENT

			if ($seperate_table == "inactiveIngredient") {
				$searchpath = array_searchRecursive("INACTIVEINGREDIENT", $xmlDrugFile->data[0]);
				if ($searchpath !== false) {
					$subarray = $xmlDrugFile->data[0];
					for ($i = 0; $i < (sizeof($searchpath)-2); $i = $i+1) {
						$subarray = $subarray[$searchpath[$i]];
					}
					$i = $searchpath[$i];
					for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
						if ($subarray[$k]["name"] != "INACTIVEINGREDIENT") {
							continue;
						}
						//$subsubarray = $subarray[$k]["child"][0];
						$subsubarray = $subarray[$k]["child"];
						for ($l = 0; $l < sizeof($subsubarray); $l = $l+1) {
							if ($subsubarray[$l]["name"] != "NAME") {
								if ($subsubarray[$l]["name"] == "INACTIVEINGREDIENTSUBSTANCE") {
									for ($m = 0; $m < sizeof($subsubarray[$l]["child"]); $m = $m + 1) {
										if ($subsubarray[$l]["child"][$m]["name"] != "NAME") {
											continue;
										}
										$fields[] = $subsubarray[$l]["child"][$m]["content"];
										
										$found_inactive_ingredients += 1;
									}
								}
								continue;
							}
							$fields[] = $subsubarray[$l]["content"];
							$found_inactive_ingredients += 1;
						}
					}
					if (sizeof($fields) == 0) {
						$errors[$file] .= " INACTIVEINGRIDIENT";
					}
				}
// SIDE EFFECTS
			} else if ($seperate_table == "side_effects") {
				// The most common...
				$searchpath = array_searchRecursive_loose("The most common side effects of", $xmlDrugFile->data[0]);
				if ($searchpath !== false) {
					if ($parent_subtree["child"]) {
						$i = 0;
						while($parent_subtree["child"][0]["child"][$i]["name"] == "ITEM") {
							$side_effect = $parent_subtree["child"][0]["child"][$i]["content"];
							if (($side_effect != null) && ($side_effect != "")) {
								$fields[] = $side_effect;
							}
							$i++;
						}
					} else {
						//Q8. WHAT ARE THE MOST COMMON SIDE EFFECTS OF GLIPIZIDE AND METFORMIN HYDROCHLORIDE TABLETS
						$subarray = $xmlDrugFile->data[0];
						for ($i = 0; $i < (sizeof($searchpath)-2); $i = $i+1) {
							$subarray = $subarray[$searchpath[$i]];
						}
						$i = $searchpath[$i]+1;
						if (preg_match("/Q[0-9]*[\.]? .*/", $subarray[$i-1]["content"])) {
							$contraindication = $subarray[$i]["content"];
							$fields[] = $contraindication;
						} else {
							$errors[$file] .= "SIDE EFFECTS";
/*
							for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
								if ($subarray[$k]["name"] != "TEXT") {
									continue;
								}
								$subsubarray = $subarray[$k]["child"][0];
								if ($subsubarray["name"] == "PARAGRAPH") {
									$contraindication = $subsubarray["content"];
									$fields[] = $contraindication;
								} else {
									echo "$file - CONTRAINDICATIONS ! \n";
								}
							}
							*/
						}
					}
				}
			} else if ($seperate_table =="boxed_warning") {
				// BOXED WARNING SECTION
				getXmlFields("BOXED WARNING SECTION", "-1");
			} else if (($seperate_table == "overdosage") ||
				($seperate_table == "warnings") ||
				($seperate_table == "precautions") ||
				($seperate_table == "description") ||
				($seperate_table == "adverse_reactions") ||
				($seperate_table == "indications_and_usage") ||
				($seperate_table == "contraindications") ||
				($seperate_table == "how_supplied") ||
				($seperate_table == "clinical_pharmacology") ||
				($seperate_table == "supplemental_patient_material") ||
				($seperate_table == "dosage_and_administration")) {
				getXmlFields($seperate_table);
			}


			foreach ($fields as $field) {
				if (!is_array($field)) {
					$field = str_replace("&#8217;", "'", $field);
					$field = str_replace("'", "\'", $field);
					$sql_query = "INSERT INTO ".$seperate_table." (drug, field)  VALUES (".$drug_id.", '".$field."')";
					if (!mysql_query ($sql_query)) {
						echo (mysql_error() . " - query: ".$sql_query);
					}
					if (strlen($field) > 10) {
						$found_text[$seperate_table]["count"] = $found_text[$seperate_table]["count"] + 1;
						$found_text[$seperate_table]["length"] = $found_text[$seperate_table]["length"] + strlen($field);
					}
				} else {
					$sql1 = "";
					$sql2 = "";
					
					$field_size = sizeof($field);
					$seperate_table_array_size = sizeof($seperate_table_array);
/*
					if (sizeof($field) > sizeof($seperate_table_array)) {
						echo "ERROR: " . $drugName . ":\n";
						print_r(array_keys($field));
						die();
					}
*/
					foreach ($field as $fieldname => $fieldcontent) {
						$seperate_table_array_size--;
						$field_size--;
						$fieldcontent = str_replace("&#8217;", "'", $fieldcontent);
						$fieldcontent = str_replace("'", "\'", $fieldcontent);
						//if($fieldcontent == end($field)) {
						if ($seperate_table_array_size == 0) {
							if ($field_size > 0) {
								echo "ERROR: " . $drugName . ":\n";
								print_r(array_keys($field));
								die();
							}
							$sql1 .= $fieldname;
							$sql2 .= "'".$fieldcontent."'";
						} else {
							$sql1 .= $fieldname.", ";
							$sql2 .= "'".$fieldcontent."', ";
						}
					}
					$sql_query = "INSERT INTO ".$seperate_table." (drug, ".$sql1.")  VALUES (".$drug_id.", ".$sql2.")";
					if (!mysql_query ($sql_query)) {
						echo (mysql_error() . " - query: ".$sql_query);
					}		
					if (strlen($sql2) > 10) {
						$found_text[$seperate_table]["count"] = $found_text[$seperate_table]["count"] + 1;
						$found_text[$seperate_table]["length"] = $found_text[$seperate_table]["length"] + strlen($sql2);
						
					}
				}
			}
		}
	}
}

// CREATE ORGANIZATION TABLE
$table_dailymed_organizations = "organizations";

$query = 'SELECT id, representedOrganization FROM '.$database_table_drugs.' where representedOrganization IS NOT NULL';
$result = mysql_query($query);
while ($row = mysql_fetch_row($result)) {
	$query = 'SELECT id, name FROM '.$table_dailymed_organizations.' where name = "'.$row[1].'"';
	$result1 = mysql_query($query);
	if (mysql_num_rows($result1) > 1) {
		die("[DIE] duplicate entry - query: ".$query);
	}
	while ($row1 = mysql_fetch_row($result1)) {
		$query = 'UPDATE '.$database_table_drugs.' SET representedOrganization = "'.$row1[0].'" where id = "'.$row[0].'"';
		$result2 = mysql_query($query);
		if (!$result2) {
			die("[DIE] query: ".$query);
		}
	}
}

// ERROR PRINTING
// print_r($errors);

// STATS PRINTING
print_r($found_text);

?>