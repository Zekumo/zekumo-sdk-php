# Zekumo PHP SDK

Official PHP 8.2+ SDK covering every Zekumo player REST endpoint and synchronous
WebSocket rooms/chat. Install with Composer, then create
`new Zekumo\Client($appId, $baseUrl)`. API failures throw `ZekumoException`
with stable `errorCode` and HTTP `status`. Intended for games, web backends,
CLI tools and workers. Never embed an app secret in distributed clients.

