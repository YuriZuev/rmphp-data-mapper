<?php

namespace Rmphp\DataMapper;

abstract class AbstractEntity {

	/**
	 * @param string $name
	 * @param $value
	 * @return void
	 */
	public function __set(string $name, $value): void {
		$this->$name = $value;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function __get(string $name) {
		return $this->$name ?? null;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset(string $name): bool {
		return isset($this->$name);
	}

}
