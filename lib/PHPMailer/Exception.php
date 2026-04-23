<?php

/**
 * PHPMailer Exception class.
 *
 * https://github.com/PHPMailer/PHPMailer
 *
 * @author Marcus Bointon (Synchro)
 * @author Jim Jagielski (jimjag)
 * @author Andy Prevost (codeworxtech)
 * @author Brent R. Matzelle (original founder)
 * @license LGPL-2.1-only
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer exception handler.
 */
class Exception extends \Exception
{
    /**
     * Prettify error message output.
     */
    public function errorMessage(): string
    {
        return ' ' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . " \n";
    }
}

