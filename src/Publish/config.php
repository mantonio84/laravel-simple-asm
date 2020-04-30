<?php
return [
	"library" => resource_path("assets.json"),	
	"streams" => [
		"javascript" => [
						 "stub" => "<script type=\"text/javascript\" src=\"%path%\"></script>",
						 "extensions" => ["js","js.map","min.js"],
						 "auto_find_on_view" => true,
						],
		"css" => [
					"stub" => "<link rel=\"stylesheet\" type=\"text/css\" href=\"%path%\">",	
					"extensions" => ["css","min.css"],
					"auto_find_on_view" => true,
				],
	],
	"cache" => [	
		"server" => (env('APP_ENV', 'production')==="production"),
		"client" => (env('APP_DEBUG', false)===false)
	]	
];
