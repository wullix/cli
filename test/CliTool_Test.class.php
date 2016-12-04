<?php
use Wullix\Tool\FileTool;
require_once(dirname(__FILE__)."/../../_composer/vendor/autoload.php");


use Wullix\Cli\CliTool;
use Wullix\Connector\UnittestConnector;

class CliTool_Test extends UnittestConnector{



	public function testisCli(){
		$result = CliTool::isCli();
		if(php_sapi_name () === 'cli' && !$this->assertTrue($result)){
			$this->dump($result);
		}			
		if(php_sapi_name () !== 'cli' && !$this->assertFalse($result)){
			$this->dump($result);
		}			
	}
	
	public function testexecShell(){
		$tmpFolder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'UNITTEST_CLI'.DIRECTORY_SEPARATOR;
		mkdir($tmpFolder);
		touch ($tmpFolder.'test1');
		touch ($tmpFolder.'test2');
		$result = CliTool::execShell("ls -l $tmpFolder");
		if(!$this->assertTrue(strpos($result,'test1')!==false && strpos($result,'test2')!==false)){
			$this->dump($result);
		}
		FileTool::unlinkRecursive($tmpFolder,true);
		
	}
}