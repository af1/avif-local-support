<?php

declare(strict_types=1);

namespace Ddegner\AvifLocalSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for CLI environment variable handling.
 * Consolidates PATH building logic used across multiple classes.
 */
final class Environment {

	/**
	 * CLI environment keys allowed to pass through.
	 *
	 * @var array<int,string>
	 */
	private const CLI_ENV_ALLOWED_KEYS = array(
		'PATH',
		'HOME',
		'LC_ALL',
		'LC_COLLATE',
		'LC_CTYPE',
		'LC_MESSAGES',
		'LC_MONETARY',
		'LC_NUMERIC',
		'LC_TIME',
		'LANG',
		'LANGUAGE',
		'TMP',
		'TEMP',
		'TMPDIR',
		'RUBYOPT',
		'UMASK',
		'UMASKS',
		'USER',
		'USERNAME',
		'LOGNAME',
		'HOME',
	);

	/**
	 * CLI environment keys beginning with these prefixes are allowed.
	 *
	 * @var array<int,string>
	 */
	private const CLI_ENV_ALLOWED_PREFIXES = array(
		'MAGICK_',
		'OMP_',
		'AVIFLOSU_',
	);

	/**
	 * Explicitly deny dangerous CLI keys.
	 *
	 * @var array<int,string>
	 */
	private const CLI_ENV_FORBIDDEN_KEYS = array(
		'LD_LIBRARY_PATH',
		'LD_PRELOAD',
		'DYLD_LIBRARY_PATH',
		'DYLD_INSERT_LIBRARIES',
		'PYTHONSTARTUP',
		'NODE_PATH',
		'NODE_OPTIONS',
	);

	/**
	 * Explicitly deny dangerous CLI key prefixes.
	 *
	 * @var array<int,string>
	 */
	private const CLI_ENV_FORBIDDEN_PREFIXES = array(
		'LD_',
		'PYTHON',
		'PERL',
		'RUBY',
		'RUBIES_',
	);

	/**
	 * Maximum env value length to avoid abuse.
	 */
	private const MAX_CLI_ENV_VALUE_LENGTH = 2048;

	/**
	 * Build the default PATH string with platform-specific additions.
	 */
	public static function buildDefaultPath(): string {
		$path = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin';

		if ( PHP_OS_FAMILY === 'Darwin' ) {
			if ( @is_dir( '/opt/homebrew/bin' ) ) {
				$path .= ':/opt/homebrew/bin';
			}
			if ( @is_dir( '/opt/local/bin' ) ) {
				$path .= ':/opt/local/bin';
			}
		}

		return $path;
	}

	/**
	 * Build the default CLI environment variables string.
	 */
	public static function buildDefaultEnvString(): string {
		$path = self::buildDefaultPath();
		return "PATH=$path\nHOME=/tmp\nLC_ALL=C";
	}

	/**
	 * Build a normalized environment array suitable for proc_open.
	 *
	 * @param array|null $env Optional existing env to normalize.
	 * @return array<string, string>
	 */
	public static function normalizeEnv( ?array $env = null ): array {
		$env = is_array( $env ) ? self::sanitizeCliEnvArray( $env ) : array();

		if ( empty( $env['PATH'] ) ) {
			$env['PATH'] = getenv( 'PATH' ) ?: self::buildDefaultPath();
		}

		// Ensure Darwin-specific paths are included
		if ( PHP_OS_FAMILY === 'Darwin' ) {
			$currentPath = (string) $env['PATH'];
			if ( @is_dir( '/opt/homebrew/bin' ) && strpos( $currentPath, '/opt/homebrew/bin' ) === false ) {
				$env['PATH'] .= ':/opt/homebrew/bin';
			}
			if ( @is_dir( '/opt/local/bin' ) && strpos( $currentPath, '/opt/local/bin' ) === false ) {
				$env['PATH'] .= ':/opt/local/bin';
			}
		}

		if ( empty( $env['HOME'] ) ) {
			$env['HOME'] = getenv( 'HOME' ) ?: '/tmp';
		}

		if ( empty( $env['LC_ALL'] ) ) {
			$env['LC_ALL'] = 'C';
		}

		/**
		 * Filters the environment used for CLI operations.
		 *
		 * @param array $env
		 */
		$env = apply_filters( 'aviflosu_cli_environment', $env );
		return self::sanitizeCliEnvArray( is_array( $env ) ? $env : array() );
	}

	/**
	 * Parse CLI environment string into an array.
	 *
	 * @param string $envString Newline-separated KEY=VALUE pairs.
	 * @return array<string, string>
	 */
	public static function parseEnvString( string $envString ): array {
		$env   = array();
		$lines = explode( "\n", $envString );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' || strpos( $line, '=' ) === false ) {
				continue;
			}
			[$key, $val]         = explode( '=', $line, 2 );
			$env[ trim( $key ) ] = trim( $val );
		}

		return self::sanitizeCliEnvArray( $env );
	}

	/**
	 * Convert a user-provided CLI env string into a safe canonical form.
	 */
	public static function sanitizeCliEnvString( string $envString ): string {
		$env = self::parseEnvString( $envString );
		if ( empty( $env ) ) {
			return '';
		}
		$lines = array();
		ksort( $env );
		foreach ( $env as $key => $val ) {
			$lines[] = $key . '=' . $val;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Sanitize user-provided CLI args before handing to the encoder.
	 */
	public static function sanitizeCliArgsString( string $argsString ): string {
		$args = array();
		$tokens = str_getcsv( $argsString, ' ', '"', '\\' );
		$count = 0;
		foreach ( $tokens as $token ) {
			$token = trim( (string) $token );
			if ( '' === $token ) {
				continue;
			}
			$token = str_replace( array( "\r", "\n", "\t", "\0" ), '', $token );
			$token = trim( $token );
			if ( '' === $token ) {
				continue;
			}
			if ( strlen( $token ) > 1024 ) {
				$token = substr( $token, 0, 1024 );
			}
			if ( strlen( (string) $token ) > 1 && str_contains( (string) $token, ' ' ) ) {
				$token = '"' . str_replace( '"', '\'', (string) $token ) . '"';
			}
			$args[] = $token;
			++$count;
			if ( $count >= 128 ) {
				break;
			}
		}

		return implode( ' ', $args );
	}

	/**
	 * Return a sanitized env array.
	 *
	 * @param array<string, string> $env
	 * @return array<string, string>
	 */
	public static function sanitizeCliEnvArray( array $env ): array {
		$out = array();
		foreach ( $env as $key => $value ) {
			$key = is_string( $key ) ? strtoupper( trim( $key ) ) : '';
			if ( '' === $key || ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $key ) ) {
				continue;
			}

			if ( ! self::isAllowedCliEnvKey( $key ) ) {
				continue;
			}

			$value = self::sanitizeCliEnvValue( (string) $value );
			if ( '' !== $key && '' !== $value || in_array( $key, self::CLI_ENV_ALLOWED_KEYS, true ) ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	private static function isAllowedCliEnvKey( string $key ): bool {
		if ( in_array( $key, self::CLI_ENV_FORBIDDEN_KEYS, true ) ) {
			return false;
		}

		foreach ( self::CLI_ENV_FORBIDDEN_PREFIXES as $forbiddenPrefix ) {
			if ( str_starts_with( $key, $forbiddenPrefix ) ) {
				return false;
			}
		}

		if ( in_array( $key, self::CLI_ENV_ALLOWED_KEYS, true ) ) {
			return true;
		}

		foreach ( self::CLI_ENV_ALLOWED_PREFIXES as $allowedPrefix ) {
			if ( str_starts_with( $key, $allowedPrefix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function sanitizeCliEnvValue( string $value ): string {
		$value = trim( str_replace( "\0", '', $value ) );
		if ( '' === $value ) {
			return '';
		}

		$value = preg_replace( '/[\\x00-\\x1f\\x7f]/', '', $value ) ?: '';
		if ( strlen( $value ) > self::MAX_CLI_ENV_VALUE_LENGTH ) {
			$value = substr( $value, 0, self::MAX_CLI_ENV_VALUE_LENGTH );
		}

		return $value;
	}

	/**
	 * Best-effort CPU core count detection.
	 * Returns at least 1 when detection is unavailable.
	 */
	public static function detectCpuCoreCount(): int {
		// Prefer explicitly reported CPU count on Windows.
		$winCount = (int) ( getenv( 'NUMBER_OF_PROCESSORS' ) ?: 0 );
		if ( $winCount > 0 ) {
			return $winCount;
		}

		// Try POSIX-style commands when shell execution is available.
		if ( function_exists( 'shell_exec' ) ) {
			$commands = array();
			if ( 'Darwin' === PHP_OS_FAMILY || 'BSD' === PHP_OS_FAMILY ) {
				$commands[] = 'sysctl -n hw.ncpu 2>/dev/null';
			}
			$commands[] = 'nproc 2>/dev/null';
			$commands[] = 'getconf _NPROCESSORS_ONLN 2>/dev/null';

			foreach ( $commands as $command ) {
				$output = @shell_exec( $command );
				if ( ! is_string( $output ) ) {
					continue;
				}
				$count = (int) trim( $output );
				if ( $count > 0 ) {
					return $count;
				}
			}
		}

		return 1;
	}

	/**
	 * Recommended default thread limit:
	 * one less than detected cores, but never below 1.
	 */
	public static function detectRecommendedThreadLimit(): int {
		$cores = self::detectCpuCoreCount();
		return max( 1, $cores - 1 );
	}
}
