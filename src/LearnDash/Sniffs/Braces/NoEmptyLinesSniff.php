<?php
/**
 * Ensures that no empty lines exist immediately after or before a curly brace.
 *
 * @package StellarWP/learndash-php-sniffs
 */

namespace StellarWP\PHP_Sniffs\LearnDash\Sniffs\Braces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * NoEmptyLinesSniff class
 */
class NoEmptyLinesSniff implements Sniff {
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return  array<string>
	 */
	public function register(): array {
		return array( T_OPEN_CURLY_BRACKET );
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcs_file   The file being scanned.
	 * @param int                         $stack_index  The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 */
	public function process( File $phpcs_file, $stack_index ): void {
		// Just in case.
		$tokens = $phpcs_file->getTokens();
		if ( isset( $tokens[ $stack_index ]['scope_opener'] ) === false ) {
			return;
		}

		$this->processOpen( $phpcs_file, $stack_index );
		$this->processClose( $phpcs_file, $stack_index );
	}

	/**
	 * Processes the opening section of the token's declaration.
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcs_file   The file being scanned.
	 * @param int                         $stack_index  The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 */
	public function processOpen( File $phpcs_file, $stack_index ): void {
		$tokens = $phpcs_file->getTokens();
		if ( ! isset( $tokens[ $stack_index ]['scope_opener'] ) ) {
			return;
		}

		$opener = $tokens[ $stack_index ]['scope_opener'];

		// Find the next non-empty token.
		$next = $phpcs_file->findNext(
			T_WHITESPACE,
			( $opener + 1 ),
			null,
			true
		);

		if ( $next === false ) {
			return;
		}

		// Calculate the number of lines between the next token and the opening brace.
		$lines = $tokens[ $next ]['line'] - $tokens[ $opener ]['line'];

		// If there is more than one line, remove the blank lines.
		if ( $lines > 1 ) {
			$error = 'Opening brace must not be followed by a blank line';
			$fix   = $phpcs_file->addFixableError( $error, $opener, 'EmptyLineAfterOpeningBrace' );

			if ( $fix === true ) {
				$phpcs_file->fixer->beginChangeset();

				for ( $i = ( $opener + 1 ); $i < $next; $i++ ) {
					if ( $tokens[ $i ]['line'] !== $tokens[ $opener ]['line'] ) {
						continue;
					}

					$phpcs_file->fixer->replaceToken( $i, '' );
				}

				$phpcs_file->fixer->endChangeset();
			}
		}
	}

	/**
	 * Processes the closes section of the token's declaration.
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcs_file   The file being scanned.
	 * @param int                         $stack_index  The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 */
	public function processClose( File $phpcs_file, $stack_index ): void {
		$tokens = $phpcs_file->getTokens();
		if ( ! isset( $tokens[ $stack_index ]['scope_closer'] ) ) {
			return;
		}

		$closer = $tokens[ $stack_index ]['scope_closer'];

		// Find the previous non-empty token.
		$previous = $phpcs_file->findPrevious(
			T_WHITESPACE,
			( $closer - 1 ),
			null,
			true
		);

		if ( $previous === false ) {
			return;
		}

		// Calculate the number of lines between the previous token and the closing brace.
		$lines = $tokens[ $closer ]['line'] - $tokens[ $previous ]['line'];

		// If there is more than one line, remove the blank lines.
		if ( $lines > 1 ) {
			$error = 'Closing brace must not be preceded by a blank line';
			$fix   = $phpcs_file->addFixableError( $error, $closer, 'EmptyLineBeforeClosingBrace' );

			if ( $fix === true ) {
				$phpcs_file->fixer->beginChangeset();

				for ( $i = ( $previous + 1 ); $i < $closer; $i++ ) {
					if ( $tokens[ $i ]['line'] !== $tokens[ $previous ]['line'] ) {
						continue;
					}

					$phpcs_file->fixer->replaceToken( $i, '' );
				}

				$phpcs_file->fixer->endChangeset();
			}
		}
	}
}
