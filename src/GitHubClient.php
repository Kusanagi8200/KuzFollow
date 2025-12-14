<?php
declare(strict_types=1);

final class GitHubClient
{
    private string $token;
    private string $api = 'https://api.github.com';

    public function __construct(string $token){ $this->token = $token; }

    private function req(string $m, string $p, array $q=[]): array {
        $u = $this->api.$p.($q?'?'.http_build_query($q):'');
        $c = curl_init($u);
        curl_setopt_array($c,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CUSTOMREQUEST=>$m,
            CURLOPT_HTTPHEADER=>[
                'Accept: application/vnd.github+json',
                'User-Agent: kuzfollow',
                'Authorization: Bearer '.$this->token,
            ],
        ]);
        $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c);
        if($code>=400){ $j=json_decode($r?:'{}',true); throw new RuntimeException($j['message']??'API error'); }
        return json_decode($r?:'[]',true);
    }
    private function pages(string $p): array {
        $a=[]; for($i=1;$i<=10;$i++){ $d=$this->req('GET',$p,['per_page'=>100,'page'=>$i]); if(!$d)break; $a=array_merge($a,$d);} return $a;
    }

    public function followers(string $u): array { return $this->pages("/users/$u/followers"); }
    public function following(string $u): array { return $this->pages("/users/$u/following"); }

    public function follow(string $u): void   { $this->req('PUT',"/user/following/$u"); }
    public function unfollow(string $u): void { $this->req('DELETE',"/user/following/$u"); }

    public function repos(string $u): array  { return $this->pages("/users/$u/repos"); }
    public function events(string $u): array { return $this->req('GET',"/users/$u/events/public",['per_page'=>30]); }
}
