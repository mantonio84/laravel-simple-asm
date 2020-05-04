<?php
namespace mantonio84\SimpleAsm\Classes;
use Illuminate\View\View;
use Spatie\Url\Url as UrlManipulator;


class Manager {
	public static $library=null;
	
	protected $views=[];
	
	protected $others=[];
	protected $kv=array();	
	protected $reg=null;
	protected $computed=null;
	
	public static function getLibraryFilePath(){
		$nf=config("asm.library","");
		if (!empty($nf) && is_file($nf)){			
			return $nf;
		}
		return null;
	}
	
	public static function parseLibraryFile(bool $forced=false){
		if (is_null(static::$library) || $forced===true){
			static::$library=array();
			$nf=static::getLibraryFilePath();
			if (!empty($nf)){				
				static::$library=json_decode(file_get_contents($nf),true);
			}
			static::$library = is_array(static::$library) ? static::$library : array();
		}
		return static::$library;
	}
	
	public static function saveLibrary(array $data){
		$nf=static::getLibraryFilePath();
		if (!empty($nf)){				
			file_put_contents($nf,json_encode($data));
			return true;
		}	
		return false;
	}
	
	public function __construct(){
		$this->reg=new Registry();		
	}
	
	public function acceptView(View &$view){
		$nm=$view->getName();
		if (!in_array($nm,$this->views)){
			$this->views[]=$nm;
			$this->computed=null;
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
		$this->computed=null;
	}
	
	public function dump($stream){		
		if (!is_string($stream) || empty($stream)){
			return "";
		}
		$stream=strtolower(trim($stream));
		if (!in_array($stream,$this->get_all_sections())){
			return "";
		}		
						
		$ret=array("<!-- begin mma:$stream -->");
		if ($stream=="javascript"){			
			if (!request()->ajax()){
				$uid="";
				if ($this->reg->isNotEmpty()){
					$uid=uniqid("___mmareginit");
					$ret[]="<script type=\"text/javascript\"> \n\r function $uid(){ Registry.fill(\"".$this->reg->toBase64()."\"); } \n\r </script>";			
					$uid.="();";
				}
				$ret[]=$this->stub_compile($this->get_qualified_asset_path("/vendor/mantonio84/simpleasm/objectpath.min.js"),"<script type=\"text/javascript\" src=\"%file%\"></script>");
				$ret[]=$this->stub_compile($this->get_qualified_asset_path("/vendor/mantonio84/simpleasm/registry_utils.min.js"),"<script onload=\"$uid\" type=\"text/javascript\" src=\"%file%\"></script>");				
			}else{
				if ($this->reg->isNotEmpty()){
					$ret[]="<script type=\"text/javascript\"> \n\r Registry.fill(\"".$this->reg->toBase64()."\");  \n\r </script>";			
				}
			}
		}
		
		
		
		$computedAssets=Cache::make(["others" => $this->others, "views" => $this->views])->remember($stream, function ($stream){
			return \Arr::get($this->evaluate_all,$stream,[]);
		});
		
		foreach ($computedAssets as $asset){
			$ret[]=$this->get_stubbed($asset,$stream);
		}
		
		
		$ret[]="<!-- end mma:$stream -->";
		return "\n\r".implode("\n\r",$ret)."\n\r";
	}
	
	protected function get_stubbed(string $asset, $stream == null){
		$q=$this->get_qualified_asset_path($asset);
		
		$strem=is_string($stream) ? $stream : $this->get_appropriate_section($asset);
		if (empty($stream)){
			return $q;
		}
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
		return $this->stub_compile($q,$stub);
	}
	
	protected function stub_compile(string $qualified_asset_path, string $stub){
		return str_replace("%file%",$qualified_asset_path,$stub);
	}
	
	protected function get_qualified_asset_path(string $asset){
		if (!$this->is_remote($asset)){
			return asset($asset);
		}else{
			return $asset;		
		}
	}
	
	protected function evaluate_all(bool $forced=false){				
			
		if (is_null($this->computed) || $forced===true){
			
			$computedAssets=array_fill_keys($this->get_all_sections(),array());
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
			
					
			$this->computed=$computedAssets;
		}
		return $this->computed;
	}
	
	
	protected function evaluate(string $name, string $root, bool $recursive=true, array $to_exclude=[]){
		static::parseLibraryFile();
		if (isset(static::$library[$root]) && isset(static::$library[$root][$name]) && !in_array($name,$to_exclude)){
			$ret=[];
			$dep=array();
			foreach (static::$library[$root][$name] as $asset){
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