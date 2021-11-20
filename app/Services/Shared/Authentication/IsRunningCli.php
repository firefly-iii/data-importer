<?php
declare(strict_types=1);


namespace App\Services\Shared\Authentication;

/**
 * Trait IsRunningCli
 */
trait IsRunningCli
{
    /**
     * @return bool
     */
    protected function isCli(): bool
    {
        if (defined('STDIN')) {
            return true;
        }

        if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
            return true;
        }

        return false;
    }
}
