<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper;

use Exception;
use ReflectionException;
use Stringable;

class DataMapper extends AbstractDataMapper {

	/**
	 * @param object $object
	 * @param callable|null $method
	 * @return array
	 * @throws Exception
	 */
	public function extract(object $object, ?callable $method = null) : array {
		try {
			$reflection = self::getClassReflection($object);

			$fieldValue = [];
			foreach($reflection->getProperties() as $property){
				$propertyName = $property->getName();

				$mapAttribute = self::getMapAttribute($object, $property);

				if($mapAttribute->ignoreExtract) continue;

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

					if(is_array($propertyValue)) {
						continue;
					}

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
	 * Полный рекурсивный дамп объекта: массивы объектов и одиночные объектные
	 * свойства (в том числе другие Entity, а не только VO с get()/Stringable)
	 * разворачиваются рекурсивно, а не теряются молча. Управляется отдельным
	 * флагом Map::$ignoreNormalize, независимым от extract().
	 *
	 * @param object $object
	 * @param callable|null $method
	 * @return array
	 * @throws Exception
	 */
	public function normalize(object $object, ?callable $method = null) : array {
		try {
			$reflection = self::getClassReflection($object);

			$fieldValue = [];
			foreach($reflection->getProperties() as $property){
				$propertyName = $property->getName();

				$mapAttribute = self::getMapAttribute($object, $property);

				if($mapAttribute->ignoreNormalize) continue;

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

					if(is_array($propertyValue)) {
						$fieldValue[$fieldName] = array_map(
							fn($item) => is_object($item) ? $this->normalize($item, $method) : $item,
							$propertyValue
						);
						continue;
					}

					if($reflection->hasMethod('get'.ucfirst($propertyName))){
						$fieldValue[$fieldName] = $object->{'get'.ucfirst($propertyName)}();
					}
					elseif(is_object($propertyValue)){
						if(method_exists($propertyValue, 'get')){
							$fieldValue[$fieldName] = $propertyValue->get();
						}
						elseif($propertyValue instanceof Stringable) {
							$fieldValue[$fieldName] = (string)$propertyValue;
						}
						else {
							$fieldValue[$fieldName] = $this->normalize($propertyValue, $method);
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
	 * @return object
	 * @throws Exception
	 * @throws DataMapperException Если поле, отмеченное strict, не было инициализировано при гидратации
	 */
	public function hydrate(array $data, string|object $target): object {
		return self::hydrateObject($data, $target);
	}

	/**
	 * @param object $object
	 * @param array|object $data
	 * @param bool $clone
	 * @return object
	 * @throws Exception
	 * @throws DataMapperException Если поле, отмеченное strict, не было инициализировано при гидратации
	 */
	public function update(object $object, array|object $data, bool $clone = false) : object {
		$newObject = $clone ? clone $object : $object;
		return self::hydrateObject((is_object($data)) ? $this->extract($data) : $data, $newObject, true);
	}

	/**
	 * @param object $source
	 * @param string|object $target
	 * @param callable|null $method
	 * @return object
	 * @throws Exception
	 * @throws DataMapperException Если поле, отмеченное strict, не было инициализировано при гидратации
	 */
	public function map(object $source, string|object $target, ?callable $method = null): object {
		$data = $this->extract($source, $method);
		return $this->hydrate($data, $target);
	}

}
