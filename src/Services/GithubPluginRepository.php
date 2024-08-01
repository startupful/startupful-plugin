<?php

namespace Startupful\StartupfulPlugin\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class GithubPluginRepository
{
    protected $client;
    protected $cacheTime = 120; // 2분

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
        return Cache::remember('startupful_plugins', $this->cacheTime, function () {
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
                    $latestRelease = $this->getLatestRelease($item['full_name']);
                    return [
                        'name' => $item['name'],
                        'full_name' => $item['full_name'],
                        'description' => $item['description'],
                        'stars' => $item['stargazers_count'],
                        'url' => $item['html_url'],
                        'package_name' => $item['full_name'],
                        'latest_version' => $latestRelease['tag_name'] ?? null,
                        'release_url' => $latestRelease['html_url'] ?? null,
                    ];
                }, $data['items']);

            } catch (\Exception $e) {
                return [];
            }
        });
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

    private function getLatestRelease($repoFullName)
    {
        return Cache::remember("latest_release_{$repoFullName}", $this->cacheTime, function () use ($repoFullName) {
            try {
                $response = $this->client->get("repos/{$repoFullName}/releases/latest");
                $release = json_decode($response->getBody()->getContents(), true);
                return [
                    'tag_name' => $release['tag_name'] ?? null,
                    'html_url' => $release['html_url'] ?? null,
                ];
            } catch (\Exception $e) {
                return [
                    'tag_name' => 'dev-master',
                    'html_url' => null,
                ];
            }
        });
    }

    public function getLatestVersion($packageName)
    {
        return Cache::remember("latest_version_{$packageName}", $this->cacheTime, function () use ($packageName) {
            try {
                $response = $this->client->get("repos/{$packageName}/releases/latest");
                $data = json_decode($response->getBody()->getContents(), true);
                return $data['tag_name'] ?? null;
            } catch (\Exception $e) {
                \Log::error('GitHub API Error: ' . $e->getMessage());
                return null;
            }
        });
    }
}