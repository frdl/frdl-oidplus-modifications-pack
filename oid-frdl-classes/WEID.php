<?php
namespace Wehowski;

use Composer\Script\Event;


class WEID {

	const NS = 'weid:';
	const BASE = '1-3-6-1-4-1-SZ5-8';
	const ADDON_FILE = __DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'oid-frdl-inc'.\DIRECTORY_SEPARATOR.'frdlweb_oidplus_bootstrap_addon.phps';
	const REGEX_REQUIRE_FILE = "/(?<THECODELINE>require\s([^\']+)\'frdlweb\_addon\.php\';)/";
	
	public static function array_merge_if(){
		$args = func_get_args();
		$func = array_shift($args); 
		$result = [];
		
		if(is_callable($func)){
			for($i = 0; $i<count($args); $i++){
			    $r = call_user_func_array($func, array_shift($args));	
				if(is_array($r)){
				  $result = array_merge($r);
				  break;	
				}
			}
		}
				
		return $result;
	}
	
	
	public static function install(?Event $event=null){	
		
		if($event){
	      $io                 = $event->getIO ();
          $composer           = $event->getComposer();
	      $io->write ( 'command -'.$event->getName().'- event' );
	      $io->write ( __METHOD__ );
	      $targetDir = $composer->getPackage()->getTargetDir(); 	  
	 
			if(empty($targetDir)){		
				$targetDir = getcwd();		
			}	  	
		}else{
	      $io                 = false;
          $composer           = false;
	      $targetDir = getcwd();					
		}
		
		$addon_file = false;
		$configfile = false;
	
		   $params = [$targetDir];
		   $cfiles = self::array_merge_if((function($targetDir, $dirPattern){ return WEID::rglob($targetDir.$dirPattern);}),
								[getcwd(),'/**/userdata/baseconfig/config.inc.php'], 
								 [self::getRootDir(), '/**/userdata/baseconfig/config.inc.php'],
								[getcwd(), '/userdata/baseconfig/config.inc.php']
					);	
		   if(0<count($cfiles)){
			  $configfile=$cfiles[0];   
		   }
	
		
		if(is_string($configfile) && file_exists($configfile)){
			$addon_file = dirname($configfile) . \DIRECTORY_SEPARATOR.'frdlweb_addon.php';
		}
		
		if(false!==$addon_file){
			if(!file_exists($addon_file) || sha1_file($addon_file) !== sha1_file(self::ADDON_FILE)){
				file_put_contents($addon_file, file_get_contents(self::ADDON_FILE));
			}
			
			if(file_exists($addon_file) && filemtime($configfile) < filemtime($addon_file) ){
				$c = file_get_contents($configfile);
				$c =  preg_replace(self::REGEX_REQUIRE_FILE, '', $c);
				$c = trim($c);
				$c.=\PHP_EOL;				
				$c.= "require '".$addon_file."';";
				$c.=\PHP_EOL;
				file_put_contents($configfile, $c);
			}
		}
	}
	
	public static function weLuhnGetCheckDigit($str) {
		
		$wrkstr = str_replace('-', '', $str); // remove separators
		for ($i=0; $i<36; $i++) {
			$wrkstr = str_ireplace(chr(ord('a')+$i), $i+10, $wrkstr);
		}
		$nbdigits = strlen($wrkstr);
		$parity = $nbdigits & 1;
		$sum = 0;
		for ($n=$nbdigits-1; $n>=0; $n--) {
			$digit = $wrkstr[$n];
			if (($n & 1) != $parity) $digit *= 2;
			if ($digit > 9) $digit -= 9;
			
			$sum += $digit;
		}
		return ($sum%10) == 0 ? 0 : 10-($sum%10);
	}

	public static function weid2oid($weid, $namespace=null, $base=null) {
		if(null===$base){
		  $base = self::BASE;	
		}
		if(null===$namespace){
		   $namespace = self::NS;	
		}
		return \WeidOidConverter::weid2oid($weid, $namespace, $base);
	}

	public static function oid2weid($oid, $namespace=null, $base=null) {					
		if(null===$base){
		  $base = self::BASE;	
		}
		if(null===$namespace){
		   $namespace = self::NS;	
		}
		return \WeidOidConverter::oid2weid($oid, $namespace, $base);
	}
	public static function toDec($numstring) {
		return self::base_convert_bigint($numstring, 36, 10);
	}
	public static function alphanum($numstring) {
		return self::base_convert_bigint($numstring, 10, 36);
	}	
	public static function base_convert_bigint( $numstring,  $frombase,  $tobase) {
		$frombase_str = '';
		for ($i=0; $i<$frombase; $i++) {
			$frombase_str .= strtoupper(base_convert($i, 10, 36));
		}

		$tobase_str = '';
		for ($i=0; $i<$tobase; $i++) {
			$tobase_str .= strtoupper(base_convert($i, 10, 36));
		}

		$length = strlen($numstring);
		$result = '';
		$number = array();
		for ($i = 0; $i < $length; $i++) {
			$number[$i] = stripos($frombase_str, $numstring[$i]);
		}
		do { // Loop until whole number is converted
			$divide = 0;
			$newlen = 0;
			for ($i = 0; $i < $length; $i++) { // Perform division manually (which is why this works with big numbers)
				$divide = $divide * $frombase + $number[$i];
				if ($divide >= $tobase) {
					$number[$newlen++] = (int)($divide / $tobase);
					$divide = $divide % $tobase;
				} else if ($newlen > 0) {
					$number[$newlen++] = 0;
				}
			}
			$length = $newlen;
			$result = $tobase_str[$divide] . $result; // Divide is basically $numstring % $tobase (i.e. the new character)
		}
		while ($newlen != 0);

		return $result;
	}
	
	public static function getRootDir($path = null){
		if(null===$path){
			$path = $_SERVER['DOCUMENT_ROOT'];
		}
 
		if(''!==dirname($path) && '/'!==dirname($path) //&& @chmod(dirname($path), 0755) 
		   &&  true===@is_writable(dirname($path))  
		  ){ 
			return self::getRootDir(dirname($path));
		}else{
			return $path; 
		}
	}


	
 public static function rglob($pattern, $flags = 0, $traversePostOrder = false) {
    // Keep away the hassles of the rest if we don't use the wildcard anyway
    if (strpos($pattern, '/**/') === false) {
        return glob($pattern, $flags);
    }

    $patternParts = explode('/**/', $pattern);

    // Get sub dirs
    $dirs = glob(array_shift($patternParts) . '/*', \GLOB_ONLYDIR | \GLOB_NOSORT);

    // Get files for current dir
    $files = glob($pattern, $flags);

    foreach ($dirs as $dir) {
        $subDirContent = self::rglob($dir . '/**/' . implode('/**/', $patternParts), $flags, $traversePostOrder);

        if (!$traversePostOrder) {
            $files = array_merge($files, $subDirContent);
        } else {
            $files = array_merge($subDirContent, $files);
        }
    }

    return $files;
 }	
	
}