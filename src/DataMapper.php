<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper;

use Exception;
use ReflectionException;
use Stringable;

class DataMapper extends AbstractDataMapper implements DataMapperInterface {

	/** @inheritDoc */
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

	/** @inheritDoc */
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

	/** @inheritDoc */
	public function hydrate(array $data, string|object $target): object {
		return self::hydrateObject($data, $target);
	}

	/** @inheritDoc */
	public function update(object $object, array|object $data, bool $clone = false) : object {
		$newObject = $clone ? clone $object : $object;
		return self::hydrateObject((is_object($data)) ? $this->extract($data) : $data, $newObject, true);
	}

	/** @inheritDoc */
	public function map(object $source, string|object $target, ?callable $method = null): object {
		$data = $this->extract($source, $method);
		return $this->hydrate($data, $target);
	}

}
