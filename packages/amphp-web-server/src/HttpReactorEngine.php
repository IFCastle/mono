<?php

declare(strict_types=1);

namespace IfCastle\AmphpWebServer;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use IfCastle\Amphp\AmphpReadableStreamAdapter;
use IfCastle\Amphp\ReadableStreamAdapter;
use IfCastle\AmphpWebServer\Http\ResponseFactory;
use IfCastle\AmpPool\Exceptions\FatalWorkerException;
use IfCastle\AmpPool\Worker\WorkerInterface;
use IfCastle\Application\Console\ConsoleLoggerInterface;
use IfCastle\Application\Console\LoggerFilterByLevel;
use IfCastle\Application\Environment\PublicEnvironmentInterface;
use IfCastle\Application\Environment\SystemEnvironmentInterface;
use IfCastle\Application\RequestEnvironment\RequestEnvironment;
use IfCastle\Application\RequestEnvironment\RequestPlanInterface;
use IfCastle\Async\ReadableStreamInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\Exceptions\UnexpectedValueType;
use IfCastle\Protocol\Http\HttpResponseInterface;
use IfCastle\Protocol\ResponseFactoryInterface;
use Psr\Log\LogLevel;

final class HttpReactorEngine extends \IfCastle\Amphp\AmphpEngine
{
    /**
     * @var \WeakReference<WorkerInterface>|null
     */
    private \WeakReference|null $worker = null;

    public function __construct(WorkerInterface $worker, private readonly SystemEnvironmentInterface $systemEnvironment)
    {
        $this->worker               = \WeakReference::create($worker);
    }

    #[\Override]
    public function start(): void
    {
        $worker                     = $this->worker->get();

        if ($worker === null) {
            return;
        }

        $systemEnvironment          = $this->systemEnvironment;
        $config                     = $systemEnvironment->resolveDependency(ConfigInterface::class)->requireSection('server');
        $systemEnvironment->set(ConsoleLoggerInterface::class, $worker->getLogger());

        $host                       = $config['host'] ?? throw new FatalWorkerException('Config failed: Host is not defined');
        $port                       = $config['port'] ?? '9095';
        $isDebugMode                = $config['debugMode'] ?? false;

        // Logger for the server engine
        $serverLogger               = new LoggerFilterByLevel(
            $worker->getLogger(), $isDebugMode ? LogLevel::DEBUG : LogLevel::ERROR
        );

        $socketFactory              = $worker->getWorkerGroup()->getSocketStrategy()->getServerSocketFactory();
        $clientFactory              = new SocketClientFactory($serverLogger);
        $httpServer                 = new SocketHttpServer($serverLogger, $socketFactory, $clientFactory);

        // 2. Expose the server to the network
        $httpServer->expose($host . ':' . $port, new BindContext()->withTcpNoDelay());

        $requestPlan                = $systemEnvironment->resolveDependency(RequestPlanInterface::class);
        $publicEnvironment          = $systemEnvironment->findDependency(PublicEnvironmentInterface::class);
        $environment                = $publicEnvironment ?? $systemEnvironment;

        $worker->getLogger()->info('HTTP server should be started on http://' . $host . ':' . $port);

        // 3. Handle incoming connections and start the server
        $httpServer->start(
            new ClosureRequestHandler(static function (Request $request) use ($requestPlan, $environment): Response {

                $requestEnv         = new RequestEnvironment($request, $environment);
                // bind response factory
                $requestEnv->set(ResponseFactoryInterface::class, new ResponseFactory());

                $response           = null;

                try {
                    $environment->setRequestEnvironment($requestEnv);
                    $requestPlan->executePlan($requestEnv);
                    $response       = $requestEnv->getResponse();
                } finally {
                    $requestEnv->dispose();
                }

                /* @phpstan-ignore-next-line */
                if ($response instanceof HttpResponseInterface) {
                    return self::buildResponse($response);
                }

                throw new UnexpectedValueType('response', $response, HttpResponseInterface::class);
            }),
            new DefaultErrorHandler(),
        );

        // 4. Await termination of the worker
        $worker->awaitTermination();

        // 5. Stop the HTTP server
        $httpServer->stop();
    }

    private static function buildResponse(HttpResponseInterface $responseMutable): Response
    {
        $body                       = $responseMutable->getBody();

        if ($body instanceof ReadableStreamAdapter) {
            $body                   = $body->readableStream;
        } elseif ($body instanceof ReadableStreamInterface) {
            $body                   = new AmphpReadableStreamAdapter($body);
        }

        return new Response($responseMutable->getStatusCode(), $responseMutable->getHeaders(), $body);
    }
}
