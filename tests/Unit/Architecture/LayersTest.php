<?php
/**
 * The dependency rule, checked on every class.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Domain ← Application ← (Infrastructure, Presentation); only Plugin knows them all.
 */
final class LayersTest extends TestCase {

	/**
	 * Layers each layer may reference.
	 */
	private const ALLOWED = array(
		'Domain'         => array( 'Domain' ),
		'Application'    => array( 'Domain', 'Application' ),
		'Infrastructure' => array( 'Domain', 'Application', 'Infrastructure' ),
		'Presentation'   => array( 'Domain', 'Application', 'Presentation' ),
	);

	/**
	 * Every class file with its layer.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function class_files(): array {
		$src   = dirname( __DIR__, 3 ) . '/src';
		$files = array();
		foreach ( array_keys( self::ALLOWED ) as $layer ) {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src . '/' . $layer ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'php' === $file->getExtension() ) {
					$files[ $layer . substr( $file->getPathname(), strlen( $src . '/' . $layer ) ) ] = array( $layer, $file->getPathname() );
				}
			}
		}

		return $files;
	}

	#[DataProvider( 'class_files' )]
	public function test_a_layer_only_references_the_layers_it_may_depend_on( string $layer, string $file ): void {
		preg_match_all( '/LeadMagnet\\\\Locker\\\\(\w+)\\\\/', (string) file_get_contents( $file ), $matches );

		$forbidden = array_diff( array_unique( $matches[1] ), self::ALLOWED[ $layer ], array( 'Tests' ) );

		$this->assertSame( array(), array_values( $forbidden ), "{$layer} must not depend on " . implode( ', ', $forbidden ) );
	}

	#[DataProvider( 'class_files' )]
	public function test_domain_and_application_do_not_call_wordpress( string $layer, string $file ): void {
		if ( ! in_array( $layer, array( 'Domain', 'Application' ), true ) ) {
			$this->assertTrue( true );
			return;
		}

		$called = array();
		$tokens = token_get_all( (string) file_get_contents( $file ) );
		foreach ( $tokens as $i => $token ) {
			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}
			$next = $tokens[ $i + 1 ] ?? null;
			$prev = $this->previous( $tokens, $i );
			if ( '(' === $next && ! in_array( $prev, array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) && isset( self::wordpress_functions()[ strtolower( $token[1] ) ] ) ) {
				$called[] = $token[1];
			}
		}

		$this->assertSame( array(), $called, "{$layer} calls WordPress functions." );
	}

	/**
	 * Token type before position $i, skipping whitespace.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $i      Position.
	 * @return int|string|null
	 */
	private function previous( array $tokens, int $i ): int|string|null {
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				continue;
			}
			return is_array( $tokens[ $j ] ) ? $tokens[ $j ][0] : $tokens[ $j ];
		}

		return null;
	}

	/**
	 * Names of all WordPress functions, from the stubs PHPStan uses.
	 *
	 * @return array<string, true>
	 */
	private static function wordpress_functions(): array {
		static $names = null;
		if ( null === $names ) {
			preg_match_all( '/^\s*function (\w+)\(/m', (string) file_get_contents( dirname( __DIR__, 3 ) . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php' ), $matches );
			$names = array_fill_keys( array_map( 'strtolower', $matches[1] ), true );
		}

		return $names;
	}
}
