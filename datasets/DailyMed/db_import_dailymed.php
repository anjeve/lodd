<?php

/**
* Create MySQL Dump from Dailymed XML files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

$database_dailymed = "lodd_dailymed";
$database_drugbank = "lodd";

$database_table_drugs = "drugs";
$seperate_tables = array(
	"side_effects", "contraindications", "adverse_reactions", "overdosage", "inactiveIngredient", "dosage");
//	"dosage_administration" => array("dosage_label", "dosage"));

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_dailymed);

$database_drugbank_table_drugs = "drugs";
$database_drugbank_table_brandnames = "brandnames";
$database_drugbank_table_synonyms = "synonyms";

$path = "dailymed/1";
if ($dir=opendir($path)) {
	while($file=readdir($dir)) {
		if (!is_dir($file) && (strpos($file,".xml") !== false)) {
			$files[] = $file;
		}
	}
	closedir($dir);
}

//mysql_query ("drop table ".$database_table_drugs.";");

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
//	mysql_query ("drop table ".$name.";");
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

foreach ($files as $file) {
	unset($xmlDrugFile);
	$drug_id = $drug_id+1;
//	if ($file == "F0FF4F27-5185-4881-A749-C6B7A0CA5696.xml") {
	if (($drug_id > 3741)) {
//		echo $file;
		//$xmlDrugFile = new XMLParser("dailymed/F0FF4F27-5185-4881-A749-C6B7A0CA5696.xml");
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
				
/*
		if (array_searchRecursive("MANUFACTUREDMEDICINE", $xmlDrugFile->data[0]) !== false ) {
			if ($parent_subtree["child"][1]["name"] == "NAME") {
				$drugName = str_replace("'", "\'", $parent_subtree["child"][1]["content"]);
				// echo $drug_id . " - ". $drugName. "\n";
				if ($parent_subtree["child"][2]["attributes"]["DISPLAYNAME"]) {
					$temp = $parent_subtree["child"][2]["attributes"]["DISPLAYNAME"];
					$temp = ucwords(strtolower($temp));
					$fullDrugName = $drugName . " (" . $temp . ")";
				}

				$sql_drugbank_id = null;
				$sql_drugbank_id_field = null;
				
				// DRUGBANK ID
				mysql_select_db ($database_drugbank);
				$result = mysql_query('SELECT drug FROM '.$database_drugbank_table_brandnames.' where field="'.$drugName.'"');
				$drugbank_id = array();
				while ($row = mysql_fetch_row($result)) {
					$drugbank_id[] = $row[0];
				}
				
				if (array_searchRecursive("ACTIVEINGREDIENTSUBSTANCE", $xmlDrugFile->data[0]) !== false ) {
					$drugbank_equivalent_found = false;
					if ($parent_subtree["child"][1]["name"] == "NAME") {
						$activeIngridient = str_replace("'", "\'", $parent_subtree["child"][1]["content"]);
					
						$sql_drug2 = null;
						if ($parent_subtree["child"][2]["child"][0]["name"] == "ACTIVEMOIETY") {
							$activeIngridient2 = str_replace("'", "\'", $parent_subtree["child"][2]["child"][0]["child"][1]["content"]);
							if ($activeIngridient2 != null) {
								$sql_drug2 = ' OR field="'.$activeIngridient2.'"';
								$sql_drug2_generic = ' OR genericName="'.$activeIngridient2.'"';
							}
						}

						$result = mysql_query('SELECT drug FROM '.$database_drugbank_table_synonyms.' where field="'.$activeIngridient.'"'.$sql_drug2);
						$drugbank_id1 = array();
						while ($row = mysql_fetch_row($result)) {
							$drugbank_id1[] = $row[0];
						}
						$result = mysql_query('SELECT id FROM '.$database_drugbank_table_drugs.' where genericName="'.$activeIngridient.'"'.$sql_drug2_generic);
						while ($row = mysql_fetch_row($result)) {
							$drugbank_id1[] = $row[0];
						}
						$intersected_drugbankids = array_intersect($drugbank_id, $drugbank_id1);
						if (sizeof($intersected_drugbankids) == 1)  {
							// echo $drug_id . " - ". sizeof($drugbank_id). "  found\n";
							$identified_drugs = $identified_drugs+1;
							echo "identified: ".$identified_drugs."/".$drug_id."\n";
							$drugbank_id_found = array_pop($intersected_drugbankids);
							//echo "\t$drugName - ".$drugbank_id_found."\n"; 
							$sql_drugbank_id_field = ", drugbank_id";
							$sql_drugbank_id = ", '".$drugbank_id_found."'";
						} else if (sizeof($intersected_drugbankids) > 1) {
							echo "more ids found: ".$intersected_drugbankids."/".$drug_id."\n"; 
						}

					}
				}
				
				
				mysql_select_db ($database_dailymed);

				$sql_query = "INSERT INTO ".$database_table_drugs." (id, name, fullName".$sql_drugbank_id_field.")  VALUES (".$drug_id.", '".$drugName."', '".$fullDrugName."'".$sql_drugbank_id.")";
				if (!mysql_query ($sql_query)) {
					die(mysql_error() . " - query: ".$sql_query);
				}		
*/

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
					$i = $searchpath[$i]+1;
					for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
						if ($subarray[$k]["name"] != "INACTIVEINGREDIENT") {
							continue;
						}
						$subsubarray = $subarray[$k]["child"][0];
						if ($subsubarray["child"][0]["name"] == "NAME") {
							$inactiveIngridient = $subsubarray["child"][0]["content"];
							$fields[] = $inactiveIngridient;
						} else {
							$errors[$file] .= " INACTIVEINGRIDIENT";
						}
					}
/*				
					if ($parent_subtree["child"][0]["child"][1]["name"] == "NAME") {
						$inactiveIngridient = str_replace("'", "\'", $parent_subtree["child"][0]["child"][1]["content"]);
						$fields[] = $inactiveIngridient;
					}
*/
					
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
// OVERDOSAGE						
			} else if ($seperate_table == "overdosage") {
				$searchpath = array_searchRecursive("OVERDOSAGE", $xmlDrugFile->data[0]);
				if ($searchpath !== false ) {
					$subarray = $xmlDrugFile->data[0];
					for ($i = 0; $i < (sizeof($searchpath)-2); $i = $i+1) {
						$subarray = $subarray[$searchpath[$i]];
					}
					$i = $searchpath[$i]+1;
					for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
						if ($subarray[$k]["name"] == "TEXT") {
							$subsubarray = $subarray[$k]["child"];
							for ($j = 0; $j < sizeof($subsubarray); $j=$j+1) { 
								if ($subsubarray[$j]["name"] == "PARAGRAPH") {
									$overdosage = $subsubarray[$j]["content"];
									if ($j == 0) {
										$fields[] = $overdosage;
									} else {
										$fields[sizeof($fields)-1] .= " $overdosage";
									}
								} else {
									$errors[$file] .= " OVERDOSAGE";
								}
							}
						} else if ($subarray[$k]["name"] == "COMPONENT") {
							$subsubarray = $subarray[$k]["child"];
							for ($j = 0; $j < sizeof($subsubarray); $j=$j+1) { 
								if ($subsubarray[$j]["name"] == "SECTION") {
									for ($l = 0; $l < sizeof($subsubarray[$j]["child"]); $l=$l+1) { 
										$temp = $subsubarray[$j]["child"][$l];
										if ($temp["name"] == "TITLE") {
											$overdosage = "<br/>".$temp["content"].":";
											if (sizeof($fields) > 0) {
												$fields[sizeof($fields)-1] .= " $overdosage";
											}
										} else if ($temp["name"] == "TEXT") {
											if ($temp["child"][0]["name"] == "PARAGRAPH") {
												$overdosage = $temp["child"][0]["content"];
												if (sizeof($fields) > 0) {
													$fields[sizeof($fields)-1] .= " $overdosage";
												}
											}
										}
									}
								} else {
									$errors[$file] .= " OVERDOSAGE";
								}
							}
						}
					}
				}

// ADVERSE REACTIONS
			} else if ($seperate_table == "adverse_reactions") {
				$searchpath = array_searchRecursive("ADVERSE REACTIONS", $xmlDrugFile->data[0]);
				if ($searchpath !== false ) {
					$subarray = $xmlDrugFile->data[0];
					for ($i = 0; $i < (sizeof($searchpath)-2); $i = $i+1) {
						$subarray = $subarray[$searchpath[$i]];
					}
					$i = $searchpath[$i]+1;
					for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
						if ($subarray[$k]["name"] != "TEXT") {
							continue;
						}
						$subsubarray = $subarray[$k]["child"][0];
						if ($subsubarray["name"] == "PARAGRAPH") {
							$contraindication = $subsubarray["content"];
							$fields[] = $contraindication;
						} else {
							$errors[$file] .= " ADVERSE REACTIONS";
						}
					}
				}
// CONTRAINDICATIONS
			} else if ($seperate_table == "contraindications") {
				$searchpath = array_searchRecursive("CONTRAINDICATIONS", $xmlDrugFile->data[0]);
				if ($searchpath !== false ) {
					$subarray = $xmlDrugFile->data[0];
					for ($i = 0; $i < (sizeof($searchpath)-2); $i = $i+1) {
						$subarray = $subarray[$searchpath[$i]];
					}
					$i = $searchpath[$i]+1;
					for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
						if ($subarray[$k]["name"] != "TEXT") {
							continue;
						}
						$subsubarray = $subarray[$k]["child"][0];
						if ($subsubarray["name"] == "PARAGRAPH") {
							$contraindication = $subsubarray["content"];
							$fields[] = $contraindication;
						} else {
							$errors[$file] .= " CONTRAINDICATIONS";
						}

					}
				}
// DOSAGE AND ADMINISTRATION	
			} else if ($seperate_table == "dosage") {
				$searchpath = array_searchRecursive("DOSAGE AND ADMINISTRATION", $xmlDrugFile->data[0]);
				if ($searchpath !== false ) {
					$subarray = $xmlDrugFile->data[0];
					for ($i = 0; $i < (sizeof($searchpath)-2); $i = $i+1) {
						$subarray = $subarray[$searchpath[$i]];
					}
					$i = $searchpath[$i]+1;
					if ($drug_id == 10) {
						echo "";
					}
					for ($k = $i; $k < sizeof($subarray); $k=$k+1) {
						if ($subarray[$k]["name"] == "TEXT") {
							$subsubarray = $subarray[$k]["child"];
							for ($j = 0; $j < sizeof($subsubarray); $j=$j+1) { 
								if ($subsubarray[$j]["name"] == "PARAGRAPH") {
									$overdosage = $subsubarray[$j]["content"];
									if ($j == 0) {
										$fields[] = $overdosage;
									} else {
										$fields[sizeof($fields)-1] .= " $overdosage";
									}
								} else {
									$errors[$file] .= " OVERDOSAGE";
								}
							}
						} else if ($subarray[$k]["name"] == "COMPONENT") {
							$subsubarray = $subarray[$k]["child"];
							for ($j = 0; $j < sizeof($subsubarray); $j=$j+1) { 
								if ($subsubarray[$j]["name"] == "SECTION") {
									for ($l = 0; $l < sizeof($subsubarray[$j]["child"]); $l=$l+1) { 
										$temp = $subsubarray[$j]["child"][$l];
										if ($temp["name"] == "TITLE") {
											$overdosage = "<br/>".$temp["content"].":";
											if (sizeof($fields) > 0) {
												$fields[sizeof($fields)-1] .= " $overdosage";
											}
										} else if ($temp["name"] == "TEXT") {
											if ($temp["child"][0]["name"] == "PARAGRAPH") {
												$overdosage = $temp["child"][0]["content"];
												if (sizeof($fields) > 0) {
													$fields[sizeof($fields)-1] .= " $overdosage";
												}
											}
										}
									}
								} else {
									$errors[$file] .= " DOSAGE";
								}
							}
						}						
/*
						$subsubarray = $subarray[$k]["child"];
						if (($subsubarray["child"][2]["name"] == "TITLE") && ($subsubarray["name"] == "SECTION")) {
							for ($paragaph_location = sizeof($subsubarray["child"][2]); $paragaph_location > 2; $paragaph_location = $paragaph_location - 1) {
								if (($subsubarray["child"][$paragaph_location]["name"] == "TEXT")) {
									if ($subsubarray["child"][$paragaph_location]["child"] != null) {
										$dosage = $subsubarray["child"][2]["content"];
										$fields[] = $dosage . ":";
										foreach ($subsubarray["child"][$paragaph_location]["child"] as $description_p) {
											if ($description_p["name"] == "PARAGRAPH") {
												$description = $description_p["content"];
												$fields[sizeof($fields)-1] .= " " . $description;
											}
										}
									}
								} else if (($subsubarray["child"][sizeof($subsubarray["child"])-1]["name"] == "COMPONENT")){
									for ($paragaph_location1 = sizeof($subsubarray["child"])-1; $paragaph_location1 > 2; $paragaph_location1 = $paragaph_location1 - 1) {
										$subarray1 = $subsubarray["child"][$paragaph_location1]["child"][0];
										if ($subarray1["child"][2]["name"] == "TITLE") {
											for ($paragaph_location2 = (sizeof($subarray1["child"])-1); $paragaph_location2 > 2; $paragaph_location2 = $paragaph_location2 - 1) {
												if (($subarray1["child"][$paragaph_location2]["name"] == "TEXT")) {
													$dosage = $subarray1["child"][2]["content"];
													$fields[] = $dosage .":";
													foreach ($subarray1["child"][$paragaph_location2]["child"] as $description_p) {
														if ($description_p["name"] == "PARAGRAPH") {
															$description = $description_p["content"];
															$fields[sizeof($fields)-1] .= " " . $description;
														}
													}
												}
											}

										}

									}
								}								
							}
						}
*/
					}
									
					/*
					$i = 0;
					while($parent_subtree["child"][0]["child"][$i]["name"] == "ITEM") {
						$dosage = $parent_subtree["child"][0]["child"][$i]["content"];
						if (($dosage != null) && ($dosage != "")) {
							$fields[] = $dosage;
						}
						$i++;
					}
					*/
				}
			}


			foreach ($fields as $field) {
				if (!is_array($field)) {
					$field = str_replace("&#8217;", "'", $field);
					$field = str_replace("'", "\'", $field);
					$sql_query = "INSERT INTO ".$seperate_table." (drug, field)  VALUES (".$drug_id.", '".$field."')";
					if (!mysql_query ($sql_query)) {
						die(mysql_error() . " - query: ".$sql_query);
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
						die(mysql_error() . " - query: ".$sql_query);
					}		
				}
			}
		}
	}
}

// ERROR PRINTING
print_r($errors);



// if(is_array($val)){


/*
				else {
					$file_handle = fopen("dailymed/".$file, "r");
					if (!$file_handle) {
						die ("File not found ".$file);
					}
					while (!feof($file_handle)) {
						$line = trim(fgets($file_handle));
						if (strpos($line, "The most common side effects of") !== false) {
							echo $file."\n";
						}
					}	
					
*/

?>