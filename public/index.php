<?php
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    'settings' => [
        'db' => [
            'connection'  => 'sqlite',
            'user'   => null,
            'pass'   => null,
            'dbname' => __DIR__.'/db.sqlite',
        ],
    ],
]);

$container = $containerBuilder->build();

$container->set('db', function (ContainerInterface $c) {
    ['db' => [
        'connection' => $connection,
        'user' => $user,
        'pass' => $pass,
        'dbname' => $dbname,
    ]] = $c->get('settings');

    return new PDO("{$connection}:{$dbname}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$beforeMiddleware = function ($request, $handler) {
    $this->get('db')->exec(
        'CREATE TABLE IF NOT EXISTS todos (
            id        INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            text      VARCHAR NOT NULL,
            completed BOOLEAN NOT NULL
        );'
    );
    return $handler->handle($request);
};
$afterMiddleware = function ($request, $handler) {
    return $handler->handle($request)
        ->withHeader('Content-Type', 'application/json');
};
$app->add($beforeMiddleware);
$app->add($afterMiddleware);

$app->get('/list', function (Request $request, Response $response) {
    $response->getBody()
         ->write(json_encode($this->get('db')->query('SELECT * FROM todos')->fetchAll()));
    return $response;
});

$app->get('/add', function (Request $request, Response $response) {
    ['name' => $nama] = $request->getQueryParams();

    $db = $this->get('db');
    $stmt = $db->prepare('INSERT INTO todos(text, completed) VALUES(?, ?)');
    $stmt->execute([$nama, 0]);
    $response->getBody()->write(json_encode(['res' => 'ok']));
    return $response;
});

$app->get('/complete/{id}', function (Request $request, Response $response, array $args) {
    ['id' => $id] = $args;

    $db = $this->get('db');
    $stmt = $db->prepare('UPDATE todos SET completed = ? WHERE id = ?');
    $stmt->execute([1, $id]);
    $response->getBody()->write(json_encode(['res' => 'ok']));
    return $response;
});

$app->run();
