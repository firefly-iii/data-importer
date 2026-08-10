<?php

declare(strict_types=1);

namespace App\Services\Akahu\Validation;

use App\Services\Shared\Validation\NewJobDataCollectorInterface;
use App\Services\Akahu\Request\GetAccountsRequest;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use Illuminate\Support\MessageBag;
use App\Services\Akahu\Model\Account\Account;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class NewJobDataCollector implements NewJobDataCollectorInterface
{
    private ImportJob $importJob;

    public function collectAccounts(): MessageBag
    {
        $messages = new MessageBag();
        $request = new GetAccountsRequest();

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            if (SymfonyResponse::HTTP_UNAUTHORIZED === $e->getCode()) {
                // Start a reauthentication flow
                session()->put('akahu_reauthenticate', 'true');

                $msg = 'Akahu credentials could not be authenticated, either the access tokens provided are invalid or have been revoked. You can try authenticating again or see the logs for more infomation.';

                $messages->add('no_accounts', $msg);
                Log::error($msg . ' | ' . $e->getMessage());

                return $messages;
            }

            if (SymfonyResponse::HTTP_FORBIDDEN === $e->getCode()) {
                // Start a reauthentication flow
                session()->put('akahu_reauthenticate', 'true');

                $msg = 'Akahu returned Forbidden when using the provided credentials, make sure all necessary permissions are granted in the Akahu website. You can try authenticating again or see the logs for more infomation.';

                $messages->add('no_accounts', $msg);
                Log::error($msg . ' | ' . $e->getMessage());

                return $messages;
            }

            throw new ImporterErrorException(sprintf(
                "Failed get account details from the Akahu api: %s",
                $e->getMessage()
            ));
        }

        $this->importJob->setServiceAccounts($response->getAccounts());

        return $messages;
    }

    public function validate(): MessageBag
    {
        return new MessageBag();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
    }

    public function getFlowName(): string
    {
        return 'akahu';
    }
}
