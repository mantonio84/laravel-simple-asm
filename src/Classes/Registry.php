<?php
namespace mantonio84\SimpleAsm\Classes;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class Registry implements \ArrayAccess, Arrayable, Jsonable, JsonSerializable {
        
    private $data=array();
   
    
    public function get(string $path, $default=null){
        return \Arr:get($this->data,$path,$default);        
    }
    
    public function has($path){
        return \Arr::has($this->data,$path);
    }
    
    public function put($path, $value=null){
        \Arr::set($this->data,$path,$value);       
    }
    
    public function forget(string $path){
        \Arr::forget($this->data,$path);
    }
    
    public function clear(){
        $this->data=array();
    }
    
    public function toArray(){
        return array_map(function ($value){
			return $value instanceof Arrayable ? $value->toArray() : $value;
		},$this->data);
    }
    
    public function isEmpty(){
        return empty($this->data);
    }
	
	public function isNotEmpty(){
        return !empty($this->data);
    }
    
    public function fromArray(array $arr){
        $this->data=$arr;
    }  
	
	public function fromJson(string $jsonData){
		$this->data=json_decode($jsonData,true);
		if (!is_array($this->data)){
			$this->clear();
		}
	}
	
	public function merge(array $arr){
        $this->data=array_merge($this->data,$arr);
    }  
	
	public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return array_key_exists($this->data,$offset);
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset) {
        return $this->offsetExists($offset) ? $this->data[$offset] : null;
    }
	
	public function __get($name){
		return $this->offsetGet($name);
	}
	
	public function __set($name,$value){
		$this->offsetSet($name,$value);
	}
	
	public function __isset($name){
		return $this->offsetExists($name);
	}
	
	public function __unset($name){
		return $this->offsetUnset($name);
	}
	
	public function all(){
		return $this->data;
	}
	
	public function jsonSerialize() {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            }

            return $value;
        }, $this->all());
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
	
	public function __toString(){
		return $this->toJson();
	}
	
	
}
