<?php

namespace Ngfw\DNSDumpster\Console;

use Illuminate\Console\Command;
use Ngfw\DNSDumpster\DNSDumpster;
use Ngfw\DNSDumpster\Exceptions\ApiException;
use Ngfw\DNSDumpster\Exceptions\ConfigurationException;
use Ngfw\DNSDumpster\Exceptions\InvalidDomainException;
use Ngfw\DNSDumpster\Exceptions\RateLimitException;

class DNSDumpsterLookupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dnsdumpster:lookup
                            {domain : The domain to lookup}
                            {--page=1 : The page number for paginated results}
                            {--force : Force refresh from API, bypassing cache}
                            {--bulk : Treat domain argument as comma-separated list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lookup DNS information for a domain using DNSDumpster API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $dnsDumpster = app(DNSDumpster::class);
            $domain = $this->argument('domain');
            $page = (int) $this->option('page');
            $forceRefresh = $this->option('force');
            $bulk = $this->option('bulk');

            if ($bulk) {
                $this->handleBulkLookup($dnsDumpster, $domain, $page, $forceRefresh);
            } else {
                $this->handleSingleLookup($dnsDumpster, $domain, $page, $forceRefresh);
            }

            return Command::SUCCESS;
        } catch (ConfigurationException $e) {
            $this->error('Configuration Error: ' . $e->getMessage());
            $this->info('Please ensure DNSDumpster_API_KEY and DNSDumpster_API_URL are set in your .env file.');

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Unexpected Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Handle single domain lookup.
     *
     * @param  DNSDumpster  $dnsDumpster
     * @param  string  $domain
     * @param  int  $page
     * @param  bool  $forceRefresh
     * @return void
     */
    private function handleSingleLookup(DNSDumpster $dnsDumpster, string $domain, int $page, bool $forceRefresh): void
    {
        $this->info("Looking up DNS information for: {$domain}");

        if ($forceRefresh) {
            $this->info('Force refresh enabled - bypassing cache');
        }

        try {
            $data = $dnsDumpster->fetchData($domain, $page, $forceRefresh);

            $this->info('✓ DNS data retrieved successfully');
            $this->line('');
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
        } catch (InvalidDomainException $e) {
            $this->error('Invalid Domain: ' . $e->getMessage());
        } catch (RateLimitException $e) {
            $this->error('Rate Limit Exceeded: ' . $e->getMessage());
            $this->info('Please try again later.');
        } catch (ApiException $e) {
            $this->error('API Error: ' . $e->getMessage());
            $this->info('HTTP Status Code: ' . $e->getStatusCode());
        }
    }

    /**
     * Handle bulk domain lookup.
     *
     * @param  DNSDumpster  $dnsDumpster
     * @param  string  $domains
     * @param  int  $page
     * @param  bool  $forceRefresh
     * @return void
     */
    private function handleBulkLookup(DNSDumpster $dnsDumpster, string $domains, int $page, bool $forceRefresh): void
    {
        $domainList = array_map('trim', explode(',', $domains));

        $this->info('Looking up DNS information for ' . count($domainList) . ' domains');

        $result = $dnsDumpster->fetchBulkData($domainList, $page, $forceRefresh);

        $this->line('');
        $this->info('✓ Bulk lookup completed');
        $this->info('Successful: ' . count($result['results']));
        $this->info('Failed: ' . count($result['errors']));

        if (count($result['results']) > 0) {
            $this->line('');
            $this->info('=== Successful Results ===');
            foreach ($result['results'] as $domain => $data) {
                $this->line('');
                $this->comment("Domain: {$domain}");
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            }
        }

        if (count($result['errors']) > 0) {
            $this->line('');
            $this->error('=== Errors ===');
            foreach ($result['errors'] as $domain => $error) {
                $this->line('');
                $this->comment("Domain: {$domain}");
                $this->error('Error: ' . $error['error']);
            }
        }
    }
}
