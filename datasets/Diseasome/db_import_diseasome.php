<?php

/**
* Create MySQL Dump from Diseasome files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

require_once("../scripts/lodd_utils.php");

$seperate_dbs = array("diseaseGenes");

$database_drugbank = "lodd";
$database_diseasome = "lodd_diseasome";

$database_drugbank_table_drug_targets = "drug_targets";
$database_drugbank_table_targets = "targets";

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
mysql_select_db ($database_diseasome);

$file_s2 = "supplementary_tableS2.txt";
$file = "supplementary_tableS1.txt";
$file_hgnc = "hgnc.n3";
$database_disease = "diseases";
$database_disease_genes = "disease_genes";
$database_drug_targets = "drug_targets";

$genes = array();

$file_handle = fopen($file_s2, "r");
if (!$file_handle) {
	die ("File not found ".$file_s2);
}
while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$disease_order_id = $line_parts[0];
	if (is_numeric($disease_order_id)) {
		//$disease_id = trim($line_parts[0]);
		$data[]["disease_id"] = trim($line_parts[0]);
		$data[sizeof($data)-1]["name"] = trim($line_parts[1]);
		$data[sizeof($data)-1]["class"] = trim($line_parts[2]);
		$data[sizeof($data)-1]["size"] = trim($line_parts[3]);
		$data[sizeof($data)-1]["degree"] = trim($line_parts[4]);
		$data[sizeof($data)-1]["classDegree"] = trim($line_parts[5]);
		$disease_genes = explode(",", trim(str_replace("\"", "", $line_parts[6])));
		$data[sizeof($data)-1]["genes"] = $disease_genes;
		foreach ($disease_genes as $gene) {
			if (!in_array($gene, $genes)) {
				$genes[] = str_replace("\"", "", $gene);
			}
		}
	}
}

$file_handle = fopen($file, "r");
if (!$file_handle) {
	die ("File not found ".$file);
}
while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	$line_parts = explode("\t", $line);
	$disease_order_id = $line_parts[0];
	if (is_numeric($disease_order_id)) {
		$data[]["name"] = trim($line_parts[1]);
		$disease_genes = explode(",", trim($line_parts[2]));
		$data[sizeof($data)-1]["genes"] = $disease_genes;
		foreach ($disease_genes as $gene) {
			if (!in_array($gene, $genes)) {
				$genes[] = str_replace("\"", "", $gene);
			}
		}
		$data[sizeof($data)-1]["omim"] = trim($line_parts[3]);
		$data[sizeof($data)-1]["chromosomalLocation"] = trim($line_parts[4]);
		$data[sizeof($data)-1]["class"] = trim($line_parts[5]);
		$data[sizeof($data)-1]["diseaseSubtypeOf"] = trim($line_parts[0]);
	}
}


$file_handle = fopen($file_hgnc, "r");
if (!$file_handle) {
	die ("File not found ".$file);
}
$hgnc = array();
while (!feof($file_handle)) {
	$line = trim(fgets($file_handle));
	if (preg_match("/<http:\/\/hgnc\.bio2rdf\.org\/hgnc:([^>]*)> <http:\/\/bio2rdf\.org\/owl\/bio2rdf#symbol> <http:\/\/symbol\.bio2rdf\.org\/symbol:([^>]*)>/", $line, $match)) {
		if (in_array($match[2], $genes)) {
			$hgnc[$match[1]]["symbol"] = $match[2];
		}
	}
	if (preg_match("/<http:\/\/hgnc\.bio2rdf\.org\/hgnc:([^>]*)> <http:\/\/bio2rdf\.org\/owl\/bio2rdf#xChromosome> <http:\/\/geneid\.bio2rdf\.org\/geneid:([^>]*)>/", $line, $match)) {
		$hgnc[$match[1]]["chromosome"] = $match[2];
	}
	if (preg_match("/<http:\/\/hgnc\.bio2rdf\.org\/hgnc:([^>]*)> <http:\/\/bio2rdf\.org\/owl\/bio2rdf#xGeneID> <http:\/\/geneid\.bio2rdf\.org\/geneid:([^>]*)>/", $line, $match)) {
		$hgnc[$match[1]]["geneId"] = $match[2];
	}
	if (preg_match("/<http:\/\/hgnc\.bio2rdf\.org\/hgnc:([^>]*)> <http:\/\/bio2rdf\.org\/owl\/bio2rdf#xOMIM> <http:\/\/omim\.bio2rdf\.org\/omim:([^>]*)>/", $line, $match)) {
		$hgnc[$match[1]]["omim"] = $match[2];
	}
}

$hgnc_matching = array();
foreach ($hgnc as $id => $hgnc_entry) {
	if (in_array($hgnc_entry["symbol"], $genes)) {
		$hgnc_matching[$hgnc_entry["symbol"]]["id"] = $id;
		$hgnc_matching[$hgnc_entry["symbol"]]["chromosome"] = $hgnc_entry["chromosome"];
		$hgnc_matching[$hgnc_entry["symbol"]]["geneId"] = $hgnc_entry["geneId"];
		$hgnc_matching[$hgnc_entry["symbol"]]["omim"] = $hgnc_entry["omim"];
	}
}
unset($hgnc);

echo "ANALYZATION DONE\n";

mysql_query ("drop table ".$database_disease.";");
mysql_query ("drop table ".$database_disease_genes.";");
mysql_query ("drop table ".$database_drug_targets.";");

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_disease." ( 
	id int(10) NOT NULL,
	disease_id varchar(10) NULL,
	diseaseSubtypeOf int(10) NOT NULL,
	name varchar(400) NOT NULL,
	omim varchar(400) NULL,
 	chromosomalLocation varchar(400) NULL,
 	class varchar(400) NULL,
 	size int(10) NULL,
 	degree int(10) NULL,
 	classDegree int(10) NULL,
 	PRIMARY KEY  (id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_disease_genes." ( 
	disease int(10) NOT NULL,
	gene varchar(15) NOT NULL,
	hgnc varchar(15) NULL,
	geneid varchar(15) NULL,
 	PRIMARY KEY  (disease, gene)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

$sql_query = "CREATE TABLE IF NOT EXISTS ".$database_drug_targets." ( 
	disease int(10) NOT NULL,
	drug varchar(15) NOT NULL,
 	PRIMARY KEY  (disease, drug)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
if (!mysql_query ($sql_query)) {
	die(mysql_error() . " - query: ".$sql_query);
}

echo "DB CREATION DONE\n";

foreach ($data as $disease_id => $disease_info) {
	$sql_query1 = "INSERT INTO ".$database_disease." (id, ";
	$sql_query2 = "VALUES ('".$disease_id."', ";
	foreach ($disease_info as $id => $content) {
		if ($id == "name") {
			if (strpos($content, "\"") === 0) {
				$content = trim(str_replace("\"", "", $content));
			}
			$content = preg_replace("/(.?) \([0-9]*\)/", "$1", $content);
		}
		if ($id != "genes") {
			if (strpos($content, "\"") === 0) {
				$content = trim(str_replace("\"", "", $content));
			}
			$content = preg_replace("/(.?) \([0-9]*\)/", "$1", $content);
			$sql_query1 .=  $id . ", ";
			$sql_query2 .= "'".str_replace("'", "\'", $content)."', ";
			$sql_query2 = str_replace("\\\\", "\\", $sql_query2);
		} else {
			foreach ($content as $gene) {
				$gene = trim(str_replace("\"", "", $gene));
				$gene = trim(preg_replace("/\([0-9]*\)/", "", $gene));
				// DrugBank Link
				mysql_select_db ($database_drugbank);
				
				// bio2rdf symbol
				$drugbank_ids = array();
				$mysqlquery = 'SELECT '.$database_drugbank_table_drug_targets.'.drug FROM '.$database_drugbank_table_drug_targets.', '.$database_drugbank_table_targets.' where '.$database_drugbank_table_targets.'.geneName = "'.$gene.'" AND '.$database_drugbank_table_drug_targets.'.target = '.$database_drugbank_table_targets.'.id';
				$result = mysql_query($mysqlquery);
				while ($row = mysql_fetch_row($result)) {
					$drugbank_ids[] = $row[0];
				}

				mysql_select_db ($database_diseasome);
				
				foreach ($drugbank_ids as $drugbank_id) {
					$sql_query = "INSERT INTO ".$database_drug_targets." (drug, disease) VALUES ('".$drugbank_id."', '".$disease_id."');";
					if (!mysql_query ($sql_query)) {
						if (strpos(mysql_error(), "Duplicate entry") === false) {
							die("[DIE] linkage, drug ".$drugbank_id." - disease ".$disease_id." : ". mysql_error() . " - query: ".$gene_sql_query);
						}
					}
				}
					
				// /DrugBank Link
				$sql1 = null;
				$sql2 = null;
				foreach ($hgnc_matching as $hgnc_symbol => $hgnc_entry) {
					if ($hgnc_symbol == $gene) {
						if (($hgnc_entry["chromosome"] == $data[$disease_id]["chromosomalLocation"]) 
							&& ($hgnc_entry["omim"] == $data[$disease_id]["omim"])
							&& ($hgnc_entry["geneId"] != null)
							&& ($hgnc_entry["id"] != null)) {
							$sql1 = ", geneid, hgnc";
							$sql2 = ", '".$hgnc_entry["geneId"]."', '".$hgnc_entry["id"]."'";
						}
					}
				}
				$gene_sql_query = "INSERT INTO ".$database_disease_genes." (disease, gene".$sql1.") VALUES ('".$disease_id."', '".$gene."'".$sql2.");";
				if (!mysql_query ($gene_sql_query)) {
					if (strpos(mysql_error(), "Duplicate entry") === false) {
						die("[DIE] linkage, gene ".$gene." at drugcard ".$disease_id." : ". mysql_error() . " - query: ".$gene_sql_query);
					}
				}
			}	
		}
	}
	$sql_query1 = substr($sql_query1, 0, -2).") ";
	$sql_query2 = substr($sql_query2, 0, -2).") ";
	if (!mysql_query ($sql_query1.$sql_query2)) {
		die(mysql_error() . " - query: ".$sql_query1.$sql_query2);
	}
}

echo "DISEASES & GENES DONE\n";

fclose($file_handle);

?>