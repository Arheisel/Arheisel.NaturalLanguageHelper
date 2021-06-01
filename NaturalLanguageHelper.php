<?php
spl_autoload_register(function ($class_name) {
    require $class_name.'.php';
});

class NaturalLanguageHelper{

	public static function mb_replace($search, $replace, $subject, &$count=0) { //credit to Gumbo
	    if (!is_array($search) && is_array($replace)) {
	        return false;
	    }
	    if (is_array($subject)) {
	        // call mb_replace for each single string in $subject
	        foreach ($subject as &$string) {
	            $string = &self::mb_replace($search, $replace, $string, $c);
	            $count += $c;
	        }
	    } elseif (is_array($search)) {
	        if (!is_array($replace)) {
	            foreach ($search as &$string) {
	                $subject = self::mb_replace($string, $replace, $subject, $c);
	                $count += $c;
	            }
	        } else {
	            $n = max(count($search), count($replace));
	            while ($n--) {
	                $subject = self::mb_replace(current($search), current($replace), $subject, $c);
	                $count += $c;
	                next($search);
	                next($replace);
	            }
	        }
	    } else {
	        $parts = mb_split(preg_quote($search), $subject);
	        $count = count($parts)-1;
	        $subject = implode($replace, $parts);
	    }
	    return $subject;
	}
    
	public static function normalizeString($str){

		require 'replacements.php';

		$str = self::toUTF8($str);
		$str = mb_strtolower($str);
		$str = self::mb_replace(array_keys($replacements), array_values($replacements), $str);
		return preg_replace('!\s+!', ' ', $str);
	}
    
	public static function compareStrings($input, $against){
		if(!$input) return 0;

		if($input == $against)return 100;

		$inputArray = explode(" ", $input);
		$inputCount = count($inputArray);
		$inputDuplicates = self::resolveDuplicates($inputArray);
		//$inputArray = array_values($inputArray);

		$againstArray = explode(" ", $against);
		
		$totalScore = 0;
		$maxScore = 98/$inputCount;
		$matchedArray = array();
		//Log::echo("\nMaxScore: {$maxScore}\n\n");
        
		foreach($inputArray as $key1 => $word1){
			$len = mb_strlen($word1);
			$score = 0;
			$bestWord = "";
			$matchedArrayTemp = array();
			foreach($againstArray as $key2 => $word2){	
				if(!self::compareWords($key1, $word1, $key2, $word2, $score, $maxScore, $bestWord, $matchedArrayTemp, $len, $inputCount)){
					break;
				}
			}
			$matchedArray += $matchedArrayTemp;
			$totalScore += $score;
			//Log::echo("Total Score: {$totalScore}\n\n");
		}
		if($inputCount > 1){
			//$bestWindowStart = 0;
			ksort($matchedArray, SORT_NUMERIC);

			$totalScore -= self::scoreContext($matchedArray, $inputCount, $inputDuplicates);


		}
 
		return $totalScore;
	}

	public static function resolveDuplicates(&$inputArray){
		$inputDuplicates = array();
		foreach ($inputArray as $key => $value) {
			$found = array_search($value, $inputArray, TRUE);
			if($found !== FALSE && $found !== $key){
				$inputDuplicates[$key] = $found;
				unset($inputArray[$key]);
			}
			else $inputDuplicates[$key] = $key;
		}
		ksort($inputDuplicates, SORT_NUMERIC);

		return $inputDuplicates;
	}

	public static function compareWords($key1, $word1, $key2, $word2, &$score, $maxScore, &$bestWord, &$matchedArrayTemp, $word1Len, $inputCount){
		if($word2 && $word2 == $bestWord){
			$matchedArrayTemp[$key2] = $key1;
		}
		elseif($word1 == $word2){
			$score = $maxScore;
			$bestWord = $word2;
			$matchedArrayTemp[$key2] = $key1;
			if($inputCount == 1) return false;
		}
		else{
			$lev = levenshtein($word1, $word2, 1, 2, 2);
			$partialScore = (($word1Len - $lev)/$word1Len) * $maxScore;
			if($partialScore > $score){
				$score = $partialScore;
				$bestWord = $word2;
				$matchedArrayTemp = array();
				$matchedArrayTemp[$key2] = $key1;
			} 
		}		
		return true;
	}

	public static function scoreContext($matchedArray, $inputCount, $inputDuplicates){
		$windowLenght = $inputCount * 3;

		$bestWindow;
		//$bestWindowStart;
		$bestWindowScore = 15;

		while(!empty($matchedArray)){
			$window = array();
			$windowStart = key($matchedArray);

			foreach ($matchedArray as $key => $value) {
				if($key < $windowStart+$windowLenght) $window[$key] = $value;
				else break;
			}

			$count = count($window);
			if($count > 1){
				foreach ($window as $key => $value) {
					if($key == 0) break;
					$window[$key - $windowStart] = $value;
					unset($window[$key]);
				}

				//$windowstr = var_export($window, true);

				$inputDuplicatesCopy = $inputDuplicates;
				foreach ($window as $key => $value) {
					foreach ($inputDuplicatesCopy as $Dkey => $Dvalue) {
						if($value == $Dvalue){
							$window[$key] = $Dkey;
							unset($inputDuplicatesCopy[$Dkey]);
							continue 2;
						}
					}
					unset($window[$key]);
				}

				$count = count($window); //some keys may get deleted

				$a = $count * self::sumA($window);
				$b = array_sum(array_keys($window)) * array_sum(array_values($window));
				$c = $count * self::sumC($window);
				$d = array_sum(array_keys($window)) ** 2;

				$m = ($a - $b)/($c - $d);
				$slopeScore = abs($m - 1) * 3;

				$countScore = ($inputCount - $count) * 2;

				$orderPenalty = 0;
				$lastWord1 = -1;
				foreach ($window as $value) {
					if($value <= $lastWord1) $orderPenalty++;
					$lastWord1 = $value;
				}
				$orderPenalty *= 0.5;

				$totalScore = $slopeScore + $countScore + $orderPenalty;
				$totalScore *= 3;

				if($totalScore < $bestWindowScore){
					//$bestWindow = $window;
					//$bestWindowStart = $windowStart;
					$bestWindowScore = $totalScore;

				}
			}

			unset($matchedArray[$windowStart]);
		}

		return $bestWindowScore;
	}

	public static function sumA($array){
		$sum = 0;
		foreach ($array as $y => $x) {
			$sum += $x*$y;
		}
		return $sum;
	}

	public static function sumC($array){
		$sum = 0;
		foreach ($array as $y => $x) {
			$sum += $y*$y;
		}
		return $sum;
	}

	public static function compareAgainstIndex($input, $index){
		if(!$input) return 0;

		$inputArray = explode(" ", $input);
		$inputCount = count($inputArray);
		$inputDuplicates = $inputDuplicates = self::resolveDuplicates($inputArray);

		$indexArray = json_decode($index, TRUE);

		$totalScore = 0;
		$maxScore = 98/$inputCount;

		$matchedArray = array();

		foreach ($inputArray as $key1 => $word1) {
			$len = mb_strlen($word1);
			//$min = ceil(($len + 1)/2);
			//$max = 2*$len - 1;

			$columnIndexHigh = $len;
			$columnIndexLow = $len - 1;
			$i = 0;

			$score = 0;
			$matchedArrayTemp = array();

			while (true) {
				if($i <= 2 || $i%3 != 0){
					$partialMaxScore = ((2*$len-$columnIndexHigh)/$len)*$maxScore;

					if($partialMaxScore <= 0 || $score > $partialMaxScore)
						break;
					if(!array_key_exists($columnIndexHigh, $indexArray)) continue;

					//$againstArray = array_keys($indexArray[$columnIndexHigh]);

					foreach ($indexArray[$columnIndexHigh] as $word2 => $word2PosArray) {
						if(!self::compareIndexedWords($key1, $word1, $word2, $word2PosArray, $score, $maxScore, $matchedArrayTemp, $len)){
							break 2;
						}
					}

					$columnIndexHigh++;
				}
				else{
					$partialMaxScore = ((2*$columnIndexLow-$len)/$len)*$maxScore;

					if($partialMaxScore <= 0 || $score > $partialMaxScore)
						break;
					if(!array_key_exists($columnIndexLow, $indexArray)) continue;

					//$againstArray = array_keys($indexArray[$columnIndexLow]);

					foreach ($indexArray[$columnIndexLow] as $word2 => $word2PosArray) {
						if(!self::compareIndexedWords($key1, $word1, $word2, $word2PosArray, $score, $maxScore, $matchedArrayTemp, $len)){
							break 2;
						}
					}
					

					$columnIndexLow--;
				}
				$i++;
			}
			$matchedArray += $matchedArrayTemp;
			$totalScore += $score;
		}

		if($inputCount > 1){
			//$bestWindowStart = 0;
			ksort($matchedArray, SORT_NUMERIC);

			$totalScore -= self::scoreContext($matchedArray, $inputCount, $inputDuplicates);
		}

		return $totalScore;
	}


	public static function compareIndexedWords($key1, $word1, $word2, $word2PosArray, &$score, $maxScore, &$matchedArrayTemp, $word1Len){
		if($word1 == $word2){
			$score = $maxScore;
			foreach ($word2PosArray as $value) {
				$matchedArrayTemp[$value] = $key1;
			}
			return false;
		}
		else{
			$lev = levenshtein($word1, $word2, 1, 2, 2);
			$partialScore = (($word1Len - $lev)/$word1Len) * $maxScore;
			if($partialScore > $score){
				$score = $partialScore;
				$matchedArrayTemp = array();
				foreach ($word2PosArray as $value) {
					$matchedArrayTemp[$value] = $key1;
				}
			} 
		}		
		return true;
	}

	public static function buildIndex($string){
		$result = array();

		$string = self::normalizeString($string);
		$stringArray = explode(" ", $string);

		foreach ($stringArray as $key => $value) {
			$len = mb_strlen($value);
			if(array_key_exists($len, $result)){
				if(array_key_exists($value, $result[$len])){
					array_push($result[$len][$value], $key);
				}
				else{
					$result[$len][$value] = array($key);
				}
			}
			else{
				$result[$len] = array();
				$result[$len][$value] = array($key);
			}
		}

		return json_encode($result);
	}


	public static function toUTF8($str){
		if(self::isUTF8($str)) return $str;
		else return utf8_encode($str);
	}

	public static function toANSI($str){
		if(self::isUTF8($str)) return utf8_decode($str);
		else return $str;
	}

	public static function isUTF8($str){
		$isoChars = array(
			"á","Á","é","É","í","Í","ó","Ó","ú","Ú","ü","Ü","ñ","Ñ"
		);
		foreach ($isoChars as $key => $value) {
			$isoChars[$key] = utf8_decode($value);
		}

		$len = mb_strlen($str);
		for ($i=0; $i < $len; $i++) { 
			foreach ($isoChars as $isoChar) {
				if($str[$i] == $isoChar) return false;
			}
		}
		return true;
	}

	public static function splitSentences($text, $keepAll = false){ //please god help me
		require 'abbreviations.php';

		$sentences = array();
		if($keepAll) $addLen = 10;
		else $addLen = 0;

		$sentences2 = self::explode($text, 1 + $addLen, '. ','! ','? ');
		$sentences2 = self::explode($sentences2, 0 + $addLen, "\r\n", "\n", "\r");

		$count = count($sentences2);
		for($i = 0; $i < $count; $i++){
			$sentence = self::rejoinAbbreviations($sentences2, $i, $abbreviations, $count);
			array_push($sentences, $sentence);
		}
		Log::echo("\n\n".var_export($sentences, true)."\n\n");
		return $sentences;
	}

	public static function rejoinAbbreviations(&$sentences, &$i, &$abbreviations, $count){
		
		$pos = mb_strrpos($sentences[$i], ' ');
		if($pos === FALSE) $lastWord = $sentences[$i];
		else $lastWord = mb_substr($sentences[$i], $pos + 1);
		//$lastWord = mb_ereg_replace("\(|\[|\{\)\]\}", "", $lastWord);
		$lastWord = self::normalizeString($lastWord);
		//log::echo("\nLast Word: '{$lastWord}'\n\n");
		if(in_array($lastWord, $abbreviations, true) && $i < $count - 1){
			$currentSentence = $sentences[$i];
			return $currentSentence." ".self::rejoinAbbreviations($sentences, ++$i, $abbreviations, $count);
		}
		else return $sentences[$i];
	}

	public static function explode($haystack, $keepLen = 1, $needle1){
		$needles = func_get_args();
		unset($needles[0]);
		unset($needles[1]);

		if(is_array($haystack)) $sentences = $haystack;
		else $sentences = array($haystack);
		foreach ($needles as $needle){
			$needleLen = mb_strlen($needle);
			if($keepLen > $needleLen) $addLen = $needleLen;
			else $addLen = $keepLen;			
			$sentences2 = array();
			foreach ($sentences as $sentence) {
				$lastPos = 0;
				while (true) {
					$pos = mb_strpos($sentence, $needle, $lastPos);
					if($pos !== FALSE){
						$str = mb_substr($sentence, $lastPos, $pos - $lastPos + $addLen);
						$lastPos = $pos + $needleLen;
						array_push($sentences2, $str);
					}
					else{
						$str = mb_substr($sentence, $lastPos);
						array_push($sentences2, $str);
						break;
					}
				}
			}
			$sentences = $sentences2;
		}
		return $sentences;
	}

	public static function implode($pieces, &$count = NULL){
		if(!is_array($pieces)) ErrorHandler::throwError("pieces is not Array");

		$pieces = array_values($pieces);
		$string = "";
		$total = count($pieces);
		$count = $total;

		for($i = 0; $i < $total; $i++){
			if($i == $total - 1 && $total != 1) $string .= " y ";
			elseif($i > 0 ) $string .= ', ';
			
			$string .= $pieces[$i];
		}

		return $string;
	}
    
} 
