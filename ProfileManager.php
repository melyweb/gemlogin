<?php

require_once __DIR__ . '/GemLoginAPI.php';

class ProfileManager {
    private $api;

    public function __construct(GemLoginAPI $api) {
        $this->api = $api;
    }

    // Get profiles by group ID
    public function getProfilesByGroupId(int $groupId) {
        $response = $this->api->getProfiles($groupId);
        if ($response['success'] && isset($response['data'])) {
            return $response['data'];
        }
        return []; // Or handle the error as needed
    }

    // Close a profile by ID
    public function closeProfile(int $profileId) {
        return $this->api->closeProfile($profileId);
    }

    // Change fingerprint for a list of profiles
    public function changeFingerprint(array $profileIds) {
        return $this->api->changeFingerprint($profileIds);
    }

    // Execute a script on a profile
    public function executeScript(string $scriptId, array $profileIds, array $parameters = [], bool $closeBrowser = true) {
        return $this->api->executeScript($scriptId, $profileIds, $parameters, $closeBrowser);
    }

    // Terminate a script
    public function killExecuteScript(string $scriptId, array $profileIds, bool $closeBrowser = true) {
        return $this->api->killExecuteScript($scriptId, $profileIds, $closeBrowser);
    }

    // Check script status
    public function checkScriptStatus(string $scriptId, int $profileId) {
        return $this->api->checkScriptStatus($scriptId, $profileId);
    }
}