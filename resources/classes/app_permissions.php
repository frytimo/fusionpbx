<?php

class app_permissions {

	private array $names;
	protected string $group_name;
	private string $prefix;
	private string $permission_name;

	public function __construct(string $prefix = '') {
		if (!empty($prefix)) {
			$this->prefix($prefix);
		}
	}

	public function add(string $name, array $groups = []): self {
		$this->names[$name] = $groups;
		return $this;
	}

	public function group(string $group_name): self {
		$this->group_name = $group_name;
		return $this;
	}

	public function groups(array $groups): self {
		foreach ($groups as $group) {
			$this->names[$this->permission_name][] = $group;
		}
		return $this;
	}

	public function meld(array $names, array $groups): self {
		foreach ($names as $name) {
			foreach ($groups as $group) {
				$this->names[$name][] = $group;
			}
		}
		return $this;
	}

	public function name(string $permission): self {
		$permission = $this->prefix . $permission;
		$this->names[$permission][] = $this->group_name;
		$this->permission_name = $permission;
		return $this;
	}

	public function names(array $names): self {
		foreach ($names as $name) {
			$name = $this->prefix . $name;
			$this->names[$name][] = $this->group_name;
		}
		return $this;
	}

	public function prefix(string $prefix): self {
		if (!str_ends_with($prefix, '_')) {
			$prefix .= '_';
		}
		$this->prefix = $prefix;
		return $this;
	}

	public function to_array(): array {
		$permissions = [];
		foreach ($this->names as $name => $groups) {
			$permissions['name'] = $name;
			foreach ($groups as $group) {
				$permissions['groups'][] = $group;
			}
		}
		return $permissions;
	}

	public static function new(): self {
		return new self();
	}
}


// Example usage:
// $permissions = app_permissions::new()
// 	->prefix('call_active')
// 		->meld(
// 			['view', 'eavesdrop', 'hangup', 'domain'],
// 			['superadmin', 'admin']
// 		)
// 		->meld(
// 			['all','direction','profile','application','codec','secure'],
// 			['superadmin']
// 		)
// ;
//
// $permissions = app_permissions::new()
// 	->prefix('call_recordings')
//  	->meld(
//			['view','add','edit','delete'],
//			['superadmin','admin']
// 		)
// ;
// $permissions = app_permissions::new()
// 	->prefix('access_control')
// 		->group(group_name: 'superadmin')
// 			->names(
// 				'delete',
// 				'add',
// 				'view',
// 				'edit',
// 			)
// 	->prefix('access_control_node')
// 		->group(group_name: 'superadmin')
// 			->names(
// 				'delete',
// 				'add',
// 				'view',
// 				'edit',
// 			)
// ;
// $permissions = app_permissions::new()
// 	->prefix('call_active')
// 		->add('view'     , ['superadmin','admin'])
// 		->add('eavesdrop', ['superadmin','admin'])
// 		->add('hangup'   , ['superadmin','admin'])
// 		->group('superadmin')
// 			->names(
// 				'all',
// 				'direction',
// 				'profile',
// 				'application',
// 				'codec',
// 				'secure',
// 			)
// 		->add('domain', ['superadmin','admin'])
// ;

