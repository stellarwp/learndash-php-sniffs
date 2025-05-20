<?php
/**
 * Custom PHPCS sniff to check for valid sprintf format specifiers.
 *
 * @author LearnDash
 * @license GPL-2.0-or-later
 */

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Checks for valid sprintf format specifiers in PHP code.
 */
class SprintfFormatSniff implements Sniff
{
    /**
     * Returns the tokens that this sniff is interested in.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING];
    }

    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token in the stack.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Only check sprintf and printf calls.
        if (!in_array(strtolower($token['content']), ['sprintf', 'printf'], true)) {
            return;
        }

        // Find the opening parenthesis.
        $openPtr = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr + 1);
        if (!$openPtr) {
            return;
        }

        // Find the first argument (should be a string literal).
        $firstArgPtr = $phpcsFile->findNext([T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING], $openPtr + 1, null, false, null, true);
        if (!$firstArgPtr) {
            return;
        }

        $formatString = $tokens[$firstArgPtr]['content'];
        $formatString = $this->stripQuotes($formatString);

        // Regex to match all sprintf/printf format specifiers (including invalid ones).
        $pattern_all = '/%(?:\d+\$)?[+-]?(?:\d+)?(?:\.\d+)?([a-zA-Z])/';
        preg_match_all($pattern_all, $formatString, $matches, PREG_OFFSET_CAPTURE);

        // Allowed conversion characters for sprintf/printf.
        $allowed = [
            'b', 'c', 'd', 'e', 'E', 'f', 'F', 'g', 'G', 'o', 's', 'u', 'x', 'X'
        ];

        foreach ($matches[1] as $idx => $match) {
            $specifier = $match[0];
            if (!in_array($specifier, $allowed, true)) {
                $fullMatch = $matches[0][$idx][0];
                $phpcsFile->addError(
                    'Invalid sprintf/printf format specifier: "%s"',
                    $firstArgPtr,
                    'InvalidSprintfSpecifier',
                    [$fullMatch]
                );
            }
        }
    }

    /**
     * Strips quotes from a PHP string literal.
     *
     * @param string $str
     *
     * @return string
     */
    protected function stripQuotes($str)
    {
        if (strlen($str) > 1 && ($str[0] === '"' || $str[0] === "'")) {
            return substr($str, 1, -1);
        }
        return $str;
    }
}
