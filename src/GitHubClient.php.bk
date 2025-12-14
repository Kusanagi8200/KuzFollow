<?php
declare(strict_types=1);

final class GitHubClient
{
    private string $token;
    private string $api = 'https://api.github.com';

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    private function request(string $method, string $path, array $query = []): array
    {
        $url = $this->api . $path;
        if ($query) $url .= '?' . http_build_query($query);

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
            CURLOPT_TIMEOUT        => 25,
        ]);

        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            throw new RuntimeException('cURL error: ' . $err);
        }

        $json = json_decode($res ?: '[]', true);

        if ($code >= 400) {
            $msg = is_array($json) && isset($json['message']) ? (string)$json['message'] : 'GitHub API error';
            throw new RuntimeException("GitHub API {$code}: {$msg}");
        }

        return is_array($json) ? $json : [];
    }

    private function paginate(string $path, array $query = []): array
    {
        $all = [];
        for ($page = 1; $page <= 20; $page++) { // up to 2000 items
            $data = $this->request('GET', $path, $query + ['per_page' => 100, 'page' => $page]);
            if (!$data) break;
            $all = array_merge($all, $data);
        }
        return $all;
    }

    /* GRAPH */
    public function followers(string $u): array { return $this->paginate("/users/{$u}/followers"); }
    public function following(string $u): array { return $this->paginate("/users/{$u}/following"); }

    /* ACTIONS */
    public function follow(string $u): void { $this->request('PUT', "/user/following/{$u}"); }
    public function unfollow(string $u): void { $this->request('DELETE', "/user/following/{$u}"); }

    /* DATA */
    public function reposOwnerSorted(string $u): array
    {
        return $this->paginate("/users/{$u}/repos", [
            'type' => 'owner',
            'sort' => 'updated',
            'direction' => 'desc',
        ]);
    }

    public function eventsPublic(string $u, int $limit = 30): array
    {
        return $this->request('GET', "/users/{$u}/events/public", [
            'per_page' => max(1, min(100, $limit)),
        ]);
    }
}
