<?php declare(strict_types=1);
namespace Zekumo;
use WebSocket\Client as Socket;
final class Realtime {
    private ?Socket $socket=null;
    public function __construct(private Client $client) {}
    public function connect(): void { $url=preg_replace('#^http#','ws',rtrim($this->client->baseUrl,'/')).'/v1/ws?token='.rawurlencode($this->client->token()); $this->socket=new Socket($url); }
    public function send(string $type,mixed $data=null): void { if(!$this->socket) throw new \LogicException('not connected'); $e=['type'=>$type]; if($data!==null)$e['data']=$data; $this->socket->text(json_encode($e,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)); }
    public function receive(): array { if(!$this->socket) throw new \LogicException('not connected'); return json_decode($this->socket->receive()->getContent(),true,512,JSON_THROW_ON_ERROR); }
    public function close(): void { $this->socket?->close(); $this->socket=null; }
    public function createRoom(string $name,int $maxPlayers=0,mixed $meta=null): void {$this->send('room.create',['name'=>$name,'max_players'=>$maxPlayers,'meta'=>$meta]);}
    public function joinRoom(string $id): void {$this->send('room.join',['room_id'=>$id]);} public function leaveRoom(): void {$this->send('room.leave');}
    public function listRooms(): void {$this->send('room.list');} public function syncState(mixed $state): void {$this->send('room.state',['state'=>$state]);}
    public function roomMessage(mixed $data): void {$this->send('room.msg',['data'=>$data]);} public function subscribe(string $channel): void {$this->send('chat.sub',['channel'=>$channel]);}
    public function unsubscribe(string $channel): void {$this->send('chat.unsub',['channel'=>$channel]);} public function chat(string $channel,string $content): void {$this->send('chat.send',compact('channel','content'));}
}
