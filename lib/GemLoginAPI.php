<?php
require_once __DIR__ . '/../config/constants.php';

class GemLoginAPI {
    private $client;
    private $baseUrl;

    public function __construct($baseUrl = null) {
        $this->baseUrl = $baseUrl ?? GEMLOGIN_API_URL;
        $this->client = new GuzzleHttp\Client(['base_uri' => $this->baseUrl]);
    }

    // Helper function to make GET requests
    private function get($endpoint, $query = []) {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    // Helper function to make POST requests
    private function post($endpoint, $body = []) {
        try {
            $response = $this->client->post($endpoint, ['json' => $body]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    // Get a list of profiles
    public function getProfiles($groupId = null, $page = 1, $perPage = 50, $sort = 0, $search = null) {
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
        return $this->get('/api/profiles', $query);
    }

    // Get profile details by ID
    public function getProfile($id) {
        return $this->get("/api/profile/{$id}");
    }

    // Start a profile
    public function startProfile($id, $url = null, $winSize = null, $winPos = null, $additionalArgs = null) {
        $query = [];
        if ($url !== null) {
            $query['url'] = $url;
        }
        if ($winSize !== null) {
            $query['win_size'] = $winSize;
        }
        if ($winPos !== null) {
            $query['win_pos'] = $winPos;
        }
        if ($additionalArgs !== null) {
            $query['additional_args'] = $additionalArgs;
        }
        return $this->get("/api/profiles/start/{$id}", $query);
    }

    // Close a profile by ID
    public function closeProfile($id) {
        return $this->get("/api/profiles/close/{$id}");
    }

    // Create a new profile
    public function createProfile($profileData) {
        return $this->post("/api/profiles/create", $profileData);
    }

    // Update a profile
    public function updateProfile($profileId, $profileData) {
        return $this->post("/api/profiles/update/{$profileId}", $profileData);
    }

    // Delete a profile
    public function deleteProfile($id) {
        return $this->get("/api/profiles/delete/{$id}");
    }

    // Change fingerprint for a list of profiles
    public function changeFingerprint($profileIds) {
        $profileIdsString = implode(',', $profileIds);
        return $this->get("/api/profiles/changeFingerprint", ['profileIds' => $profileIdsString]);
    }

    // Get a list of scripts
    public function getScripts() {
        return $this->get("/api/scripts");
    }

    // Execute a script
    public function executeScript($scriptId, $profileIds, $parameters = [], $closeBrowser = true) {
        $body = [
            'profileIds' => $profileIds,
            'closeBrowser' => $closeBrowser,
            'parameters' => $parameters
        ];
        return $this->post("/api/scripts/execute/{$scriptId}", $body);
    }

    // Terminate a running script
    public function killExecuteScript($scriptId, $profileIds, $closeBrowser = true) {
        $body = [
            'profileIds' => $profileIds,
            'closeBrowser' => $closeBrowser
        ];
        return $this->post("/api/scripts/kill-execute/{$scriptId}", $body);
    }

    // Check script status
    public function checkScriptStatus($scriptId, $profileId) {
        $body = [
            'profileId' => $profileId
        ];
        return $this->post("/api/scripts/check-status/{$scriptId}", $body);
    }

    // Get a list of browser versions
    public function getBrowserVersions() {
        return $this->get("/api/browser_versions");
    }

    // Get a list of groups
    public function getGroups() {
        return $this->get("/api/groups");
    }
    
    // Ping the API to check connectivity
    public function ping() {
        try {
            // Try a simple request to check if the API is available
            $response = $this->get('/api/profiles', ['per_page' => 1]);
            
            // If we got a valid response, the API is online
            if (isset($response['success'])) {
                return [
                    'success' => true,
                    'message' => 'API is online and responding',
                    'data' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'API responded but with invalid format',
                    'data' => $response
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not connect to API: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    // Run a script on a single profile (simplified version of executeScript)
    public function runScript($scriptId, $profileId, $parameters = []) {
        return $this->executeScript($scriptId, [$profileId], $parameters);
    }
}
?>
