<?php

$path = "dailymed";
if ($dir=opendir($path)) {
	while($file=readdir($dir)) {
		if (!is_dir($file) && (strpos($file,".xml") !== false)) {
			$file_handle = fopen("dailymed/".$file, "r");
			$file_handle1 = fopen("dailymed/1/".$file, "w");
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
				
				
				
				/*
				if(strpos($line, "most important factor in duodenal ulcer healing") !== false) {
					echo "";
				}
				*/
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
			/*
			if (preg_match_all("/<paragraph>.*?<\/paragraph>/", $line, $match)) {
				foreach ($match[0] as $matched_p) {
				*/
/*
				if ($line_nr == 620 && $file == "01C3122F-E791-40A5-B52E-963CB6685F71.xml" ) {
					echo "";
				}
*/

				$line = preg_replace("/<content[^>]*>/", "", $line);
					$line = preg_replace("/<\/content>/", "", $line);


				/*
				}
			}
*/
/*
			if (preg_match_all("/<paragraph>.*?<\/paragraph>/", $line, $match)) {
				foreach ($match[0] as $matched_p) {
*/
				$line = preg_replace("/<linkHtml[^>]*>/", "", $line);
					$line = preg_replace("/<\/linkHtml>/", "", $line);
/*
			}
			}
*/

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
}


?>

