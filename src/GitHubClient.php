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
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: kuzfollow',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code >= 400) {
            $j = json_decode($res ?: '{}', true);
            throw new RuntimeException($j['message'] ?? 'GitHub API error');
        }

        return json_decode($res ?: '[]', true);
    }

    private function paginate(string $path): array
    {
        $all = [];
        for ($p=1; $p<=10; $p++) {
            $r = $this->request('GET', $path, ['per_page'=>100,'page'=>$p]);
            if (!$r) break;
            $all = array_merge($all, $r);
        }
        return $all;
    }

    /* GRAPH */
    public function followers(string $u): array { return $this->paginate("/users/$u/followers"); }
    public function following(string $u): array { return $this->paginate("/users/$u/following"); }

    /* ACTIONS */
    public function follow(string $u): void   { $this->request('PUT',    "/user/following/$u"); }
    public function unfollow(string $u): void { $this->request('DELETE', "/user/following/$u"); }

    /* DATA */
    public function repos(string $u): array  { return $this->paginate("/users/$u/repos"); }
    public function events(string $u): array { return $this->request('GET',"/users/$u/events/public",['per_page'=>30]); }
}
