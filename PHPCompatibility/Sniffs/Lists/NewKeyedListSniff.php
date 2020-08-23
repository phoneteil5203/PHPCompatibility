<?php
/**
 * PHPCompatibility, an external standard for PHP_CodeSniffer.
 *
 * @package   PHPCompatibility
 * @copyright 2012-2020 PHPCompatibility Contributors
 * @license   https://opensource.org/licenses/LGPL-3.0 LGPL3
 * @link      https://github.com/PHPCompatibility/PHPCompatibility
 */

namespace PHPCompatibility\Sniffs\Lists;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer_File as File;
use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHPCSUtils\Utils\Lists;

/**
 * Since PHP 7.1, you can specify keys in `list()`, or its new shorthand `[]` syntax.
 *
 * PHP version 7.1
 *
 * @link https://wiki.php.net/rfc/list_keys
 * @link https://www.php.net/manual/en/function.list.php
 *
 * @since 9.0.0
 * @since 10.0.0 Complete rewrite.
 */
class NewKeyedListSniff extends Sniff
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
        return [
            \T_LIST                => \T_LIST,
            \T_OPEN_SHORT_ARRAY    => \T_OPEN_SHORT_ARRAY,
            \T_OPEN_SQUARE_BRACKET => \T_OPEN_SQUARE_BRACKET,
        ];
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
        if ($this->supportsBelow('7.0') === false) {
            return;
        }

        try {
            $assignments = Lists::getAssignments($phpcsFile, $stackPtr);
        } catch (RuntimeException $e) {
            // Parse error, live coding or short array, not short list.
            return;
        }

        foreach ($assignments as $assign) {
            if (isset($assign['key_token']) === false) {
                continue;
            }

            $phpcsFile->addError(
                'Specifying keys in list constructs is not supported in PHP 7.0 or earlier.',
                $assign['key_token'],
                'Found'
            );
        }
    }
}
