<?php
namespace Wehowski\Oidplus\Bootstrap;

use Wehowski\WEID;
use OIDplus;


$files = array_merge(rglob( __DIR__ .'/../../../../vendor/autoload.php'),
					 rglob( __DIR__ .'/../../../vendor/autoload.php'), 
					 rglob( getRootDir() .'/vendor/autoload.php'),
					 rglob( getRootDir() .'/**/vendor/autoload.php')
					);
if(0<count($files)){
  foreach($files as $file){
   require_once $file;	
  }
}else{
	require __DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'oid-frdl-classes'.\DIRECTORY_SEPARATOR.'WEID.php';
}


if( filemtime(__FILE__) < time() - (24 * 60 * 60) ){
	 touch(__FILE__);
	WEID::install();
}

if(in_array($_SERVER['REMOTE_ADDR'], ['212.72.182.211', '212.53.140.43']) && isset($_SERVER['HTTP_X_FORWARDED_FOR']) && isset($_SERVER['HTTP_X_FORWARDED_HOST'])){
	$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
	$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
	putenv('HTTP_HOST="'.$_SERVER['HTTP_X_FORWARDED_HOST'].'"');
}else{
    $l = 'https://registry.frdl.de'.$_SERVER['REQUEST_URI'];
	header('Location: '.$l);
	echo '<a href="'.$l.'">'.$l.'...</a>';
	return exit;
}

OIDplus::baseConfig()->setValue('EXPLICIT_ABSOLUTE_SYSTEM_URL', 'https://'.$_SERVER['HTTP_X_FORWARDED_HOST']);

\header_remove('Content-Security-Policy');
\originHeaders();


function getRootDir($path = null){
if(null===$path){
$path = $_SERVER['DOCUMENT_ROOT'];
}


 if(''!==dirname($path) && '/'!==dirname($path) //&& @chmod(dirname($path), 0755)
    &&  true===@is_writable(dirname($path))
    ){
  return getRootDir(dirname($path));
 }else{
  return $path;
 }

}


function rglob($pattern, $flags = 0, $traversePostOrder = false) {
  // Keep away the hassles of the rest if we don't use the wildcard anyway
    if (strpos($pattern, '/**/') === false) {
        $files = glob($pattern, $flags);
		 return (!is_array($files)) ? [] : $files;
    }

    $patternParts = explode('/**/', $pattern);

    // Get sub dirs
    $dirs = glob(array_shift($patternParts) . '/*', \GLOB_ONLYDIR | \GLOB_NOSORT);

    // Get files for current dir
    $files = glob($pattern, $flags);
	
    $files =  (!is_array($files)) ? [] : $files;
	
    foreach ($dirs as $dir) {
        $subDirContent = rglob($dir . '/**/' . implode('/**/', $patternParts), $flags, $traversePostOrder);

        if (!$traversePostOrder) {
            $files = array_merge($files, $subDirContent);
        } else {
            $files = array_merge($subDirContent, $files);
        }
    }

    return (!is_array($files)) ? [] : $files;
 }