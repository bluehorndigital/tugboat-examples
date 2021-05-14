<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/vendor/autoload.php';

class Tugboat
{
    private const API_URL = 'https://api.tugboat.qa/v3/';
    private $client;
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::API_URL,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function requestWithApiKey($method, $uri, array $payload = [])
    {
        $request_options = [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $_ENV['TUGBOAT_TOKEN']),
            ]
        ];
        if ($payload !== []) {
            if ($method === 'GET') {
                throw new \InvalidArgumentException('Cannot set a body with a GET request');
            }
            $request_options['json'] = $payload;
        }

        return $this->client->request($method, $uri, $request_options);
    }

    public function requestWithUrlToken($method, $uri, $token)
    {
        return $this->client->request($method, $uri, [
            'headers' => [
                'Authorization' => sprintf('Authorization: Bearer %s', $token),
            ]
        ]);
    }
}


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

(new SingleCommandApplication('Tugboat'))
    ->addArgument('branch', InputArgument::REQUIRED, 'Must be a branch in this repository.')
    ->addArgument('config', InputArgument::OPTIONAL, 'Path to configuration')
    ->setCode(static function (InputInterface $input, OutputInterface $output) {
        $branch = $input->getArgument('branch');
        // if no config name passed, assume it is the same as the branch.
        $config = $input->getArgument('config') ?: $branch;
        $config_path = __DIR__ . '/configs/' . $config . '.yml';
        if (!file_exists($config_path)) {
            $output->writeln("<error>Could not load `configs/$config.yml`.</error>");
        }
        $tugboat_config = Yaml::parse(file_get_contents($config_path));
        $tugboat_client = new Tugboat;

        $tugboat_response = $tugboat_client->requestWithApiKey('POST', 'previews', [
            'ref' => $branch,
            'config' => $tugboat_config,
            'name' => $branch,
            'repo' => $_ENV['TUGBOAT_REPOSITORY'],
        ]);
        $response = \json_decode((string) $tugboat_response->getBody());
        $output->writeln("<info>Success! Follow the build here: https://dashboard.tugboat.qa/{$response->preview}</info>");
        $output->writeln("<comment>Job ID: {$response->job}");

        for (;;) {
            $output->writeln("<comment>Checking preview build status...</comment>");

            $job_status_response = $tugboat_client->requestWithApiKey('GET', "jobs/{$response->job}");

            $job_status = \json_decode((string) $job_status_response->getBody());
            if ($job_status->type === 'preview') {
                $output->writeln("<info>Built! Visit {$job_status->url}");
                break;
            }
            if ($job_status_response->hasHeader('Retry-After')) {
                $retry_after = (int) $job_status_response->getHeader('Retry-After')[0];
                sleep($retry_after);
            }
        }
    })
    ->run();
