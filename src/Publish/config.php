<?php
return [
	"library" => resource_path("assets.json"),	
	"streams" => [
		"js" => [					     
						 "stub" => "<script type=\"text/javascript\" src=\"%file%\"></script>",
						 "extensions" => ["js"],
						 "auto_find_on_view" => true,
						],
		"css" => [						
					"stub" => "<link rel=\"stylesheet\" type=\"text/css\" href=\"%file%\">",	
					"extensions" => ["css"],
					"auto_find_on_view" => true,
				],
	],
	"cache" => (env('APP_ENV', 'production')==="production"),
];
