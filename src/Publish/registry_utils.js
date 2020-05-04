Registry = function(){
	var registry_data = objectPath({});
	var first=true;
	function fill(data){
		try {
			var rd=JSON.parse(atob(data)); 
		}catch(err) {
			return false;
		}
		if (first===false){
			var currentData=Registry.get();
			registry_data=objectPath(Object.assign({},currentData,rd));			
		}else{
			registry_data=objectPath(rd);
			first=false;
		}   
		return true;		
	} 
	function dump(){
		return registry_data.get();
	}
  return{
    fill: fill,
    get: registry_data.get,
	has: registry_data.has,
	toObject: dump,	
  }
}();