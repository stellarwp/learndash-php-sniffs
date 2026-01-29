<?php
/**
 * Disallows public and protected class constants.
 *
 * Public/protected class constants cannot be deprecated, so we should use
 * public/protected static properties instead.
 *
 * @package StellarWP/learndash-php-sniffs
 */

namespace StellarWP\PHP_Sniffs\LearnDash\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * DisallowNonPrivateConstSniff class
 */
class DisallowNonPrivateConstSniff implements Sniff {
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return [ T_CONST ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 *
	 * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();

		// Check if this const is inside a class/interface/trait.
		if ( empty( $tokens[ $stack_ptr ]['conditions'] ) ) {
			return;
		}

		$conditions     = $tokens[ $stack_ptr ]['conditions'];
		$last_condition = end( $conditions );
		$valid_scopes   = [ T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM ];

		if ( ! in_array( $last_condition, $valid_scopes, true ) ) {
			return;
		}

		// Skip over whitespace, comments, and 'final' keyword to find visibility modifier.
		$skip_tokens = [
			T_WHITESPACE,
			T_FINAL,
			T_COMMENT,
			T_DOC_COMMENT_CLOSE_TAG,
		];

		$previous_ptr = $phpcs_file->findPrevious(
			$skip_tokens,
			$stack_ptr - 1,
			null,
			true, // Exclude these tokens (find something that's NOT in this list).
			null,
			true  // Local scope only.
		);

		if ( $previous_ptr === false ) {
			// No visibility means public by default in PHP.
			$phpcs_file->addError(
				'Class constants should be private. Use a static property for public/protected visibility to allow deprecation.',
				$stack_ptr,
				'ImplicitPublicConst'
			);
			return;
		}

		$previous_token = $tokens[ $previous_ptr ];

		if ( $previous_token['code'] === T_PUBLIC ) {
			$phpcs_file->addError(
				'Public class constants are not allowed. Use a public static property instead to allow deprecation.',
				$stack_ptr,
				'PublicConst'
			);
		} elseif ( $previous_token['code'] === T_PROTECTED ) {
			$phpcs_file->addError(
				'Protected class constants are not allowed. Use a protected static property instead to allow deprecation.',
				$stack_ptr,
				'ProtectedConst'
			);
		} elseif ( $previous_token['code'] !== T_PRIVATE ) {
			// No visibility modifier found (implicit public).
			$phpcs_file->addError(
				'Class constants should be private. Use a static property for public/protected visibility to allow deprecation.',
				$stack_ptr,
				'ImplicitPublicConst'
			);
		}
	}
}
