<?php

// Skrypt pobiera pliki epub ze strony Roberta Szmidta i łączy
// krótkie teksty w jeden EPUB (duże są kopiowane do katalogu
// docelowego)
//
// Download needs user and password - files will be downloaded, when
// credentials are OK

$download = true;
$merge = false; // not fully complete - merging short texts into one EPUB
$endPage = 5; // how many pages are on www site
$path="/tmp/books"; // output folder

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

if ($download) {
    echo "Please enter user: ";
    $handle = fopen("php://stdin", "r");
    $user = trim(fgets($handle));
    fclose($handle);

    echo "Please enter password: ";
    $handle = fopen("php://stdin", "r");
    $password = trim(fgets($handle));
    fclose($handle);

    $file = file_get_contents("http://www.bazaebokow.robertjszmidt.pl/ebooki_r");
    $form_build_id=findBetween($file, "name=\"form_build_id\" value=\"", "", "\"");

    $options = array(
    'http'=>array(
    'method'=>"POST",
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
    "Cookie: has_js=1\r\n",
    'content'=>"name=$user&".
    "pass=".urlencode($password)."&".
    "form_build_id=$form_build_id&".
    "form_id=user_login_block&".
    "op=Zaloguj"
    )
    );
    $context = stream_context_create($options);

    $file = file_get_contents("http://www.bazaebokow.robertjszmidt.pl/ebooki_r?destination=ebooki_r", false, $context);
    //var_dump($http_response_header);
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
    'method'=>"GET",
    'header' =>"Cookie: has_js=1; $cookie\r\n",
    'content'=>""
    )
    );
    $context = stream_context_create($options);

    function readEpub($id,$context)
    {
        global $path;

        $file = file_get_contents("http://www.bazaebokow.robertjszmidt.pl/node/$id", false, $context);
        $filename= findBetween($file, "application/epub+zip\" src=\"/modules/file/icons/application-octet-stream.png\" /> <a href=\"", "", "\"");
        if ($filename!="") {
            echo " file is ".basename($filename);
            $file = file_get_contents($filename, false, $context);
            file_put_contents("$path/".basename($filename), $file);
        }
    }

    mkdir("$path", 0700);

    for ($i=1;$i<($endPage+1);$i++) {
        while (true) {
            $t = "<td  class=\"views-field views-field-name active views-align-center\">";
            if (!strstr($file, $t)) { break;
            }
            $file = findNext($file, $t);
            $file = findNext($file, "<a href=\"/node/");
            $id = strstr($file, "\">", true);
            echo "id is $id";
            readEpub($id, $context);
            echo "\n";
        }
        if ($i==$endPage) { break;
        }
        $file = file_get_contents("http://www.bazaebokow.robertjszmidt.pl/ebooki_r?page=$i", false, $context);
    }
}

if ($merge) {
    $tocContentOpf1="";
    $tocContentOpf2="";
    $tocContentOpf3="";
    $tocTocNCX="";
    $tocTocXHTML="";

    exec("mkdir $path/output");
    exec("mkdir $path/output/META-INF");
    exec("mkdir $path/output/OEBPS");
    exec("mkdir $path/output2");
    exec("mkdir $path/tmp");

    function scanXHTMLFiles($dir)
    {
        $result = [];
        foreach(scandir($dir) as $key => $filename) {
            if (in_array($filename, array(".",".."))) { continue;
            }
            $fullName = "$dir/$filename";
            if (is_dir($fullName)) {
                foreach (scanXHTMLFiles($fullName) as $key=>$filename2) {
                    $result[] = "$filename/$filename2";
                }
            } else if (strstr($filename, ".xhtml") || $filename=="content.opf") {
                $result[] = $filename;
            }
        }
        return $result;
    }

    $num=1;
    foreach (scandir("$path") as $key => $value) {
        if (!in_array($value, array(".","..")) && !is_dir("$path/$value")) {
            echo "Processing $value\n";
            exec("mkdir $path/tmp");
            exec("cd $path/tmp && unzip $path/$value");
            $name="";
            $size=0;
            $process=true;
            $OPFname="";
            foreach(scanXHTMLFiles("$path/tmp") as $key=>$filename) {
                if (strstr($filename, "content.opf")) {
                    $OPFname = $filename;
                    continue;
                }
                if (filesize("$path/tmp/$filename")>$size) {
                    $name=$filename;
                    $size = filesize("$path/tmp/$filename");
                } else if (filesize("$path/tmp/$filename")>4000) {
                    echo ("Potential next chapter!\n");
                    $process=false;
                    break;
                }
            }
            if ($process && $OPFname!="") {
                $OPF = file_get_contents("$path/tmp/$OPFname");
                $text = file_get_contents("$path/tmp/$name");
                $author = findBetween($OPF, "<dc:creator", "<", "</dc:creator>");
                $title = findBetween($OPF, "<dc:title>", "", "</dc:title>");

                $tocContentOpf1=$tocContentOpf1."<item id=\"".$value."_xhtml\" media-type=\"application/xhtml+xml\" href=\"$value.xhtml\" />\n";

                $tocContentOpf2=$tocContentOpf2."<itemref idref=\"".$value."_xhtml\"/>\n";

                if ($tocContentOpf3 == "") {
                    $tocContentOpf3 = "<reference href=\"$value.xhtml\" type=\"text\" title=\"Tekst\"/>\n";
                }

                $tocTocNCX=$tocTocNCX."<navPoint id=\"index_$num\" playOrder=\"$num\">\n".
                "<navLabel>\n".
                "<text>$title</text>\n".
                "</navLabel>\n".
                "<content src=\"$value.xhtml\"/>\n".
                "</navPoint>\n";

                $tocTocXHTML=$tocTocXHTML."<li>\n".
                "<a href=\"$value.xhtml\">$title</a>\n".
                "</li>\n";

                file_put_contents("$path/output/OEBPS/$value.xhtml", $text);
                $num++;
            } else {
                exec("cp $path/$value $path/output2/$value");
            }
            exec("rm -rf $path/tmp");
        }
    }

    file_put_contents("$path/output/mimetype", "application/epub+zip");

    $txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<container version=\"1.0\" xmlns=\"urn:oasis:names:tc:opendocument:xmlns:container\">\n".
    "<rootfiles>\n".
    "<rootfile full-path=\"OEBPS/content.opf\" media-type=\"application/oebps-package+xml\"/>\n".
    "</rootfiles>\n".
    "</container>";

    file_put_contents("$path/output/META-INF/container.xml", $txt);

    file_put_contents("$path/output/OEBPS/style.css", "body {text-align:justify}");

    $txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<ncx xmlns=\"http://www.daisy.org/z3986/2005/ncx/\"\n".
    "xmlns:py=\"http://genshi.edgewall.org/\"\n".
    "version=\"2005-1\"\n".
    "xml:lang=\"pl\">\n".
    "<head>\n".
      "<meta name=\"cover\" content=\"cover\"/>\n".
      "<meta name=\"dtb:uid\" content=\"urn:uuid:e5953946-ea06-4599-9a53-f5c652b89f6c\"/>\n".
      "<meta name=\"dtb:depth\" content=\"1\"/>\n".
      "<meta name=\"dtb:totalPageCount\" content=\"0\"/>\n".
      "<meta name=\"dtb:maxPageNumber\" content=\"0\"/>\n".
    "</head>\n".
    "<docTitle>\n".
      "<text>Op. z fantastykapolska.pl (".date('dmy').")</text>\n".
    "</docTitle>\n".
    "<navMap>\n".
    $tocTocNCX.
    "</navMap>\n".
    "</ncx>\n";

    file_put_contents("$path/output/OEBPS/toc.ncx", $txt);

    $txt="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n".
    "<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
      "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
      "xml:lang=\"pl\" lang=\"pl\">\n".
    "<head>\n".
      "<title>Op. z fantastykapolska.pl (".date('dmy').")</title>\n".
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

    file_put_contents("$path/output/OEBPS/toc.xhtml", $txt);

    $txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<package xmlns=\"http://www.idpf.org/2007/opf\"\n".
         "xmlns:opf=\"http://www.idpf.org/2007/opf\"\n".
         "xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n".
         "unique-identifier=\"bookid\"\n".
         "version=\"3.0\"\n".
         "xml:lang=\"pl\">\n".
    "<metadata>\n".
        "<dc:identifier id=\"bookid\">urn:uuid:e5953946-ea06-4599-9a53-f5c652b89f6c</dc:identifier>\n".
        "<dc:language>pl-PL</dc:language>\n".
        "<meta name=\"generator\" content=\"Skrypt z mwiacek.com\"/>\n".
        "<dc:title>Op. z fantastykapolska.pl (".date('dmy').")</dc:title>\n".
        "<dc:description>\n".
        "Opowiadania z fantastykapolska.pl przetworzone skryptem z mwiacek.com. Wersja z dnia ".date('dmy').".\n".
        "</dc:description>\n".
        "<dc:creator id=\"creator-0\">A.zbiorowy+skrypt z mwiacek.com</dc:creator>\n".
        "<meta refines=\"#creator-0\" property=\"role\" scheme=\"marc:relators\">aut</meta>\n".
        "<meta refines=\"#creator-0\" property=\"file-as\">A.zbiorowy+skrypt z mwiacek.com</meta>\n".
        "<meta name=\"cover\" content=\"cover\"></meta>\n".
        "<meta property=\"dcterms:modified\">".date('Y-m-d\TH:i:s\Z')."</meta>\n".
    "</metadata>\n".
    "<manifest>\n".
    "<item id=\"style_css\" media-type=\"text/css\" href=\"style.css\" />\n".
        "<item id=\"cover\" media-type=\"image/jpeg\" href=\"cover.jpg\" properties=\"cover-image\" />\n".
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

    file_put_contents("$path/output/OEBPS/content.opf", $txt);

    $txt = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
    "<!DOCTYPE html>\n".
    "<html xmlns=\"http://www.w3.org/1999/xhtml\"\n".
      "xmlns:epub=\"http://www.idpf.org/2007/ops\"\n".
      "xml:lang=\"pl\" lang=\"pl\">\n".
    "<head>\n".
        "<title>Op. z fantastykapolska.pl (".date('dmy').")</title>\n".
        "<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />\n".
    "</head>\n".
    "<body xml:lang=\"pl\" lang=\"pl\">\n".
    "<div>\n".
      "<img src=\"cover.jpg\"/>\n".
    "</div>\n".
    "</body>\n".
    "</html>";

    file_put_contents("$path/output/OEBPS/cover-page.xhtml", $txt);

    exec("cp cover.jpg $path/output/OEBPS/cover.jpg");
    exec("cd $path/output && zip -rv fpolska.zip OEBPS META-INF mimetype");
    exec("mv $path/output/fpolska.zip $path/output2/fpolska.epub");

    echo ($num-1)." texts processed\n";
}

?>
