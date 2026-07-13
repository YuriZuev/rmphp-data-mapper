<?php
/**
 * @author Zuev Yuri
 */

namespace Rmphp\DataMapper\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Map {

	public function __construct(
		public ?string $hydrateFrom = null,  // Имя ключа при гидратации
		public ?string $extractTo = null,  // Имя ключа при экстракции
		public bool $useSnakeCase  = true,  // SnakeCace при экстракции
		public bool $ignore = false,        // Полностью игнорировать
		public bool $fillNull = false,   // Включать null при экстракции
	) {
	}

}
