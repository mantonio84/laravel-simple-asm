__lvmccr__ = function(){
	var registry_data = {};
	var first=true;
	function fill(data){		
		try {
			var rd=JSON.parse(atob(data)); 			
		}catch(err) {			
			return false;			
		}
		if (first===false){			
			Object.assign(registry_data,rd);			
		}else{
			registry_data=rd;
			first=false;
		}   		
		return true;		
	} 
	function dump(){
		return objectPath.get(registry_data);
	}
	function get(key, def){
		return objectPath.get(registry_data, key, def);
	}
	function has(key){
		if (typeof key === "undefined" || key === null){
			return !first;
		}
		return objectPath.has(registry_data, key);
	}
	function only(keys){
		if (!Array.isArray(keys)){
			return {};
		}
		var ret={};
		for (var i=0;i<keys.length;i++){
			var k=keys[i];
			if (has(k)){
				ret[k]=get(k);
			}
		}
		return ret;
	}
  return{
    fill: fill,
    get: get,
	has: has,
	toObject: dump,
	only: only,
  }
};

Registry = __lvmccr__();
Environment = __lvmccr__();