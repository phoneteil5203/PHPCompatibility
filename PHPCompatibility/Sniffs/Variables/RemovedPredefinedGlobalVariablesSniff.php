<?php
/**
 * PHPCompatibility, an external standard for PHP_CodeSniffer.
 *
 * @package   PHPCompatibility
 * @copyright 2012-2020 PHPCompatibility Contributors
 * @license   https://opensource.org/licenses/LGPL-3.0 LGPL3
 * @link      https://github.com/PHPCompatibility/PHPCompatibility
 */

namespace PHPCompatibility\Sniffs\Variables;

use PHPCompatibility\AbstractRemovedFeatureSniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Utils\Conditions;
use PHPCSUtils\Utils\FunctionDeclarations;
use PHPCSUtils\Utils\Parentheses;
use PHPCSUtils\Utils\Scopes;

/**
 * Detect the use of removed global variables. Suggests alternatives if available.
 *
 * PHP version 5.3+
 *
 * @link https://wiki.php.net/rfc/deprecations_php_7_2#php_errormsg
 *
 * @since 5.5   Introduced `LongArrays` sniff.
 * @since 7.0   Introduced `RemovedGlobalVariables` sniff.
 * @since 7.0.7 The `LongArrays` sniff now throws a warning for deprecated and an error for removed.
 *              Previously the `LongArrays` sniff would always throw a warning.
 * @since 7.1.0 The `RemovedGlobalVariables` sniff now extends the `AbstractNewFeatureSniff`
 *              instead of the base `Sniff` class.
 * @since 7.1.3 Merged the `LongArrays` sniff into the `RemovedGlobalVariables` sniff.
 * @since 9.0.0 Renamed from `RemovedGlobalVariablesSniff` to `RemovedPredefinedGlobalVariablesSniff`.
 */
class RemovedPredefinedGlobalVariablesSniff extends AbstractRemovedFeatureSniff
{

    /**
     * A list of removed global variables with their alternative, if any.
     *
     * The array lists : version number with false (deprecated) and true (removed).
     * If's sufficient to list the first version where the variable was deprecated/removed.
     *
     * @since 5.5
     * @since 7.0
     *
     * @var array(string => array(string => bool|string))
     */
    protected $removedGlobalVariables = [
        'HTTP_POST_VARS' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_POST',
        ],
        'HTTP_GET_VARS' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_GET',
        ],
        'HTTP_ENV_VARS' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_ENV',
        ],
        'HTTP_SERVER_VARS' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_SERVER',
        ],
        'HTTP_COOKIE_VARS' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_COOKIE',
        ],
        'HTTP_SESSION_VARS' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_SESSION',
        ],
        'HTTP_POST_FILES' => [
            '5.3'         => false,
            '5.4'         => true,
            'alternative' => '$_FILES',
        ],

        'HTTP_RAW_POST_DATA' => [
            '5.6'         => false,
            '7.0'         => true,
            'alternative' => 'php://input',
        ],

        'php_errormsg' => [
            '7.2'         => false,
            '8.0'         => true,
            'alternative' => 'error_get_last()',
        ],
    ];


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @since 5.5
     * @since 7.0
     *
     * @return array
     */
    public function register()
    {
        return [\T_VARIABLE];
    }


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @since 5.5
     * @since 7.0
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->supportsAbove('5.3') === false) {
            return;
        }

        $tokens  = $phpcsFile->getTokens();
        $varName = \substr($tokens[$stackPtr]['content'], 1);

        if (isset($this->removedGlobalVariables[$varName]) === false) {
            return;
        }

        if (Scopes::isOOProperty($phpcsFile, $stackPtr) === true) {
            // Ok, so this was a class property declaration, not our concern.
            return;
        }

        // Check for static usage of class properties shadowing the removed global variables.
        $prevToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true, null, true);
        if ($prevToken !== false && $tokens[$prevToken]['code'] === \T_DOUBLE_COLON) {
            return;
        }

        // Do some additional checks for the $php_errormsg variable.
        if ($varName === 'php_errormsg'
            && $this->isTargetPHPErrormsgVar($phpcsFile, $stackPtr, $tokens) === false
        ) {
            return;
        }

        // Still here, so throw an error/warning.
        $itemInfo = [
            'name' => $varName,
        ];
        $this->handleFeature($phpcsFile, $stackPtr, $itemInfo);
    }


    /**
     * Get the relevant sub-array for a specific item from a multi-dimensional array.
     *
     * @since 7.1.0
     *
     * @param array $itemInfo Base information about the item.
     *
     * @return array Version and other information about the item.
     */
    public function getItemArray(array $itemInfo)
    {
        return $this->removedGlobalVariables[$itemInfo['name']];
    }


    /**
     * Get the error message template for this sniff.
     *
     * @since 7.1.0
     *
     * @return string
     */
    protected function getErrorMsgTemplate()
    {
        return "Global variable '\$%s' is ";
    }


    /**
     * Filter the error message before it's passed to PHPCS.
     *
     * @since 8.1.0
     *
     * @param string $error     The error message which was created.
     * @param array  $itemInfo  Base information about the item this error message applies to.
     * @param array  $errorInfo Detail information about an item this error message applies to.
     *
     * @return string
     */
    protected function filterErrorMsg($error, array $itemInfo, array $errorInfo)
    {
        if ($itemInfo['name'] === 'php_errormsg') {
            $error = \str_replace('Global', 'The', $error);
        }
        return $error;
    }

    /**
     * Run some additional checks for the `$php_errormsg` variable.
     *
     * @since 8.1.0
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     * @param array                       $tokens    Token array of the current file.
     *
     * @return bool
     */
    private function isTargetPHPErrormsgVar(File $phpcsFile, $stackPtr, array $tokens)
    {
        $scopeStart = 0;

        $validOwners = [
            \T_CLOSURE,
            \T_FUNCTION,
        ];

        /*
         * If the variable is detected within the scope of a function/closure, limit the checking.
         */
        $function = Conditions::getLastCondition($phpcsFile, $stackPtr, $validOwners);

        // It could also be a function param, which is not in the function scope.
        if ($function === false && isset($tokens[$stackPtr]['nested_parenthesis']) === true) {
            $lastOwner = Parentheses::lastOwnerIn($phpcsFile, $stackPtr, $validOwners);
            if ($lastOwner !== false) {
                // Function declaration parameter shadowing the name of the reserved var.
                return false;
            }
        }

        if ($function !== false) {
            $scopeStart = $tokens[$function]['scope_opener'];
        }

        /*
         * Now, let's do some additional checks.
         */
        $nextNonEmpty = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        // Is the variable being used as an array ?
        if ($nextNonEmpty !== false && $tokens[$nextNonEmpty]['code'] === \T_OPEN_SQUARE_BRACKET) {
            // The PHP native variable is a string, so this is probably not it
            // (except for array access to string, but why would you in this case ?).
            return false;
        }

        // Is this a variable assignment ?
        if ($nextNonEmpty !== false
            && isset(Tokens::$assignmentTokens[$tokens[$nextNonEmpty]['code']]) === true
        ) {
            return false;
        }

        // Is this a function param shadowing the PHP native one ?
        if ($function !== false) {
            $parameters = FunctionDeclarations::getParameters($phpcsFile, $function);
            if (\is_array($parameters) === true && empty($parameters) === false) {
                foreach ($parameters as $param) {
                    if ($param['name'] === '$php_errormsg') {
                        return false;
                    }
                }
            }

            // Has this variable been imported via a closure `use` ?
            if ($tokens[$function]['code'] === \T_CLOSURE
                && isset($tokens[$function]['parenthesis_closer'])
            ) {
                $hasUse = $phpcsFile->findNext(
                    Tokens::$emptyTokens,
                    ($tokens[$function]['parenthesis_closer'] + 1),
                    null,
                    true
                );

                if ($hasUse !== false && $tokens[$hasUse]['code'] === \T_USE) {
                    $useParameters = FunctionDeclarations::getParameters($phpcsFile, $hasUse);
                    if (\is_array($useParameters) === true && empty($useParameters) === false) {
                        foreach ($useParameters as $param) {
                            if ($param['name'] === '$php_errormsg') {
                                return false;
                            }
                        }
                    }
                }
            }
        }

        $skipPast = [
            'T_CLASS'      => true,
            'T_ANON_CLASS' => true,
            'T_INTERFACE'  => true,
            'T_TRAIT'      => true,
            'T_FUNCTION'   => true,
            'T_CLOSURE'    => true,
        ];

        // Walk back and see if there is an assignment to the variable within the same scope.
        for ($i = ($stackPtr - 1); $i >= $scopeStart; $i--) {
            if ($tokens[$i]['code'] === \T_CLOSE_CURLY_BRACKET
                && isset($tokens[$i]['scope_condition'])
                && isset($skipPast[$tokens[$tokens[$i]['scope_condition']]['type']])
            ) {
                // Skip past functions, classes etc.
                $i = $tokens[$i]['scope_condition'];
                continue;
            }

            if ($tokens[$i]['code'] !== \T_VARIABLE || $tokens[$i]['content'] !== '$php_errormsg') {
                continue;
            }

            $nextNonEmpty = $phpcsFile->findNext(Tokens::$emptyTokens, ($i + 1), null, true);

            if ($nextNonEmpty !== false
                && isset(Tokens::$assignmentTokens[$tokens[$nextNonEmpty]['code']]) === true
            ) {
                return false;
            }
        }

        return true;
    }
}
