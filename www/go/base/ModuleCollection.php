<?php
/**
 * Group-Office
 * 
 * Copyright Intermesh BV. 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 *
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @license AGPL/Proprietary http://www.group-office.com/LICENSE.TXT
 * @link http://www.group-office.com
 * @copyright Copyright Intermesh BV
 * @version $Id: Number.php 7962 2011-08-24 14:48:45Z mschering $
 * @author Merijn Schering <mschering@intermesh.nl>
 * @package GO.base
 */

/**
 * A collection that holds all the installed modules.
 * 
 * @author Merijn Schering <mschering@intermesh.nl>
 * @version $Id: config.class.inc.php 7687 2011-06-23 12:00:34Z mschering $
 * @copyright Copyright Intermesh BV.
 * @package GO.base 
 */

namespace GO\Base;


class ModuleCollection extends Model\ModelCollection{
	
	private $_allowedModules;
	
	public function __construct($model='GO\Base\Model\Module'){

		parent::__construct($model);
	}
	
	private function _isAllowed($moduleid){
		
		if(!isset($this->_allowedModules))
			$this->_allowedModules=empty(\GO::config()->allowed_modules) ? array() : explode(',', \GO::config()->allowed_modules);
		
		return empty($this->_allowedModules) || in_array($moduleid, $this->_allowedModules);			
	}
	
	/**
	 * Returns an array of all module classes as string found in the modules folder.
	 * 
	 * @return array Module class names eg. \GO\Calendar\Module
	 */
	public function getAvailableModules($returnInstalled=false){
		$folder = new Fs\Folder(\GO::config()->root_path.'modules');
		
		$folders = $folder->ls();
		$modules = array();
		foreach($folders as $folder){
			if($folder->isFolder()){
				$ucfirst = ucfirst($folder->name());
//				$moduleClass = $folder->path().'/'.$ucfirst.'Module.php';
				if($this->isAvailable($folder->name()) && ($returnInstalled || !Model\Module::model()->findByPk($folder->name(), false, true))){
					$modules[]='GO\\'.$ucfirst.'\\'.$ucfirst.'Module';
				}
			}
		}
		
		return $modules;		
	}
	
	/**
	 * Check if a module is available
	 * 
	 * @param string $moduleId
	 * @return boolean
	 */
	public function isAvailable($moduleId){
		
		if(!$this->_isAllowed($moduleId))
			return false;
		
		$folder = new Fs\Folder(\GO::config()->root_path.'modules/'.$moduleId);
		
		$ucfirst = ucfirst($moduleId);
		$moduleClassPath = $folder->path().'/'.$ucfirst.'Module.php';
		
		if(!file_exists($moduleClassPath) || !\GO::scriptCanBeDecoded($moduleClassPath)){
			return false;
		}

		$moduleClass = 'GO\\'.$ucfirst.'\\'.$ucfirst.'Module';

		if(!class_exists($moduleClass)){
			return false;
		}

		$mod = new $moduleClass;
		return $mod->isAvailable();			

	}
	
	
	
	

	/**
	 * Call a method of a module class. eg. \GO\Notes\NotesModule::firstRun
	 * 
	 * @deprecated Preferrably use events with listeners because it has better performance
	 * @param string $method
	 * @param array $params 
	 */
	public function callModuleMethod($method, $params=array(), $ignoreAclPermissions=true){
		
		$oldIgnore = \GO::setIgnoreAclPermissions($ignoreAclPermissions);
		$modules = $this->getAllModules();
		
		foreach($modules as $module)
		{	
//			if($this->_isAllowed($module->id)){
				$file = $module->path.ucfirst($module->id).'Module.php';
				//todo load listeners
				if(file_exists($file)){
					//require_once($file);
					$class='GO\\'.ucfirst($module->id).'\\'.ucfirst($module->id).'Module';

					$object = new $class;
					if(method_exists($object, $method)){					
//						\GO::debug('Calling '.$class.'::'.$method);
						call_user_func_array(array($object, $method), $params);
						//$object->$method($params);
					}
				}
//			}
		}
		
		\GO::setIgnoreAclPermissions($oldIgnore);
	}
	
	private $_modules;
	
	public function __get($name) {
		
		if(!isset($this->_modules[$name])){		
			if(!$this->isAvailable($name))
				return false;

			$model = parent::__get($name);

			if(!$model)
				$model=false;

			$this->_modules[$name]=$model;
		}
		
		return $this->_modules[$name];
	}
	
	/**
	 * Check if a module is installed.
	 * 
	 * @param string $moduleId
	 * @return Model\Module 
	 */
	public function isInstalled($moduleId){
		$model = $this->model->findByPk($moduleId, false, true);
		
		if(!$model || !$this->_isAllowed($model->id) || !$model->isAvailable())
				return false;
		
		return $model;
	}
	
	
	
	
	public function __isset($name){
		try{
			$module = $this->$name;
			return isset($module);
		}catch(Exception\AccessDenied $e){
			return false;
		}
	}
	
	/**
	 * Query all modules.
	 * 
	 * @return Model\Module[]
	 */
	public function getAllModules($ignoreAcl=false){
		
		$findParams = Db\FindParams::newInstance()->order("sort_order");
		
		if($ignoreAcl)
			$findParams->ignoreAcl ();
		
		$stmt = $this->model->find($findParams);
		$modules = array();
		while($module = $stmt->fetch()){
			if($this->_isAllowed($module->id) && $module->isAvailable())
				$modules[]=$module;
		}
		
		return $modules;
	}
	
	/**
	 * Find all classes in a folder of all modules.
	 * 
	 * For example findClassses("model") finds all models.
	 * 
	 * @param string $subfolder
	 * @return ReflectionClass array
	 */
	public function findClasses($subfolder){
		
		$classes =array();
		$modules = $this->getAllModules();
		
		foreach($modules as $module)
			$classes = array_merge($classes, $module->moduleManager->findClasses($subfolder));
		
		return $classes;
	}
}