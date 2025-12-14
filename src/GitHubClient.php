<?php
declare(strict_types=1);

final class GitHubClient
{
    private string $token;
    private string $apiBase = 'https://api.github.com';
    private int $perPage;

    public function __construct(string $token, int $perPage = 100)
    {
        $this->token = $token;
        $this->perPage = $perPage;
    }

    private function request(string $method, string $path, array $query = []): array
    {
        $url = $this->apiBase . $path;
        if (!empty($query)) $url .= '?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: kuzfollow',
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("cURL error: {$err}");
        }

        $json = json_decode($body, true);
        if ($code >= 400) {
            $msg = (is_array($json) && isset($json['message'])) ? $json['message'] : $body;
            throw new RuntimeException("GitHub API {$code}: {$msg}");
        }

        return is_array($json) ? $json : [];
    }

    private function paginate(string $path, array $query = []): array
    {
        $all = [];
        $page = 1;

        while (true) {
            $data = $this->request('GET', $path, array_merge($query, [
                'per_page' => $this->perPage,
                'page'     => $page,
            ]));

            if (count($data) === 0) break;
            $all = array_merge($all, $data);
            $page++;
        }

        return $all;
    }

    public function getUser(string $user): array { return $this->request('GET', "/users/{$user}"); }

    public function followers(string $user): array { return $this->paginate("/users/{$user}/followers"); }

    public function following(string $user): array { return $this->paginate("/users/{$user}/following"); }

    public function follow(string $username): void { $this->request('PUT', "/user/following/{$username}"); }

    public function unfollow(string $username): void { $this->request('DELETE', "/user/following/{$username}"); }

    // --- Nouveaux endpoints ---
    public function repos(string $user): array
    {
        // "owner" = repos dont tu es propriétaire (le plus “propre” pour un dashboard)
        return $this->paginate("/users/{$user}/repos", [
            'type' => 'owner',
            'sort' => 'updated',
            'direction' => 'desc',
        ]);
    }

    public function starred(string $user): array
    {
        return $this->paginate("/users/{$user}/starred", [
            'sort' => 'created',
            'direction' => 'desc',
        ]);
    }

    public function orgs(string $user): array { return $this->paginate("/users/{$user}/orgs"); }

    public function gists(string $user): array { return $this->paginate("/users/{$user}/gists"); }

    public function events(string $user, int $perPage = 30): array
    {
        // GitHub limite fortement l’historique (évènements récents)
        return $this->request('GET', "/users/{$user}/events/public", [
            'per_page' => min(max($perPage, 1), 100),
        ]);
    }

    public function receivedEvents(string $user, int $perPage = 30): array
    {
        return $this->request('GET', "/users/{$user}/received_events/public", [
            'per_page' => min(max($perPage, 1), 100),
        ]);
    }
}
