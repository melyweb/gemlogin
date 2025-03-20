<?php

require_once __DIR__ . '/vendor/autoload.php'; // Autoload Composer dependencies

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GemLoginAPI {
    private $client;
    private $baseUrl;

    public function __construct(string $baseUrl) {
        $this->baseUrl = $baseUrl;
        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    // Helper function to make GET requests
    private function get(string $endpoint, array $query = []) {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            // Log the error or handle it as needed
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    // Helper function to make POST requests
    private function post(string $endpoint, array $body = []) {
        try {
            $response = $this->client->post($endpoint, ['json' => $body]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            // Log the error or handle it as needed
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    // API: Close browser session by profile ID
    public function closeProfile(int $id) {
        return $this->get("/api/profiles/close/{$id}");
    }

    // API: Get a list of profiles
    public function getProfiles(int $groupId = null, int $page = 1, int $perPage = 50, int $sort = 0, string $search = null) {
        $query = [
            'page' => $page,
            'per_page' => $perPage,
            'sort' => $sort,
        ];
        if ($groupId !== null) {
            $query['group_id'] = $groupId;
        }
        if ($search !== null) {
            $query['search'] = $search;
        }
        return $this->get("/api/profiles", $query);
    }

    // API: Get a list of scripts
    public function getScripts() {
        return $this->get("/api/scripts");
    }

    // API: Get profile details by ID
    public function getProfile(int $id) {
        return $this->get("/api/profile/{$id}");
    }
    
    // API: Check if a profile is running
    public function isProfileRunning(int $id) {
        try {
            // First try the status endpoint if available
            $response = $this->get("/api/profile/{$id}/status");
            
            if (isset($response['success']) && $response['success']) {
                if (isset($response['data']['running']) && $response['data']['running']) {
                    return ['success' => true, 'running' => true];
                }
                
                if (isset($response['data']['status'])) {
                    $status = $response['data']['status'];
                    $running = ($status === 'active' || $status === 'running' || $status === 'online');
                    return ['success' => true, 'running' => $running, 'status' => $status];
                }
            }
            
            // Fall back to checking the profile state directly
            $response = $this->get("/api/profiles/is-running/{$id}");
            if (isset($response['success'])) {
                return [
                    'success' => true, 
                    'running' => (isset($response['data']) && $response['data']),
                    'status' => isset($response['data']) && $response['data'] ? 'running' : 'stopped'
                ];
            }
            
            return ['success' => false, 'message' => 'Could not determine profile status'];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    // Return the base URL for troubleshooting
    public function getBaseUrl() {
        return $this->baseUrl;
    }

    // API: Update fingerprint for a list of profiles
    public function changeFingerprint(array $profileIds) {
        $profileIdsString = implode(',', $profileIds);
        return $this->get("/api/profiles/changeFingerprint", ['profileIds' => $profileIdsString]);
    }

    // API: Update a profile
    public function updateProfile(int $profileId, array $profileData) {
        return $this->post("/api/profiles/update/{$profileId}", $profileData);
    }

    // API: Create a new profile
    public function createProfile(array $profileData) {
        return $this->post("/api/profiles/create", $profileData);
    }

    // API: Get a list of browser versions
    public function getBrowserVersions() {
        return $this->get("/api/browser_versions");
    }

    // API: Get a list of groups
    public function getGroups() {
        return $this->get("/api/groups");
    }

    // API: Delete a profile
    public function deleteProfile(int $id) {
        return $this->get("/api/profiles/delete/{$id}");
    }
    
    // API: Ping the server to check if it's online
    public function ping() {
        try {
            $response = $this->client->get("/api/ping");
            $data = json_decode($response->getBody()->getContents(), true);
            return [
                'success' => true,
                'message' => $data['message'] ?? 'API is online',
                'data' => $data
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => 'Could not connect to API: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    // API: Terminate a running script
    public function killExecuteScript(string $scriptId, array $profileIds, bool $closeBrowser = true) {
        $body = [
            'profileIds' => $profileIds,
            'closeBrowser' => $closeBrowser
        ];
        return $this->post("/api/scripts/kill-execute/{$scriptId}", $body);
    }

    // API: Execute a script
    public function executeScript(string $scriptId, array $profileIds, array $parameters = [], bool $closeBrowser = true) {
        $body = [
            'profileIds' => $profileIds,
            'closeBrowser' => $closeBrowser,
            'parameters' => $parameters
        ];
        return $this->post("/api/scripts/execute/{$scriptId}", $body);
    }

    // API: Check the status of a script
    public function checkScriptStatus(string $scriptId, int $profileId) {
        $body = [
            'profileId' => $profileId
        ];
        return $this->post("/api/scripts/check-status/{$scriptId}", $body);
    }
    
    // API: Run a script on a single profile (simplified version of executeScript)
    public function runScript(string $scriptId, int $profileId, array $parameters = []) {
        return $this->executeScript($scriptId, [$profileId], $parameters);
    }
    
    // API: Start a profile with a URL
    public function startProfile(int $profileId, string $url = 'https://google.com', string $winSize = '1280,720', string $winPos = null) {
        $query = [
            'url' => $url,
            'win_size' => $winSize
        ];
        
        if ($winPos !== null) {
            $query['win_pos'] = $winPos;
        }
        
        return $this->get("/api/profiles/start/{$profileId}", $query);
    }
}
