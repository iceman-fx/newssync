<?php
/*
	Redaxo-Addon NewsSync
	Helperfunktionen
	v1.0.10
	by Falko Müller @ 2026
*/

class newssync_helper
{
	
    public function __construct()
    {
    }


	//Ajax-Inhalte holen
	public static function bindAjax($ep)
	{	$op = $ep->getSubject();
		$op = preg_replace('/(.*<\!-- ###AJAX### -->)(.*)(<\!-- ###\/AJAX### -->.*)/s', '$2', $op);
		
		return $op;
	}


	//SVG-Größe holen
	public static function getSvgDimensions($media)
	{   $return = array('width'=>'', 'height'=>'');

		if (!empty($media)):
			$mObj = rex_media::get($media);

			if ($mObj->getType() == 'image/svg+xml'):
				$xml = simplexml_load_file(rex_path::media($media));

				$viewBox = $xml['viewBox'] ? $xml['viewBox']->__toString() : 0;
				$viewBox = preg_split('/[\s,]+/', $viewBox);
				$width = (float) ($viewBox[2] - $viewBox[0] ?? 0);
				$height = (float) ($viewBox[3] - $viewBox[1] ?? 0);

				if (!$height && !$width) {
					$width = $xml['width'] ? $xml['width']->__toString() : 0;
					$height = $xml['height'] ? $xml['height']->__toString() : 0;
				}

				$return['width']    = $width;
				$return['height']   = $height;
			endif;
		endif;
		
		return $return;
	}


	//Timebuster
	public static function getTimebuster($filepath, $addfrontend=true)
	{	$return = $abspath = $filepath;
	
		$path = rex_path::frontend();
			$path = str_replace('\\', '/', $path);
		$abspath = ($addfrontend) ? str_replace('//', '/', $path.preg_replace('#^../#', '/', $filepath)) : $abspath;
		
		if (file_exists($abspath)):
			$stamp = filemtime($abspath);											//Letzte Änderungszeit
				$stamp = (empty($stamp)) ? md5_file($abspath) : $stamp;				//Dateihash
			
			$return = $filepath.'?v='.$stamp;
		endif;
		
		return $return;
	}


	//fix JSON-String
	public static function fixJsonString(string $str)
	{
		$str = trim( preg_replace('/,(\s*[}\]])/', '$1', $str) );					//überflüssige Kommata vor schließenden Klammern entfernen
		$str = trim( preg_replace('/,$/', '', $str) );								//überflüssige Kommata am Ende des Strings entfernen
		$str = preg_replace_callback('/([{,]\s*)([a-zA-Z0-9_]+)\s*:/', 				//doppelte Anführungszeichen um Keys einfügen, sofern nicht vorhanden
			function($matches) {
				return $matches[1] . '"' . $matches[2] . '":';
			}, $str);

		return $str;
	}
	

	//Inhalte aufbereiten
	public static function getMonthName($int)
	{	/*
		wandelt numerische Monatszahl in geschriebene Monatsnamen
		Aufruf:	$var = formbuilder_helper::getMonthName(5);
		*/
		$m[1] = 'Januar';
		$m[2] = 'Februar';
		$m[3] = 'März';
		$m[4] = 'April';
		$m[5] = 'Mai';
		$m[6] = 'Juni';
		$m[7] = 'Juli';
		$m[8] = 'August';
		$m[9] = 'September';
		$m[10] = 'Oktober';
		$m[11] = 'November';
		$m[12] = 'Dezember';
	
		$int = intval($int);
		return ($int > 0) ? $m[$int] : $int;
	}


	public static function fillNull($str = "", $stellen = 2)
	{	/*
		füllt den Wert mit führenden nullen auf
		Aufruf:	$var = formbuilder_helper::fillNull($str);
				$var = formbuilder_helper::fillNull($string, integer);
		*/
        $str = (string)$str;
		return (!empty($str) || $str == 0) ? str_pad($str, $stellen, 0, STR_PAD_LEFT) : $str;
	}


	public static function arrayString($str = "", $out = 'array', $del = '#')
	{	/*
		bereitet Array-String als Ausgabe oder Array auf
		Aufruf: $var = formbuilder_helper::arrayString($str);$
				$var = formbuilder_helper::arrayString($string|$array, '#', 'string|array');
		*/
		$op = array();
		
		if (!empty($str)):
			$out = ($out == 'string') ? 'string' : 'array';
			$del = (empty($del)) ? '#' : $del;
			
			$tmp = (is_array($str)) ? $str : explode($del, $str);
				foreach ($tmp as $val):
					if (!empty($val)):
						array_push($op, $val);
						continue;
					endif;
				endforeach;
		endif;
		
		return ($out == 'array') ? $op : implode(", ", $op);
	}


	//Zeichen & Tags maskieren/entwerten
	public static function maskChar($str)
	{	//maskiert folgende Sonderzeichen: & " < > '
		if (!empty($str)):
            if (is_array($str)):
                $str = self::maskArray($str);
            else:
                $str = stripslashes($str);
                $str = htmlspecialchars($str, ENT_QUOTES);
                $str = trim($str);
            endif;
		endif;
		
		return $str;
	}


	public static function maskArray($arr)
	{	//maskiert Array-Werte mit maskChar()
		if (is_array($arr)):
			$arr = array_map(function($str) { return self::maskChar($str); }, $arr);
		endif;
		
		return $arr;
	}


	public static function maskSingleQuote($str)
	{	//ersetzt Single-Quotes: '
		return (!empty($str)) ? str_replace("'", "&#039;", $str) : $str;
	}


	public static function maskDoubleQuote($str)
	{	//ersetzt Double-Quotes: "
		return (!empty($str)) ? str_replace('"', "&quot;", $str) : $str;
	}


	public static function maskSql($str)
	{	//maskiert den Wert für DB-Abfrage
		$s = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
    	$r = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");
		return (!empty($str)) ? str_replace($s, $r, $str) : $str;
	}


	public static function unmaskQuotes($str)
	{	//de-maskiert folgende Anführungszeichen: " '
		return (!empty($str)) ? str_replace(array("&quot;", "&#039;"), array('"', "'"), $str) : $str;
	}


	public static function revChar($str)
	{	//de-maskiert folgende Sonderzeichen: & " < > '
		if (!empty($str)):
			$chars = array("&amp;amp;quot;"=>'"', "&amp;quot;"=>'"', "&amp;"=>"&", "&lt;"=>"<", "&gt;"=>">", "&quot;"=>'"', "&#039;"=>"'");
			foreach ($chars as $key => $value):
				$str = str_replace($key, $value, $str);
			endforeach;
		endif;
		
		return $str;
	}


	public static function blockTags($str, $allowHtml = false)
	{	//entfernt bekannte Tags (PHP, JS, HTML)
		if ($str != ""):
			$str = stripslashes($str);
			$str = str_replace("\xc2\xa0", ' ', $str);														//&nbsp; ersetzen in normales WhiteSpace (UTF8)
			$str = (!$allowHtml) ? strip_tags($str, '<br>') : $str;											//HTML-Tags entfernen
			
				$phps = array("/<\?php/i", "/<\?/i", "/<%/i", "/<script language=\"php\">/i", "/<script language='php'>/i", "/\?>/i", "/%>/i");
					foreach ($phps as $key):
						$str = preg_replace($key, " ", $str);
					endforeach;
				$js = array("/<script.*>/i", "/<\/script>/i");
					foreach ($js as $key):
						$str = preg_replace($key, " ", $str);
					endforeach;
					
			$str = preg_replace("/\h+/", " ", $str);														//entfernt 1 oder mehrere horizontale Leerzeichen
			$str = trim($str);
		endif;
		
		return $str;
	}


	public static function sanitizeHTML($str, $cleanLinks = true)
	{	//String mit HTMLPurifier säubern
		//$str = self::blockTags($str, true);
		
		require_once rex_path::addon('newssync', 'vendor/htmlpurifier/library/HTMLPurifier.auto.php');
			$htmlPurifierConfig = HTMLPurifier_Config::createDefault();
			$htmlPurifierConfig->set('Core.Encoding', 'UTF-8');
			
			$htmlPurifierConfig->set('HTML.Allowed', 'p,br,b,strong,i,em,u,s,small,sub,sup,ul,ol,li,blockquote,pre,code,hr,span,font,div,h1,h2,h3,h4,h5,h6,a[href|title|target],img[src|alt|title|width|height|style],table,thead,tbody,tfoot,tr,td,th');
			$htmlPurifierConfig->set('HTML.AllowedAttributes', [
				'a.href','a.title','a.target',
				'span.style','div.style',
				'img.src','img.alt','img.title','img.width','img.height','img.style','img.float','img.border',
				'table.style','thead.style','tbody.style','tfoot.style','tr.style','td.style','th.style',
				'td.colspan','td.rowspan','th.colspan','th.rowspan'
			]);
			
			$htmlPurifierConfig->set('CSS.AllowedProperties', [
				'color', 'background-color', 'font', 'font-weight', 'font-style', 'text-decoration', 'text-align', 'vertical-align', 'margin',
				'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom',
				'border', 'border-width', 'border-style', 'border-color', 'width', 'height', 'float'
			]);
			$htmlPurifierConfig->set('CSS.Trusted', false);
			
			$htmlPurifierConfig->set('AutoFormat.AutoParagraph', true);
			$htmlPurifierConfig->set('AutoFormat.RemoveEmpty', true);
			$htmlPurifierConfig->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);

		$purifier = new HTMLPurifier($htmlPurifierConfig);
		$str = $purifier->purify($str);
		
		return $str;
	}


	public static function noQuote($str)
	{	//ersetzt Double-Quotes: "
		return (!empty($str)) ? str_replace('"', "'", $str) : $str;
	}


	public static function textOnly($str, $nobreak = true, $mask = false)
	{	/*
		entfernt HTML-Tags, Zeilenumbrüche und Tabstops
		Aufruf: textOnly($str);																			//gibt den String als Text zurück & Umbrüche wurden entfernt
				textOnly($str, false, true);															//entfernt keine Umbrüche, maskiert die Ausgabe zusätzlich
		*/        
		if ($str != ""):
			$str = stripslashes($str);
			$str = str_replace(array("\xc2\xa0", "&nbsp;"), " ", $str);									//&nbsp; ersetzen in normales WhiteSpace (UTF8, Entity)
			$str = str_replace("\t", ' ', $str);														//Tabstop (\t) ersetzen in normales WhiteSpace
            $str = preg_replace('/\h+/', " ", $str);                                                    //Mehrfache (horizontale) Leerzeichen entfernen
			$str = self::blockTags($str);																//Script-Tags entfernen
				
                //Zeilenumbrüche entfernen
				if ($nobreak):
					$str = nl2br($str);
					$str = str_replace(array("<br>", "<br/>", "<br />"), " ", $str);
					$str = strip_tags($str);
					$str = str_replace(array("\r\n", "\n\r", "\n", "\r"), " ", $str);
                else:
                    $str = str_replace(array("<br>", "<br/>", "<br />"), "\n", $str);
                    $str = strip_tags($str);
                    $str = str_replace(array("\r\n", "\n\r", "\n", "\r"), "\n", $str);                  //alle Arten von Umbrüchen auf \n reduzieren
                    $str = preg_replace("/\n\h+\n/i", "\n\n", $str);                                    //Umbruch-Leerzeichen-Umbruch ersetzen
                    $str = preg_replace("/\n{3,}/i", "\n\n", $str);                                     //3+ Umbrüche auf 2 reduzieren
				endif;
                
				//Zeichen maskieren
				$str = ($mask) ? self::maskChar($str) : $str;
                
            $str = preg_replace('/\h+/', " ", $str);                                                    //Mehrfache (horizontale) Leerzeichen entfernen
			$str = trim($str);
		endif;
        
		return $str;
	}


	public static function stripTags($str, $allowed_tags = array())
	{	/*
		entfernt Tags mit Aufbau: <...>
		Aufruf: formbuilder_helper::stripTags($str);
				formbuilder_helper::stripTags($str, array('h1','a'));
		*/
		$allowed_tags = array_map('strtolower', $allowed_tags);
	
		$rstr = preg_replace_callback('/<\/?([^>\s]+)[^>]*>/i', function ($matches) use (&$allowed_tags) {
			return in_array(strtolower($matches[1]), $allowed_tags) ? $matches[0] : '';
		}, $str);
		
		return $rstr;
	}


	public static function utf8urldecode($str) {
		$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($str));
		return html_entity_decode($str, null, 'UTF-8');
	}


	public static function subStr($str, $limit=100, $addDots = true, $isUtf8=true)
	{	//Texte kürzen inkl. Beachtung von HTML-Tags
		$ret = ""; $retLength = $position = 0;
		$tags = array();
		
		$re = ($isUtf8) ? '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;|[\x80-\xFF][\x80-\xBF]*}' : '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;}';
		
		while ($retLength < $limit && preg_match($re, $str, $match, PREG_OFFSET_CAPTURE, $position)):
			list($tag, $tagPosition) = $match[0];
		
			//print text leading up to the tag
			$tmp = substr($str, $position, $tagPosition - $position);
			
			if ($retLength + strlen($tmp) > $limit):
				$ret .= substr($tmp, 0, $limit - $retLength);
				$retLength = $limit;
				break;
			endif;
			
			$ret .= $tmp;
			$retLength += strlen($tmp);
			
			if ($retLength >= $limit) break;
			
			if ($tag[0] == '&' || ord($tag) >= 0x80):
				//pass the entity or UTF-8 multibyte sequence through unchanged
				$ret .= $tag;
				$retLength++;
			else:
				//handle the tag
				$tagName = $match[1][0];
				
				if ($tag[1] == '/'):
					//this is a closing tag
					$openingTag = array_pop($tags);
					assert($openingTag == $tagName); 																		//check that tags are properly nested		
					$ret .= $tag;
				elseif ($tag[strlen($tag) - 2] == '/'):
					//self-closing tag
					$ret .= $tag;
				else:
					//opening tag
					$ret .= $tag;
					$tags[] = $tagName;
				endif;
			endif;	
			
			$position = $tagPosition + strlen($tag);																		//continue after the tag
		endwhile;
		
		if ($retLength < $limit && $position < strlen($str)) { $ret .= substr($str, $position, $limit - $retLength); }		//print any remaining text
		if ($addDots) { $ret .= ' ...'; }																					//add ... to the text
		
		while (!empty($tags)) $ret .= '</'.array_pop($tags).'>';															//close any open tags
		
		return $ret;
	}
	
}
?>