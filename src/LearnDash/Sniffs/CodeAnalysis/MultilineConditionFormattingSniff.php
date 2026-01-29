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
	 * The string to use for one level of indentation.
	 *
	 * Defaults to tab (WordPress/LearnDash standard).
	 * Can be configured in phpcs.xml:
	 *
	 * <rule ref="LearnDash.CodeAnalysis.MultilineConditionFormatting">
	 *     <properties>
	 *         <property name="indent" value="    "/>
	 *     </properties>
	 * </rule>
	 *
	 * @var string
	 */
	public string $indent = "\t";

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

		// Get the base indentation from the control structure.
		$base_indent = $this->get_line_indent( $phpcs_file, $stack_ptr );

		// Check this condition and any sub-conditions.
		$this->check_condition_group( $phpcs_file, $open_paren, $close_paren, $base_indent, 1 );
	}

	/**
	 * Gets the indentation of the line containing the given token.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the token.
	 *
	 * @return string The indentation string.
	 */
	private function get_line_indent( File $phpcs_file, int $stack_ptr ): string {
		$tokens = $phpcs_file->getTokens();
		$line   = $tokens[ $stack_ptr ]['line'];

		// Find the first token on this line.
		for ( $i = $stack_ptr; $i >= 0; $i-- ) {
			if ( $tokens[ $i ]['line'] !== $line ) {
				break;
			}
		}

		$first_on_line = $i + 1;

		// If the first token is whitespace, that's our indent.
		if ( $tokens[ $first_on_line ]['code'] === T_WHITESPACE ) {
			return $tokens[ $first_on_line ]['content'];
		}

		return '';
	}

	/**
	 * Checks a condition group (content between parentheses) for proper formatting.
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int    $open_paren   The position of the opening parenthesis.
	 * @param int    $close_paren  The position of the closing parenthesis.
	 * @param string $base_indent  The base indentation for this condition.
	 * @param int    $depth        The nesting depth (for indentation calculation).
	 *
	 * @return void
	 */
	private function check_condition_group( File $phpcs_file, int $open_paren, int $close_paren, string $base_indent, int $depth ): void {
		$tokens = $phpcs_file->getTokens();

		// Collect all boolean operators at this level (not inside nested parens).
		$operators     = [];
		$nested_groups = [];
		$nesting_level = 0;
		$nested_start  = null;

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
			$this->check_operators_formatting( $phpcs_file, $operators, $open_paren, $close_paren, $base_indent, $depth );
		}

		// Recursively check nested groups.
		foreach ( $nested_groups as $group ) {
			$this->check_condition_group( $phpcs_file, $group[0], $group[1], $base_indent, $depth + 1 );
		}
	}

	/**
	 * Checks that operators are properly formatted (at start of lines).
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int[]  $operators    Array of operator token positions.
	 * @param int    $open_paren   The opening parenthesis position.
	 * @param int    $close_paren  The closing parenthesis position.
	 * @param string $base_indent  The base indentation.
	 * @param int    $depth        The nesting depth.
	 *
	 * @return void
	 */
	private function check_operators_formatting( File $phpcs_file, array $operators, int $open_paren, int $close_paren, string $base_indent, int $depth ): void {
		$tokens = $phpcs_file->getTokens();

		// Check if this is a single-line condition with multiple parts.
		$open_line  = $tokens[ $open_paren ]['line'];
		$close_line = $tokens[ $close_paren ]['line'];

		if ( $open_line === $close_line && count( $operators ) > 0 ) {
			// Single line with multiple conditions - should be multiline.
			$fix = $phpcs_file->addFixableWarning(
				'Conditions with multiple boolean expressions should be split across multiple lines, with one condition per line.',
				$open_paren,
				'SingleLineMultipleConditions'
			);

			if ( $fix === true ) {
				$this->fix_single_line_condition( $phpcs_file, $operators, $open_paren, $close_paren, $base_indent, $depth );
			}

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
				$fix = $phpcs_file->addFixableWarning(
					'Boolean operator "%s" should be at the beginning of the line, not after other code.',
					$op_ptr,
					'OperatorNotAtLineStart',
					[ $tokens[ $op_ptr ]['content'] ]
				);

				if ( $fix === true ) {
					$this->fix_operator_at_end( $phpcs_file, $op_ptr, $first_on_line, $base_indent, $depth );
				}
			}
		}
	}

	/**
	 * Fixes a single-line condition by splitting it across multiple lines.
	 *
	 * @param File   $phpcs_file   The file being scanned.
	 * @param int[]  $operators    Array of operator token positions.
	 * @param int    $open_paren   The opening parenthesis position.
	 * @param int    $close_paren  The closing parenthesis position.
	 * @param string $base_indent  The base indentation.
	 * @param int    $depth        The nesting depth.
	 *
	 * @return void
	 */
	private function fix_single_line_condition( File $phpcs_file, array $operators, int $open_paren, int $close_paren, string $base_indent, int $depth ): void {
		$tokens = $phpcs_file->getTokens();
		$fixer  = $phpcs_file->fixer;

		// Get the indentation of the line containing the opening paren.
		// This is used for the closing paren to maintain alignment.
		$open_paren_line_indent = $this->get_line_indent( $phpcs_file, $open_paren );

		// Content indent is one level deeper than the opening paren line.
		$content_indent = $open_paren_line_indent . $this->indent;

		$fixer->beginChangeset();

		// Add newline after opening paren.
		$fixer->addContent( $open_paren, "\n" . $content_indent );

		// Remove any whitespace right after opening paren.
		$next = $open_paren + 1;
		if ( $tokens[ $next ]['code'] === T_WHITESPACE ) {
			$fixer->replaceToken( $next, '' );
		}

		// For each operator, add newline before it.
		foreach ( $operators as $op_ptr ) {
			// Remove whitespace before operator.
			$prev = $op_ptr - 1;
			if ( $tokens[ $prev ]['code'] === T_WHITESPACE ) {
				$fixer->replaceToken( $prev, '' );
			}

			// Add newline and indent before operator.
			$fixer->addContentBefore( $op_ptr, "\n" . $content_indent );

			// Ensure single space after operator.
			$next_token = $op_ptr + 1;
			if ( $tokens[ $next_token ]['code'] === T_WHITESPACE ) {
				$fixer->replaceToken( $next_token, ' ' );
			}
		}

		// Add newline before closing paren at the same level as the opening paren line.
		$prev = $close_paren - 1;
		if ( $tokens[ $prev ]['code'] === T_WHITESPACE ) {
			$fixer->replaceToken( $prev, '' );
		}
		$fixer->addContentBefore( $close_paren, "\n" . $open_paren_line_indent );

		$fixer->endChangeset();
	}

	/**
	 * Fixes an operator that is at the end of a line by moving it to the start of the next line.
	 *
	 * @param File   $phpcs_file    The file being scanned.
	 * @param int    $op_ptr        The operator token position.
	 * @param int    $first_on_line The first non-whitespace token on the line before the operator.
	 * @param string $base_indent   The base indentation.
	 * @param int    $depth         The nesting depth.
	 *
	 * @return void
	 */
	private function fix_operator_at_end( File $phpcs_file, int $op_ptr, int $first_on_line, string $base_indent, int $depth ): void {
		$tokens = $phpcs_file->getTokens();
		$fixer  = $phpcs_file->fixer;

		// Get the indentation from the current line (where the operator is).
		// This ensures the operator stays at the same level as the condition above it.
		$indent = $this->get_line_indent( $phpcs_file, $first_on_line );

		$fixer->beginChangeset();

		$op_line  = $tokens[ $op_ptr ]['line'];
		$operator = $tokens[ $op_ptr ]['content'];

		// Remove whitespace before operator (on same line).
		$prev = $op_ptr - 1;
		if (
			$tokens[ $prev ]['code'] === T_WHITESPACE
			&& $tokens[ $prev ]['line'] === $op_line
		) {
			$fixer->replaceToken( $prev, '' );
		}

		// Remove the operator.
		$fixer->replaceToken( $op_ptr, '' );

		// Remove all whitespace after operator until we hit non-whitespace.
		$next_ptr = $op_ptr + 1;
		while (
			isset( $tokens[ $next_ptr ] )
			&& $tokens[ $next_ptr ]['code'] === T_WHITESPACE
		) {
			$fixer->replaceToken( $next_ptr, '' );
			$next_ptr++;
		}

		// Add newline, indent, operator, and space before the next token.
		$fixer->addContentBefore( $next_ptr, "\n" . $indent . $operator . ' ' );

		$fixer->endChangeset();
	}
}
