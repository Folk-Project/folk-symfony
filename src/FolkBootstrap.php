<?php

declare(strict_types=1);

namespace Folk\Symfony;

use Folk\Sdk\Grpc\GrpcRouter;
use Folk\Sdk\Worker\HandlerLoop;
use Symfony\Component\HttpKernel\KernelInterface;

final class FolkBootstrap
{
    public static function register(KernelInterface $kernel): void
    {
        if (!\function_exists('folk_worker_run')) {
            return;
        }

        $GLOBALS['folk_worker_boot_hook'] = static function (HandlerLoop $loop) use ($kernel): void {
            $kernel->boot();
            $container = $kernel->getContainer();

            // Stamp request_id onto application logs for correlation with Folk's
            // Rust-side access log. Only applies when the logger is Monolog; reads
            // the id at log time, so nothing to reset between requests.
            if ($container->has('logger')) {
                $logger = $container->get('logger');
                if ($logger instanceof \Monolog\Logger) {
                    $logger->pushProcessor(new Log\FolkRequestIdProcessor());
                }
            }

            // HTTP handler
            $loop->registerHttpHandler(
                new Handler\SymfonyHttpHandler($kernel),
            );

            // Jobs handler
            $loop->registerJobsHandler(
                new Jobs\SymfonyJobHandler($container),
            );

            // gRPC handler (if configured via parameters)
            try {
                /** @var array<string, class-string> $grpcServices */
                $grpcServices = $container->hasParameter('folk.grpc.services')
                    ? $container->getParameter('folk.grpc.services')
                    : [];
            } catch (\Throwable) {
                $grpcServices = [];
            }

            if ($grpcServices !== []) {
                $router = new GrpcRouter();
                foreach ($grpcServices as $name => $class) {
                    $router->register($name, $container->get($class));
                }
                $loop->registerGrpcHandler($router);
            }

            // Resetters
            $loop->registerResetter(new Reset\KernelResetter($kernel));

            if ($container->has('doctrine')) {
                $loop->registerResetter(new Reset\DoctrineResetter($container));
            }
        };
    }
}
