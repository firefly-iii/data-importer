<?php

declare(strict_types=1);

namespace Tests\Feature\SimpleFIN;

use Tests\TestCase;
use App\Support\Constants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Mockery;
use Override;

/**
 * @internal
 *
 * @coversNothing
 */
final class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Set up SimpleFIN demo configuration
        Config::set('importer.simplefin.demo_token', 'demo_token_123');
        Config::set('importer.simplefin.demo_url', 'https://demo:demo@beta-bridge.simplefin.org/simplefin');
    }

    public function testDemoModeCheckboxSubmission(): void
    {
        // Set the flow cookie to SimpleFIN
        $response = $this->withCookie(Constants::FLOW_COOKIE, 'simplefin')
            ->post(route('003-upload.upload'), [
                'use_demo' => '1',
            ])
        ;

        // Check that we don't redirect back to upload (which indicates validation failure)
        $this->assertNotSame(route('003-upload.index'), $response->getTargetUrl());

        // If demo mode works correctly, we should redirect to configuration
        $response->assertRedirect(route('004-configure.index'));

        // Verify session data was set correctly
        $this->assertSame('demo_token_123', session(Constants::SIMPLEFIN_TOKEN));
        $this->assertSame('https://demo:demo@beta-bridge.simplefin.org/simplefin', session(Constants::SIMPLEFIN_BRIDGE_URL));
        $this->assertTrue(session(Constants::SIMPLEFIN_IS_DEMO));
        $this->assertTrue(session(Constants::HAS_UPLOAD));
    }

    public function testDemoModeCheckboxValueInterpretation(): void
    {
        // Test various ways the checkbox might be submitted
        $testCases = [
            ['use_demo' => '1'],
            ['use_demo' => 'on'],
            ['use_demo' => true],
            ['use_demo' => 1],
        ];

        foreach ($testCases as $case) {
            $response = $this->withCookie(Constants::FLOW_COOKIE, 'simplefin')
                ->post(route('003-upload.upload'), $case)
            ;

            $this->assertNotSame(route('003-upload.index'), $response->getTargetUrl(), 'Failed for case: '.json_encode($case));
        }
    }

    public function testManualModeRequiresTokenAndUrl(): void
    {
        // Test that manual mode (no demo checkbox) requires token and URL
        $response = $this->withCookie(Constants::FLOW_COOKIE, 'simplefin')
            ->post(route('003-upload.upload'), [
                // No use_demo, no token, no URL
            ])
        ;

        // Should redirect back to upload with validation errors
        $response->assertRedirect(route('003-upload.index'));
        $response->assertSessionHasErrors(['simplefin_token', 'bridge_url']);
    }

    public function testManualModeWithValidCredentials(): void
    {
        $response = $this->withCookie(Constants::FLOW_COOKIE, 'simplefin')
            ->post(route('003-upload.upload'), [
                'simplefin_token' => 'valid_token_123',
                'bridge_url'      => 'https://bridge.example.com',
            ])
        ;

        // Should attempt to connect (may fail due to invalid credentials, but shouldn't fail validation)
        // The exact behavior depends on whether SimpleFINService is mocked
        $this->assertNotSame(route('003-upload.index'), $response->getTargetUrl());
    }

    public function testRequestDataLogging(): void
    {
        // Enable debug logging to capture the request data
        Log::shouldReceive('debug')
            ->with('UploadController::upload() - Request All:', Mockery::type('array'))
            ->once()
        ;

        Log::shouldReceive('debug')
            ->with('handleSimpleFINFlow() - Request All:', Mockery::type('array'))
            ->once()
        ;

        Log::shouldReceive('debug')
            ->with('handleSimpleFINFlow() - Raw use_demo input:', Mockery::type('array'))
            ->once()
        ;

        Log::shouldReceive('debug')
            ->with('handleSimpleFINFlow() - Evaluated $isDemo:', [true])
            ->once()
        ;

        $this->withCookie(Constants::FLOW_COOKIE, 'simplefin')
            ->post(route('003-upload.upload'), [
                'use_demo' => '1',
            ])
        ;
    }
}
