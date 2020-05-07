<?php
namespace mantonio84\SimpleAsm\Classes;
use MatthiasMullie\Minify;

class Cache {

	protected $seeds=array();	
        
        public static function make(array $seeds=[]){
            return new static($seeds);
        }

	public function __construct(array $seeds=[]){
		$this->seeds=$seeds;
	}
	
	public function remember(string $stream, \Closure $filler){		
		if (config("asm.cache",true)!==true){		
			return $this->map_files_to_url($this->run_filler($filler,$stream));
		}		
		return cache()->remember($this->get_buffer_key($stream), now()->addWeek(), function () { 
			return $this->map_files_to_url($this->run_filler($filler,$stream));
		});		
	}
	
	
	protected function map_files_to_url(array $files){
		return array_values(array_filter(array_map(function ($itm) {
			if ($this->is_remote($itm)){
				return $itm;
			}else{
				return asset($itm);
			}
		},$files)));
	}
	
	
	protected function run_filler(\Closure $filler, string $stream){
		$files=call_user_func($filler,$stream);
		return is_array($files) ? $files : [];		
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
			return file_get_contents($f,false,$context) ?? "";
		}else{
			return file_get_contents($f) ?? "";
		}
		
	}
	
	protected function get_buffer_key(string $stream){	
		$seeds=array_merge($this->seeds,[
			'stream' => $stream,
			'lib' => Manager::getLibraryHash(),
			"extensions" => config("asm.streams.$stream.extensions",[]),
			"ajax" => request()->ajax(),
		]);
		return sha1(json_encode($seeds));
	}
	
	protected function is_remote(string $w){
		return (stripos($w,"http://")===0 || stripos($w,"https://")===0);
	}
}