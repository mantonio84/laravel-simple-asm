<?php

namespace mantonio84\SimpleAsm;

use Illuminate\Support\ServiceProvider;
use mantonio84\SimpleAsm\Commands\ClearCache;

class ASMServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('simple-asset-manager', function ($app) {
			return new mantonio84\SimpleAsm\Classes\Manager;
		});
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
		if ($this->app->runningInConsole()) {
			$this->commands([
				ClearCache::class					
			]);
		}
	
		$this->publishes([
			__DIR__.'/Publish/config.php' => config_path('asm.php'),
			__DIR__.'/Publish/assets.json' => resource_path("assets.json"),
		]);
		
		$this->publishes([
			__DIR__.'/Publish/registry_utils.js' => public_path('vendor/mantonio84/simpleasm/registry_utils.min.js'),
			__DIR__.'/Publish/objectpath.min.js' => public_path('vendor/mantonio84/simpleasm/objectpath.min.js'),
		], 'public');
		
        \View::creator('*', function(&$view) {               
            app("simple-asset-manager")->acceptView($view);            
        });
		
		\Blade::directive("ASM", function ($expression){
			return "<?php echo app(\"simple-asset-manager\")->dump($expression); ?>";
        });
		
    }

}
