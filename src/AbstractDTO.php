<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper;

use Exception;

abstract class AbstractDTO extends AbstractDataMapper {

	/**
	 * @param array|object ...$data
	 * @return static
	 * @throws Exception
	 */
	public static function fromData(array|object ...$data) : static {
		$array = array_map(function($item) {
			return (is_object($item)) ? get_object_vars($item) : $item;
		}, $data);
		return self::fromArray(array_merge(...$array));
	}

	/**
	 * @param object $object
	 * @return static
	 * @throws Exception
	 */
	public static function fromObject(object $object): static {
		return self::fromArray(get_object_vars($object));
	}

	/**
	 * @param array $data
	 * @return static
	 * @throws Exception
	 */
	public static function fromArray(array $data): static {
		return self::hydrateObject($data, static::class);
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		return get_object_vars($this);
	}
}
