<?php
/**
 * PHPCompatibility, an external standard for PHP_CodeSniffer.
 *
 * @package   PHPCompatibility
 * @copyright 2012-2020 PHPCompatibility Contributors
 * @license   https://opensource.org/licenses/LGPL-3.0 LGPL3
 * @link      https://github.com/PHPCompatibility/PHPCompatibility
 */

namespace PHPCompatibility\Sniffs\FunctionNameRestrictions;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer_File as File;
use PHPCSUtils\Utils\FunctionDeclarations;
use PHPCSUtils\Utils\Namespaces;
use PHPCSUtils\Utils\Scopes;

/**
 * Detect declaration of a namespaced function called `assert()`.
 *
 * As of PHP 7.3, a compile-time deprecation warning will be thrown when a function
 * called `assert()` is declared. In PHP 8 this will become a compile-error.
 *
 * Methods are unaffected.
 * Global, non-namespaced, `assert()` function declarations were always a fatal
 * "function already declared" error, so not the concern of this sniff.
 *
 * PHP version 7.3
 *
 * @link https://www.php.net/manual/en/migration73.deprecated.php#migration73.deprecated.core.assert
 * @link https://wiki.php.net/rfc/deprecations_php_7_3#defining_a_free-standing_assert_function
 * @link https://www.php.net/manual/en/function.assert.php
 *
 * @since 9.0.0
 */
class RemovedNamespacedAssertSniff extends Sniff
{

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @since 9.0.0
     *
     * @return array
     */
    public function register()
    {
        return [\T_FUNCTION];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @since 9.0.0
     *
     * @param \PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                   $stackPtr  The position of the current token in the
     *                                         stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->supportsAbove('7.3') === false) {
            return;
        }

        $funcName = FunctionDeclarations::getName($phpcsFile, $stackPtr);

        if (strtolower($funcName) !== 'assert') {
            return;
        }

        if (Scopes::isOOMethod($phpcsFile, $stackPtr) === true) {
            return;
        }

        if (Namespaces::determineNamespace($phpcsFile, $stackPtr) === '') {
            // Not a namespaced function declaration. This may be a parse error, but not our concern.
            return;
        }

        $phpcsFile->addWarning('Declaring a free-standing function called assert() is deprecated since PHP 7.3.', $stackPtr, 'Found');
    }
}
