<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper;

use Exception;

interface DataMapperInterface {

	/**
	 * @param object $object
	 * @param callable|null $method
	 * @return array
	 * @throws Exception
	 */
	public function extract(object $object, ?callable $method = null): array;

	/**
	 * @param object $object
	 * @param callable|null $method
	 * @return array
	 * @throws Exception
	 */
	public function normalize(object $object, ?callable $method = null): array;

	/**
	 * @param array $data
	 * @param string|object $target
	 * @return object
	 * @throws Exception
	 * @throws DataMapperException
	 */
	public function hydrate(array $data, string|object $target): object;

	/**
	 * @param object $object
	 * @param array|object $data
	 * @param bool $clone
	 * @return object
	 * @throws Exception
	 * @throws DataMapperException
	 */
	public function update(object $object, array|object $data, bool $clone = false): object;

	/**
	 * @param object $source
	 * @param string|object $target
	 * @param callable|null $method
	 * @return object
	 * @throws Exception
	 * @throws DataMapperException
	 */
	public function map(object $source, string|object $target, ?callable $method = null): object;

}
