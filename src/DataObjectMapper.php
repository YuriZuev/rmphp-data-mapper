<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper;

use Exception;
use ReflectionException;
use Stringable;

class DataObjectMapper extends AbstractDataMapper {

	/**
	 * @param object $object
	 * @param callable|null $method
	 * @return array
	 * @throws Exception
	 */
	public function extract(object $object, callable $method = null) : array {
		try {
			$reflection = self::getClassReflection($object);

			$fieldValue = [];
			foreach($reflection->getProperties() as $property){
				$propertyName = $property->getName();

				$mapAttribute = self::getMapAttribute($object, $property);

				if($mapAttribute->ignore) continue;

				if($mapAttribute->extractTo){
					$fieldName = $mapAttribute->extractTo;
				}
				elseif($mapAttribute->useSnakeCase){
					$fieldName = self::toSnake($propertyName);
				}
				else {
					$fieldName = $propertyName;
				}

				if($property->isInitialized($object)) {
					$propertyValue = $property->getValue($object);

					if(is_array($propertyValue)) continue;

					if($reflection->hasMethod('get'.ucfirst($propertyName))){
						$fieldValue[$fieldName] = $object->{'get'.ucfirst($propertyName)}();
					}
					elseif($property->hasType() && class_exists($property->getType()->getName())){
						if(is_object($propertyValue)){
							if(method_exists($propertyValue, 'get')){
								$fieldValue[$fieldName] = $propertyValue->get();
							}
							elseif($propertyValue instanceof Stringable) {
								$fieldValue[$fieldName] = (string)$propertyValue;
							}
						}
					}
					elseif(is_bool($propertyValue)){
						$fieldValue[$fieldName] = (int)$propertyValue;
					}
					else{
						$fieldValue[$fieldName] = $propertyValue;
					}
				}
				if(!array_key_exists($fieldName, $fieldValue) && $mapAttribute->fillNull){
					$fieldValue[$fieldName] = null;
				}
			}
			return (isset($method)) ? array_map($method, $fieldValue) : $fieldValue;
		}
		catch(ReflectionException $exception){
			throw new Exception($exception->getMessage());
		}

	}

	/**
	 * @param array $data
	 * @param string|object $target
	 * @return object|mixed
	 * @throws Exception
	 */
	public function hydrate(array $data, string|object $target): object {
		return self::hydrateObject($data, $target);
	}

	/**
	 * @param object $object
	 * @param array|object $data
	 * @return object
	 * @throws Exception
	 */
	public function update(object $object, array|object $data) : object {
		return $this->hydrate((is_object($data)) ? $this->extract($data) : $data, clone $object, true);
	}

	/**
	 * @param object $source
	 * @param string|object $target
	 * @param callable|null $method
	 * @return object
	 * @throws Exception
	 */
	public function map(object $source, string|object $target, ?callable $method = null): object {
		$data = $this->extract($source, $method);
		return $this->hydrate($data, $target);
	}

}
