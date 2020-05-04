<?php

namespace mantonio84\SimpleAsm\Commands;

use Illuminate\Console\Command;
use mantonio84\SimpleAsm\Classes\Cache as AsmCache;

class ClearCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asm:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Svuota la cache su disco di Laravel Simple Asset Manager';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
   
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {        
		$path=AsmCache::getCompiledRealPath();
		$files=array();
		$sz=0;
		if (is_dir($path)){				
			$files=scandir($path);
			if (!is_array($files)){
				$this->error("Impossibile aprire il percorso di cache su disco!");
				return;
			}
			$files=array_slice($files,2);			
			foreach ($files as $f){
				$f=$path.$f;
				$sz+=filesize($f);
				unlink($f);
			}
		}
		$this->line("Cache Laravel Simple Asset Manager svuotata: rimossi ".count($files)." dal disco per un totale di ".$this->format_file_size($sz));
    }
	
	protected function format_file_size($size) {
		$size = floatval($size);
		if ($size == 0)
			return "0 Bytes";
		$filesizename = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
		return $size ? round($size / pow(1024, ($i = floor(log(abs($size), 1024)))), 2) . $filesizename[$i] : '0 Bytes';
	}

}
