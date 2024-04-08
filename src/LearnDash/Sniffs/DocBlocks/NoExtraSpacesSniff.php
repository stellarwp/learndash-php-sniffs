<?php
/**
 * Ensures that no extra spaces are included after keywords like @since in a docblock.
 *
 * @package StellarWP/learndash-php-sniffs
 */

namespace StellarWP\PHP_Sniffs\LearnDash\Sniffs\DocBlocks;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * NoExtraSpacesSniff class
 */
class NoExtraSpacesSniff implements Sniff {
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array<string>
	 */
	public function register(): array {
		return array( T_DOC_COMMENT_TAG );
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
		$tokens = $phpcs_file->getTokens();

		// Get the content of the docblock keyword (e.g., "@since").
		$tag_content = trim( $tokens[ $stack_index ]['content'] );

		// Get the next non-whitespace token.
		$next_token = $phpcs_file->findNext( T_WHITESPACE, $stack_index + 1, null, true );

		// Check if the next token contains more than one space.
		// The check against linebreaks is used to prevent keywords that are not followed by anything (Like @after in our Tests) from causing issues.
		if (
			$next_token !== false
			&& $tokens[ $next_token ]['content'] !== ' '
			&& $tokens[ $next_token ]['content'] !== "\n"
		) {
			$error = 'Only one space is allowed after the keywords in docblocks.';
			$fix   = $phpcs_file->addFixableError( $error, $stack_index, 'ExtraSpacesAfterKeyword' );

			if ( $fix ) {
				// Replace multiple spaces with a single space.
				$phpcs_file->fixer->replaceToken( $next_token, ' ' );
			}
		}
	}
}
