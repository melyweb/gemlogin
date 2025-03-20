<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/GemLoginAPI.php';

// API Base URL
$baseUrl = 'http://localhost:1010'; // Replace with your actual base URL
$api = new GemLoginAPI($baseUrl);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GemLogin API Documentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: sans-serif;
        }
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }
        .api-section {
            margin-bottom: 20px;
        }
        .api-card {
            margin-bottom: 15px;
        }
        .code-block {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .code-block pre {
            margin: 0;
        }
        .nav-link.active {
            font-weight: bold;
        }
        /* Sticky Sidebar */
        #sidebarMenu {
            position: sticky;
            top: 20px; /* Adjust as needed */
            height: calc(100vh - 40px); /* Adjust as needed */
        }
    </style>
</head>
<body>

    <header class="py-3 mb-4 border-bottom">
        <div class="container d-flex flex-wrap justify-content-center">
            <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                <span class="fs-4">GemLogin API Documentation</span>
            </a>
        </div>
    </header>

    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <nav id="sidebarMenu" class="d-flex flex-column p-3 text-white bg-dark" style="height: 80vh; overflow-y: auto;">
                    <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-4">API Endpoints</span>
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="#profiles" class="nav-link text-white active" aria-current="page">
                                Profiles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#groups" class="nav-link text-white">
                                Groups
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#scripts" class="nav-link text-white">
                                Scripts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#browser-versions" class="nav-link text-white">
                                Browser Versions
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <div class="col-md-9">
                <main class="api-content">
                    <section id="profiles" class="api-section">
                        <h2>Profiles Management</h2>
                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/profiles</code> - Get a list of profiles
                            </div>
                            <div class="card-body">
                                <p>Get a list of profiles with optional filters and pagination.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>group_id</code> (integer, optional): Filter profiles by group ID.</li>
                                        <li><code>page</code> (integer, optional, default: 1): Page number.</li>
                                        <li><code>per_page</code> (integer, optional, default: 50): Profiles per page.</li>
                                        <li><code>sort</code> (integer, optional): Sort order (0: newest, 1: oldest, 2: A-Z, 3: Z-A).</li>
                                        <li><code>search</code> (string, optional): Search keyword.</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Profiles retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Test Profile",
      "raw_proxy": "http://proxy.example.com:8080",
      "browser_type": "chrome",
      "browser_version": "128",
      "group_id": 1,
      "profile_path": "/path/to/profile",
      "note": "Sample note",
      "created_at": "2024-09-04T12:00:00Z"
    }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/profile/{id}</code> - Get profile details by ID
                            </div>
                            <div class="card-body">
                                <p>Get profile details by ID.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>id</code> (integer, required): ID of the profile to retrieve.</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 1,
    "name": "Test Profile",
    "raw_proxy": "http://proxy.example.com:8080",
    "browser_type": "chrome",
    "browser_version": "128",
    "group_id": 1,
    "profile_path": "/path/to/profile",
    "note": "Sample note",
    "created_at": "2024-09-04T12:00:00Z"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/profiles/start/{id}</code> - Start a profile
                            </div>
                            <div class="card-body">
                                <p>Start a Chrome browser instance with the configuration specified in the profile ID. The browser is configured with extensions, user data, and press settings as needed.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>id</code> (integer, required): ID of the profile to start.</li>
                                        <li><code>url</code> (string, optional): URL to open in the browser window.</li>
                                        <li><code>additional_args</code> (string, optional): Additional arguments to pass to the Chrome Browser. Arguments should be separated by spaces.</li>
                                        <li><code>win_pos</code> (string, optional): Position of the browser window in the format: x,y</li>
                                        <li><code>win_size</code> (string, optional): Size of the browser window in the format: width, height</li>
                                        <li><code>win_scale</code> (number, optional): Scale factor for the browser's device scale</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Browser successfully started",
  "data": {
    "browser_pid": 1234,
    "profile_id": 1,
    "url": "http://example.com",
    "window_position": "0,0",
    "window_size": "1280,720",
    "window_scale": 1.0
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/profiles/close/{id}</code> - Close a profile
                            </div>
                            <div class="card-body">
                                <p>Closes the browser instance associated with the given profile ID and updates the profile status to '1' (indicating inactive or closed). If the profile is not found, the status is updated to '1' regardless.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>id</code> (integer, required): ID of the profile to close.</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Browser successfully closed and profile status updated"
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/profiles/changeFingerprint</code> - Change fingerprint for profiles
                            </div>
                            <div class="card-body">
                                <p>Updates the fingerprint data (webgl_metadata, device_name, mac_address...) for a list of profiles based on their IDs. Randomized values are generated for these fields.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>profileIds</code> (string, required): A comma-separated list of profile IDs to update fingerprints for. Example: <code>1,2,3</code></li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Change fingerprint success"
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="groups" class="api-section">
                        <h2>Groups Management</h2>
                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/groups</code> - Get a list of groups
                            </div>
                            <div class="card-body">
                                <p>Retrieve a list of all groups with their basic details.</p>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Successful operation, returns a list of groups",
  "data": [
    {
      "id": 1,
      "name": "All",
      "user_id": 1,
      "created_by": 1,
      "createdAt": "2021-09-07T12:00:00Z",
      "updatedAt": "2021-09-07T12:00:00Z"
    }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="scripts" class="api-section">
                        <h2>Scripts Management</h2>
                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/scripts</code> - Get a list of scripts
                            </div>
                            <div class="card-body">
                                <p>Fetches all available scripts with their associated parameters and metadata.</p>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 1,
      "name": "Sample Script",
      "parameters": [
        {
          "name": "delay",
          "label": "Execution Delay",
          "type": "number",
          "description": "Delay in seconds before execution",
          "defaultValue": "",
          "required": true
        }
      ]
    }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header bg-info">
                                <span class="badge bg-success">POST</span> <code>/api/scripts/execute/{id}</code> - Execute a script
                            </div>
                            <div class="card-body">
                                <p>Executes the specified script with the provided parameters and configuration options.</p>
                                <div class="alert alert-warning">
                                    <strong>Important:</strong> This endpoint has specific requirements for the format of profile IDs.
                                </div>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>id</code> (string, required): ID of the script to execute.</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Request Body (application/json)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "profileIds": [
    "1",    // IMPORTANT: Profile IDs must be strings, not integers
    "2"
  ],
  "closeBrowser": true,
  "parameters": {
    "delay": "5",
    "url": "https://example.com"
  }
}</code></pre>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h5>Important Notes</h5>
                                    <div class="alert alert-danger">
                                        <strong>Profile ID Format:</strong> The profile IDs in the profileIds array must be strings, not integers.
                                        If you pass them as integers, you may encounter the error: "Profile id is required".
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Script executed successfully",
  "id": "h5SF-JmCa4nvFYfh2gRc"
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-success">POST</span> <code>/api/scripts/kill-execute/{id}</code> - Terminate a running script
                            </div>
                            <div class="card-body">
                                <p>Terminates a running script with the specified ID.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>id</code> (string, required): ID of the script to terminate.</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Request Body (application/json)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "profileIds": [
    "1",
    "2"
  ],
  "closeBrowser": true
}</code></pre>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Kill script success"
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-success">POST</span> <code>/api/scripts/check-status/{id}</code> - Check script status
                            </div>
                            <div class="card-body">
                                <p>Checks whether the specified script is currently running, based on the provided profile ID.</p>
                                <div class="mt-3">
                                    <h5>Parameters</h5>
                                    <ul>
                                        <li><code>id</code> (string, required): ID of the script to check.</li>
                                    </ul>
                                </div>
                                <div class="mt-3">
                                    <h5>Request Body (application/json)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "profileId": "1"  // Note: Use string format for profile ID
}</code></pre>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "Script is running",
  "is_running": true
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="browser-versions" class="api-section">
                        <h2>Browser Versions</h2>
                        <div class="api-card card">
                            <div class="card-header">
                                <span class="badge bg-primary">GET</span> <code>/api/browser_versions</code> - Get browser versions
                            </div>
                            <div class="card-body">
                                <p>Retrieve a list of browser versions from an external service and returns them in a structured format.</p>
                                <div class="mt-3">
                                    <h5>Response (200 OK)</h5>
                                    <div class="code-block">
                                        <pre><code class="language-json">{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 1,
      "version": "128"
    }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    document.querySelector(targetId).scrollIntoView({
                        behavior: 'smooth'
                    });
                    // Highlight active link
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
