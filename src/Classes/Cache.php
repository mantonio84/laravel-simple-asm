<?php
namespace mantonio84\SimpleAsm\Classes;
use MatthiasMullie\Minify;

class Cache {
	
	protected $seeds=array();	

	public static function make(array $seeds=[]){
		return new static($seeds);
	}
	
	public static function getCompiledPath(string $filename){
		return preg_replace('/[\/]+/','/',\Str::start($filename, 'asm/'));
	}
	
	public static function getCompiledRealPath(string $filename=""){
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
			   $compiler->addFile($f);
		   }
		   $compiler->minify($filepath);
		}
		
		return [basename($filepath)];
	}
	
	protected function get_buffer_key(){	
		$seeds=array_merge($this->seeds,[
			'lib' => Manager::parseLibraryFile(),
			"config" => config("asm.streams",[]),
			"ajax" => request()->ajax()
		]);
		return sha1(json_encode($seeds));
	}
}