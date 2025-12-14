<?php
declare(strict_types=1);

final class GitHubClient
{
    private string $token;
    private string $api = 'https://api.github.com';
    private int $perPage;

    public function __construct(string $token, int $perPage = 100)
    {
        $this->token = $token;
        $this->perPage = $perPage;
    }

    private function request(string $path, array $query = []): array
    {
        $url = $this->api . $path;
        if ($query) $url .= '?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: kuzfollow',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode($res ?: '[]', true);
        if ($code >= 400) {
            throw new RuntimeException($json['message'] ?? 'GitHub API error');
        }
        return $json;
    }

    private function paginate(string $path, array $query = []): array
    {
        $all = [];
        for ($page = 1; ; $page++) {
            $data = $this->request($path, $query + [
                'per_page' => $this->perPage,
                'page' => $page,
            ]);
            if (!$data) break;
            $all = array_merge($all, $data);
        }
        return $all;
    }

    /* FOLLOW GRAPH */
    public function followers(string $u): array { return $this->paginate("/users/$u/followers"); }
    public function following(string $u): array { return $this->paginate("/users/$u/following"); }
    public function follow(string $u): void { $this->request("/user/following/$u"); }
    public function unfollow(string $u): void { $this->request("/user/following/$u"); }

    /* REPOS */
    public function repos(string $u): array
    {
        return $this->paginate("/users/$u/repos", [
            'type' => 'owner',
            'sort' => 'updated',
        ]);
    }

    /* EVENTS */
    public function events(string $u, int $limit = 30): array
    {
        return $this->request("/users/$u/events/public", [
            'per_page' => min($limit, 100),
        ]);
    }
}
