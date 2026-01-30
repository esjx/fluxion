<?php
namespace Esj\Core;

abstract class ExportData
{
	
	protected $exportTo;
	protected $stringData;
	protected $tempFile;
	protected $tempFilename;
	public $filename;
	
	public function __construct($exportTo = "browser", $filename = "exportdata")
    {
		
		if(!in_array($exportTo, array('browser', 'file', 'string')))
			Application::error("$exportTo is not a valid ExportData export type");
		
		$this->exportTo = $exportTo;
		$this->filename = $filename;
		
	}
	
	public function addRow($row)
    {
		$this->write($this->generateRow($row));
	}
	
	public function initialize()
    {

		switch($this->exportTo) {
			
			case 'browser':
				$this->sendHttpHeaders();
				break;
			
			case 'string':
				$this->stringData = '';
				break;
			
			case 'file':
				$this->tempFilename = tempnam(sys_get_temp_dir(), 'exportdata');
				$this->tempFile = fopen($this->tempFilename, "w");
				break;
			
		}
		
		$this->write($this->generateHeader());
		
	}
	
	public function finalize()
    {
		
		$this->write($this->generateFooter());
		
		switch($this->exportTo) {
			
			case 'browser':
				flush();
				break;
			
			case 'string':
				break;
			
			case 'file':
				fclose($this->tempFile);
                copy($this->tempFilename, $this->filename);
                unlink($this->tempFilename);
                chmod($this->filename, 0777);
				break;
			
		}

		return $this->tempFilename;

	}
	
	protected function write($data)
    {
		
		switch($this->exportTo) {
			
			case 'browser':
				echo $data;
				break;
			
			case 'string':
				$this->stringData .= $data;
				break;
			
			case 'file':
				fwrite($this->tempFile, $data);
				break;
			
		}
		
	}
	
	public function getString()
    {
		return $this->stringData;
	}
	
	protected function generateHeader(): string
    {
        return '';
    }
	
	protected function generateFooter(): string
    {
        return '';
    }
	
	abstract public function sendHttpHeaders();
	
	abstract protected function generateRow($row);
	
}
