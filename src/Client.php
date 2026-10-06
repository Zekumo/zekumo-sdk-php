<?php declare(strict_types=1);
namespace Zekumo;
use GuzzleHttp\Client as Http;

final class Client {
    private Http $http;
    public function __construct(public readonly string $appId, public readonly string $baseUrl, private string $token = '', ?Http $http = null) {
        $this->http = $http ?? new Http(['timeout'=>30]);
    }
    public function token(): string { return $this->token; }
    public function setToken(string $token): void { $this->token=$token; }
    private function seg(string $v): string { return rawurlencode($v); }
    public function request(string $method, string $path, mixed $body=null, array $query=[], bool $auth=true): mixed {
        $options=['http_errors'=>false,'query'=>array_filter($query,fn($v)=>$v!==null&&$v!=='')];
        if ($body!==null) $options['json']=$body;
        if ($auth && $this->token!=='') $options['headers']['Authorization']='Bearer '.$this->token;
        $r=$this->http->request($method,rtrim($this->baseUrl,'/').$path,$options); $text=(string)$r->getBody(); $data=$text===''?null:json_decode($text,true);
        if ($r->getStatusCode()>=300) { $e=is_array($data)?($data['error']??[]):[]; throw new ZekumoException($r->getStatusCode(),$e['code']??'http_'.$r->getStatusCode(),$e['message']??($text?:$r->getReasonPhrase())); }
        return $data;
    }
    private function login(string $path,array $body): array { $body['app_id']=$this->appId; $out=$this->request('POST',$path,$body,[],false); $this->token=$out['token']; return $out; }
    public function loginGuest(string $deviceId,string $nickname=''): array { return $this->login('/v1/auth/login',['provider'=>'guest','device_id'=>$deviceId,'nickname'=>$nickname]); }
    public function loginPassword(string $username,string $password): array { return $this->login('/v1/auth/login',['provider'=>'password','username'=>$username,'password'=>$password]); }
    public function loginTicket(string $ticket): array { return $this->login('/v1/auth/login',['provider'=>'sso','ticket'=>$ticket]); }
    public function register(string $username,string $password,string $nickname=''): array { return $this->login('/v1/auth/register',['provider'=>'password','username'=>$username,'password'=>$password,'nickname'=>$nickname]); }
    public function logout(): void { $this->token=''; }
    public function me(): array { return $this->request('GET','/v1/player/profile'); }
    public function updatePlayer(?string $nickname=null,mixed $profile=null): array { return $this->request('PUT','/v1/player/profile',array_filter(['nickname'=>$nickname,'profile'=>$profile],fn($v)=>$v!==null)); }
    public function bind(string $ticket='',string $username='',string $password=''): mixed { return $this->request('POST','/v1/player/bind',compact('ticket','username','password')); }
    public function dataList(): array { return $this->request('GET','/v1/player/data')['entries']; }
    public function dataGet(string $key): mixed { return $this->request('GET','/v1/player/data/'.$this->seg($key))['value']; }
    public function dataSet(string $key,mixed $value): array { return $this->request('PUT','/v1/player/data/'.$this->seg($key),['value'=>$value]); }
    public function dataDelete(string $key): mixed { return $this->request('DELETE','/v1/player/data/'.$this->seg($key)); }
    public function submitScore(string $board,int $score,string $mode='max'): array { return $this->request('POST','/v1/leaderboards/'.$this->seg($board).'/score',compact('score','mode')); }
    public function leaderboard(string $board,int $limit=0,int $offset=0,string $scope=''): array { return $this->request('GET','/v1/leaderboards/'.$this->seg($board),null,compact('limit','offset','scope'))['entries']; }
    public function leaderboardMe(string $board): array { return $this->request('GET','/v1/leaderboards/'.$this->seg($board).'/me'); }
    public function achievements(bool $unlocked=false): array { return $this->request('GET','/v1/achievements'.($unlocked?'/unlocked':''))['achievements']; }
    public function friends(): array { return $this->request('GET','/v1/friends')['friends']; }
    public function friendRequests(): array { return $this->request('GET','/v1/friends/requests')['requests']; }
    public function friendAction(string $action,string $id): mixed { $key=in_array($action,['request','block'],true)?'target_player_id':'request_id'; return $this->request('POST','/v1/friends/'.$action,[$key=>$id]); }
    public function removeFriend(string $id): mixed { return $this->request('DELETE','/v1/friends/'.$this->seg($id)); }
    public function balances(): array { return $this->request('GET','/v1/currency')['balances']; }
    public function ledger(string $id,int $limit=0,int $offset=0): array { return $this->request('GET','/v1/currency/'.$this->seg($id).'/ledger',null,compact('limit','offset'))['entries']; }
    public function spend(string $id,int $amount,string $idempotencyKey,string $note=''): array { return $this->request('POST','/v1/currency/'.$this->seg($id).'/spend',['amount'=>$amount,'idempotency_key'=>$idempotencyKey,'note'=>$note]); }
    public function mailbox(bool $unreadOnly=false): array { return $this->request('GET','/v1/mailbox',null,$unreadOnly?['unread_only'=>'true']:[]); }
    public function mailAction(string $id,string $action): mixed { return $this->request('POST','/v1/mailbox/'.$this->seg($id).'/'.$action); }
    public function announcements(array $filters=[]): array { return $this->request('GET','/v1/apps/'.$this->seg($this->appId).'/announcements',null,$filters,false)['announcements']; }
    public function callFunction(string $name,mixed $body=null,bool $public=false): array { $p=$public?'/v1/apps/'.$this->seg($this->appId).'/functions/':'/v1/functions/'; return $this->request('POST',$p.$this->seg($name),$body,[],!$public); }
    public function checkUpdate(string $version,array $options=[]): array { return $this->request('GET','/v1/apps/'.$this->seg($this->appId).'/updates/check',null,['version'=>$version]+$options,false); }
    public function releases(): array { return $this->request('GET','/v1/apps/'.$this->seg($this->appId).'/releases',null,[],false)['releases']; }
    public function reportLog(string $message,string $level='',string $event='',array $fields=[]): mixed { return $this->request('POST','/v1/logs',compact('message','level','event','fields')); }
    public function dialogues(): array { return $this->request('GET','/v1/dialogues')['scripts']; }
    public function dialogue(string $key): array { return $this->request('GET','/v1/dialogues/'.$this->seg($key)); }
    public function chatHistory(string $channel,int $beforeId=0,int $limit=0): array { return $this->request('GET','/v1/chat/history',null,['channel'=>$channel,'before_id'=>$beforeId,'limit'=>$limit])['messages']; }
    public function kvGet(string $namespace,string $key): mixed { return $this->request('GET','/v1/kv/'.$this->seg($namespace).'/'.$this->seg($key))['value']; }
    public function kvList(string $namespace,int $limit=0,int $offset=0): array { return $this->request('GET','/v1/kv/'.$this->seg($namespace),null,compact('limit','offset'))['keys']; }
    public function realtime(): Realtime { return new Realtime($this); }
}
