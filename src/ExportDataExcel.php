<?php
namespace Esj\Core;

class ExportDataExcel extends ExportData
{
	
	const XmlHeader = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";
	const XmlFooter = "</Workbook>";
	
	public $encoding = 'UTF-8';
	public $title = 'Sheet1';

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
	
	function generateHeader(): string
    {
		
		$output = stripslashes(sprintf(self::XmlHeader, $this->encoding)) . "\n";
		
		$output .= "<Styles>\n";
		$output .= "	<Style ss:ID=\"sDT\"><NumberFormat ss:Format=\"Short Date\"/></Style>\n";
		$output .= "</Styles>\n";
		
		$output .= sprintf("<Worksheet ss:Name=\"%s\">\n <Table>\n", htmlentities($this->title));
		
		return $output;
		
	}
	
	function generateFooter(): string
    {

        $output = " </Table>\n</Worksheet>\n";
		$output .= self::XmlFooter;
		
		return $output;
		
	}
	
	function generateRow($row): string
    {
		
		$output = " <Row>\n";
		
		foreach ($row as $v)
			$output .= $this->generateCell($v);
		
		$output .= " </Row>\n";
		
		return $output;
		
	}
	
	private function generateCell($item): string
    {
		
		$output = '';
		$style = '';

        $item = preg_replace("/(<([^>]+)>)/sim", "", $item);

		if (preg_match("/^-?\d+(?:[.,]\d+)?$/",$item) && (strlen($item) < 15)) {
			
			$type = 'Number';
			
		} elseif (preg_match("/^(\d{1,2}|\d{4})[\/\-]\d{1,2}[\/\-](\d{1,2}|\d{4})([^\d].+)?$/",$item) && ($timestamp = strtotime($item)) && ($timestamp !== false) && ($timestamp < strtotime('+500 years'))) {
			
			$type = 'DateTime';
			$item = strftime("%Y-%m-%dT%H:%M:%S", $timestamp);
			$style = 'sDT';
			
		} else {
			
			$type = 'String';
			
		}
		
		$item = str_replace('&#039;', '&apos;', htmlspecialchars($item, ENT_QUOTES));

		$output .= " ";
		$output .= $style ? "<Cell ss:StyleID=\"$style\">" : "<Cell>";
		$output .= sprintf("<Data ss:Type=\"%s\">%s</Data>", $type, $item);
		$output .= "</Cell>\n";
		
		return $output;
		
	}
	
	function sendHttpHeaders()
    {
		
		header("Content-Type: application/vnd.ms-excel; charset=" . $this->encoding);
		header("Content-Disposition: inline; filename=\"" . basename($this->filename) . "\"");
		
	}
	
}
