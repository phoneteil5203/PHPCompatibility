<?php
/**
 * PHPCompatibility, an external standard for PHP_CodeSniffer.
 *
 * @package   PHPCompatibility
 * @copyright 2012-2020 PHPCompatibility Contributors
 * @license   https://opensource.org/licenses/LGPL-3.0 LGPL3
 * @link      https://github.com/PHPCompatibility/PHPCompatibility
 */

namespace PHPCompatibility\Sniffs\Generators;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\BackCompat\Helper;
use PHPCSUtils\Utils\Conditions;

/**
 * As of PHP 7.0, a `return` statement can be used within a generator for a final expression to be returned.
 *
 * PHP version 7.0
 *
 * @link https://www.php.net/manual/en/migration70.new-features.php#migration70.new-features.generator-return-expressions
 * @link https://wiki.php.net/rfc/generator-return-expressions
 * @link https://www.php.net/manual/en/language.generators.syntax.php
 *
 * @since 8.2.0
 */
class NewGeneratorReturnSniff extends Sniff
{

    /**
     * Scope conditions within which a yield can exist.
     *
     * @since 9.0.0
     *
     * @var array
     */
    private $validConditions = [
        \T_FUNCTION => \T_FUNCTION,
        \T_CLOSURE  => \T_CLOSURE,
    ];


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @since 8.2.0
     *
     * @return array
     */
    public function register()
    {
        $targets = [
            \T_YIELD,
        ];

        /*
         * The `yield` keyword was introduced in PHP 5.5 with the token T_YIELD.
         * The `yield from` keyword was introduced in PHP 7.0 and tokenizes as
         * "T_YIELD T_WHITESPACE T_STRING".
         *
         * Pre-PHPCS 3.1.0, the T_YIELD token was not correctly back-filled for PHP < 5.5.
         * Also, as of PHPCS 3.1.0, the PHPCS tokenizer adds a new T_YIELD_FROM
         * token.
         *
         * So for PHP 5.4 icw PHPCS < 3.1.0, we need to look for T_STRING with content "yield".
         * For PHP 5.5+ we need to look for T_YIELD.
         * For PHPCS 3.1.0+, we also need to look for T_YIELD_FROM.
         */
        if (\version_compare(\PHP_VERSION_ID, '50500', '<') === true
            && \version_compare(Helper::getVersion(), '3.1.0', '<') === true
        ) {
            $targets[] = \T_STRING;
        }

        if (\defined('T_YIELD_FROM')) {
            $targets[] = \T_YIELD_FROM;
        }

        return $targets;
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @since 8.2.0
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void|int Void or a stack pointer to skip forward.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->supportsBelow('5.6') !== true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === \T_STRING
            && $tokens[$stackPtr]['content'] !== 'yield'
        ) {
            return;
        }

        $prevNonEmpty = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        if ($prevNonEmpty !== false
            && ($tokens[$prevNonEmpty]['type'] === 'T_FN_ARROW'
            || $tokens[$prevNonEmpty]['code'] === \T_DOUBLE_ARROW)
        ) {
            /*
             * Yield in an arrow function, which can only contain one expression.
             * And as `yield` can not be used within an array declaration or a foreach expression,
             * just checking for the double arrow should be sufficient.
             */
            return;
        }

        $function = Conditions::getLastCondition($phpcsFile, $stackPtr, $this->validConditions);
        if ($function === false) {
            // Yield outside function scope, fatal error, but not our concern.
            return;
        }

        if (isset($tokens[$function]['scope_opener'], $tokens[$function]['scope_closer']) === false) {
            // Can't reliably determine start/end of function scope.
            return;
        }

        $targets = [\T_RETURN, \T_CLOSURE, \T_FUNCTION, \T_CLASS, \T_ANON_CLASS];
        $current = $tokens[$function]['scope_opener'];

        while (($current = $phpcsFile->findNext($targets, ($current + 1), $tokens[$function]['scope_closer'])) !== false) {
            if ($tokens[$current]['code'] === \T_RETURN) {
                $phpcsFile->addError(
                    'Returning a final expression from a generator was not supported in PHP 5.6 or earlier',
                    $current,
                    'ReturnFound'
                );

                return $tokens[$function]['scope_closer'];
            }

            // Found a nested scope in which return can exist without problems.
            if (isset($tokens[$current]['scope_closer'])) {
                // Skip past the nested scope.
                $current = $tokens[$current]['scope_closer'];
            }
        }

        // Don't examine this function again.
        return $tokens[$function]['scope_closer'];
    }
}
