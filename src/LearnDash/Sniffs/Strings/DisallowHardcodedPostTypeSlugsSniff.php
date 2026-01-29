<?php
/**
 * Disallows hardcoded post type slugs.
 *
 * Post type slugs should be retrieved using LDLMS_Post_Types::get_post_type_slug()
 * or learndash_get_post_type_slug() with the appropriate constant.
 *
 * @package StellarWP/learndash-php-sniffs
 */

namespace StellarWP\PHP_Sniffs\LearnDash\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * DisallowHardcodedPostTypeSlugsSniff class
 */
class DisallowHardcodedPostTypeSlugsSniff implements Sniff {
	/**
	 * Known LearnDash post type slugs and their corresponding constants.
	 *
	 * @var array<string, string>
	 */
	private const POST_TYPE_SLUGS = [
		'sfwd-courses'      => 'LDLMS_Post_Types::COURSE',
		'sfwd-lessons'      => 'LDLMS_Post_Types::LESSON',
		'sfwd-topic'        => 'LDLMS_Post_Types::TOPIC',
		'sfwd-quiz'         => 'LDLMS_Post_Types::QUIZ',
		'sfwd-question'     => 'LDLMS_Post_Types::QUESTION',
		'sfwd-certificates' => 'LDLMS_Post_Types::CERTIFICATE',
		'sfwd-assignment'   => 'LDLMS_Post_Types::ASSIGNMENT',
		'sfwd-essays'       => 'LDLMS_Post_Types::ESSAY',
		'sfwd-transactions' => 'LDLMS_Post_Types::TRANSACTION',
		'groups'            => 'LDLMS_Post_Types::GROUP',
		'ld-exam'           => 'LDLMS_Post_Types::EXAM',
		'ld-coupon'         => 'LDLMS_Post_Types::COUPON',
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array<int>
	 */
	public function register(): array {
		return [
			T_CONSTANT_ENCAPSED_STRING,
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
		$token  = $tokens[ $stack_ptr ];

		// Get the string content without quotes.
		$content = trim( $token['content'], '"\'' );

		// Check if it matches a known post type slug.
		if ( ! isset( self::POST_TYPE_SLUGS[ $content ] ) ) {
			return;
		}

		$constant = self::POST_TYPE_SLUGS[ $content ];

		$phpcs_file->addError(
			'Hardcoded post type slug "%s" is not allowed. Use LDLMS_Post_Types::get_post_type_slug( %s ) or learndash_get_post_type_slug( %s ) instead.',
			$stack_ptr,
			'HardcodedPostTypeSlug',
			[ $content, $constant, $constant ]
		);
	}
}
