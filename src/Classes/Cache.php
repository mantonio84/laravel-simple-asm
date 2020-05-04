<?php
namespace mantonio84\SimpleAsm\Classes;
use MatthiasMullie\Minify;

class Cache {
	private const CACHE_FOLDER="asm/";
	protected $seeds=array();	

	public static function make(array $seeds=[]){
		return new static($seeds);
	}
	
	public static function getCompiledPath(string $filename){
		return preg_replace('/[\/]+/','/',\Str::start($filename, static::CACHE_FOLDER));
	}
	
	public static function getCompiledRealPath(string $filename=""){
		$folder=public_path(static::CACHE_FOLDER);
		if (!is_dir($folder)){
			mkdir($folder);
		}
		return public_path(static::getCompiledPath($filename));
	}

	public function __construct(array $seeds=[]){
		$this->seeds=$seeds;
	}
	
	public function remember(string $stream, \Closure $filler){
		if (config("asm.cache",true)!==true){		
			return call_user_func($filler,$stream);
		}
		$key=$this->get_buffer_key();		
		$filepath=static::getCompiledRealPath($key.".".strtolower($stream));
		if (!is_file($filepath)){
		   $files=call_user_func($filler,$stream);
		   $files=is_array($files) ? $files : [];
		   if (empty($files)){
			   return [];
		   }
		   $compilerName="\MatthiasMullie\\Minify\\".strtoupper($stream);
		   if (!class_exists($compilerName)){
			   return $files;
		   }
		   $compiler=new $compilerName();
		   foreach ($files as $f){
			   $compiler->add($this->readAssetFile($f));
		   }
		   $compiler->minify($filepath);
		}
		
		return [static::getCompiledPath(basename($filepath))];
	}
	
	protected function readAssetFile(string $f){
		if ($this->is_remote($f)){
			$context = stream_context_create(
				array(
					"http" => array(
						"header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
					)
				)
			);	
			return file_get_contents($f,false,$context);
		}else{
			return file_get_contents($f);
		}
		
	}
	
	protected function get_buffer_key(){	
		$seeds=array_merge($this->seeds,[
			'lib' => Manager::parseLibraryFile(),
			"config" => config("asm.streams",[]),
			"ajax" => request()->ajax()
		]);
		return sha1(json_encode($seeds));
	}
	
	protected function is_remote(string $w){
		return (stripos($w,"http://")===0 || stripos($w,"https://")===0);
	}
}