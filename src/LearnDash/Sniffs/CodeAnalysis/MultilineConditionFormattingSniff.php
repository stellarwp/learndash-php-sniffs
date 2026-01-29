<?php
/**
 * Enforces multiline formatting for conditions with multiple boolean expressions.
 *
 * When a condition has multiple boolean expressions (using && or ||):
 * - Each condition should be on its own line
 * - Operators should be at the beginning of lines, not at the end
 * - This applies to sub-conditions inside parentheses as well
 *
 * @package StellarWP/learndash-php-sniffs
 */

namespace StellarWP\PHP_Sniffs\LearnDash\Sniffs\CodeAnalysis;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * MultilineConditionFormattingSniff class
 */
class MultilineConditionFormattingSniff implements Sniff {
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return [
			T_IF,
			T_ELSEIF,
			T_WHILE,
			T_FOR,
			T_SWITCH,
		];
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

		// Find the opening parenthesis of the condition.
		$open_paren = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $stack_ptr + 1 );

		if ( $open_paren === false || ! isset( $tokens[ $open_paren ]['parenthesis_closer'] ) ) {
			return;
		}

		$close_paren = $tokens[ $open_paren ]['parenthesis_closer'];

		// Check this condition and any sub-conditions.
		$this->check_condition_group( $phpcs_file, $open_paren, $close_paren );
	}

	/**
	 * Checks a condition group (content between parentheses) for proper formatting.
	 *
	 * @param File $phpcs_file  The file being scanned.
	 * @param int  $open_paren  The position of the opening parenthesis.
	 * @param int  $close_paren The position of the closing parenthesis.
	 *
	 * @return void
	 */
	private function check_condition_group( File $phpcs_file, int $open_paren, int $close_paren ): void {
		$tokens = $phpcs_file->getTokens();

		// Collect all boolean operators at this level (not inside nested parens).
		$operators        = [];
		$nested_groups    = [];
		$nesting_level    = 0;
		$nested_start     = null;

		for ( $i = $open_paren + 1; $i < $close_paren; $i++ ) {
			$token = $tokens[ $i ];

			// Track nested parentheses for recursive checking.
			if ( $token['code'] === T_OPEN_PARENTHESIS ) {
				if ( $nesting_level === 0 ) {
					$nested_start = $i;
				}
				$nesting_level++;
				continue;
			}

			if ( $token['code'] === T_CLOSE_PARENTHESIS ) {
				$nesting_level--;
				if ( $nesting_level === 0 && $nested_start !== null ) {
					$nested_groups[] = [ $nested_start, $i ];
					$nested_start    = null;
				}
				continue;
			}

			// Only track operators at the current level.
			if ( $nesting_level > 0 ) {
				continue;
			}

			if (
				$token['code'] === T_BOOLEAN_AND
				|| $token['code'] === T_BOOLEAN_OR
				|| $token['code'] === T_LOGICAL_AND
				|| $token['code'] === T_LOGICAL_OR
			) {
				$operators[] = $i;
			}
		}

		// If there are operators at this level, check formatting.
		if ( count( $operators ) > 0 ) {
			$this->check_operators_formatting( $phpcs_file, $operators, $open_paren, $close_paren );
		}

		// Recursively check nested groups.
		foreach ( $nested_groups as $group ) {
			$this->check_condition_group( $phpcs_file, $group[0], $group[1] );
		}
	}

	/**
	 * Checks that operators are properly formatted (at start of lines).
	 *
	 * @param File  $phpcs_file  The file being scanned.
	 * @param int[] $operators   Array of operator token positions.
	 * @param int   $open_paren  The opening parenthesis position.
	 * @param int   $close_paren The closing parenthesis position.
	 *
	 * @return void
	 */
	private function check_operators_formatting( File $phpcs_file, array $operators, int $open_paren, int $close_paren ): void {
		$tokens = $phpcs_file->getTokens();

		// Check if this is a single-line condition with multiple parts.
		$open_line  = $tokens[ $open_paren ]['line'];
		$close_line = $tokens[ $close_paren ]['line'];

		if ( $open_line === $close_line && count( $operators ) > 0 ) {
			// Single line with multiple conditions - should be multiline.
			$phpcs_file->addWarning(
				'Conditions with multiple boolean expressions should be split across multiple lines, with one condition per line.',
				$open_paren,
				'SingleLineMultipleConditions'
			);
			return;
		}

		// For multiline conditions, check each operator is at the start of its line.
		foreach ( $operators as $op_ptr ) {
			$op_line = $tokens[ $op_ptr ]['line'];

			// Find the first non-whitespace token on this line.
			$first_on_line = null;

			for ( $i = $op_ptr - 1; $i > $open_paren; $i-- ) {
				if ( $tokens[ $i ]['line'] !== $op_line ) {
					break;
				}

				if ( $tokens[ $i ]['code'] !== T_WHITESPACE ) {
					$first_on_line = $i;
				}
			}

			// If there's a non-whitespace token before the operator on the same line,
			// the operator is not at the start.
			if ( $first_on_line !== null ) {
				$phpcs_file->addWarning(
					'Boolean operator "%s" should be at the beginning of the line, not after other code.',
					$op_ptr,
					'OperatorNotAtLineStart',
					[ $tokens[ $op_ptr ]['content'] ]
				);
			}
		}
	}
}
