<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Rmphp\DataMapper\Attribute\Map;
use Throwable;

abstract class AbstractDataMapper {

	protected static array $reflectionClassCache = [];
	protected static array $attributeCache = [];
	protected static array $classEmptyAvailableCache = [];

	/**
	 * @throws Exception
	 * @throws DataMapperException Если строгое (strict) свойство не удалось инициализировать при гидратации
	 */
	protected static function hydrateObject(array $data, string|object $target, bool $update = false): mixed {
		try {

			$object = is_string($target) ? new $target() : $target;
			$reflection = self::getClassReflection($object);

			$value = [];
			foreach($reflection->getProperties() as $property){
				$propertyName = $property->getName();
				$mapAttribute = self::getMapAttribute($object, $property);

				if($mapAttribute->ignore || $mapAttribute->ignoreHydrate) continue;

				// по значению а атрибуте
				if($mapAttribute->hydrateFrom && array_key_exists($mapAttribute->hydrateFrom, $data)){
					$value[$propertyName] = $data[$mapAttribute->hydrateFrom];
				}
				// значение в массиве по ключю с именем свойства
				elseif(array_key_exists($propertyName, $data)){
					$value[$propertyName] = $data[$propertyName];
				}
				// значение в массиве по ключю с именем свойства в snake case
				elseif(array_key_exists(self::toSnake($propertyName), $data)){
					$value[$propertyName] = $data[self::toSnake($propertyName)];
				}
				elseif($update){
					continue;
				}


				$propertyTypeName = $property->hasType() ? $property->getType()->getName() : null;

				// если есть внутренний метод (приоритетная обработка)
				if($reflection->hasMethod('set'.ucfirst($propertyName))){
					$object->{'set'.ucfirst($propertyName)}($value[$propertyName] ?? null);
				}
				// Если тип свойства класс
				elseif($propertyTypeName && class_exists($propertyTypeName)){
					if(array_key_exists($propertyName, $value)){
						// значение объект
						if($value[$propertyName] instanceof $propertyTypeName){
							$object->{$propertyName} = $value[$propertyName];
						}
						// NewInstance
						elseif($propertyObject = self::instanceByValue($propertyTypeName, $value[$propertyName])){
							$object->{$propertyName} = $propertyObject;
						}
					}
					// Значения нет и VO может быть без параметров
					elseif(self::isEmptyAvailable($propertyTypeName)){
						$object->{$propertyName} = new ($propertyTypeName);
					}
				}

				// Базовые типы при наличии значения
				elseif(array_key_exists($propertyName, $value)){

					// Base: Hasn`t type
					if(!$propertyTypeName){
						$object->{$propertyName} = $value[$propertyName];
					}
					// Base: Number
					elseif(in_array($propertyTypeName, ['float', 'int'])){
						if(is_numeric($value[$propertyName])) $object->{$propertyName} = $value[$propertyName];
					}
					// Base: Boolean
					elseif($propertyTypeName == 'bool'){
						$object->{$propertyName} = (bool)$value[$propertyName];
					}
					// Base: NotNull
					elseif(isset($value[$propertyName])){
						$object->{$propertyName} = $value[$propertyName];
					}
					// Base: Null
					elseif($property->getType()->allowsNull()){
						$object->{$propertyName} = $value[$propertyName];
					}
				}

				// Strict: обязательное поле не инициализировано - объект не создаётся
				if($mapAttribute->strict && !$property->isInitialized($object)){
					throw new DataMapperException("Property '{$propertyName}' is required (strict) but was not initialized while hydrating ".get_class($object));
				}
			}
			return $object;
		} catch(ReflectionException $exception){
			throw new Exception($exception->getMessage());
		}
	}


	/**
	 * @param object $class
	 * @return ReflectionClass
	 */
	protected static function getClassReflection(object $class): ReflectionClass {
		return self::$reflectionClassCache[get_class($class)] ??= new ReflectionClass(get_class($class));
	}

	/**
	 * @param object $class
	 * @param ReflectionProperty $property
	 * @return Map
	 */
	protected static function getMapAttribute(object $class, ReflectionProperty $property): Map {
		$propertyName = $property->getName();
		if(!isset(self::$attributeCache[get_class($class)][$propertyName])){
			self::$attributeCache[get_class($class)][$propertyName] = !empty($property->getAttributes(Map::class))
				? $property->getAttributes(Map::class)[0]->newInstance()
				: new Map();
		}
		return self::$attributeCache[get_class($class)][$propertyName];
	}

	/**
	 * @param string $camel
	 * @return string
	 */
	protected static function toSnake(string $camel): string {
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
	}

	/**
	 * @param string $className
	 * @return bool
	 */
	protected static function isEmptyAvailable(string $className): bool {
		if(isset(self::$classEmptyAvailableCache[$className])) return self::$classEmptyAvailableCache[$className];
		try {
			if(!$constructor = (new ReflectionClass($className))->getConstructor()) return self::$classEmptyAvailableCache[$className] = false;
			foreach ($constructor->getParameters() as $param) {
				if (!$param->isDefaultValueAvailable()) {
					return self::$classEmptyAvailableCache[$className] = false;
				}
			}
			return self::$classEmptyAvailableCache[$className] = true;
		} catch (\ReflectionException $e) {
			return self::$classEmptyAvailableCache[$className] = false;
		}
	}

	/**
	 * @param string $className
	 * @param $value
	 * @return object|null
	 */
	protected static function instanceByValue(string $className, $value): ?object {
		try {
			return new ($className)($value);
		} catch(Throwable $e) {
			return null;
		}
	}
}
