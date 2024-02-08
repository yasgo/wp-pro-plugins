<?php

namespace WPForms\Vendor;

\set_include_path(\get_include_path() . \PATH_SEPARATOR . "..");
include_once "xlsxwriter.class.php";
$writer = new XLSXWriter();
$keywords = array('some', 'interesting', 'keywords');
$writer->setTitle('Some Title');
$writer->setSubject('Some Subject');
$writer->setAuthor('Some Author');
$writer->setCompany('Some Company');
$writer->setKeywords($keywords);
$writer->setDescription('Some interesting description');
$writer->setTempDir(\sys_get_temp_dir());
//set custom tempdir
//----
$sheet1 = 'merged_cells';
$header = array("string", "string", "string", "string", "string");
$rows = array(array("Merge Cells Example"), array(100, 200, 300, 400, 500), array(110, 210, 310, 410, 510));
$writer->writeSheetHeader($sheet1, $header, $col_options = ['suppress_row' => \true]);
foreach ($rows as $row) {
    $writer->writeSheetRow($sheet1, $row);
}
$writer->markMergedCell($sheet1, $start_row = 0, $start_col = 0, $end_row = 0, $end_col = 4);
//----
$sheet2 = 'utf8';
$rows = array(
    array('Spreadsheet', '_'),
    array("Hoja de cálculo", "Hoja de cálculo"),
    array("Електронна таблица", "Електронна таблица"),
    //utf8 encoded
    array("電子試算表", "電子試算表"),
);
$writer->writeSheet($rows, $sheet2);
//----
$sheet3 = 'fonts';
$format = array('font' => 'Arial', 'font-size' => 10, 'font-style' => 'bold,italic', 'fill' => '#eee', 'color' => '#f00', 'fill' => '#ffc', 'border' => 'top,bottom', 'halign' => 'center');
$writer->writeSheetRow($sheet3, $row = array(101, 102, 103, 104, 105, 106, 107, 108, 109, 110), $format);
$writer->writeSheetRow($sheet3, $row = array(201, 202, 203, 204, 205, 206, 207, 208, 209, 210), $format);
//----
$sheet4 = 'row_options';
$writer->writeSheetHeader($sheet4, ["col1" => "string", "col2" => "string"], $col_options = array('widths' => [10, 10]));
$writer->writeSheetRow($sheet4, array(101, 'this text will wrap'), $row_options = array('height' => 30, 'wrap_text' => \true));
$writer->writeSheetRow($sheet4, array(201, 'this text is hidden'), $row_options = array('height' => 30, 'hidden' => \true));
$writer->writeSheetRow($sheet4, array(301, 'this text will not wrap'), $row_options = array('height' => 30, 'collapsed' => \true));
$writer->writeToFile('xlsx-advanced.xlsx');
