<?php
namespace Wullix\Cli;

use Wullix\Tool\ColorStringTool;
use Wullix\Tool\BenchTool;
use Wullix\Tool\LockTool;

defined( 'WULLIX_CR') or define ( 'WULLIX_CR', CliTool::getCariageReturn () );
defined( 'WULLIX_IS_CLI') or define ( 'WULLIX_IS_CLI', CliTool::isCli () );
class CliTool {
	public static $currentFile = null;
	public static $verbose = false;
	public static $useLock=true;
	public static $lockTtl=3600;
	public static $levelColor = array (
			"NORMAL"	=> null,
			"INFO"		=> "cyan",
			"NOTICE"	=> "yellow",
			"WARNING"	=> "purple",
			"ERROR"		=> "red", 
			"OK"		=> "green", 
			"KO"		=> "red", 
	);
	private static $progressBarUsed = false;
	private static $progressBarCurrent = null;
	private static $progressBarTotal = null;
	private static $progressBarSize = null;
	
	private static $executionParams = array();
	
	
	public static function isCli() {
		if (php_sapi_name () === 'cli') {
			return true;
		}
		return false;
	}
	public static function getCariageReturn() {
		static $CR;
		if (! empty ( $CR )) {
			return $CR;
		}
		if (self::isCli ()) {
			// $CR = "\n";
			$CR = PHP_EOL;
		} else {
			$CR = "<br />\n";
		}
		return $CR;
	}
	public static function getAllExecutionParams(){
		if(!empty(self::$executionParams)){
			return 	self::$executionParams;
		}
		if(CliTool::isCli() && !empty($_SERVER['argv']['1'])){
			for($i=1;$i<count($_SERVER['argv']);$i++){
				$tmp = $_SERVER['argv'][$i];
				preg_match_all("/(.*)=(.*)/",$tmp,$matches);
				if(!empty($matches[1][0]) && !empty($matches[1][0])){
					/*
					 $tmpVar = "ARG_".strtoupper($matches[1][0]);
					$$tmpVar = $matches[2][0];
					*/
					self::$executionParams[$matches[1][0]] = $matches[2][0];
				}else{
					self::$executionParams[$tmp] = true;
				}
			}
		
		}else{
			self::$executionParams = $_GET;
		}
		return self::$executionParams;
	}
		
	public static function getExecutionParams($params){
		$executionParams = self::getAllExecutionParams();		
		if(isset($executionParams[$params])){
			return $executionParams[$params];
		}
		return false;
	}	
	
	
	/**
	 * Start cli with timer
	 * 
	 * @param string $file        	
	 */
	public static function startCli($file = null) {
		self::currentFile ( $file );
		BenchTool::startChrono ( $file );
		
		echo ColorStringTool::fgColor ( self::$levelColor ['INFO'], "=========START===[{$file}]===[" . date ( "Y-m-d H:i:s" ) . "]" ) . WULLIX_CR;
	}
	
	/**
	 * Normal finish for cli
	 * 
	 * @param string $file        	
	 */
	public static function endCli($file = null) {
		self::currentFile ( $file );
		if(self::$progressBarUsed){ //si utilisé temporisé
			self::progressBarUpdate(self::$progressBarTotal);
		}
		echo ColorStringTool::fgColor ( self::$levelColor ['INFO'], "=========END=====[{$file}]===[" . date ( "Y-m-d H:i:s" ) . "]===[Execution time : " . BenchTool::getChrono ( $file ) . "]" ) . WULLIX_CR;
	}
	/**
	 * exit script and display message in red
	 * 
	 * @param unknown $message        	
	 */
	public static function stopExecution($message,$skipUnlock=false) {
		echo ColorStringTool::fgColor ( self::$levelColor ['ERROR'], $message ) . WULLIX_CR;
		file_put_contents('php://stderr',$message . WULLIX_CR);
		self::endCli ( self::getParentFile () );
		if(!$skipUnlock){
			self::unlock(self::$currentFile);
		}
		exit (); 
	}
	
	/**
	 * Manage automaticaly lock system.
	 * if lock present exit script
	 * 
	 * @param string $file        	
	 * @param string $ttl        	
	 */
	public static function autoLock($file = null, $ttl = null) {
		self::currentFile ( $file );
		if(self::getExecutionParams('unlock')){
			self::showNotice("unlock forced");
			if(file_exists(LockTool::getLockPath($file))){
				self::unlock($file);
			}
		}
		if(!self::$useLock){
			return;
		}
		
		if (! LockTool::lock ( $file, $ttl )) {
			self::stopExecution ( "No lock available for [{$file}]",$skipUnlock=true );
		}
		CliTool::showMessage("Lock enabled for [{$ttl}]");
		
	}
	/**
	 * realse lock
	 * 
	 * @param string $file        	
	 * @return boolean
	 */
	public static function unlock($file = null) {
		if(!self::$useLock){
			return true;
		}
		
		self::currentFile ( $file );
		
		if (! LockTool::unlock ( $file )) {
			self::stopExecution ( "unable to unlock [{$file}]",$skipUnlock=true );
		}
		return true;
	}
	
	public static function renewLock($file = null,$ttl = null){ 
		if(!self::$useLock){
			return true;
		}
		
		if($ttl===null){
			$ttl = self::$lockTtl;
		}
		
		self::currentFile ( $file );
		
		if (! LockTool::renewLock ( $file, $ttl )) {
			self::stopExecution ( "unable to renewLock [{$file}]",$skipUnlock=true );
		}
		CliTool::showMessage("Lock renew for [{$file}][{$ttl}]");
		return true;
	}	
	
	
	public static function showMessage($message) {
		if (!self::$verbose) {
			return;
		}
		
		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['NORMAL'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['NORMAL'], $message ).WULLIX_CR;
		}
		self::flushBuffer();
	}
	public static function showOk($message) {
		if (!self::$verbose) {
			return;
		}
	
		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['OK'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['OK'], $message ).WULLIX_CR;
		}
		self::flushBuffer();
	}	
	public static function showKo($message) {
		if (!self::$verbose) {
			return;
		}
	
		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['KO'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['KO'], $message ).WULLIX_CR;
		}
		self::flushBuffer();
		
	}	
	public static function showInfo($message) {
		if (!self::$verbose) {
			return;
		}

		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['INFO'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['INFO'], $message ).WULLIX_CR;
		}
		self::flushBuffer();
		
	}
	public static function showNotice($message) {
		if (!self::$verbose) {
			return;
		}
		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['NOTICE'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['NOTICE'], $message ).WULLIX_CR;
		}
		self::flushBuffer();
		
	}
	public static function showWarning($message) {
		if (!self::$verbose) {
			return;
		}
		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['WARNING'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['WARNING'], $message ).WULLIX_CR;
		}
		self::flushBuffer();
		
	
	}
	public static function showError($message) {
		if (!self::$verbose) {
			return;
		}
		if(self::$progressBarUsed){ //si utilisé temporisé
			echo "\r".str_pad(ColorStringTool::fgColor ( self::$levelColor ['ERROR'], $message ), 100, " ", STR_PAD_RIGHT).WULLIX_CR;
			file_put_contents('php://stderr',$message . WULLIX_CR);
			self::progressBarUpdate();
		}else{
			echo ColorStringTool::fgColor ( self::$levelColor ['ERROR'], $message ).WULLIX_CR;
			file_put_contents('php://stderr',$message . WULLIX_CR);
		}
		
		self::flushBuffer();
		
	}
	
	
	public static function progressBarUpdate2($current,$end){
		$perc =  floor( ($current/$end)*100);
		
		
		echo "\r 1/10 [===>----------------------------------------------]  {$perc}% 00:00:09"; 
		//flush(); 
	}
	
	
	
	
	
	public static function progressBarUpdate($done=null, $total=null, $size = 30) {
		if (!self::$verbose) {
			return;
		}		
		static $start_time;
		self::$progressBarUsed = true;
		if(!is_null($done)){
			self::$progressBarCurrent = $done;
		}else{
			$done = self::$progressBarCurrent;
		}

		if(!is_null($total)){
			self::$progressBarTotal = $total;
		}else{
			$total = self::$progressBarTotal;
		}

		if(!is_null($size)){
			self::$progressBarSize = $size;
		}else{
			$size = self::$progressBarSize;
		}

	
		
	
		// if we go over our bound, just ignore it
		if ($done > $total){
			return;
		}
		
		if (empty ( $start_time )){
			$start_time = time ();
		}
		$now = time ();
		
		$perc = ( double ) ($done / $total);
		
		$bar = floor ( $perc * $size );
		
		$status_bar = "\r[";
		$status_bar .= str_repeat ( "=", $bar );
		if ($bar < $size) {
			$status_bar .= ">";
			$status_bar .= str_repeat ( " ", $size - $bar );
		} else {
			$status_bar .= "=";
		}
		
		$disp = number_format ( $perc * 100, 0 );
		
		$status_bar .= "] $disp%  $done/$total";
		if ($done > 0) {
			$rate = ($now - $start_time) / $done;
		} else {
			$rate = ($now - $start_time) / 1;
		}
		$left = $total - $done;
		$eta = round ( $rate * $left, 2 );
		
		$elapsed = $now - $start_time;
		
		$status_bar .= " remaining: " . number_format ( $eta ) . " sec.  elapsed: " . number_format ( $elapsed ) . " sec.";
		
		if(!CliTool::isCli()){
			echo "$status_bar  ".WULLIX_CR;
			self::flushBuffer();
		}else{
			echo "$status_bar  ";
		}
	
		
		// flush();
		
		// when done, send a newline
		if ($done == $total) {
			self::$progressBarUsed = false;
			self::$progressBarCurrent = null;
			self::$progressBarTotal = null;
			self::$progressBarSize = null;
			$start_time = null;
			echo "\n";
		}
		self::flushBuffer();
		
		
	}
	
	public static function flushBuffer(){
		if(!CliTool::isCli()){
			ob_flush();
			flush();
		}
		
	}
	
	public static function cleanLogProgressBar($string){
		$string = str_replace("\r","",$string);
		$string =preg_replace("~\[.*elapsed\: [0-9]* sec\.~", "", $string);
		return $string;
	}
	
	public static function cleanLogColor($string){
		return ColorStringTool::cleanLogColor($string);
	}
	public static function convertCliHtmlColor($string){
		return ColorStringTool::convertCliHtmlColor($string);
	}
	public static function cleanForLog($string){
		$string = CliTool::cleanLogProgressBar($string);
		return CliTool::cleanLogColor($string);		
		
	}
	public static function cleanForLogHtml($string){
		$string = CliTool::cleanLogProgressBar($string);
		$string = str_replace("\n","<br/>\n",$string);
		return CliTool::convertCliHtmlColor($string);
	}
	
	private static function currentFile(&$file = null) {
		if (empty ( $file )) {
			$file = self::$currentFile;
		}
		if (empty ( $file )) {
			$file = self::getParentFile ();
		}
		return $file;
	}
	private static function getParentFile() {
		$tmp = debug_backtrace ();
		foreach ( $tmp as $value ) {
			if ($value ['file'] != __FILE__) {
				return $value ['file'];
			}
		}
	}
	public static function openProcess($shell_exec,$exec_path="/tmp",$log_file="/tmp/execShell.log",$log_file_mode="w"){
		declare(ticks = 1);
		$params['exec_shell']=$shell_exec;
		$params['exec_path']=$exec_path;
		$params['log_file']=$log_file;
		//$params['log_file_mode']="a";
		$params['log_file_mode']=$log_file_mode;


		$RETURN['response']="";
		$RETURN['log_error']="";
		$RETURN['exec_error']=true;

		$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("file", $params['log_file'], $params['log_file_mode']) // stderr is a file to write to
		);

		$cwd = $params['exec_path'];
		$env = array('parent_pid' => posix_getpid());

		if(defined('PHP_CLI_DIR') && is_dir(PHP_CLI_DIR)){
			$process = proc_open(PHP_CLI_DIR.'php', $descriptorspec, $pipes, $cwd, $env);
			//echo PHP_CLI_DIR.'php';
		}else{
			$process = proc_open('php', $descriptorspec, $pipes, $cwd, $env);
			//echo "default php-cli".PHP_CLI_DIR;
		}

		if (is_resource($process)) {
			// $pipes now looks like this:
			// 0 => writeable handle connected to child stdin
			// 1 => readable handle connected to child stdout
			// Any error output will be appended to /tmp/error-output.txt
			fwrite($pipes[0], $params['exec_shell']);
			fclose($pipes[0]);

			$RETURN['response']=stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			// It is important that you close any pipes before calling
			// proc_close in order to avoid a deadlock
			$return_value = proc_close($process);
			//var_dump($return_value);
			//echo "command returned $return_value\n";
			if(is_file($params['log_file'])){
				$RETURN['log_error']=file_get_contents($params['log_file']);
				if($RETURN['log_error']!=""){
					$RETURN['exec_error']=true;
				}else{
					$RETURN['exec_error']=false;
				}
				if(is_file($params['log_file'])){
					unlink($params['log_file']);
				}
			}

		}
		return $RETURN;
	}
	public static function execShell($shell_exec){
		//echo $shell_exec."<br>\n";
		return shell_exec($shell_exec);
	}
}
