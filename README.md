# Arheisel.NaturalLanguageHelper
 Natural Language Helper Library using Levenshtein. It supports spelling mistakes and words out of order.
 
 ## Usage
 
 Use the `normalizeString($str)` function to normalize all the special characters and prepare it for the comparison. You can edit the `replacements.php` file for your working language (Current is Spanish)
 
 `compareStrings($input, $against)` Will compare the two strings and return a score between 0 and 100 depending on how well the two strings match. 
 
 Note: 100 is reserved for when the two strings match exactly, maximun score otherwise is 98.
 
 Note: reversing the arguments yields different results
 
 Also, the library includes a `buildIndex($string)` function that returns a json encoded string that can be later compared against with the `compareAgainstIndex($input, $index)` for much higher performance.
 
 The function `splitSentences($text, $keepAll = false)` splits large texts into an array of sentences that can be later fed to the `compareStrings` functions one by one. For that the `abbreviations.php` file needs to be set to the correct language (Current is Spanish)
 
 If useful this library also includes a Multibyte `mb_replace($search, $replace, $subject, &$count=0)` function. Credit to Gumbo.
 

