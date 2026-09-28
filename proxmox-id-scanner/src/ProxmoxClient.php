<?php

/**
 * Minimal Proxmox VE API client: ticket login + cluster/resources lookup.
 * Talks to a single host, but if that host is a cluster member,
 * cluster/resources returns data for every node in the cluster.
 */
class ProxmoxClient
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private bool $verifySsl;
    private ?string $authCookie = null;

    public function __construct(string $host, int $port, string $username, string $password, bool $verifySsl = false)
    {
        $this->baseUrl = "https://{$host}:{$port}/api2/json";
        $this->username = $username;
        $this->password = $password;
        $this->verifySsl = $verifySsl;
    }

    /**
     * @throws RuntimeException on connection failure, HTTP error, or bad credentials.
     */
    public function login(): void
    {
        $response = $this->request('POST', '/access/ticket', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);

        $ticket = $response['data']['ticket'] ?? null;
        if (!$ticket) {
            throw new RuntimeException('Login response did not contain a ticket');
        }

        $this->authCookie = $ticket;
    }

    /**
     * Returns the raw list of VM/CT resources (qemu + lxc) across all nodes
     * visible to this host.
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function getVmResources(): array
    {
        if ($this->authCookie === null) {
            throw new RuntimeException('Not logged in');
        }

        $response = $this->request('GET', '/cluster/resources?type=vm', null, true);

        return $response['data'] ?? [];
    }

    /**
     * @throws RuntimeException
     */
    private function request(string $method, string $path, ?array $fields, bool $authenticated): array
    {
        $ch = curl_init($this->baseUrl . $path);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields ?? []);
        }

        if ($authenticated) {
            $options[CURLOPT_COOKIE] = 'PVEAuthCookie=' . $this->authCookie;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("Connection error: {$error}");
        }

        if ($httpCode === 401) {
            throw new RuntimeException('Authentication failed (401)');
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("API returned HTTP {$httpCode}");
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from API');
        }

        return $decoded;
    }
}
