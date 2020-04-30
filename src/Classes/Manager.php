<?php
namespace mantonio84\SimpleAsm\Classes;
use Illuminate\View\View;
use Cache;
use Spatie\Url\Url as UrlManipulator;

class Manager {
	protected $views=[];
	protected $lib=null;
	protected $lib_hash=null;
	protected $others=[];
	protected $kv=array();	
	protected $reg=null;
	
	public function __construct(){
		$this->reg=new Registry();
		$nf=$this->get_library_file_path();
		if (!empty($nf)){
			$this->lib_hash=sha1_file($nf);
		}
	}
	
	public function acceptView(View &$view){
		$nm=$view->getName();
		if (!in_array($nm,$this->views)){
			$this->views[]=$nm;
		}			
	}
	
	public function registry($key=null, $value=null){
		if (is_string($key) && !empty($key)){
			if (is_null($value)){
				return $this->reg[$key];
			}else{
				$this->reg[$key]=$value;
			}
		}else{
			return $this->reg;
		}
	}
	
	public function push($w){
		$this->others=array_values(array_unique(array_merge($this->others,is_array($w) ? $w : func_get_args())));
	}
	
	public function dump($stream){
		if (!is_string($stream)){
			return "";
		}
		$stream=strtolower(trim($stream));
		if (!in_array($stream,$this->get_all_sections()){
			return "";
		}		
		$ret=array("<!-- begin mma:$stream -->");
		if ($stream=="javascript"){
			if ($this->reg->isNotEmpty()){
				if (!request()->ajax()){
											
					$uid=uniqid("___mmareginit");
					$ret[]="<script type=\"text/javascript\"> \n\r function $uid(){ _initRegistry(\"".base64_encode($this->reg)."\"); } \n\r </script>";			
					$ret[]=$this->get_stubbed("/vendor/mantonio84/simpleasm/objectpath.min.js");		
					$ret[]=str_ireplace("<script ","<script onload=\"$uid();\" ",$this->get_stubbed("/vendor/mantonio84/simpleasm/registry_utils.js"));
				}else{
					$ret[]="<script type=\"text/javascript\"> \n\r _initRegistry(\"".base64_encode($this->reg)."\"); \n\r </script>";
				}
			}else{
				if (!request()->ajax()){
					$ret[]=$this->get_stubbed("/vendor/mantonio84/simpleasm/objectpath.min.js");	
					$ret[]=$this->get_stubbed("/vendor/mantonio84/simpleasm/registry_utils.js");
				}
			}
		}
		
		$bkey="simple-asset-manager.".sha1(json_encode("config" => config("asm.streams",[]), "ajax" => request()->ajax(), "lib" => $this->lib_hash, "others" => $this->others, "views" => $this->views));
		
		if (config("asm.cache.server",true)===true){		
			$computedAssets=Cache::rememberForever($bkey, function (){
				return $this->evaluate_all();
			});
		}else{
			Cache::forget($bkey);
			$computedAssets=$this->evaluate_all();
		}
						
		$computedAssets=array_map([$this,"get_stubbed"],\Arr::get($computedAssets , $stream, []));
		
		$ret=array_merge($ret,$computedAssets);
		
		$ret[]="<!-- end mma:$stream -->"
		return "\n\r".implode("\n\r",$ret)."\n\r";
	}
	
	protected function get_stubbed(string $asset){
		$q=$this->get_qualified_asset_path($f);
		$stub=config("asm.streams.$stream.stub","%file%");
		if (strlen($stub)>1){
			if ($stub[0]=="@"){
				$f=resource_path(substr($stub,1));				
				if (is_file($f)){
					$stub=file_get_contents($f);
				}else{
					return $q;
				}
			}
		}
		return str_replace("%file%",$q,$stub);
	}
	
	protected function get_qualified_asset_path(string $asset){
		if (!$this->is_remote($asset)){
			$asset=asset($asset);
		}
		$client_cache_enabled=(config("asm.cache.client",true)===true);
		if (!$client_cache_enabled){
			$asset = (string) UrlManipulator::fromString($asset)->withQueryParameter("_asm",microtime(true));
		}
		return $asset;		
	}
	
	protected function evaluate_all(){		
			
		$computedAssets=array_fill_keys($this->get_all_sections(),array());
		$this->loadLibrary();	
		if (empty($this->lib)){			
			return  $computedAssets;
		}
		
		$required=array_merge(
			["every","request"],
			[request()->ajax() ? "ajax" : "display", "request"],
			array_map(function ($itm){
				return ["views", $itm];
			},$this->views),
			array_map(function ($itm){
				return ["packages", $itm];
			},$this->others);
		);		
		
		$dep=array();			
		foreach ($required as $r){
			$a=$this->evaluate($r[0],$r[1],true,$dep);
			$dep=array_merge($dep,$a['included']);
			foreach ($a['found'] as $f){
				$w=$this->get_appropriate_section($f);
				$computedAssets[$w][]=$f;
			}
		}				
		
		foreach (config("asm.streams",[]) as $name => $config){
			if (\Arr::get($config,"auto_find_on_view")===true){
				$extensions=\Arr::get($config,"extensions",[]);
				if (!empty($extensions)){					
					foreach ($extensions as $e){
						$f="views/".str_replace(".","/",$view).".".$e;
						if (is_file(public_path($f))){
							 $computedAssets[$name][]=$f;					
						}
					}	
				}
			}
		}
		
		$computedAssets=array_map(function ($itm){
			return array_values(array_unique($itm));
		}, $computedAssets);
		
				
		return  $computedAssets;
	}
	
	
	protected function evaluate(string $name, string $root, bool $recursive=true, array $to_exclude=[]){
		$this->loadLibrary();				
		if (isset($this->lib[$root]) && isset($this->lib[$root][$name]) && !in_array($name,$to_exclude)){
			$ret=[];
			$dep=array();
			foreach ($this->lib[$root][$name] as $asset){
				$asset=trim($asset);
				if (is_string($asset) && !empty($asset)){					
					if ($asset[0]=="@"){
						if ($recursive){
							$a=$this->evaluate(substr($asset,1), "packages", true, array_merge($dep,$to_exclude));
							$ret=array_merge($ret,$a['found']);
							$dep=array_merge($dep,$a['included']);
						}						
					}else{
						$dep[]=$name;
						$ret[]=$this->is_remote($asset) ? $asset : \Str::start($asset,"/");						
					}
				}
			}
			return ["found" => $ret, "included" => $dep];
		}		
		return ["found" => [], "included" => []];
	}
	
	protected function loadLibrary(){
		if (is_null($this->lib)){
			$this->lib=array();			
			$nf=$this->get_library_file_path();
			if (!empty($nf)){				
				$this->lib=json_decode(file_get_contents($nf),true);
				if (!is_array($this->lib)){
					$this->lib=array();
				}
			}
		}
	}
	
	protected function get_library_file_path(){
		$this->lib=array();			
		$nf=config("asm.library","");
		if (!empty($nf) && is_file($nf)){			
			return $nf;
		}
		return null;
	}
	
	protected function get_all_sections(){
		return array_keys(config("asm.streams",[]));
	}
	
	protected function get_appropriate_section(string $w){
		$e=$this->get_extension($w);
		if (empty($e)){
			return null;
		}
		if (!isset($this->kv[$e])){
			$sections=config("asm.streams",[]);
			$found=null;
			foreach ($sections as $name => $config){				
				if (in_array($e,\Arr::get($config,"extensions",[]))){
					$found=$name;
					break;
				}
			}
			$this->kv[$e]=$found;
		}
		return $this->kv[$e];
	}
	
	protected function get_extension(string $w){
		if ($this->is_remote($w)){
			$w=parse_url($w, PHP_URL_PATH);
		}
		$e=strtolower(pathinfo($w,PATHINFO_EXTENSION));
		return is_string($e) ? $e : "";
	}
	
	protected function is_remote(string $w){
		return (stripos($w,"http://")===0 || stripos($w,"https://")===0);
	}
}