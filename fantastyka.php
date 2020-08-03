<?php

// (c) 02.2020, 07.2020 by Marcin Wiącek mwiacek.com
// Formatted with phpcbf
//
// skrypt pobiera pliki z fantastyka.pl
// i tworzy plik epub we wskazanym katalogu.
//
// wymagany plik cover.jpg w aktualnym katalogu
// i komendy zip, cd i mv

// ------ CONFIG -----------
$path = "/tmp";
$set = 1; //1 = biblioteka, 2 = poczekalnia (bez tekstów w bibliotece)
        //3 = archiwum (bez tekstów w bibliotece)
        //4 = kolejka (wymaga parametrów logowania; niesprawdzone)
        //5 = konkurs (wymaga ID konkursu)
$log = false;
$allPages = true;
$allowResume = true; // when true, doesn't download pages when they exist on disk (we check for .xhtml only)
$downloadArticles = true;
$userNumber = ""; // profile number for user; empty means all users
$downloadImages = false; // valid only when $downloadArticles = true; when false replacing <img> with <a>
                            // TODO: pobieranie obrazków z innych serwerów niż fantastyka.pl

$startPage = 1; // used when $allPages = false
$endPage = 39; // used when $allPages = false
$downloadOnlyFew = false; // download only 5 articles when $downloadArticles=true; useful for script testing
$konkursID = 165;

// -------------------------

if ($set == 1) {
    $word = "biblioteka";
} else if ($set == 2) {
    $word = "poczekalnia";
} else if ($set == 3) {
    $word = "archiwum";
} else if ($set == 4) {
    $word = "kolejka";

    echo "Please enter user: ";
    $handle = fopen("php://stdin", "r");
    $user = trim(fgets($handle));
    fclose($handle);

    echo "Please enter password: ";
    $handle = fopen("php://stdin", "r");
    $password = trim(fgets($handle));
    $password = urlencode($password);
    fclose($handle);

    $file = file_get_contents("https://www.fantastyka.pl/");
    //    var_dump($http_response_header);
    $form_build_id=findBetween($file, "name=\"_csrf_token\" value=\"", "", "\"");

    $cookie = "";
    foreach ($http_response_header as &$value) {
        if (strpos($value, "Set-Cookie: ")===false) {
            continue;
        }
        $cookie = findBetween($value, "Set-Cookie: ", "", "; ");
        break;
    }

    $options = array(
    'http'=>array(
        'method'=>"POST",
            'header'=>"Content-Type: application/x-www-form-urlencoded\r\n".
                "Cookie: $cookie\r\n",
            'content'=>"_csrf_token=$form_build_id&".
            "_remember_me=on&".
                "_username=$user&".
                "_password=$password&".
                "_submit="
        )
    );

    $context = stream_context_create($options);
    $file = file_get_contents("https://www.fantastyka.pl/login_check", false, $context);
    //    var_dump($http_response_header);

    $cookie = "";
    $second = false;
    foreach ($http_response_header as &$value) {
        if (strpos($value, "Set-Cookie: ")===false) {
            continue;
        }
        if ($cookie != "") {
            $cookie = $cookie."; ";
            $second = true;
        }
        $cookie = $cookie.findBetween($value, "Set-Cookie: ", "", "; ");
    }

    if (!$second) {
        echo "Sorry Gregory, wrong credentials\n";
        exit;
    }

    $options = array(
    'http'=>array(
            'method'=>"GET",
            'header'=>"Cookie: $cookie\r\n"
    )
    );
    $context = stream_context_create($options);
} else if ($set == 5) {
    $word = "konkurs";
} else {
    echo("Unknown set!\n");
    exit;
}
if ($allPages) {
    $startPage = 1;
}

$tocContentOpf1="";
$tocContentOpf2="";
$tocContentOpf3="";
$tocTocNCX="";
$tocTocXHTML="";

function findNext($text, $start)
{
    $f2 = strstr($text, $start);
    return substr($f2, strlen($start));
}

function findBetween($text, $start, $start2, $end)
{
    $f2 = findNext($text, $start);
    if ($start2!="") {
        $f2 = findNext($f2, $start2);
    }
    return strstr($f2, $end, true);
}

function addAuthorInfo($author, $authorID, $articleID, $articleName)
{
    global $path;

    if (!file_exists("$path/OEBPS/authors/$authorID.xhtml")) {
        $txt="<?xml version=\"1.0\" encoding=\"UTF-8\"?>".
        "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n".
        "<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
        "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
        "xml:lang=\"pl\" lang=\"pl\">\n".
        "<head>\n".
        "<link rel=\"stylesheet\" href=\"../style.css\" type=\"text/css\" />\n".
        "<meta charset=\"utf-8\" /></head><body xml:lang=\"pl\" lang=\"pl\"><h1>Autor $author</h1><ul>\n";
        file_put_contents("$path/OEBPS/authors/$authorID.xhtml", $txt);
    }
    file_put_contents("$path/OEBPS/authors/$authorID.xhtml", "<li><a href=\"../$articleID.xhtml\">$articleName</a></li>\n", FILE_APPEND);
}

function addKonkursInfo($konkurs, $konkursID, $articleID, $articleName)
{
    global $path;

    if (!file_exists("$path/OEBPS/konkurs/$konkursID.xhtml")) {
        $txt="<?xml version=\"1.0\" encoding=\"UTF-8\"?>".
        "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n".
        "<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
        "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
        "xml:lang=\"pl\" lang=\"pl\">\n".
        "<head>\n".
        "<link rel=\"stylesheet\" href=\"../style.css\" type=\"text/css\" />\n".
        "<meta charset=\"utf-8\" /></head><body xml:lang=\"pl\" lang=\"pl\"><h1>Konkurs $konkurs</h1><ul>\n";
        file_put_contents("$path/OEBPS/konkurs/$konkursID.xhtml", $txt);
    }
    file_put_contents("$path/OEBPS/konkurs/$konkursID.xhtml", "<li><a href=\"../$articleID.xhtml\">$articleName</a></li>\n", FILE_APPEND);
}

function processArticle($id,$title,$num, $author0, $authorID0, $konkurs, $konkursID)
{
    global $path, $downloadImages, $tocContentOpf1, $allowResume, $log, $set, $context;

    if ($allowResume && file_exists("$path/OEBPS/$id.xhtml")) { 
        $f=file_get_contents("$path/OEBPS/$id.xhtml", false, $context);
        if (strstr($f, "<body xml:lang=\"pl\" lang=\"pl\">\nAutor: $author0<br />")) {
            $f = str_replace(
                "<body xml:lang=\"pl\" lang=\"pl\">\nAutor: $author0<br />", 
                "<body xml:lang=\"pl\" lang=\"pl\">\nAutor: <a href=\"authors/$authorID0.xhtml\">$author0</a><br />", $f
            );
            file_put_contents("$path/OEBPS/$id.xhtml", $f);
        }
        if ($konkurs != "") {
            $tagi = strstr($f, "\nTagi: ");
            $tagi = strstr($tagi, "<br />", true);
            if (stristr($tagi, " $konkurs")) {
                $tagi2 = str_ireplace(
                    " $konkurs",
                    " <a href=\"konkurs/$konkursID.xhtml\">$konkurs</a>", $tagi
                );
                $f = str_replace($tagi, $tagi2, $f);

                file_put_contents("$path/OEBPS/$id.xhtml", $f);
            }
        }
        return;
    }

    if ($set == 4) {
        $f=file_get_contents("https://www.fantastyka.pl/opowiadania/pokaz/".$id, false, $context);
    } else {
        $f=file_get_contents("https://www.fantastyka.pl/opowiadania/pokaz/".$id);
    }

    $descriptionOrHr = "<hr>".trim(findBetween($f, "<div class=\"clear linia\" style=\"margin-top: 1px;\"></div>", "", "</div>"));
    if ($descriptionOrHr!="<hr>") { $descriptionOrHr=$descriptionOrHr."<hr>";
    }

    $author = findBetween(
        $f, "<p class=\"naglowek-kom\"><a class=\"login\" href=\"/profil/", ">", "<"
    );
    $author = str_replace("&", "&amp;", $author);
    $authorID = findBetween($f, "<p class=\"naglowek-kom\"><a class=\"login\" href=\"/profil/", "", "\"");

    $info = findBetween($f, "<p class=\"data\">", "", "<");

    if ($log) { file_put_contents("$path/log", "before tags\n", FILE_APPEND);
    }
    $tags = "";
    $f2 = $f;
    while (true) {
        $t=" class=\"znajomy\">";
        if (strstr($f2, $t)) {
            $f2 = findNext($f2, $t);
            $tags = $tags.", ".trim(strstr($f2, "</", true));
            continue;
        }
        break;
    }
    $f2 = $f;
    while (true) {
        $t=" class=\"redakcja\">";
        if (strstr($f2, $t)) {
            $f2 = findNext($f2, $t);
            $tags = $tags.", ".trim(strstr($f2, "</", true));
            continue;
        }
        break;
    }
    $f2 = $f;
    while (true) {
        $t="<a href=\"/opowiadania/tag/s/";
        if (strstr($f2, $t)) {
            $f2 = findNext($f2, $t);
            $f2 = findNext($f2, ">");
            $tags = $tags.", ".trim(strstr($f2, "</a>", true));
            continue;
        }
        break;
    }
    if (strstr($f, "<img src=\"/images/srebro.png\" class=\"piorko\" />")) { $tags = $tags.", <b>Srebrne PIÓRKO</b>";
    }
    if (strstr($f, "<img src=\"/images/zloto.png\" class=\"piorko\" />")) { $tags = $tags.", <b>ZŁOTE PIÓRKO</b>";
    }
    if ($tags!="") { $tags="Tagi: $tags<br>\n";
    }
    $tags = str_replace("&", "&amp;", $tags);
    if ($konkurs != "") {
        $tags = str_ireplace(
            " $konkurs",
            " <a href=\"konkurs/$konkursID.xhtml\">$konkurs</a>", $tags
        );
    }
    if ($log) { file_put_contents("$path/log", "after tags\n", FILE_APPEND);
    }

    $txt = findBetween(
        $f, "<section class=\"opko no-headline\">", "<article>", "</article>"
    );

    if ($downloadImages) {
        $f2 = $txt;
        while (true) {
            $t = "src=\"http://www.fantastyka.pl/upload/";
            if (!strstr($f2, $t)) { break;
            }
            $f2 = strstr($f2, $t);
            $f2 = substr($f2, 5);
            $url = strstr($f2, "\"", true);
            if ($log) {            file_put_contents("$path/log", "image $url\n", FILE_APPEND);
            }
            $f3=file_get_contents(str_replace(" ", "%20", $url));
            $tmp=explode("/", "$url");
            $localfile = end($tmp);
            echo "localfile is $url $localfile\n";
            file_put_contents("$path/OEBPS/$localfile", $f3);
            $tocContentOpf1=$tocContentOpf1."<item id=\"$localfile\" media-type=\"image/jpeg\" href=\"$localfile\" properties=\"image\" />\n";
        }
        $txt = str_replace("\"http://www.fantastyka.pl/upload/", "\"", $txt);
    } else {
        $txt = preg_replace('/<img (.*?) \/>/', " <a \\1>Obrazek</a> ", $txt);
        $txt = preg_replace('/src=\"(.*?)\"/', "href=\"\\1\"", $txt);
    }


    $txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n".
    "<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
    "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
    "xml:lang=\"pl\" lang=\"pl\">\n".
    "<head>\n".
    "<title>$title</title>\n".
    "<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />\n".
    "</head>\n".
    "<body xml:lang=\"pl\" lang=\"pl\">\n".
    "Autor: <a href=\"authors/$authorID.xhtml\">$author</a><br>\n".
    "$tags".
    "Info: $info\n".
    "$descriptionOrHr\n".
    trim($txt).
    "\n".
    "</body>\n</html>";

    $txt = str_replace("<br>", "<br />", $txt);
    $txt = str_replace("<hr>", "<hr />", $txt);
    $txt = preg_replace('/<p(.*?)>/', "<p\\1 />", $txt);
    $txt = str_replace("</p>", "", $txt);
    $txt = str_replace("<p />&nbsp; <p />", "<p />", $txt);
    $txt = str_replace("<p /><p />", "<p />", $txt);
    $txt = str_replace("<p /><p />", "<p />", $txt);
    $txt = str_replace("<br /><br />", "<br />", $txt);
    $txt = str_replace("<hr /><p />", "<hr />", $txt);
    $txt = str_replace("&nbsp;", " ", $txt);
    $txt = str_replace("\t\t", "\t", $txt);
    $txt = str_replace("  ", " ", $txt);
    $txt = str_replace(" <p ", "<p ", $txt);
    $txt = str_replace("<p /> <hr />", "<hr />", $txt);
    $txt = str_replace("&oacute;", "ó", $txt);
    $txt = str_replace("\n\t<span class=\"koniec\">Koniec</span>", "", $txt);
    $txt = str_replace("Tagi: , ", "Tagi: ", $txt);

    file_put_contents("$path/OEBPS/$id.xhtml", $txt);
}

$path = $path."/".$word;

if (!$allowResume && file_exists("$path")) {
    echo("Directory $path exists! Delete it first!\n");
    exit;
}
mkdir("$path", 0700);

mkdir("$path/OEBPS", 0700);
mkdir("$path/META-INF", 0700);
exec("rm -r $path/OEBPS/authors");
mkdir("$path/OEBPS/authors", 0700);
exec("rm -r $path/OEBPS/konkurs");
mkdir("$path/OEBPS/konkurs", 0700);

$num=1;
$pagenum=$startPage;
if ($log) { file_put_contents("$path/log", "start");
}
while (true) {
    if ($set == 4) {
        if ($pagenum==1) {
            $f=file_get_contents("https://www.fantastyka.pl/opowiadania/wszystkie/w/w/$word/0/d", false, $context);
        } else {
            //wild guess
            $f=file_get_contents("https://www.fantastyka.pl/opowiadania/wszystkie/w/w/$word/0/d/$pagenum", false, $context);
        }
    } else if ($set == 5) {
        $f=file_get_contents("https://www.fantastyka.pl/opowiadania/konkursy/$konkursID", false, $context);        
    } else {
        if ($pagenum==1) {
            $f=file_get_contents("https://www.fantastyka.pl/opowiadania/$word");
        } else {
            if ($set == 3) {
                $f=file_get_contents("https://www.fantastyka.pl/opowiadania/$word/d/$pagenum");
            } else {
                $f=file_get_contents("https://www.fantastyka.pl/opowiadania/$word/w/w/w/0/d/$pagenum");
            }
        }
    }
    echo "reading page $pagenum from $word\n";
    if ($log) {    file_put_contents("$path/log", "reading page $pagenum from $word\n", FILE_APPEND);
    }
    $f2 = $f;
    while (true) {
        $t = "<div class=\"autor\"><a href=\"/profil/";
        if (!strstr($f2, $t)) { break;
        }
        $f2 = findNext($f2, $t);
        $userId = strstr($f2, "\"", true);
        if ($userNumber!="" && strcmp($userNumber, $userId)) { continue;
        }
        $user = findNext($f2, "\">");
        $user = strstr($user, ":</a>", true);
        $user = str_replace("&nbsp;", " ", $user);

        $t = "><a href=\"/opowiadania/pokaz/";
        if (!strstr($f2, $t)) { break;
        }
        $f2 = findNext($f2, $t);
        $id = strstr($f2, "\"", true);

        if ($id != "10823" && $id != "8313") {
            echo "id is ".$id;
            //            echo "user id is $userId id is ".$id;
            if ($log) {            file_put_contents("$path/log", "id is $id\n", FILE_APPEND);
            }

            //            if ($set == 3) {
                $params = strstr($f2, "<div class=\"clear linia\"></div>", true);
            //            }

            $f2 = findNext($f2, ">");
            if ($id == "56842934") {
                $title="DZIEŃ (bez) PRĄDU! - Czuby Aka kontra Czterech Jeźdźców Apo Kalipsy";
            } else {
                $title = strstr($f2, "<", true);
                $title = preg_replace('/(&(?!#|amp;))/', "&amp;", $title);
            }

            addAuthorInfo($user, $userId, $id, $title);

            if (strstr($params, "<a class=\"konkurs\" href=\"/opowiadania/konkursy/")) {
                $konkursID = findNext($params, "<a class=\"konkurs\" href=\"/opowiadania/konkursy/");
                $konkursID = strstr($konkursID, "\"", true);
        
                $konkurs = findNext($params, "<a class=\"konkurs\" href=\"/opowiadania/konkursy/");
                $konkurs = findNext($konkurs, ">");
                $konkurs = strstr($konkurs, "</a>", true);
                //                echo " konkurs '$konkurs' ";

                addKonkursInfo($konkurs, $konkursID, $id, $title);
            } else{
                $konkurs ="";
                $konkursID ="";
            }

            echo " autor '$user' title ".$title."\n";
            if ($log) {            file_put_contents("$path/log", "title is $title\n", FILE_APPEND);
            }

            if ($set == 3 && strstr($params, "<div class=\"punkty\" title=\"opowiadanie w bibliotece\">OK<div>bib</div></div>")) {
                echo "  library\n";
                if ($log) {            file_put_contents("$path/log", "library\n", FILE_APPEND);
                }
            } else {
                if ($downloadArticles) { processArticle($id, $title, $num, $user, $userId, $konkurs, $konkursID);
                }

                $tocContentOpf1=$tocContentOpf1."<item id=\"".$id."_xhtml\" media-type=\"application/xhtml+xml\" href=\"$id.xhtml\" />\n";

                $tocContentOpf2=$tocContentOpf2."<itemref idref=\"".$id."_xhtml\"/>\n";

                if ($tocContentOpf3 == "") {
                    $tocContentOpf3 = "<reference href=\"$id.xhtml\" type=\"text\" title=\"Tekst\"/>\n";
                }

                $tocTocNCX=$tocTocNCX."<navPoint id=\"index_$num\" playOrder=\"$num\">\n".
                "<navLabel>\n".
                "<text>$title</text>\n".
                "</navLabel>\n".
                "<content src=\"$id.xhtml\"/>\n".
                "</navPoint>\n";

                $tocTocXHTML=$tocTocXHTML."<li>\n".
                "<a href=\"$id.xhtml\">$title</a>\n".
                "</li>\n";

                $num++;
            }
        }

        if ($downloadOnlyFew && $num==5) { break;
        }
    }
    if ($downloadOnlyFew && $num==5) { break;
    }
    if ($set==5) { break;
    }

    if ($allPages) {
        if (strstr($f, "/$pagenum\" title=\"koniec\">") 
            || strstr($f, "$word/".($pagenum-1)."/d\" title=\"koniec\">")
        ) { break;
        }
    } else {
        if ($pagenum==$endPage) { break;
        }
    }
    $pagenum++;
}

file_put_contents("$path/mimetype", "application/epub+zip");

$txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
"<container version=\"1.0\" xmlns=\"urn:oasis:names:tc:opendocument:xmlns:container\">\n".
  "<rootfiles>\n".
    "<rootfile full-path=\"OEBPS/content.opf\" media-type=\"application/oebps-package+xml\"/>\n".
  "</rootfiles>\n".
"</container>";

file_put_contents("$path/META-INF/container.xml", $txt);

file_put_contents("$path/OEBPS/style.css", "body {text-align:justify}");

$txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
  "<ncx xmlns=\"http://www.daisy.org/z3986/2005/ncx/\"\n".
    "xmlns:py=\"http://genshi.edgewall.org/\"\n".
    "version=\"2005-1\"\n".
    "xml:lang=\"pl\">\n".
  "<head>\n".
      "<meta name=\"cover\" content=\"cover\"/>\n".
      "<meta name=\"dtb:uid\" content=\"urn:uuid:e5953946-ea06-4599-9a53-f5c652b89f5c\"/>\n".
      "<meta name=\"dtb:depth\" content=\"1\"/>\n".
      "<meta name=\"dtb:totalPageCount\" content=\"0\"/>\n".
      "<meta name=\"dtb:maxPageNumber\" content=\"0\"/>\n".
  "</head>\n".
  "<docTitle>\n".
      "<text>".ucfirst($word)." z fantastyka.pl (".date('dmy').")</text>\n".
  "</docTitle>\n".
"<navMap>\n".
$tocTocNCX.
"</navMap>\n".
"</ncx>\n";

file_put_contents("$path/OEBPS/toc.ncx", $txt);

$txt="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
"<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n".
"<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
      "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
      "xml:lang=\"pl\" lang=\"pl\">\n".
    "<head>\n".
      "<title>".ucfirst($word)." z fantastyka.pl (".date('dmy').")</title>\n".
        "<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />\n".
    "</head>\n".
    "<body xml:lang=\"pl\" lang=\"pl\">\n".
    "<header>\n".
      "<h2>Spis treści</h2>\n".
    "</header>\n".
    "<nav epub:type=\"toc\">\n".
      "<ol>\n".
$tocTocXHTML.
 "</ol>\n".
    "</nav>\n".
    "</body>\n".
"</html>";

file_put_contents("$path/OEBPS/toc.xhtml", $txt);

foreach (scandir("$path/OEBPS/authors") as $key => $filename) {
    if (!in_array($filename, array(".","..")) && !is_dir("$path/OEBPS/authors/$filename")
        && strstr($filename, ".xhtml")
    ) {
        $tocContentOpf1=$tocContentOpf1."<item id=\"author_".$filename."\" media-type=\"application/xhtml+xml\" href=\"authors/$filename\" />\n";
        $tocContentOpf2=$tocContentOpf2."<itemref idref=\"author_".$filename."\"/>\n";
    }
}
foreach (scandir("$path/OEBPS/konkurs") as $key => $filename) {
    if (!in_array($filename, array(".","..")) && !is_dir("$path/OEBPS/konkurs/$filename")
        && strstr($filename, ".xhtml")
    ) {
        $tocContentOpf1=$tocContentOpf1."<item id=\"konkurs_".$filename."\" media-type=\"application/xhtml+xml\" href=\"konkurs/$filename\" />\n";
        $tocContentOpf2=$tocContentOpf2."<itemref idref=\"konkurs_".$filename."\"/>\n";
    }
}

$txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<package xmlns=\"http://www.idpf.org/2007/opf\"\n".
         "xmlns:opf=\"http://www.idpf.org/2007/opf\"\n".
         "xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n".
         "unique-identifier=\"bookid\"\n".
         "version=\"3.0\"\n".
         "xml:lang=\"pl\">\n".
    "<metadata>\n".
        "<dc:identifier id=\"bookid\">urn:uuid:e5953946-ea06-4599-9a53-f5c652b89f5c</dc:identifier>\n".
        "<dc:language>pl-PL</dc:language>\n".
        "<meta name=\"generator\" content=\"Skrypt z mwiacek.com\"/>\n".
        "<dc:title>".ucfirst($word)." z fantastyka.pl (".date('dmy').")</dc:title>\n".
        "<dc:description>\n".
        "Opowiadania z biblioteki z fantastyka.pl przetworzone skryptem z mwiacek.com. Wersja z dnia ".date('dmy').".\n".
        "</dc:description>\n".
        "<dc:creator id=\"creator-0\">A.zbiorowy+skrypt z mwiacek.com</dc:creator>\n".
        "<meta refines=\"#creator-0\" property=\"role\" scheme=\"marc:relators\">aut</meta>\n".
        "<meta refines=\"#creator-0\" property=\"file-as\">A.zbiorowy+skrypt z mwiacek.com</meta>\n".
        "<meta name=\"cover\" content=\"cover\"></meta>\n".
        "<meta property=\"dcterms:modified\">".date('Y-m-d\TH:i:s\Z')."</meta>\n".
    "</metadata>\n".
    "<manifest>\n".
  "<item id=\"style_css\" media-type=\"text/css\" href=\"style.css\" />\n".
        "<item id=\"cover\" media-type=\"image/jpeg\" href=\"cover$set.jpg\" properties=\"cover-image\" />\n".
        "<item id=\"cover-page_xhtml\" media-type=\"application/xhtml+xml\" href=\"cover-page.xhtml\" />\n".
        "<item id=\"toc_xhtml\" media-type=\"application/xhtml+xml\" href=\"toc.xhtml\" properties=\"nav\" />\n".
$tocContentOpf1.
 "<item id=\"ncxtoc\" media-type=\"application/x-dtbncx+xml\" href=\"toc.ncx\" />\n".
 "</manifest>\n".
    "<spine toc=\"ncxtoc\">\n".
        "<itemref idref=\"cover-page_xhtml\" linear=\"no\"/>\n".
        "<itemref idref=\"toc_xhtml\"/>\n".
$tocContentOpf2.
"</spine>\n".
    "<guide>\n".
        "<reference href=\"cover-page.xhtml\" type=\"cover\" title=\"Strona okładki\"/>\n".
        "<reference href=\"toc.xhtml\" type=\"toc\" title=\"Spis treści\"/>\n".
$tocContentOpf3.
    "</guide>\n".
"</package>";

file_put_contents("$path/OEBPS/content.opf", $txt);

$txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
"<!DOCTYPE html>\n".
"<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
      "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
      "xml:lang=\"pl\" lang=\"pl\">\n".
    "<head>\n".
        "<title>".ucfirst($word)." z fantastyka.pl (".date('dmy').")</title>\n".
        "<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />\n".
    "</head>\n".
    "<body xml:lang=\"pl\" lang=\"pl\">\n".
    "<div>\n".
      "<img src=\"cover$set.jpg\"/>\n".
    "</div>\n".
    "</body>\n".
"</html>";

file_put_contents("$path/OEBPS/cover-page.xhtml", $txt);

if ($allowResume) {
    foreach (scandir("$path/OEBPS") as $key => $filename) {
        if (!in_array($filename, array(".","..")) && !is_dir("$path/OEBPS/$filename")
            && strstr($filename, ".xhtml") && $filename!="cover-page.xhtml" && $filename!="toc.xhtml"
        ) {
            if (!strstr($tocContentOpf1, "media-type=\"application/xhtml+xml\" href=\"$filename\" />")) {
                echo "$filename is not mentioned in index!\n";
                //                exec("rm $path/OEBPS/$filename");
            }
        }
    }
}
foreach (scandir("$path/OEBPS") as $key => $filename) {
    if (!in_array($filename, array(".","..")) && !is_dir("$path/OEBPS/$filename")
        && strstr($filename, ".xhtml") && $filename!="cover-page.xhtml" && $filename!="toc.xhtml"
    ) {
        if ($set == 2 && file_exists("$path/../biblioteka/OEBPS/$filename")) {
                echo "$filename is in biblioteka, removing!\n";
                exec("rm $path/OEBPS/$filename");
        }
    }
}

foreach (scandir("$path/OEBPS/authors") as $key => $filename) {
    if (!in_array($filename, array(".","..")) && !is_dir("$path/OEBPS/authors/$filename")
        && strstr($filename, ".xhtml")
    ) {
        file_put_contents("$path/OEBPS/authors/$filename", "</ul></body></html>", FILE_APPEND);
    }
}
foreach (scandir("$path/OEBPS/konkurs") as $key => $filename) {
    if (!in_array($filename, array(".","..")) && !is_dir("$path/OEBPS/konkurs/$filename")
        && strstr($filename, ".xhtml")
    ) {
        file_put_contents("$path/OEBPS/konkurs/$filename", "</ul></body></html>", FILE_APPEND);
    }
}

exec("cp cover$set.jpg $path/OEBPS/cover$set.jpg");
exec("cd $path && zip -rv $word.zip OEBPS META-INF mimetype");
exec("mv $path/$word.zip $path/$word.epub");

if ($set == 4) {
    file_get_contents("https://www.fantastyka.pl/logout", false, $context);
    //    var_dump($http_response_header);
}

echo ($num-1)." texts processed\n";

?>
