<?php

namespace Startupful\StartupfulPlugin\Services;

use GuzzleHttp\Client;

class GithubPluginRepository
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Filament-Startupful-Plugin'
            ]
        ]);
    }

    public function getStartupfulPlugins()
    {
        try {
            $response = $this->client->get('search/repositories', [
                'query' => [
                    'q' => 'org:startupful topic:startupful-plugin',
                    'sort' => 'stars',
                    'order' => 'desc'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return array_map(function($item) {
                return [
                    'name' => $item['name'],
                    'full_name' => $item['full_name'],
                    'description' => $item['description'],
                    'stars' => $item['stargazers_count'],
                    'url' => $item['html_url'],
                    'package_name' => $item['full_name'],
                ];
            }, $data['items']);

        } catch (\Exception $e) {
            \Log::error('GitHub API Error: ' . $e->getMessage());
            return [];
        }
    }

    public function searchPlugins($keyword)
    {
        $allPlugins = $this->getStartupfulPlugins();
        
        if (empty($keyword)) {
            return $allPlugins;
        }

        return array_filter($allPlugins, function($plugin) use ($keyword) {
            return stripos($plugin['name'], $keyword) !== false || 
                   stripos($plugin['description'], $keyword) !== false;
        });
    }
}