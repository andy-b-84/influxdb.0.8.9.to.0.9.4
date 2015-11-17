<?php

if ($argc<3) { exit('needs a filename and a main column'.PHP_EOL); }

$filename = $argv[1];
$mainColumn = $argv[2];

if (!file_exists($filename)) { exit($filename.' does not exist'.PHP_EOL); }

$start = microtime(true);

$resultFilenameTemplate = $filename.'.converted.part.%002d';
$part = 1;
$resultFilename = sprintf($resultFilenameTemplate, $part);
$resultFiles = array($resultFilename);

$pointer = fopen($filename, 'r');
$resultPointer = fopen($resultFilename, 'w');

$currentTimestamp = null;
$lastTimestamp = null;
$addedMicrosecond = 0;

$originalLines = 0;
$dataLines = 0;
$treatedDataLines = 0;

$otherPartsStart = '';
$dmlFound = false;

while ($string = fgets($pointer)) {
    $originalLines++;
    $array = explode(' ', $string);
    $timestamp = trim($array[count($array)-1]);
    if ((3>count($array)) ||('#' == $array[0]) || (!is_numeric($timestamp))) {
        if (trim($string) == '# DML') {
            $dmlFound = true;
        }
        if ($dmlFound) {
            $otherPartsStart .= $string;
        }
        fputs($resultPointer, $string);
    } else {
        $dmlFound = false;
        $dataLines++;
        if (0 == ($dataLines%100000)) {
            echo $dataLines.' lines treated'.PHP_EOL;
        }
        if (0 == ($dataLines%8000000)) {
            $part++;
            fclose($resultPointer);
            $resultFilename = sprintf($resultFilenameTemplate, $part);
            $resultFiles[] = $resultFilename;
            $resultPointer = fopen($resultFilename, 'w');
            fputs($resultPointer, $otherPartsStart);
            echo '8 000 000 lines reached, switching to new file : '.$resultFilename.PHP_EOL;
        }
        $collection = $array[0];
        $point = str_replace('"', '', str_replace('""', '"UNKNOWN"', implode('\\ ', array_slice($array, 1, count($array)-2))));
        $timestamp = intval($timestamp);
        if ($lastTimestamp != $timestamp) {
            $currentTimestamp = $lastTimestamp = $timestamp;
            $addedMicrosecond = 0;
        } else {
            $addedMicrosecond++;
        }
        $timestamp+=$addedMicrosecond;
        $matches = array();
        $found = preg_match('/(.+),('.$mainColumn.'=[0-9]+i),(.+)/', $point, $matches);
        if ($found) {
            $treatedDataLines++;
            fputs($resultPointer, $collection.','.$matches[1].','.$matches[3].' '.$matches[2].' '.$timestamp.PHP_EOL);
        }
    }
}

fclose($pointer);
fclose($resultPointer);

$stop = microtime(true);
$delta = sprintf('%.2d', $stop-$start);

echo "got $originalLines lines in input file,\nfound $dataLines data lines,\ntreated $treatedDataLines lines within them,\nin $delta s.\noutput files : ".var_export($resultFiles, 1).PHP_EOL;