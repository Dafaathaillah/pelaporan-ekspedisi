const http = require('http');
const httpProxy = require('http-proxy');

const gatewayPort = Number(process.env.NGROK_GATEWAY_PORT || 9000);
const laravelTarget = process.env.LARAVEL_TARGET || 'http://127.0.0.1:8000';
const reverbTarget = process.env.REVERB_TARGET || 'http://127.0.0.1:8080';
const proxy = httpProxy.createProxyServer({ xfwd: false, ws: true });

function normalizeForwardedHeaders(request) {
    const publicProtocol = String(request.headers['x-forwarded-proto'] || 'http').split(',')[0].trim();
    request.headers['x-forwarded-proto'] = publicProtocol;
    request.headers['x-forwarded-port'] = publicProtocol === 'https' ? '443' : '80';
    request.headers['x-forwarded-host'] = request.headers.host;
}

proxy.on('error', (error, request, response) => {
    console.error(`[gateway] ${error.message}`);
    if (response && !response.headersSent) response.writeHead(502, { 'Content-Type': 'application/json' });
    if (response && response.end) response.end(JSON.stringify({ message: 'Layanan lokal belum siap.', target: request.url }));
});

const server = http.createServer((request, response) => {
    if (request.url === '/__gateway/health') {
        response.writeHead(200, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
        return response.end(JSON.stringify({ status: 'ok', laravel: laravelTarget, reverb: reverbTarget }));
    }

    normalizeForwardedHeaders(request);
    const target = request.url.startsWith('/apps/') ? reverbTarget : laravelTarget;
    proxy.web(request, response, { target });
});

server.on('upgrade', (request, socket, head) => {
    normalizeForwardedHeaders(request);
    proxy.ws(request, socket, head, { target: reverbTarget });
});
server.listen(gatewayPort, '0.0.0.0', () => console.log(`[gateway] http://0.0.0.0:${gatewayPort} -> Laravel ${laravelTarget} | Reverb ${reverbTarget}`));
