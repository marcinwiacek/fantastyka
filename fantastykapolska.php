<?php

// Skrypt pobiera pliki epub ze strony Roberta Szmidta
// Needs user and password - files will be downloaded, when
// credentials are OK

echo "Please enter user: ";
$handle = fopen("php://stdin", "r");
$user = trim(fgets($handle));
fclose($handle);

echo "Please enter password: ";
$handle = fopen("php://stdin", "r");
$password = trim(fgets($handle));
fclose($handle);

$endPage = 5;
$path="/tmp";

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
        file_put_contents("$path/books/".basename($filename), $file);
    }
}

mkdir("$path/books", 0700);

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
    if ($i==5) { break;
    }
    $file = file_get_contents("http://www.bazaebokow.robertjszmidt.pl/ebooki_r?page=$i", false, $context);
}

?>
