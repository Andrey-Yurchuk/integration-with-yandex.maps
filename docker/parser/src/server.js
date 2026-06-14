const http = require('node:http');

const port = Number(process.env.PORT || 3000);

const server = http.createServer((request, response) => {
    if (request.url === '/health') {
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ status: 'ok', parser: 'stub' }));

        return;
    }

    response.writeHead(503, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({
        status: 'unavailable',
        message: 'Parser sidecar is not implemented yet',
    }));
});

server.listen(port, () => {
    console.log(`Parser stub listening on port ${port}`);
});
