<?php
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/category.php");
require_once(WWW_DIR."/lib/movie.php");
require_once(WWW_DIR."/lib/nfo.php");
require_once(WWW_DIR."/lib/namecleaning.php");
require_once(WWW_DIR."/lib/books.php");
require_once(WWW_DIR."/lib/predb.php");



class MiscSorter {

	// **********************************************************************
	function __construct($echooutput=false) {
		$this->qualities = array('(:?..)?tv', '480[ip]?', '640[ip]?', '720[ip]?', '1080[ip]?', 'ac3', 'audio_ts', 'avi', 'bd[\- ]?rip', 'bd25', 'bd50',
		'bdmv', 'blu ?ray', 'br[\- ]?disk', 'br[\- ]?rip', 'cam', 'cam[\- ]?rip', 'dc', 'directors.?cut', 'divx\d?', 'dts', 'dvd', 'dvd[\- ]?r',
		'dvd[\- ]?rip', 'dvd[\- ]?scr', 'extended', 'hd', 'hd[\- ]?tv', 'h264', 'hd[\- ]?cam', 'hd[\- ]?ts', 'iso', 'm2ts', 'mkv', 'mpeg(:?\-\d)?',
		'mpg', 'ntsc', 'pal', 'proper', 'ppv', 'ppv[\- ]?rip', 'r\d{1}', 'repack', 'repacked', 'scr', 'screener', 'tc', 'telecine', 'telesync', 'ts',
		'tv[\- ]?rip', 'unrated', 'vhs( ?rip)', 'video_ts', 'video ts', 'x264', 'xvid', 'web[\- ]?rip');

		$this->echooutput = $echooutput;
		$this->qty = 1000;
		$this->DEBUGGING = false;

		$this->db = new DB($this->echooutput);
		$this->cat = new Category($this->echooutput);
		$this->movie = new Movie($this->echooutput);
		$this->nfolib = new Nfo($this->echooutput);
		$this->nc = new nameCleaning();


		$res = $this->db->query("SET NAMES 'utf8'");
		$res = $this->db->query("SET CHARACTER SET 'utf8'");

		mb_internal_encoding("UTF-8");
		mb_regex_encoding("UTF-8");
		mb_http_output("UTF-8");
		mb_language("uni");
	}

	function getHash($name)
	{
		return false;
	}

	function doecho ($str = '', $type = '')
	{
		if ($this->echooutput && $str != '')
		{
			if ($this->DEBUGGING && $type == 'debug')
				echo "$str\n";
			elseif ($type != 'debug')
				echo "$str\n";
		}
	}

	function nfopos ($nfo, $str)
	{
		$nfo = preg_replace('/[ \t\_\.\?]/Ui', " ", $nfo);
		$nfo = preg_replace('/  +/', " ", $nfo);
		$nfo = preg_replace('/^\s+?/Umi', "", $nfo);

		$str = preg_replace('/[ \t\_\.\?]/Ui', " ", $str);
		$str = preg_replace('/  +/', " ", $str);
		$str = preg_replace('/^\s+?/Umi', "", $str);

		$pos = stripos($nfo, $str);
		if ($pos !== false)
			return $pos/strlen($nfo);
		else
			return false;
	}


	function getIDs ($cat)
	{
		if ($cat > 0)
			$cats = $this->cat->getChildren($cat);
		else
			$cats = $this->cat->get(true, array());
		$thecategory = array();
		foreach ($cats as $c)
			$thecategory[] = $c['ID'];

		$thecategory = implode(", ", $thecategory);
		$query = sprintf("SELECT ID FROM releases WHERE nfostatus = 1 AND passwordstatus >= 0 AND releases.categoryID IN ( %s ) ORDER BY RAND() limit %d", $thecategory, $this->qty);
		$res = $this->db->query($query);

		if (count($res) == 0)
			return false;

		$this->idarr = $res[0]['ID'];
		unset($res[0]);
		foreach($res as $r)
			$this->idarr = $this->idarr.", ".$r['ID'];

		if ($this->idarr == '')
			return false;

		return $this->idarr;
	}

	function doarray($matches)
	{
		$r = array();
		$i = 0;

		$matches = array_count_values($matches);
		$matches = array_change_key_case($matches, CASE_LOWER);

		foreach ($matches as $m=>$v)
		{
			$x = -1;

			if (strlen($m) < 50)
			{
				$str = preg_replace("/\s/iU", "", $m);

				$m = strtolower($str);

				$x = 0;
				if ($m == 'imdb') {
					$x = -11;
				} else if ($m == 'anidb.net') {
					$x = -10;
				} else if ($m == 'upc') {
					$x = -9;
				} else if ($m == 'amazon.') {
					$x = -8;
				} else if ($m == 'asin' || $m == 'isbn' ) {
					$x = -7;
				}  else if ($m == 'tvrage') {
					$x = -6;
				} else if ($m == 'audiobook') {
					$x = -5;
				}  else if ($m == 'os') {
					$x = -4;
				} else if ($m == 'mac' || $m == 'macintosh' || $m == 'dmg' || $m == 'macos' || $m == 'macosx' || $m == 'osx') {
					$x = -3;
				}  else if ($m == 'itunes.apple.com/') {
					$x = -2;
				}  else if ($m == 'documentaries' || $m == 'documentary' || $m == 'doku' ) {
					$x = -1;
				} else if (preg_match('/sport|deportes|nhl|nfl|\bnba/i', $m)) {
					$x = 1000;
				} else if (preg_match('/avi|xvid|divx|mkv/i', $m)) {
					$x = 1001;
				} else if (preg_match('/\.(?:rar|001)/i', $m)) {
					$x = 1002;
				} else if (preg_match('/pdf/i', $m)) {
					$x = 1003;
				}
			}

			if ($x == -1)
			{

			} elseif ($x == 0) {
				$r[$i++] = $m;
			} else if (isset($r[$x]))
			{
				$r[$x + rand(0,100)/100] = $m;
			} else {
				$r[$x] = $m;
			}
		}
		ksort($r);
		$r = array_values($r);
		return $r;
	}

	function cleanname($name)
	{
		if (is_array($name))
			return $name;

		do {
			$original = $name;
			$name = preg_replace("/[\{\[\(]\d+[ \.\-\/]+\d+[\]\}\)]/iU", " ", $name);
			$name = preg_replace("/[\x01-\x1f\!\?\[\{\}\]\/\:\|]+/iU", " ", $name);
			$name = preg_replace("/  +/iU", " ", $name);
			$name = preg_replace("/^[\s\.]+|[\s\.]{2,}$/iU", "", $name);
			$name = preg_replace("/ \- \- /iU", " - ", $name);
			$name = preg_replace("/^[\s\-\_\.]/iU", "", $name);
			$name = trim($name);
		} while ($original != $name);

		return $name;
	}

	function dodbupdate($id, $cat, $relname = 1, $name = '', $typeid = 0, $type='', $debug = '')
	{
		if ($debug == '')
			$debug = $this->DEBUGGING;

		$query = "UPDATE `releases` SET  `categoryID` = $cat";
		if ($relname == 1 || $relname == 4)
		{
			$query = $query.", relnamestatus = 4";
		if ($name != '')
		{
				$query = $query.", `searchname` = ".$this->db->escapeString($name);
		}
		}
		switch ($type) {
			case 'imdb':
				if ($typeid != 0)
				$query = $query.", `imdbID` = $typeid";
				break;
			case 'book':
				if ($typeid != 0)
				$query = $query.", `bookinfoID` = $typeid";
				break;
			case 'music':
				if ($typeid != 0)
				$query = $query.", `musicinfoID` = $typeid";
				break;
			case 'anime':
				if ($typeid != 0)
				$query = $query.", `anidbID` = $typeid";
				break;
			case 'tv':
				if ($typeid != 0)
				$query = $query.", `rageID` = $typeid";
				break;
			default:
				break;
		}

		$query = $query." where `ID` = $id";
		$this->doecho($query);
		if (!$debug)
		{
			if ($this->db->query($query) !== false)
				return true;
		} else {
			return true;
		}
		return false;
	}

	function doOS ($nfo, $id, $cat, $relname)
	{
		$ok = false;

		$nfo = preg_replace("/[^\x09-\x80]|\?/", "", $nfo);
		$nfo = preg_replace("/[\x01-\x09\x0e-\x20]/", " ", $nfo);

//		$pattern = '/(?<!fine[ \-]|release[ \-])(?:\btitle|\bname|release)\b(?![ \-]type|[ \-]info(?:rmation)?|[ \-]date|[ \-]name|notes)(?:[\-\:\.\}\[\s]+?)([a-z0-9\.\- \(\)\']+?)/Ui';
		$pattern = '/(?<!fine[ \-\.])(?:\btitle|\bname|release)\b(?![ \-\.]type|[ \-\.]info(?:rmation)?|[ \-\.]date|[ \-\.]name|[ \-\.]notes)(?:[\-\:\.\}\[\s]+?) ?([a-z0-9\.\- \(\)\']+?)/Ui';
		$set = preg_split($pattern,  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
		if (isset($set[1]))
			$pos = $this->nfopos ($nfo, $set[1]);
		else
			$pos = false;

		$pattern = '/(?:[\s\_\.\:\xb0-\x{3000}]{2,}|^)([a-z0-9].+v(?:er(?:sion)?)?[\.\s]*?\d+\.\d(?:\.\d+)?.+)(?:[\s\_\.\:\xb0-\x{3000}]{2,}|$)/Uui';
		$set1 = preg_split($pattern,  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		if (isset($set1[1]))
		{
			$pos1 = $this->nfopos ($nfo, $set1[1]);
		}
		else
			$pos1 = false;
		if ((isset($set1[1]) && $pos1 !== false && (real) $pos > (real) $pos1) || $pos === false)
		{
			$set = $set1;
			$pos = $pos1;
		}

		$pattern = '/(?:[\*\?\-\=\|\;\:\.\[\}\]\( \xb0-\x{ff}\?]+)([a-z0-9\&].+\s*\(c\).+)(?:\s\s\s|$|\.\.\.)/Uui';
		$set1 = preg_split($pattern,  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		if (isset($set1[1]))
			$pos1 = $this->nfopos ($nfo, $set1[1]);
		else
			$pos1 = false;
		if ((isset($set1[1]) && $pos1 !== false && (real) $pos > (real) $pos1) || $pos === false)
		{
			$set = $set1;
			$pos = $pos1;
		}

//var_dump($set);

		if (!isset($set[1]) || strlen($set[1]) < 3)
		{
			$pattern = '/(?:(?:presents?|p +r +e +s +e +n +t +s)(?:[^a-z0-9]+?))([a-z0-9 \.\-\_\']+?)/Ui';
			$set = preg_split($pattern,  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
			if (isset($set[1]) && preg_match('/(another)? *(fine)? *relase/i', $set[1]))
				$set = null;
		}

		if (isset($set[1]) && preg_match('/^(.+)(\(c\)|\xA9)/i', $this->cleanname($set[1]), $tmp))
			$set[1] = $tmp[1];

		if (isset($set[1]) && strlen($set[1]) < 128)
			$ok = $this->dodbupdate($id, $cat, $relname, $this->cleanname($set[1]));

		return $ok;

	}

	function moviename ($nfo, $imdb, $name)
	{
		$qual = array();
		foreach ($this->qualities as $quality)
		{
			if (preg_match("/(?<!\[ \] )(\b".$quality."\b)(?! \[ \])/i", $nfo, $match))
			{
				$qual[] =$match[1];
			}
		}

		$name = preg_replace("/[a-f0-9]{10,}/i", " ", $name);
		$name = preg_replace("/\\\\/i", " ", $name);
		$name = $this->nc->fixerCleaner($name);
		foreach ($qual as $key=>$quality)
		{
			if (preg_match("/$quality/i", $name))
			{
				unset($qual[$key]);
			}
		}

		$n = '';
		if (count($qual) > 0)
		{
			foreach ($qual as $quality)
			{
				$n = $n ." ".$quality;
			}
		}

		$name1 = $name;

		if ($imdb > 0)
		{
			$movie = $this->movie->getMovieInfo($imdb);
			foreach (explode(" ", $movie['title']." ".$movie['year']) as $word)
			{
				echo "word ".$word."\n";;
				$tmp = preg_split("/$word/i", $name1);
				$name2 = '';

				foreach ($tmp as $t)
				{
					$name2 = $name2." ".$t;
				}
				$name1 = $name2;
			}
		}
		$name1 = trim($name1);
		$name1 = preg_replace('/[ \-\_]{2,}/', ' ', $name1);
		$name1 = preg_replace('/ {2,}/', ' ', $name1);
		$name1 = preg_replace('/ /', ' ', $name1);
		if ($imdb > 0)
			$name = $movie['title']." (".$movie['year'].") ".$name1." ".$n."_";
		else
			$name = $name1." ".$n."_";
		return trim($name);

	}

	function doAmazon ($name, $id, $nfo = "", $q, $region = 'com', $case = false, $nfo ='', $row = '')
	{
		$s = new Sites();
		$site = $s->get();
		$amazon = new AmazonProductAPI($site->amazonpubkey, $site->amazonprivkey, $site->amazonassociatetag);
		$ok = false;
echo $case."\n";
		try {
			switch ($case)
			{
				case 'upc':
					$amaz = $amazon->getItemByUpc(trim($q), $region);
					break;
				case 'asin':
					$amaz = $amazon->getItemByAsin(trim($q), $region);
					break;
				case 'isbn':
					$amaz = $amazon->searchProducts(trim($q), '', "ISBN");
					break;
			}
		} catch (Exception $e) {
			echo 'Caught exception: ',  $e->getMessage(), "\n";
			unset($s);
			unset($amaz);
			unset($amazon);
			return $ok;
		}
		if (!isset($amaz->Items->Item))
			return $ok;

		$type = $amaz->Items->Item->ItemAttributes->ProductGroup;
		switch ($type)
		{
			case 'Book':
			case 'eBooks':
				$audiobook  = false;
				$v = (string) $amaz->Items->Item->ItemAttributes->Format;
					if (stripos($v, "audiobook") !== false)
						$audiobook = true;

				$new = (string) $amaz->Items->Item->ItemAttributes->Author;
				$new = $new . " - ". (string) $amaz->Items->Item->ItemAttributes->Title;
				$name = $this->nc->fixerCleaner($new);

				$query = "SELECT ID  FROM `bookinfo` WHERE `asin` = '".(string) $amaz->Items->Item->ASIN."'";
				$rel = $this->db->query($query);
				if (count($rel) == 0)
				{
					$book = new Books();
					$bookId = $book->updateBookInfo('', $amaz);
					unset($book);
				} else {
					$bookId = $rel[0]['ID'];
				}

				$query = "SELECT * FROM releases INNER JOIN releaseaudio ON releases.ID = releaseaudio.releaseID WHERE releases.ID = $id";
				$rel = $this->db->query($query);
				if (count($rel) > 0 || $audiobook)
				{
					$ok = $this->dodbupdate($id, Category::CAT_MUSIC_AUDIOBOOK, $row["relnamestatus"], $name, $bookId, 'book');
				} else {
					$ok = $this->dodbupdate($id, Category::CAT_BOOKS_EBOOK, $row["relnamestatus"], $name, $bookId, 'book');
				}
				unset($rel);
				break;

			case 'Digital Music Track':
			case 'Digital Music Album':
			case 'Music':
				$new = (string) $amaz->Items->Item->ItemAttributes->Artist;
				if ($new != '')
					$new = $new .  " - ";
				$new = $new .(string) $amaz->Items->Item->ItemAttributes->Title;
				$name = $this->nc->fixerCleaner($new);

				$query = "SELECT *  FROM `musicinfo` WHERE `asin` = '".(string) $amaz->Items->Item->ASIN."'";
				$rel = $this->db->query($query);
				if (count($rel) == 0)
				{
					$music = new Music();
//					$musicId = $music->updateMusicInfo('', '', $amaz)
					unset($music);
				} else {
					$musicId = $rel[0]['ID'];
				}

//				$ok = $this->dodbupdate($id, 3010, $row["relnamestatus"], $name, $musicId, 'music');
				break;

				case 'Movies':
				case 'DVD':
				$new = (string) $amaz->Items->Item->ItemAttributes->Title;
				$new = $new . " (" . substr((string) $amaz->Items->Item->ItemAttributes->ReleaseDate, 0, 4) . ")";
				$new = $this->moviename ($nfo, 0, $new);
				$name = $this->nc->fixerCleaner($new);
				$ok = $this->dodbupdate($id, Category::CAT_MOVIE_OTHER, $row["relnamestatus"], $name);

				break;

			default:
				echo "* * * * * * uncatched amazon category $type ".$name;
				break;
		}
	//echo  $query."\n";

		unset($s);
		unset($amaz);
		unset($amazon);
		return $ok;
	}

	function domusicfiles ($row)
	{
		$nzbcontents = new NZBcontents();

		$m3u = '';
		$alt = '';
		$mp3 = false;
		$mp3name = '';
		$files = 0;
		$extras = 0;

		$nzbfiles = $nzbcontents->nzblist($row['guid']);

		if ($nzbfiles)
		{
			$name = $row['name'];

			$name = preg_replace("/\//",' ', $name);

	//		echo "$name\n";

			$name = preg_quote($name);



			foreach($nzbfiles as $nzbsubject)
			{

				$sub = $nzbsubject['subject']."\n";

				if (preg_match('/^[a-f0-9]+$/i', $name))
				$sub = preg_replace("/$name/i",'', $sub);

//				echo "doing music file = $sub\n";

				if (preg_match('/\.(vol\d{1,3}?\+\d{1,3}?|par2|nfo\b|sfv|par\b|p\d{1,3}?|sv\b)/iU', $sub))
				{
					$extras++;
					$alt = preg_replace('/(\.vol\d{1,3}?\+\d{1,3}?|\.par2|\.[a-z][a-z0-9]{2})+?".+?/iU', '', $sub);
				}

				if (preg_match('/\.mp3|\.flac/', $sub, $matches))
				{
					$mp3name = preg_replace('/(\.mp3".+?)/iU', '.mp3', $sub);
					$mp3name = preg_replace('/(\.flac".+?)/iU', '.flac', $sub);
					$mp3name = preg_replace('/(?iU)^[^\"]+\"(0\d+?-?(00)??)??/iU', '', $mp3name);

					$mp3 = true;
					$files++;
				}

				if (preg_match('/\.m3u|\"00+[ \-\_\.]+?|\.nfo\b|\.sfv/iU', $sub, $matches))
				{
					if (preg_match('/\.url|playlist/iU', $sub))
					{
						continue;
					}
					$sub = preg_replace('/(\.vol\d{1,3}?\+\d{1,3}?|\.par2|\.[a-z][a-z0-9]{2})+?".+?/iU', '', $sub);

					$m3u = preg_replace('/(?iU)^[^\"]+\"(0\d+?-?(00)??)??/iU', '', $sub);
				}

			}
		}
		$name = '';
//		echo "results".(($m3u != '' || ($files + $extras) / count($nzbfiles) > 0.7) && $mp3)." mp3".$mp3." m3u".$m3u."\n";

		if ( count($nzbfiles) > 0)
		{

			if (($m3u != '' || (($files + $extras) / count($nzbfiles) > 0.7)) && $mp3)
			{
				$name = $m3u;

				if ($files == 1)
					$name = $mp3name;

				if (empty($name))
				{
					$name = $alt;
				}
				if ($row["relnamestatus"] > 1)
					$name = $row["searchname"];

//				echo $row['guid']." ".$name.' '.$mp3.' '.($files + $extras) / count($nzbfiles)."\n";

			}

//			echo (($m3u != '' || ($files + $extras) / count($nzbfiles) > 0.7) && $mp3)." ".$mp3." ".$m3u."\n";

		}
		$name = $this->cleanname($name);
		$name = preg_replace("/\.[a-z][a-z0-9]{2,3}($|\" yenc)/i", "", $name);
		$name = preg_replace("/^[a-f0-9]{10,}$/i", "", $name);


		unset($file);
		unset($nzbfiles);
		unset($nzbinfo);
		unset($nzb);

		return $name;
	}

	function matchnfo ($case, $nfo, $row)
	{
		$ok = false;

		switch (strtolower($case))
		{
			case 't r a c k':
			case 'track':
			case 'trax':
			case 'lame':
			case 'album':
			case 'music':
			case '44.1kHz':
			case 'm3u':
			case 'flac':
				if (preg_match('/(a\s?r\s?t\s?i\s?s\s?t|l\s?a\s?b\s?e\s?l|mp3|e\s?n\s?c\s?o\s?d\s?e\s?r|rip|stereo|mono|single charts)/i', $nfo))
				{
					if (!preg_match('/(\bavi\b|x\.?264|divx|mvk|xvid|install(?!ation)|Setup\.exe|unzip|unrar)/i', $nfo))
					{
						$artist = preg_split('/(?:a\s?r\s?t\s?i\s?s\s?ts?\b[^ \.\:]*) *?(?!(?:[^\s\.\:\}\]\*\xb0-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\xb0-\x{3000}\?]+?)[\s\.\>\:\(\)\xb0-\x{3000}\?]((?!\:) ?[a-z0-9\&].+)(?:\s\s\s|$|\.\.\.)/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
						$title = preg_split('/(?:t+\s?i+\s?t+\s?l+\s?e+\b|a\s?l\s?b\s?u\s?m\b) *?(?!(?:[^\s\.\:\}\]\*\xb0-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\xb0-\x{3000}\?]+?)[\s\.\>\:\(\)\xb0-\x{3000}\?]((?!\:) ?[a-z0-9\&].+)(?:\s\s\s|$|\.\.\.)/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
//var_dump($artist);
//var_dump($title);

						if (!isset($title[1]) || !isset($artist[1]))
						{
							if(preg_match('/presents[\W\. \xb0-\x{3000}]+? ([^\-]+?) \- ([a-z0-9]?(?!\:).+(?:\s\s\s))/iuUm', $nfo, $matches))
							{
								$artist[1] = $matches[1];
								$title[1] = $matches[2];
							}
							if (!isset($matches[2]) && preg_match('/[\h\_\.\:\xb0-\x{3000}]{2,}?([a-z].+) \- (.+?)(?:[\?\s\_\.\:\xb0-\x{3000}]{2,}|$)/Uiu', $nfo, $matches))
							{

								$pos = $this->nfopos($nfo, $matches[1]." - ".$matches[2]);
								if ($pos !== false && $pos < 0.45 && !preg_match('/\:\d\d$/', $matches[2]) && strlen($matches[1]) < 48 && strlen($matches[2]) < 64)
								{
									if (!preg_match('/title/i', $matches[1]) && !preg_match('/title/i', $matches[2]))
									{
										$artist[1] = $matches[1];
										$title[1] = $matches[2];
									}
								}
							}
						}
						if (isset($artist[1]) && $artist[1] == " ")
							$artist[1] = $artist[3];

						if (isset($title[1]) && isset($artist[1]))
						{
							$ok = $this->dodbupdate($row['ID'], Category::CAT_MUSIC_MP3, $row["relnamestatus"], $this->cleanname($artist[1])." - ".$this->cleanname($title[1]));
						}
					}
				}
				break;
			case 'dmg':
			case 'mac':
			case 'macintosh':
			case 'macos':
			case 'macosx':
			case 'osx':
				$ok = $this-> doOS($nfo, $row['ID'], Category::CAT_PC_MAC, $row["relnamestatus"]);
				break;

			case 'windows':
			case 'win':
			case 'winall':
			case 'winxp':
			case 'plugin':
			case 'crack':
			case 'linux':
			case 'install':
			case 'application':
				$ok = $this-> doOS($nfo, $row['ID'], Category::CAT_PC_0DAY, $row["relnamestatus"]);
				break;

			case 'android':
				$ok = $this-> doOS($nfo, $row['ID'], Category::CAT_PC_PHONE_ANDROID, $row["relnamestatus"]);
				break;

			case 'ios':
			case 'iphone':
			case 'ipad':
			case 'ipod':
				$ok = $this-> doOS($nfo, $row['ID'], Category::CAT_PC_PHONE_IOS, $row["relnamestatus"]);
				break;

			case 'game':
					$set = preg_split('/\>(.*)\</Ui',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

					if (isset($set[1]))
						$ok = $this->dodbupdate($row['ID'], Category::CAT_PC_GAMES, $row["relnamestatus"], $this->cleanname($set[1]));
					else
						$ok = $this-> doOS($nfo, $row['ID'], Category::CAT_PC_GAMES, $row["relnamestatus"]);
				break;

			case 'imdb':
				$imdb = $this->movie->domovieupdate($nfo, "sorter",  $row['ID'], $this->db);
				if ($imdb !== false)
				{
					$movie = $this->movie->getMovieInfo($imdb);
					$name = $this->moviename($nfo, $imdb, $row['searchname']);

					if ($movie !== false)
					{
						if (preg_match('/sport/iU', $movie['genre']))
							$cat = Category::CAT_TV_SPORT;
						elseif (preg_match('/docu/iU', $movie['genre']))
							$cat = Category::CAT_TV_DOCUMENTARY;
						elseif (preg_match('/talk\-show/iU', $movie['genre']))
							$cat = Category::CAT_TV_OTHER;
						elseif (preg_match('/tv/iU', $movie['type']) || preg_match('/episode/iU', $movie['type']) || preg_match('/reality/iU', $movie['type']))
							$cat = Category::CAT_TV_OTHER;
						else
						{
							$cat = $this->cat->determineCategory($name, $row['groupID']);
							if ($cat == Category::CAT_MISC)
								$cat = Category::CAT_MOVIE_OTHER;
						}
					} else
						$cat = $this->cat->determineCategory($name, $row['groupID']);

					if ($cat < Category::CAT_PARENT_GAME || $cat > Category::CAT_PARENT_BOOKS + 1000)
						$cat = Category::CAT_MOVIE_OTHER;

					$ok = $this->dodbupdate($row['ID'], $cat, $row["relnamestatus"], $name, $imdb, 'imdb');
				}
				break;
			case 'audiobook':
			case 'audible':
			case 'recordedbooks':
			case 'spokenbook':
			case 'readby':
			case 'narratedby':
			case 'narrator':
			case 'speech':
				$author = preg_split('/(?:a\s?u\s?t\s?h\s?o\s?r\b)+? *?(?!(?:[^\s\.\:\}\]\*\xb0-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\xb0-\x{3000}\?]+?)[\s\.\>\:\(\)]((?!\:) ?[a-z0-9\&].+)(?:\s\s\s|$|\.\.\.)/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
				$title = preg_split('/(?:t\s?i\s?t\s?l\s?e\b|b\s?o\s?o\s?k\b)+? *?(?!(?:[^\s\.\:\}\]\*\xb0-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\xb0-\x{3000}\?]+?)[\s\.\>\:\(\)]((?!\:) ?[a-z0-9\&].+)(?:\s\s\s|$|\.\.\.)/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
//var_dump($author);
//var_dump($title);
				if (isset($author[1]) && isset($title[1]))
					$ok = $this->dodbupdate($row['ID'], Category::CAT_MUSIC_AUDIOBOOK, $row["relnamestatus"], $this->cleanname($author[1]." - ".$title[1]));
				elseif (preg_match('/[\h\_\.\:\xb0-\x{3000}]{2,}?([a-z].+) \- (.+)(?:[\s\_\.\:\xb0-\x{3000}]{2,}|$)/iu', $nfo, $matches))
				{
					$pos = $this->nfopos($nfo, $matches[1]." - ".$matches[2]);
					if ($pos !== false && $pos < 0.4 && !preg_match('/\:\d\d$/', $matches[2]) && strlen($matches[1]) < 48 && strlen($matches[2]) < 48)
						if (!preg_match('/title/i', $matches[1]) && !preg_match('/title/i', $matches[2]))
							$ok = $this->dodbupdate($row['ID'], Category::CAT_MUSIC_AUDIOBOOK, $row["relnamestatus"], $this->cleanname($matches[1]." - ".$matches[2]));
				}

				break;

			case 'comicbook':
			case 'comix':
				$ok = $this->dodbupdate($row['ID'], Category::CAT_BOOKS_COMICS, $row["relnamestatus"], '');
				break;

			case 'avi':
			case 'dvd':
			case 'h264':
			case 'mkv':
			case 'movie':
			case 'x264':
			case 'xvid':
				break;

			case 'tvrage':
				break;

			case 'hdtv':
			case 'tvseries':
				break;

			case "asin":
			case "isbn":
			case "amazon.":
			case "upc":
				$ok = false;
				if ($case == 'asin' || $case == 'isbn')
				{
					preg_match('/(?:isbn|asin)[ \:\.=]*? *?([a-zA-Z0-9\-\.]{8,20}?)/iU', $nfo, $set);
//var_dump($set);
					if (isset($set[1]))
					{
						$set[1] = preg_replace('/[\-\.]/', '', $set[1]);
echo "asin ".$set[1]."\n";
						if (strlen($set[1])>13)
							break;
						if (isset($set[1]))
						{
							$set[2] = $set[1];
							$set[1] = "com";
						}
					}
				} else if ($case == 'amazon.') {
					preg_match('/amazon\.([a-z]*?\.?[a-z]{2,3}?)\/.*\/dp\/([a-zA-Z0-9]{8,10}?)/iU', $nfo, $set);
					$case = 'asin';
				} else if ($case == 'upc') {
					preg_match('/UPC\:?? *?([a-zA-Z0-9]*?)/iU', $nfo, $set);
						if (isset($set[1]))
						{
							$set[2] = $set[1];
							$set[1] = "All";
						}
				} else {
					echo "* * * * * error in amazon";
					break;
				}

				if (count($set) > 1)
				{
					//var_dump($set);
 					$ok = $this->doAmazon ($row['name'], $row['ID'], $nfo, $set[2], $set[1], $case, $nfo, $row);
				}
				break;




		}
		return $ok;
	}


	function nfosorter ($category = Category::CAT_PARENT_MISC, $id = 0)
	{
		$this->idarr = $this->getIDs ($category);
		if ($id != 0)
			$this->idarr = $id;
		else
		{
			$pb = new Predb(true);
			echo $pb->parseTitles(0, 0, 0, 0, '')."\n";
			echo $pb->matchNfo()."\n";
			echo $pb->matchPredb()."\n";
			unset($pb);
		}

		$query = "SELECT uncompress(releasenfo.nfo) AS nfo, releases.ID, releases.guid, releases.`fromname`, releases.`name`,
	releases.searchname, groups.`name` AS gname, releases.groupID, releases.relnamestatus FROM releasenfo INNER JOIN releases ON releasenfo.releaseID =
	releases.ID INNER JOIN groups ON releases.groupID = groups.ID WHERE releases.ID in ($this->idarr) order by RAND()";

		$res = $this->db->queryDirect($query);
		if (strlen($this->idarr) > 0)
			while ($row =  $this->db->fetchAssoc($res))
			{
				$hash = $this->getHash($row['name']);
				if ($hash !== false)
					$row['searchname'] = $hash;

				$nfo = utf8_decode($row['nfo']);

				if (strlen($nfo) > 100)
				{
					$pattern = '/.+(\.rar|\.001) [0-9a-f]{6,10}?|(imdb)\.[a-z0-9\.\_\-\/]+?(?:tt|\?)\d+?\/?|(tvrage)\.com\/|(\bASIN)|(isbn)|(UPC\b)|(comic book)|(comix)|(tv series)|(\bos\b)|(documentaries)|(documentary)|(doku)|(macintosh)|(dmg)|(mac[ _\.\-]??os[ _\.\-]??x??)|(\bos\b\s??x??)|(\bosx\b)';
					$pattern = $pattern . '|(\bios\b)|(iphone)|(ipad)|(ipod)|(pdtv)|(hdtv)|(video streams)|(movie)|(audiobook)|(audible)|(recorded books)|(spoken book)|(speech)|(read by)\:?|(narrator)\:?|(narrated by)';
					$pattern = $pattern . '|(dvd)|(ntsc)|(m4v)|(mov\b)|(avi\b)|(xvid)|(divx)|(mkv)|(amazon\.)[a-z]{2,3}.*\/dp\/|(anidb.net).*aid=|(\blame\b)|(\btrack)|(trax)|(t r a c k)|(music)|(44.1kHz)|video (game)|type:(game)|(game) Type|(game)[ \.]+|(platform)|(console)|\b(win(?:dows|all|xp)\b)|(\bwin\b)';
					$pattern = $pattern . '|(m3u)|(flac\b)|(?<!writing )(application)(?! util)|(plugin)|(\bcrack\b)|(install\b)|(setup)|(magazin)|(x264)|(h264)|(itunes\.apple\.com\/)|(sport)|(deportes)|(nhl)|(nfl)|(\bnba)|(ncaa)|(album)|(\bepub\b)|(mobi)|format\W+?[^\r]*(pdf)/iU';

					$matches = preg_split($pattern, $nfo, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);

					array_shift($matches);

					$matches = $this->doarray($matches);

					foreach($matches as $m)
					{
						if (isset($m))
							$case = preg_replace('/ /', '', $m);
						else
							$case = '';

						if (($m == 'os' || $m == 'platform' || $m == 'console') && preg_match('/(?:\bos\b(?: type)??|platform|console)[ \.\:\}]+(\w+?).??(\w*?)/iU', $nfo, $set))
						{

							if (isset($set[1]))
							{
				//	var_dump($set);
								$case = strtolower($set[1]);
							}
							if (strlen($set[2]) && (stripos($set[2], 'mac') !== false || stripos($set[2], 'osx') !== false))
							{
								$case = strtolower($set[2]);
							}
						}

						$pos = $this->nfopos ($nfo, $m);
						echo "$case $pos ".$row['guid']."\n";

						if ($pos !== false && $pos > 0.55 && $case != 'imdb')
							continue;

						if ($this->matchnfo($case, $nfo, $row))
							continue(2);
					}
				}
			}
	}

	function musicnzb($id = '')
	{
		if (!empty($id))
			$query = "SELECT releases.*, g.`name` AS gname FROM releases INNER JOIN groups g ON releases.groupID = g.ID WHERE releases.ID = ($id)"; // AND NOT (`imdbID` > 1 OR `rageID` > 1 OR `musicinfoID` is not null OR `consoleinfoID` is not null OR `bookinfoID` is not null )";
		else
			$query = "SELECT releases.*, g.`name` AS gname FROM releases INNER JOIN groups g ON releases.groupID = g.ID where categoryID = ".Category::CAT_MISC."  and nfostatus >= 0 AND passwordstatus >= 0 AND not (`imdbID` is not null OR `rageID` > 0 OR `consoleinfoID` is not null OR `bookinfoID` is not null)";

		$res = $this->db->queryDirect($query);
echo "$query\n";
		echo "doing nzb music files match\n";
		while ($row =  $this->db->fetchAssoc($res))
		{
			$hash = $this->getHash($row['name']);
			if ($hash !== false)
				$row['searchname'] = $hash;

			$frommail = $row['fromname'];

		//	trigger_error("doing part 2".$row['ID']);
			$query = "SELECT releasevideo.releaseID FROM releasevideo WHERE releasevideo.releaseID = ".$row['ID'];
			$rel = $this->db->queryOneRow($query);

			if ($rel !== false)
				continue;

//			echo "\n".$row['guid']."\n";

			$query = 'SELECT releasenfo.releaseID, uncompress(releasenfo.nfo) AS nfo FROM releasenfo WHERE releasenfo.releaseID = '.$row['ID'];
			$rel = $this->db->queryOneRow($query);

			$nfo = '';
			if ($rel !== false)
				if ($rel['releaseID'] == $row['ID'])
					$nfo = $rel['nfo'];

			$name = $this->domusicfiles($row);
			if ($name !=' ' && $name !='')
				$ok = $this->dodbupdate($row['ID'], Category::CAT_MUSIC_MP3, $row["relnamestatus"], $name);

		}

	}
}

