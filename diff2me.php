<!-- forwards you do diff since your last edit 

http://localhost:81/wikipedia/diff2me.php?mode=date&date_after=2014-03-14&&project=wikipedia&article=Hinterzarten&lang=de
-->
<?php

include("shared_inc/language.inc.php");
include("shared_inc/wiki_functions.inc.php");
$inc_dir = "wikiblame_inc";

//get the language file and decide whether rtl oder ltr is used
$user_lang = read_language();
get_language('en', $inc_dir); //not translated messages will be printed in English
get_language($user_lang, $inc_dir);

if($lang=="")
{
	$lang=$user_lang; 
}

$project = $_REQUEST['project'];
if($project=="")
{
	$project="wikipedia";
}
if($lang=="blank")
{
	$server= $project.".org";
}
else
{
	$server= $lang.".".$project.".org";
}


$userName = $_REQUEST['user']; 
$mode = 'user';
$dateAfterString = $_REQUEST['date_after'];

if($dateAfterString != "")
{
	$mode='date';
	$dateParts = explode('-', $dateAfterString);
	$dateAfter = mktime(0, 0, 0, $dateParts[1], $dateParts[2], $dateParts[0]);
}



$article = $_REQUEST['article']; 
$articleenc = name_in_url($article);
$article_url = "https://".$server."/w/index.php?title=".$articleenc."&action=raw";
 
if($mode=='date')
{
	$art_text = file_get_contents($article_url);

	$is_redir = needle_in_cached_page("#redirect", $art_text);
	if(!$is_redir)
	{
		$is_redir = needle_in_cached_page("#weiterleitung", $art_text);
	}

	if($is_redir)
	{
		$redir_target = extract_link($art_text);
		$article = $redir_target; 
		$articleenc = name_in_url($article);
	}
}
$limit = 5000;
$historyurl = "http://".$server."/w/index.php?title=".$articleenc."&action=history&limit=$limit&uselang=en";
//echo $historyurl;


$history = file_get_contents($historyurl);

$searchterm = "name=\"diff\" "; //assumes that the history begins at the first occurrence of name="diff" />  <!--removed />-->

$versionen=array(); //array to store the links in

$revision_html_blocks = explode($searchterm, $history); 

/*
result in $revision_html_blocks are parts of the revision history that look like this (without line wraps) 

id="mw-diff-64569839" /> 
<a href="/w/index.php?title=Hinterzarten&amp;oldid=64569839" title="Hinterzarten">11:27, 16. Sep. 2009</a> 
<span class='history-user'>
	<a href="/wiki/Benutzer:TXiKiBoT" title="Benutzer:TXiKiBoT" class="mw-userlink">TXiKiBoT</a> 
	<span class="mw-usertoollinks">(<a href="/wiki/Benutzer_Diskussion:TXiKiBoT" title="Benutzer Diskussion:TXiKiBoT">Diskussion</a> | 
		<a href="/wiki/Spezial:Beitr%C3%A4ge/TXiKiBoT" title="Spezial:Beiträge/TXiKiBoT">Beiträge</a>)
	</span>
</span> 
<abbr class="minor" title="Kleine ÿnderung">K</abbr> 
<span class="history-size">(10.740 Bytes)</span> 
<span class="comment">(Bot: Ergänze: <a href="http://vi.wikipedia.org/wiki/Hinterzarten" class="extiw" title="vi:Hinterzarten">vi:Hinterzarten</a>)</span> (<span class="mw-history-undo"><a href="/w/index.php?title=Hinterzarten&amp;action=edit&amp;undoafter=64556690&amp;undo=64569839" title="Hinterzarten">entfernen</a></span>) </span> <small><span class='fr-hist-autoreviewed plainlinks'>[<a href="http://de.wikipedia.org/w/index.php?title=Hinterzarten&amp;stableid=64569839" class="external text" rel="nofollow">automatisch gesichtet</a>]</span></small></li> <li><span class='flaggedrevs-color-1'>(<a href="/w/index.php?title=Hinterzarten&amp;diff=64569839&amp;oldid=64556690" title="Hinterzarten">Aktuell</a>) (<a href="/w/index.php?title=Hinterzarten&amp;diff=64556690&amp;oldid=63484457" title="Hinterzarten">Vorherige</a>) <input type="radio" value="64556690" checked="checked" name="oldid" id="mw-oldid-64556690" /><input type="radio" value="64556690" 
</li>	*/

//iterate over the parts 
for($block_i = 1;$block_i<count($revision_html_blocks);$block_i++)
{
	//find the beginning of the a tag
	$start_pos_of_a = strpos($revision_html_blocks[$block_i], "<a"); 
	
	//find the closing sequence of the a tag
	$pos_of_closed_a = strpos($revision_html_blocks[$block_i], '<span class="mw-usertoollinks">'); 
	
	$length_between_both = $pos_of_closed_a - $start_pos_of_a;
	
	//extract the link from the current part like this one:
	$one_version = substr($revision_html_blocks[$block_i], $start_pos_of_a , $length_between_both);
	
	//echo "<h1>oneversion</h1>".$one_version;
	//result: <a href="/w/index.php?title=Hinterzarten&amp;oldid=147847125" title="Hinterzarten" class="mw-changeslist-date">23:32, 8 November 2015</a>â€Ž <span class='history-user'><a href="/w/index.php?title=Benutzer:Buchbibliothek&amp;action=edit&amp;redlink=1" class="new mw-userlink" title="Benutzer:Buchbibliothek (page does not exist)">Buchbibliothek</a>

	$useThis = false;
	if($mode == 'user' && stristr($one_version, ":$userName"))
	{
		$useThis = true;
	}
	else if($mode=='date')
	{
		$end_of_date = strpos($one_version, "</a>");
		$date_rev_string = strip_tags(substr($one_version, 0, $end_of_date));
		//echo $date_rev_string;
		
		if(	$datePageArr = date_parse($date_rev_string))
		{
			$datePage = mktime(	$datePageArr['hour'],
								$datePageArr['minute'],
								$datePageArr['second'],
								$datePageArr['month'],
								$datePageArr['day'],								
								$datePageArr['year']);
			if($datePage && $dateAfter>$datePage)
			{
				$useThis = true;
			}
		}
		
	}
	
	
	if($useThis==true)	
	{
		$pos_of_oldid = strpos($one_version, 'oldid=') + strlen('oldid=');
		$end_of_oldid = strpos($one_version, '"', $pos_of_oldid);
		$length_of_oldid = $end_of_oldid - $pos_of_oldid;
		//echo $pos_of_oldid . "-" . $end_of_oldid;
	
			
		$old_id= substr($one_version, $pos_of_oldid , $length_of_oldid );
		
		$redirect = "http://".$server."/w/index.php?title=".$articleenc."&type=revision&diff=new&oldid=".$old_id;
		echo '<html><head><meta http-equiv="cache-control" CONTENT="no-cache">';
		echo '<meta http-equiv="refresh" content="blabla; url='.$redirect.'"></head><body>redirecting to <a href="'.$redirect.'">diff</a> ...</body></html>';
		break;
	}	
	
	//echo $one_version.'<hr>';
}

function extract_link($haystack)
{
	$link_begin = strpos($haystack, "[[") + 2;
	$link_end = strpos($haystack, "]]");
	$link = substr($haystack, $link_begin, $link_end-$link_begin);
	return str_replace('_', ' ', $link);
}
	
function needle_in_cached_page($needle, $articletext)
{
	//echo "suche $needle in <small>$articletext</small>";
	if(stristr(strtolower($articletext), strtolower($needle)))
	{
		return true;
	}
	else
	{
		return false;
	}
}	
?>