<?php

/**
 * The LODD Utils includes the database configuration and
 * provides functions for handling XML and strings.
 *
 * @author	Anja Jentzsch <mail@anjajentzsch.de>
 */

$lodd_datasets = array("diseasome", "dailymed", "drugbank", "sider", "stitch");

$database_dailymed = "lodd_dailymed";
$database_drugbank = "lodd";
$database_diseasome = "lodd_diseasome";

require_once("../config/config.php");

$parent_subtree = array();

/**
 * Searches haystack for needle and 
 * returns an array of the key path if 
 * it is found in the (multidimensional) 
 * array, FALSE otherwise.
 *
 * @mixed array_searchRecursive ( mixed needle, 
 * array haystack [, bool strict[, array path]] )
 */
function array_searchRecursive( $needle, $haystack, $strict=false, $path=array() ) {
	global $parent_subtree;

	if(!is_array($haystack)) {
        return false;
    }
    foreach( $haystack as $key => $val ) {
        if( is_array($val) && $subPath = array_searchRecursive($needle, $val, $strict, $path) ) {
            $path = array_merge($path, array($key), $subPath);
            return $path;
        } else if (((!$strict && (strcasecmp($val, $needle) == 0)) || ($strict && $val === $needle))) {
            if ($haystack["name"] != "LINKHTML") {
	        	$path[] = $key;
				$parent_subtree = $haystack;
/*
				if (!is_array($haystack["child"])) {
					return false;
				} else {
		            return $path;
				}
				*/
				return $path;
            } else {
            	return false;
            }
        }
    }
    return false;
}

function array_searchRecursive_loose($needle, $haystack, $strict=false, $path=array(), $exact = false) {
	global $parent_subtree;

	if( !is_array($haystack) ) {
        return false;
    }
    foreach( $haystack as $key => $val) {
        if( is_array($val) && $subPath = array_searchRecursive_loose($needle, $val, $strict, $path)) {
            $path = array_merge($path, array($key), $subPath);
            return $path;
        } else if( (!$strict && (str_starts_with($val, $needle))) || ($strict && (str_starts_with($val, $needle)))) {
            $path[] = $key;
			$parent_subtree = $haystack;
            return $path;
        } else if (!is_array($val)) {
        	if (preg_match("/".$needle."/i", $val, $match)) {
	            $path[] = $key;
				$parent_subtree = $haystack;
	            return $path;
        	}
        }
    }
    return false;
}

function str_starts_with($haystack, $needle) {
	if (strpos($haystack, $needle) === 0) {
		return true;
	} else {
		return false;
	}
}

class XMLParser {
	var $filename;
	var $xml;
	var $data;

	function XMLParser($xml_file) {
		$this->filename = $xml_file;
		$this->xml = xml_parser_create();
		xml_set_object($this->xml, $this);
		xml_set_element_handler($this->xml, 'startHandler', 'endHandler');
		xml_set_character_data_handler($this->xml, 'dataHandler');
		$this->parse($xml_file);
	}

	function parse($xml_file) {
		if (!($fp = fopen($xml_file, 'r'))) {
			die('Cannot open XML data file: '.$xml_file);
			return false;
		}
		
		$bytes_to_parse = 512;

		while ($data = fread($fp, $bytes_to_parse)) {
			$parse = xml_parse($this->xml, $data, feof($fp));

			if (!$parse) {
				die(sprintf("$xml_file - XML error: %s at line %d",
				xml_error_string(xml_get_error_code($this->xml)),
				xml_get_current_line_number($this->xml)));
				xml_parser_free($this->xml
				);
			}
		}

		return true;
	}

	function startHandler($parser, $name, $attributes) {
		$data['name'] = $name;
		if ($attributes) {
			$data['attributes'] = $attributes;
		}
		$this->data[] = $data;
	}

	function dataHandler($parser, $data) {
		if (strpos($data, "Patients with severely impaired renal function") !== false) {
			echo "";
		}
		if ($data = trim($data)) {
			$index = count($this->data) - 1;
			// ANja $this->data[$index]['content'] = $data;
			$this->data[$index]['content'] .= $data;
		}
	}

	function endHandler($parser, $name) {
		if (count($this->data) > 1) {
			$data = array_pop($this->data);
			$index = count($this->data) - 1;
			$this->data[$index]['child'][] = $data;
		}
	}
}

if (false === function_exists('lcfirst')) {
	/**
     * Make a string's first character lowercase
     *
     * @param string $str
     * @return string the resulting string.
     */
	function lcfirst( $str ) {
		$str[0] = strtolower($str[0]);
		return (string)$str;
	}
}

function camelCase($text) {
	return str_replace(" ", "", lcfirst(ucwords(strtolower(trim(str_replace("_", " ", $text))))));
}

/**
 * Looks for the first occurence of $needle in $haystack and replaces it with $replace.
 *
 * @param string $needle
 * @param string $replace
 * @param string $haystack
 * @return string
 */

function str_replace_once($needle, $replace, $haystack) { 
   $pos = strpos($haystack, $needle); 
   if ($pos === false) { 
       // Nothing found 
       return $haystack; 
   } 
   return substr_replace($haystack, $replace, $pos, strlen($needle)); 
}

?>