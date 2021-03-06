<?php header('Content-Type: text/html; charset=utf-8');  ?>
<?php 

// $LastChangedDate$
// $Rev$
//shows the entries from zukunft that have been removed
include("shared_inc/wiki_functions.inc.php");

//$article = "Benutzer:Flominator/Zukunft";
$cat = $_REQUEST['cat'];
$other_cat_enc = urlencode($_REQUEST['other_cat']);
$template = urlencode($_REQUEST['template']);
$template_missing = $_REQUEST['template_missing'] == "true";
$catenc = urlencode($cat); //Wikipedia%3AZukunft
$articleenc = name_in_url($article);
$lang = "de";
$project = "wikipedia";
$server = "$lang.$project.org";
$number_of_current_entries = 0;

$plainfuture_text = retrieve_current_list($catenc, $template, $other_cat_enc, $template_missing);	
$plain_text = get_plain_text_from_article($articleenc);

//echo "<hr>$plainfuture_text<hr>";
echo '<form method="post" action="https://'.$server.'/w/index.php?action=submit&title='. $articleenc .'" target="_blank">'."\n";
echo "<textarea  name=\"wpTextbox1\">";
echo  extract_and_update_introduction($plain_text);
echo $plainfuture_text;
echo "\n&nbsp;Anzahl: $number_of_current_entries";
echo "</textarea><br>";
//echo '<input type="hidden" value="1" name="wpSection" />';
set_up_media_wiki_input_fields("Inventar-Seite mit inventory.php aktualisiert", "Inventar-Seite aktualisieren", $articleenc);
echo "</form>\n";

$entries_removed = compare_lists($plain_text, $plainfuture_text);
$entries_added= compare_lists($plainfuture_text, $plain_text);

echo '<form method="post" id="diff_form" action="https://'.$server.'/w/index.php?action=submit&title='. urlencode('Wikipedia:Spielwiese') .'" target="_blank">'."\n";

echo "<textarea  name=\"wpTextbox1\">";
echo ":via [[".$article."]]\n";
echo "\n===weg===\n";
print_diff_list($entries_removed);
echo "\n===dazu===\n";
print_diff_list($entries_added);
echo "</textarea><br>";

echo '<input type="hidden" value="new" name="wpSection" />';
set_up_media_wiki_input_fields("Änderungen", "Änderungen anschauen", urlencode('Wikipedia:Spielwiese'));
echo "</form>\n";

function extract_and_update_introduction($plain_text)
{
    $introduction = extract_section_zero($plain_text);
    $LAST_UPDATE_PARAMETER = "LastUpdate";
    $REV_TIMESTAMP = "{{subst:REVISIONTIMESTAMP}}";
    $new_introduction = "";
    if(stristr($introduction, "LastUpdate"))
    {
        $new_introduction = update_template_parameter($introduction, $LAST_UPDATE_PARAMETER, $REV_TIMESTAMP);    
    }
    else
    {
        $new_introduction = str_replace("{{Artikelinventar", "{{Artikelinventar\n|$LAST_UPDATE_PARAMETER=$REV_TIMESTAMP", $introduction);
    }
    return $new_introduction;
}
function extract_section_zero($plain_text)
{
    $endOfIntroduction = "==\n";
    $indexOfEnd = strpos($plain_text, $endOfIntroduction) + strlen($endOfIntroduction);
    return substr($plain_text, 0, $indexOfEnd);
}
function print_diff_list($entries_removed)
{
    global $cat;
    $since = $_REQUEST['last'];
    $use_diff = ($since != "");

    $url_prefix = 'http://'.$_SERVER["SERVER_NAME"].'/'.'diff2me.php?mode=date&date_after=' .$since.'&project=wikipedia&lang=de&article=';

    foreach($entries_removed AS $removed)
    {
        $article_with_link = str_replace(": [[:Kategorie:$cat|$cat]]", "", $removed);
        $article = extract_link_target($article_with_link);
        echo $article_with_link;
        if($use_diff) 
        {
            echo " ([$url_prefix". name_in_url($article) . " diff])";
        }
        echo "\n";
    }
    echo "\n Änderungen: ". count($entries_removed);
}

function get_plain_text_from_article($articleenc)
{
	global $server;
	$page = "https://".$server."/w/index.php?action=raw&title=".$articleenc;
	return file_get_contents($page);
}

function compare_lists($needles, $haystack)
{
	//echo "entering compare_lists";
	echo '<!--- it seems that some output every once in a while keeps the browser';
	echo ' from stopping to load the page. Therefor here there will be a lot of dots ';
	echo " that pop up while the script is working it's ass off: ";
	$results = array();
	//$hits = 0;
	$paragraphsRemoved = explode("\n",$needles);
	 //echo "<h2> haystack</h2><textarea>$haystack</textarea>";
	 //echo "<h2> needles</h2><textarea>$needles</textarea>";
	foreach($paragraphsRemoved AS $newLine)
	{
		set_time_limit(120);
		echo ".";
		$onlyOneNewArticle = explode("]]:", $newLine);
		if(	stristr( $onlyOneNewArticle[0], "*" ) 
		 &&	!stristr($haystack, $onlyOneNewArticle[0] )
		 &&	!stristr($haystack, str_replace('_', ' ', $onlyOneNewArticle[0] ))
		 &&	!stristr(str_replace('_', ' ',$haystack),  $onlyOneNewArticle[0] )
		)
		{
			//echo str_replace('_', ' ', $newLine) ."\n";
			$results[] = str_replace('_', ' ', $newLine);
			//$hits++;
		}
	}
	//echo "$hits hits";
	//echo "leaving compare_lists";
	echo '-->';
	sort($results);
	return $results;
}

function retrieve_current_list($catenc, $template, $other_cat_enc="", $template_not_present=false)
{
	global $cat, $number_of_current_entries;

	$all_namespaces ="ns%5B-2%5D=1&ns%5B0%5D=1&ns%5B2%5D=1&ns%5B4%5D=1&ns%5B6%5D=1&ns%5B8%5D=1&ns%5B10%5D=1&ns%5B12%5D=1&ns%5B14%5D=1&ns%5B100%5D=1&ns%5B828%5D=1&ns%5B-1%5D=1&ns%5B1%5D=1&ns%5B3%5D=1&ns%5B5%5D=1&ns%5B7%5D=1&ns%5B9%5D=1&ns%5B11%5D=1&ns%5B13%5D=1&ns%5B15%5D=1&ns%5B101%5D=1&ns%5B829%5D=1";
	$url ="https://petscan.wmflabs.org/?language=de&categories=$catenc%0D%0A$other_cat_enc&doit=1&format=tsv&$all_namespaces&depth=15&sortby=title";
	
   
   if($template!="")
	{
		
      if(!$template_not_present)
      {
         $url.="&templates_yes=$template";
      }
      else
		{
			$url.="&templates_no=$template";
		}
  	}

	ini_set('user_agent', 'script by de_user_Flominator'); 
    $csv_list = file_get_contents($url); 

	echo "$url<br/>";
	if(!$csv_list)
	{
		var_dump($http_response_header);
		die("<b>error while retrieving list from wmflabs</b>");
	}
	
	//echo strlen($csv_list);

	//echo "<h1>csv</h1>$csv_list";

	$rows = explode("\n", $csv_list);
	$bulleted_list = "";

	//echo count($rows) . "rows";
	foreach($rows AS $row)
	{
		$cols = explode("\t", $row);

		if($cols[1]!="" && $cols[1] != 'title')
		{
			
			$lemma = str_replace('_', ' ', $cols[1]);
			
			switch($cols[3])
			{
				case "Category":
					$lemma = ":Kategorie:$lemma";
					break;
				case "File";
					$lemma = ":Datei:$lemma";
					break;
				case "Template";
					$lemma = ":Vorlage:$lemma";
					break;					
			}
			
			/*if(stristr($lemma, 'Kategorie:') || stristr($lemma, 'Datei:') || stristr($lemma, 'Vorlage:'))*/
			// if(stristr($lemma, 'Category:') || stristr($lemma, 'File:') || stristr($lemma, 'Template:'))
			// {
				// $lemma = ':'.$lemma;
			// }
			
			$bulleted_list.="* [[".$lemma."]]\n";
			$number_of_current_entries = $number_of_current_entries + 1;
		}
	}
	return $bulleted_list;
}
?>