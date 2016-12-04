<?php
namespace Wullix\Cli;

use Wullix\Cli\CliTool;
use Wullix\Cli\CliInterface;
use Wullix\Tool\ColorStringTool;
abstract class CliEngine implements CliInterface {
	
	/**
	 *
	 * @var array An array containing names and types of abstract properties that must be implemented in child classes
	 */
	private $_abstract_properties = array (
			'string'	=>	array('helpMessage'),
			'array'		=>	array('cli_option')
	);
	
	private $default_cli_options = array(
		"help"			=>	null,
		"unlock"		=>	null,
		"uselock"		=>	"(true,false) default true for production otherwise false",
		"lockttl"		=>	"(60) default 60*60 secondes for lock",
		"lockname"		=>	"(__FILE__) default define name of lock.",
		"verbose"		=>	"(true,false) default true",
		"method"		=>	"Give cli specific method alternative for startCli() exemple cli.php method=firstjob or method=firstjob,secondjob",
	);
	
	
	
	final public function __construct() {
		/*
		if(CliTool::getExecutionParams('verbose')){
			CliTool::$verbose = true;
		}
		*/
		$this->_abstract_properties_existence_enforcer ();
	}
	final protected function _abstract_properties_existence_enforcer() {
		// check if the child has defined the abstract properties or not
		$current_child = get_class ( $this );
		
		foreach ( $this->_abstract_properties as $type => $properties ) {
			$count = count ( $properties );
			
			for($i = 0; $i < $count; $i ++) {
				if (property_exists ( $this, $properties [$i] ) && strtolower ( gettype ( $this->$properties [$i] ) ) == $type) {
					continue;
				}
				
				// property does not exist
				$error = $current_child . ' class must define $' . $properties [$i] . ' property as ' . $type;
				
				throw new \LogicException ( $error );
			}
		}
		
		unset ( $error, $current_child );
	}
	
	
	
	public function startEngine() {
		if(!CliTool::isCli()){
			?>
			<style>
				body{
					padding:0;
					margin:0;
					background-color:#000000;
					color:#FFFFFF;
				}
			</style>
			<script type="text/javascript">
				window.setInterval(function() {
				  //var elem = document.getElementById('data');
				  //window.scrollTo(0,document.body.scrollHeight);
				  var elem = document.body;
				  elem.scrollTop = elem.scrollHeight;
				}, 1000);
			</script>
			<?php
			
		}
		
		
		if (CliTool::getExecutionParams ( 'help' )) {
			$this->showHelp();
			return;

		}
		
		
		
		//VERBOSE
		$verbose = CliTool::getExecutionParams ( 'verbose' );
		if("false"===$verbose){
			$verbose=false;
		}else{
			$verbose=true;
		}
		CliTool::$verbose = $verbose;
		
		//LOCK
		$uselock = CliTool::getExecutionParams ( 'uselock' );
		if($uselock=="false"){
			$uselock=false;
		}elseif($uselock=="true"){
			$uselock=true;
		}else{
			$uselock=!DEBUG;
		}
		
		CliTool::$useLock = $uselock;
		$lockTtlParams = CliTool::getExecutionParams( 'lockttl' );
		if($lockTtlParams===0 || $lockTtlParams>0){
			CliTool::$lockTtl = $lockTtlParams;
		}
		CliTool::startCli();
		
		$lockName = CliTool::getExecutionParams('lockname');
		if(!empty($lockName)){
			CliTool::$currentFile = $lockName;
			CliTool::autoLock($lockName,CliTool::$lockTtl);
		}else{
			CliTool::$currentFile = get_class($this);
			CliTool::autoLock(null,CliTool::$lockTtl);
		}
		
		if($method = CliTool::getExecutionParams ( 'method' )){
			if(strpos($method,",")!==false){
				$listMethod = explode(",",$method);	
			}else{
				$listMethod[]=$method;
			}
			
			
			foreach($listMethod as $method){
				$method=trim($method);// in order to clean whitespace in cli instruction
				if(!method_exists($this, $method)){
				//if(is_callable(array($this, $method))){
					CliTool::stopExecution("The method [{$method}] doens't exist in [".get_class($this)."]");
				}
				//call_user_method($method, $this);
				call_user_func_array(array($this,$method),array());
			}
			
		}else{
			$this->startCli();
		}
				
		CliTool::unlock();
		CliTool::endCli();
		
		
		
		
		
	}
	private function showHelp(){
		CliTool::$verbose=true;
		CliTool::showMessage ("+=============================================================================================================+");
		CliTool::showMessage (str_pad("|    ".ColorStringTool::fgColor('yellow',"Command Help For [".get_class($this)."]"), 121, " ", STR_PAD_RIGHT)."|");
		CliTool::showMessage ("|=============================================================================================================|");
		CliTool::showMessage (str_pad("|    ".ColorStringTool::fgColor('yellow',$this->helpMessage)."", 121, " ", STR_PAD_RIGHT)."|");
		CliTool::showMessage ("|=============================================================================================================|");
		CliTool::showMessage ("|=============================================================================================================|");
		CliTool::showMessage (str_pad("|    ".ColorStringTool::fgColor('yellow',"Available default cli options")."", 121, " ", STR_PAD_RIGHT)."|");
		CliTool::showMessage (str_pad("|    -----------------------", 110, " ", STR_PAD_RIGHT)."|");
		foreach($this->default_cli_options as $key => $value){
			CliTool::showMessage (str_pad("|        ".ColorStringTool::fgColor('cyan',"{$key}". (!empty($value)?"  =>  {$value}":"") )."", 121, " ", STR_PAD_RIGHT)."|");
		
		}
		CliTool::showMessage ("|=============================================================================================================|");
					
			
		if(!empty($this->cli_option)){
			CliTool::showMessage (str_pad("|    ".ColorStringTool::fgColor('yellow',"extra cli options")."", 121, " ", STR_PAD_RIGHT)."|");
			CliTool::showMessage (str_pad("|    -----------------------", 110, " ", STR_PAD_RIGHT)."|");
			foreach($this->cli_option as $key => $value){
				CliTool::showMessage (str_pad("|        ".ColorStringTool::fgColor('cyan',"{$key}". (!empty($value)?"  =>  {$value}":"") )."", 121, " ", STR_PAD_RIGHT)."|");
					
			}
		}
		CliTool::showMessage ("+=============================================================================================================+");		
	}
}