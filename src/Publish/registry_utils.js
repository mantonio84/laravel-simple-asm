Registry=objectPath({});
voidRegistry=true;

function _initRegistry(data){    
    var data=JSON.parse(atob(data));
    if (!voidRegistry){
        var currentData=Registry.get();
        Registry=objectPath($.extend(true,currentData,data));
    }else{
        Registry=objectPath(data);
    }    
	voidRegistry=false;
}
